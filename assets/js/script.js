jQuery(document).ready(function ($) {
    // ارسال فرم کامنت اصلی
    $('#dadsoo-comment-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);

        // جمع‌آوری تمام داده‌های فرم به صورت داینامیک
        var formData = {};
        $form.find('input, textarea').each(function () {
            if (this.name) {
                formData[this.name] = $(this).val();
            }
        });
        formData.action = 'elinweb_agax_submit_comment';

        //console.log('Form data to send:', formData); // برای دیباگ

        $.ajax({
            url: elinwebAgaxComment.ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function () {
                $form.find('button').prop('disabled', true);
            },
            success: function (response) {
                if (response.success) {
                    alert('نظر شما ثبت شد!');
                    $form[0].reset();
                } else {
                    alert('خطا: ' + response.data);
                }
            },
            error: function (xhr) {
                console.error('AJAX Error:', xhr.responseText);
                alert('خطای سرور: ' + xhr.statusText);
            },
            complete: function () {
                $form.find('button').prop('disabled', false);
            }
        });
    });

    // بارگذاری کامنت‌ها
    $('.dadsoo-comments-container').each(function () {
        var $container = $(this);
        var $list = $container.find('.dadsoo-comments-list');
        var $loadMore = $container.find('.dadsoo-load-more');
        var itemsPerPage = parseInt($container.data('items'));
        var postId = $container.data('post-id');
        var offset = 0;

        function loadComments() {
            $.ajax({
                url: elinwebAgaxComment.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elinweb_agax_load_comments',
                    post_id: postId,
                    offset: offset,
                    items: itemsPerPage,
                    _wpnonce: elinwebAgaxComment.nonce
                },
                beforeSend: function () {
                    $loadMore.prop('disabled', true);
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.html) {
                            $list.append(response.data.html);
                            offset += itemsPerPage;

                            if (response.data.count < itemsPerPage) {
                                $loadMore.hide();
                            } else {
                                $loadMore.show();
                            }
                        }
                    }
                },
                error: function (xhr) {
                    console.error(xhr.responseText);
                },
                complete: function () {
                    $loadMore.prop('disabled', false);
                }
            });
        }

        $loadMore.on('click', loadComments);
        loadComments();
    });

    // مدیریت لایک/دیسلایک
    $(document).on('click', '.dadsoo-vote-btn', function (e) {
        e.preventDefault();

        const $button = $(this);
        const commentId = $button.data('comment-id');
        const voteType = $button.data('vote');
        const $commentContainer = $button.closest('.dadsoo-comment');

        $.ajax({
            url: elinwebAgaxComment.ajaxurl,
            type: 'POST',
            data: {
                action: 'elinweb_agax_comment_vote',
                comment_id: commentId,
                vote_type: voteType,
                _wpnonce: elinwebAgaxComment.nonce
            },
            beforeSend: function () {
                $button.prop('disabled', true).addClass('loading');
            },
            success: function (response) {
                if (response.success) {
                    updateVoteUI($commentContainer, response.data);
                }
            },
            error: function (xhr) {
                console.error('Error:', xhr.responseText);
            },
            complete: function () {
                $button.prop('disabled', false).removeClass('loading');
            }
        });
    });

    function updateVoteUI($container, data) {
        // پیدا کردن دکمه‌های مربوط به همین کامنت
        const $likeBtn = $container.find(`[data-vote="like"][data-comment-id="${data.comment_id}"]`);
        const $dislikeBtn = $container.find(`[data-vote="dislike"][data-comment-id="${data.comment_id}"]`);

        // به‌روزرسانی اعداد
        $likeBtn.find('.dadsoo-like-count').text(data.likes);
        $dislikeBtn.find('.dadsoo-dislike-count').text(data.dislikes);

        // به‌روزرسانی وضعیت active
        switch (data.status) {
            case 'added':
                if (data.vote_type === 'like') {
                    $likeBtn.addClass('active');
                    $dislikeBtn.removeClass('active');
                } else {
                    $dislikeBtn.addClass('active');
                    $likeBtn.removeClass('active');
                }
                break;
            case 'removed':
                $likeBtn.removeClass('active');
                $dislikeBtn.removeClass('active');
                break;
            case 'switched':
                if (data.vote_type === 'like') {
                    $likeBtn.addClass('active');
                    $dislikeBtn.removeClass('active');
                } else {
                    $dislikeBtn.addClass('active');
                    $likeBtn.removeClass('active');
                }
                break;
        }

        // افکت بصری
        const $activeBtn = data.vote_type === 'like' ? $likeBtn : $dislikeBtn;
        $activeBtn.find('.icon').css('transform', 'scale(1.2)');
        setTimeout(() => {
            $activeBtn.find('.icon').css('transform', 'scale(1)');
        }, 300);
    }

    // نمایش فرم پاسخ
    $(document).on('click', '.dadsoo-reply-btn', function (e) {
        e.preventDefault();

        var $button = $(this);
        var commentId = $button.data('comment-id');
        var commentUnique = $button.data('comment-unique'); // این خط اضافه شد
        var $replyForm = $('#reply-form-' + commentUnique); // این خط تغییر کرد

        if ($replyForm.is(':visible')) {
            $replyForm.slideUp(300);
            return;
        }

        $('.dadsoo-reply-form:visible').slideUp(300);

        $replyForm.html(`
        <form class="dadsoo-reply-form-inner">
            <div class="form-group">
                <label>نام و نام خانوادگی*</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>ایمیل</label>
                <input type="email" name="email">
            </div>
            <div class="form-group">
                <label>پاسخ شما*</label>
                <textarea class="dadsoo-comment-textarea" name="comment" rows="3" required></textarea>
            </div>
            <input type="hidden" name="parent_id" value="${commentId}">
            <input type="hidden" name="post_id" value="${$('input[name="post_id"]').val()}">
            <input type="hidden" name="action" value="elinweb_agax_reply_comment">
            <button type="submit" class="dadsoo-submit-btn">ارسال پاسخ</button>
            <button type="button" class="dadsoo-cancel-reply-btn">انصراف</button>
            <div class="dadsoo-message"></div>
        </form>
    `).slideDown(300);
    });

    // انصراف از پاسخ
    $(document).on('click', '.dadsoo-cancel-reply-btn', function () {
        $(this).closest('.dadsoo-reply-form').slideUp(300);
    });

    // ارسال پاسخ
    $(document).on('submit', '.dadsoo-reply-form-inner', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $message = $form.find('.dadsoo-message');
        var $replyForm = $form.closest('.dadsoo-reply-form');
        var $commentContainer = $form.closest('.dadsoo-comment');
        var $repliesContainer = $commentContainer.find('.dadsoo-replies');
        var replyComment = $form.find('.dadsoo-comment-textarea').val();

        $message.removeClass('success error').html('');

        $.ajax({
            url: elinwebAgaxComment.ajaxurl,
            type: 'POST',

            data: {
                action: 'elinweb_agax_reply_comment',
                parent_id: $form.find('input[name="parent_id"]').val(),
                post_id: $form.find('input[name="post_id"]').val(),
                name: $form.find('input[name="name"]').val(),
                email: $form.find('input[name="email"]').val(),
                comment: replyComment, // استفاده از متغیری که تعریف کردیم
                _wpnonce: elinwebAgaxComment.nonce
            },
            beforeSend: function () {
                $form.find('button').prop('disabled', true);
            },
            success: function (response) {
                if (response.success) {
                    $message.addClass('success').html('پاسخ شما با موفقیت ثبت شد.');

                    if ($repliesContainer.length === 0) {
                        $repliesContainer = $('<div class="dadsoo-replies"></div>');
                        $commentContainer.append($repliesContainer);
                    }

                    $repliesContainer.append(response.data);
                    $replyForm.slideUp(300);
                } else {
                    $message.addClass('error').html(response.data);
                }
            },
            error: function () {
                $message.addClass('error').html('خطا در ارتباط با سرور. لطفا مجددا تلاش کنید.');
            },
            complete: function () {
                $form.find('button').prop('disabled', false);
            }
        });
    });
});
