jQuery(document).ready(function ($) {
    var settings = window.dadsooAjaxComment || {};
    var i18n = settings.i18n || {};

    // نزدیک‌ترین فهرست نظرات به یک عنصر؛ اگر فرم بیرون از فهرست باشد اولین فهرست صفحه استفاده می‌شود.
    function commentsContainerFor($el) {
        var $container = $el.closest('.dadsoo-comments-container');

        return $container.length ? $container : $('.dadsoo-comments-container').first();
    }

    // پیام‌ها متن ساده‌اند؛ با text() درج می‌شوند تا خروجی سرور هیچ‌وقت به‌عنوان HTML اجرا نشود.
    function showMessage($box, type, text) {
        $box.removeClass('success error').addClass(type).text(text);
    }

    // فقط فهرست پاسخ‌های خودِ همین کامنت، نه پاسخ‌های تو در توی پایین‌تر.
    function ownChild($comment, selector) {
        return $comment.children('.dadsoo-comment-body').children(selector).first();
    }

    function iconsFor($container) {
        return {
            vote_icons: $container.attr('data-vote-icons') || '',
            avatar_size: $container.attr('data-avatar-size') || ''
        };
    }

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function setBusy($button, busy) {
        $button.toggleClass('is-loading', busy).prop('disabled', busy);
    }

    /**
     * یک عنصر را به بالای دید می‌آورد و فاصلهٔ scroll-margin-top آن را رعایت می‌کند،
     * تا هدر چسبانِ قالب رویش نیفتد.
     */
    function scrollToElement(el) {
        var offset = parseFloat(window.getComputedStyle(el).scrollMarginTop) || 0;
        var start = window.pageYOffset;
        var target = Math.max(0, el.getBoundingClientRect().top + start - offset);

        if (Math.abs(target - start) < 4) {
            return;
        }

        try {
            window.scrollTo({ top: target, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        } catch (e) {
            window.scrollTo(0, target);
        }

        // بعضی مرورگرها و وب‌ویوها اسکرول نرم را بی‌صدا نادیده می‌گیرند. اگر بعد از یک
        // لحظه اصلاً تکان نخورده بود یعنی پشتیبانی نشده، پس بدون انیمیشن می‌رویم.
        setTimeout(function () {
            if (window.pageYOffset === start) {
                window.scrollTo(0, target);
            }
        }, 250);
    }

    /**
     * اولین نظرِ دستهٔ تازه را در دید می‌آورد و روی آن فلَش می‌زند، تا کاربر ببیند
     * بارگذاری از کجا ادامه پیدا کرده است.
     */
    function highlightNewBatch($comment) {
        if (!$comment || !$comment.length) {
            return;
        }

        var el = $comment[0];

        // اگر کلاس از دفعهٔ قبل مانده باشد، انیمیشن دوباره اجرا نمی‌شود؛ حذف و
        // خواندن offsetWidth آن را از ابتدا راه می‌اندازد.
        $comment.removeClass('dadsoo-comment--new');
        void el.offsetWidth;
        $comment.addClass('dadsoo-comment--new');

        scrollToElement(el);

        setTimeout(function () {
            $comment.removeClass('dadsoo-comment--new');
        }, 2600);
    }

    // ------------------------------------------------------------ ثبت نظر

    // انتخابگر بر پایهٔ کلاس است، چون ممکن است چند ویجت فرم روی یک صفحه باشد.
    $(document).on('submit', '.dadsoo-comment-form-inner', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $message = $form.find('.dadsoo-message');
        var $container = commentsContainerFor($form);

        var formData = {};
        $form.find('input, textarea').each(function () {
            if (this.name) {
                formData[this.name] = $(this).val();
            }
        });
        formData.action = 'dadsoo_ajax_submit_comment';

        if ($container.length) {
            $.extend(formData, iconsFor($container));
        }

        $.ajax({
            url: settings.ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function () {
                setBusy($form.find('.dadsoo-submit-btn'), true);
                $form.find('button').prop('disabled', true);
                $message.removeClass('success error').empty();
            },
            success: function (response) {
                if (!response || !response.success) {
                    showMessage($message, 'error', (response && response.data) || i18n.commentFailed);
                    return;
                }

                var data = response.data || {};
                showMessage($message, 'success', data.message || i18n.commentSaved);
                $form[0].reset();

                // نظر منتشرشده (مدیر یا نویسندهٔ نوشته) بدون بارگذاری دوباره نمایش داده می‌شود.
                if (data.approved && data.html && $container.length) {
                    $container.children('.dadsoo-comments-list').prepend(data.html);

                    // یک ردیف به بالای فهرست اضافه شد، پس مبدأ صفحهٔ بعد هم یکی جلو می‌رود
                    // وگرنه «بارگذاری بیشتر» آخرین نظر قبلی را تکراری می‌آورد.
                    $container.data('offset', ($container.data('offset') || 0) + 1);
                }
            },
            error: function (xhr) {
                showMessage($message, 'error', xhr.status ? i18n.serverError : i18n.commentFailed);
            },
            complete: function () {
                setBusy($form.find('.dadsoo-submit-btn'), false);
                $form.find('button').prop('disabled', false);
            }
        });
    });

    // -------------------------------------------------------- بارگذاری نظرات

    $('.dadsoo-comments-container').each(function () {
        var $container = $(this);
        var $list = $container.children('.dadsoo-comments-list');
        var $loadMore = $container.children('.dadsoo-load-more');
        var $status = $container.children('.dadsoo-comments-status');
        var $statusText = $status.children('.dadsoo-comments-status-text');
        var loadingText = $container.attr('data-loading-text') || '';
        var emptyText = $container.attr('data-empty-text') || '';
        var itemsPerPage = parseInt($container.attr('data-items'), 10) || 5;
        var postId = $container.attr('data-post-id');
        var loading = false;
        var firstLoad = true;

        $container.data('offset', 0);

        // 'loading' هنگام بارگذاری اولیه، 'empty' وقتی هیچ نظری نیست، 'idle' در بقیهٔ حالت‌ها.
        function setStatus(state) {
            $status.removeClass('is-loading is-empty');

            if ('idle' === state) {
                $status.hide();
                return;
            }

            $statusText.text('loading' === state ? loadingText : emptyText);
            $status.addClass('loading' === state ? 'is-loading' : 'is-empty').show();
        }

        function loadComments() {
            if (loading) {
                return;
            }
            loading = true;

            $.ajax({
                url: settings.ajaxurl,
                type: 'POST',
                data: $.extend({
                    action: 'dadsoo_ajax_load_comments',
                    post_id: postId,
                    offset: $container.data('offset') || 0,
                    items: itemsPerPage,
                    _wpnonce: settings.nonce
                }, iconsFor($container)),
                beforeSend: function () {
                    // بارگذاری اول هنوز دکمه‌ای ندارد، پس چرخانه در جای فهرست نشان داده می‌شود.
                    if (firstLoad) {
                        setStatus('loading');
                    } else {
                        setBusy($loadMore, true);
                    }
                },
                success: function (response) {
                    if (!response || !response.success) {
                        setStatus(firstLoad ? 'empty' : 'idle');
                        $loadMore.hide();
                        return;
                    }

                    var data = response.data || {};
                    var wasFirstLoad = firstLoad;

                    // دستهٔ تازه از روی تفاضل گرفته می‌شود، نه از روی رشتهٔ HTML، تا
                    // فاصله‌ها و گره‌های متنی نتیجه را به هم نریزند.
                    var $before = $list.children('.dadsoo-comment');
                    if (data.html) {
                        $list.append(data.html);
                    }
                    var $added = $list.children('.dadsoo-comment').not($before);

                    // مبدأ با تعداد واقعیِ برگشتی جلو می‌رود، نه با اندازهٔ صفحه.
                    $container.data('offset', ($container.data('offset') || 0) + (data.count || 0));

                    // سرور خودش می‌گوید صفحهٔ بعدی هست یا نه، پس وقتی تعداد کل دقیقاً
                    // مضربی از اندازهٔ صفحه باشد دکمه روی صفحه جا نمی‌ماند.
                    $loadMore.toggle(!!data.has_more);

                    setStatus(wasFirstLoad && !$list.children('.dadsoo-comment').length ? 'empty' : 'idle');
                    firstLoad = false;

                    // در بارگذاری اول اسکرول نمی‌کنیم؛ پریدن صفحه هنگام باز شدن آزاردهنده است.
                    if (!wasFirstLoad) {
                        highlightNewBatch($added.first());
                    }
                },
                error: function () {
                    setStatus(firstLoad ? 'empty' : 'idle');
                    $loadMore.hide();
                },
                complete: function () {
                    loading = false;
                    setBusy($loadMore, false);
                }
            });
        }

        $loadMore.on('click', loadComments);
        loadComments();
    });

    // ------------------------------------------------------------ لایک/دیسلایک

    $(document).on('click', '.dadsoo-vote-btn', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $comment = $button.closest('.dadsoo-comment');

        $.ajax({
            url: settings.ajaxurl,
            type: 'POST',
            data: {
                action: 'dadsoo_ajax_comment_vote',
                comment_id: $button.attr('data-comment-id'),
                vote_type: $button.attr('data-vote'),
                _wpnonce: settings.nonce
            },
            beforeSend: function () {
                $button.prop('disabled', true).addClass('loading');
            },
            success: function (response) {
                if (response && response.success) {
                    updateVoteUI($comment, response.data);
                }
            },
            complete: function () {
                $button.prop('disabled', false).removeClass('loading');
            }
        });
    });

    function updateVoteUI($comment, data) {
        var selector = '[data-comment-id="' + data.comment_id + '"]';
        var $likeBtn = $comment.find('.dadsoo-vote-btn[data-vote="like"]' + selector);
        var $dislikeBtn = $comment.find('.dadsoo-vote-btn[data-vote="dislike"]' + selector);

        $likeBtn.find('.dadsoo-like-count').text(data.likes);
        $dislikeBtn.find('.dadsoo-dislike-count').text(data.dislikes);

        var $active = null;
        if ('removed' !== data.status) {
            $active = 'like' === data.vote_type ? $likeBtn : $dislikeBtn;
        }

        $likeBtn.add($dislikeBtn).each(function () {
            var isActive = $active !== null && this === $active[0];
            $(this).toggleClass('active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
        });

        if ($active) {
            var $icon = $active.find('.icon');
            $icon.css('transform', 'scale(1.2)');
            setTimeout(function () {
                $icon.css('transform', 'scale(1)');
            }, 300);
        }
    }

    // -------------------------------------------------------------- پاسخ‌ها

    function escapeAttr(value) {
        return $('<div>').text(value === undefined || value === null ? '' : value).html();
    }

    $(document).on('click', '.dadsoo-reply-btn', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $comment = $button.closest('.dadsoo-comment');

        // فرم پاسخ از روی ساختار DOM پیدا می‌شود نه با id سراسری، تا اگر همان نظر در دو
        // ویجت روی یک صفحه بیاید، دکمه فرم درست را باز کند.
        var $replyForm = ownChild($comment, '.dadsoo-reply-form');
        if (!$replyForm.length) {
            return;
        }

        if ($replyForm.is(':visible')) {
            $replyForm.slideUp(300);
            return;
        }

        $('.dadsoo-reply-form:visible').slideUp(300);

        // شناسهٔ پست از خود فهرست نظرات خوانده می‌شود؛ فرم ارسال نظر ممکن است در صفحه نباشد.
        var postId = commentsContainerFor($button).attr('data-post-id') || '';
        var commentId = $button.attr('data-comment-id');
        var honeypot = settings.honeypot || 'dadsoo_ajax_confirm';

        $replyForm.html(
            '<form class="dadsoo-reply-form-inner">' +
            '<div class="form-group"><label>' + escapeAttr(i18n.name) + '*</label><input type="text" name="name" required></div>' +
            '<div class="form-group"><label>' + escapeAttr(i18n.email) + '</label><input type="email" name="email"></div>' +
            '<div class="form-group"><label>' + escapeAttr(i18n.reply) + '*</label><textarea class="dadsoo-comment-textarea" name="comment" rows="3" required></textarea></div>' +
            '<div class="dadsoo-hp" aria-hidden="true"><input type="text" name="' + escapeAttr(honeypot) + '" value="" tabindex="-1" autocomplete="off"></div>' +
            '<input type="hidden" name="parent_id" value="' + escapeAttr(commentId) + '">' +
            '<input type="hidden" name="post_id" value="' + escapeAttr(postId) + '">' +
            '<button type="submit" class="dadsoo-submit-btn"><span class="dadsoo-spinner" aria-hidden="true"></span>' +
            '<span class="dadsoo-submit-btn-text">' + escapeAttr(i18n.send) + '</span></button>' +
            '<button type="button" class="dadsoo-cancel-reply-btn">' + escapeAttr(i18n.cancel) + '</button>' +
            '<div class="dadsoo-message" role="status" aria-live="polite"></div>' +
            '</form>'
        ).slideDown(300);
    });

    $(document).on('click', '.dadsoo-cancel-reply-btn', function () {
        $(this).closest('.dadsoo-reply-form').slideUp(300);
    });

    $(document).on('submit', '.dadsoo-reply-form-inner', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $message = $form.find('.dadsoo-message');
        var $replyForm = $form.closest('.dadsoo-reply-form');
        var $comment = $form.closest('.dadsoo-comment');
        var $container = commentsContainerFor($comment);

        var data = $.extend({
            action: 'dadsoo_ajax_reply_comment',
            _wpnonce: settings.nonce
        }, iconsFor($container));

        $form.find('input, textarea').each(function () {
            if (this.name) {
                data[this.name] = $(this).val();
            }
        });

        $message.removeClass('success error').empty();

        $.ajax({
            url: settings.ajaxurl,
            type: 'POST',
            data: data,
            beforeSend: function () {
                setBusy($form.find('.dadsoo-submit-btn'), true);
                $form.find('button').prop('disabled', true);
            },
            success: function (response) {
                if (!response || !response.success) {
                    showMessage($message, 'error', (response && response.data) || i18n.replyFailed);
                    return;
                }

                var payload = response.data || {};
                showMessage($message, 'success', payload.message || i18n.replySaved);

                // پاسخ تأییدنشده نمایش داده نمی‌شود تا کاربر آن را منتشرشده نپندارد.
                if (payload.approved && payload.html) {
                    var $replies = ownChild($comment, '.dadsoo-replies');
                    if (!$replies.length) {
                        $replies = $('<div class="dadsoo-replies"></div>');
                        $comment.children('.dadsoo-comment-body').append($replies);
                    }

                    $replies.append(payload.html);
                    $replyForm.slideUp(300);
                } else {
                    $form.find('input[name="name"], input[name="email"], textarea').val('');
                }
            },
            error: function () {
                showMessage($message, 'error', i18n.serverError);
            },
            complete: function () {
                setBusy($form.find('.dadsoo-submit-btn'), false);
                $form.find('button').prop('disabled', false);
            }
        });
    });
});
