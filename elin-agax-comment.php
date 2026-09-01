<?php
/*
Plugin Name: Elin Agax Comment
Plugin URI: https://elinweb.ir
Description: سیستم کامنت‌گذاری پیشرفته Elinweb با پاسخ‌های تو در تو و مدیریت کامل
Version: 2.3.0
Author: ایمان شادمهری
Author URI: https://elinweb.ir
Requires at least: 5.8
Requires PHP: 7.4
Text Domain: elin-agax-comment
*/

if (!defined('ABSPATH')) {
    exit;
}

define('ELINWEB_AGAX_COMMENT_VERSION', '2.3.0');

class Elinweb_Agax_Comment
{
    /** بیشترین عمق تو در تویی که استایل‌ها و رندر پشتیبانی می‌کنند. */
    const MAX_DEPTH = 3;

    /** اندازهٔ پیش‌فرض آواتار بر حسب پیکسل. */
    const DEFAULT_AVATAR_SIZE = 42;


    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('elin-agax-comment-form', array($this, 'render_comment_form'));
        add_shortcode('elin-agax-comments', array($this, 'render_comments_list'));
        add_action('wp_ajax_elinweb_agax_submit_comment', array($this, 'submit_comment'));
        add_action('wp_ajax_nopriv_elinweb_agax_submit_comment', array($this, 'submit_comment'));
        add_action('wp_ajax_elinweb_agax_load_comments', array($this, 'load_comments'));
        add_action('wp_ajax_nopriv_elinweb_agax_load_comments', array($this, 'load_comments'));
        add_action('wp_ajax_elinweb_agax_comment_vote', array($this, 'handle_vote'));
        add_action('wp_ajax_nopriv_elinweb_agax_comment_vote', array($this, 'handle_vote'));
        add_action('wp_ajax_elinweb_agax_reply_comment', array($this, 'handle_reply'));
        add_action('wp_ajax_nopriv_elinweb_agax_reply_comment', array($this, 'handle_reply'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('elementor/widgets/register', array($this, 'register_elementor_widgets'));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('elinweb-agax-comment-style', plugins_url('assets/css/style.css', __FILE__), array(), ELINWEB_AGAX_COMMENT_VERSION);

        // استایل داینامیک برای رنگ‌ها
        $custom_css = "
            :root {
                --like-color: #0073aa;
                --dislike-color: #e74c3c;
                --reply-color: #6c757d;
            }
        ";
        wp_add_inline_style('elinweb-agax-comment-style', $custom_css);

        wp_enqueue_script('elinweb-agax-comment-script', plugins_url('assets/js/script.js', __FILE__), array('jquery'), ELINWEB_AGAX_COMMENT_VERSION, true);

        wp_localize_script('elinweb-agax-comment-script', 'elinwebAgaxComment', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elinweb-agax-comment-nonce')
        ));
    }

    public function render_comment_form()
    {
        ob_start();
?>
        <div class="dadsoo-comment-form">
            <form id="dadsoo-comment-form" method="post">
                <div class="form-group">
                    <label for="dadsoo-name">نام و نام خانوادگی*</label>
                    <input type="text" id="dadsoo-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="dadsoo-email">ایمیل</label>
                    <input type="email" id="dadsoo-email" name="email">
                </div>
                <div class="form-group">
                    <label for="dadsoo-comment">نظر شما*</label>
                    <textarea
                        class="dadsoo-comment-textarea"
                        id="dadsoo-comment"
                        name="comment"
                        rows="5"
                        required
                        autocomplete="off"></textarea>
                </div>
                <input type="hidden" name="post_id" value="<?php echo esc_attr(get_the_ID()); ?>">
                <input type="hidden" name="action" value="elinweb_agax_submit_comment">
                <?php wp_nonce_field('elinweb-agax-comment-nonce', 'elinweb_agax_nonce'); ?>
                <button type="submit" class="dadsoo-submit-btn">ارسال نظر</button>
                <div class="dadsoo-message"></div>
            </form>
        </div>
    <?php
        return ob_get_clean();
    }

    public function render_comments_list($atts)
    {
        $atts = shortcode_atts(array(
            'items' => 5,
            'vote_icons' => array(),
            'avatar_size' => self::DEFAULT_AVATAR_SIZE,
        ), $atts);

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        ob_start();
    ?>
        <div class="dadsoo-comments-container" data-items="<?php echo esc_attr($atts['items']); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-avatar-size="<?php echo esc_attr($this->sanitize_avatar_size($atts['avatar_size'])); ?>" data-vote-icons="<?php echo esc_attr(wp_json_encode($this->sanitize_vote_icons($atts['vote_icons']))); ?>">
            <div class="dadsoo-comments-list"></div>
            <button class="dadsoo-load-more" style="display:none;">بارگذاری نظرات بیشتر</button>
        </div>
    <?php
        return ob_get_clean();
    }

    private function render_single_comment($comment, $depth = 0, $vote_icons = array(), $avatar_size = self::DEFAULT_AVATAR_SIZE)
    {
        $depth = max(0, min(self::MAX_DEPTH, (int) $depth));
        $avatar_size = $this->sanitize_avatar_size($avatar_size);
        $can_reply = $depth < self::MAX_DEPTH;
        $likes = get_comment_meta($comment->comment_ID, 'elinweb_agax_likes', true) ?: 0;
        $dislikes = get_comment_meta($comment->comment_ID, 'elinweb_agax_dislikes', true) ?: 0;
        $user_vote = isset($_COOKIE['elinweb_agax_vote_' . $comment->comment_ID]) ? sanitize_key(wp_unslash($_COOKIE['elinweb_agax_vote_' . $comment->comment_ID])) : '';
        $user_vote = in_array($user_vote, array('like', 'dislike'), true) ? $user_vote : '';
        $comment_unique_id = 'comment-' . $comment->comment_ID;

        ob_start();
    ?>
        <div class="dadsoo-comment depth-<?php echo $depth; ?>" id="<?php echo $comment_unique_id; ?>" data-comment-id="<?php echo $comment->comment_ID; ?>">
            <article class="dadsoo-comment-body">
                <header class="dadsoo-comment-header">
                    <div class="dadsoo-comment-author-info">
                        <div class="dadsoo-comment-avatar">
                            <?php echo get_avatar($comment->comment_author_email, $avatar_size); ?>
                        </div>
                        <span class="dadsoo-comment-author"><?php echo esc_html($comment->comment_author); ?></span>
                    </div>
                    <span class="dadsoo-comment-date"><?php echo get_comment_date('j F Y', $comment->comment_ID); ?></span>
                </header>
            <footer class="dadsoo-comment-footer">
                    <div class="dadsoo-comment-actions">
                        <?php if ($can_reply): ?>
                        <button type="button" class="dadsoo-reply-btn"
                            data-comment-id="<?php echo $comment->comment_ID; ?>"
                            data-comment-unique="<?php echo $comment_unique_id; ?>">
                            پاسخ به نظر
                        </button>
                        <?php endif; ?>
                        <button class="dadsoo-vote-btn <?php echo ($user_vote === 'like') ? 'active' : ''; ?>"
                            data-comment-id="<?php echo $comment->comment_ID; ?>"
                            data-vote="like"
                            aria-label="لایک">
                            <?php echo $this->render_vote_icons('like', $vote_icons); ?>
                            <span class="dadsoo-like-count"><?php echo $likes; ?></span>
                        </button>
                        <button class="dadsoo-vote-btn <?php echo ($user_vote === 'dislike') ? 'active' : ''; ?>"
                            data-comment-id="<?php echo $comment->comment_ID; ?>"
                            data-vote="dislike"
                            aria-label="دیسلایک">
                            <?php echo $this->render_vote_icons('dislike', $vote_icons); ?>
                            <span class="dadsoo-dislike-count"><?php echo $dislikes; ?></span>
                        </button>
                        
                    </div>
                </footer>
                <div class="dadsoo-comment-text">
                    <?php echo wp_kses(nl2br($comment->comment_content), array(
                        'br' => array(),
                        'p' => array(),
                        'strong' => array(),
                        'em' => array(),
                        'a' => array('href' => array(), 'title' => array())
                    )); ?>
                </div>

                

                <?php if ($can_reply): ?>
                    <div class="dadsoo-reply-form" id="reply-form-<?php echo $comment_unique_id; ?>" style="display:none;"></div>
                <?php endif; ?>

                <div class="dadsoo-replies">
                    <?php
                    // پاسخ‌ها همیشه رندر می‌شوند؛ فقط عمق برای استایل محدود می‌شود تا هیچ دیدگاه تأییدشده‌ای پنهان نماند.
                    $replies = get_comments(array(
                        'parent' => $comment->comment_ID,
                        'status' => 'approve',
                        'order' => 'ASC'
                    ));

                    foreach ($replies as $reply) {
                        echo $this->render_single_comment($reply, $depth + 1, $vote_icons, $avatar_size);
                    }
                    ?>
                </div>
            </article>
        </div>
    <?php
        return ob_get_clean();
    }

    private function sanitize_vote_icons($vote_icons)
    {
        if (is_string($vote_icons)) {
            $vote_icons = json_decode($vote_icons, true);
        }

        if (!is_array($vote_icons)) {
            return array();
        }

        $sanitized = array();
        foreach (array('like_outline', 'like_fill', 'dislike_outline', 'dislike_fill') as $key) {
            $value = isset($vote_icons[$key]['value']) ? $vote_icons[$key]['value'] : '';
            $library = isset($vote_icons[$key]['library']) ? sanitize_key($vote_icons[$key]['library']) : '';
            if (is_string($value) && preg_match('/^[a-zA-Z0-9_\-\s]+$/', $value)) {
                $sanitized[$key] = array(
                    'value' => $value,
                    'library' => $library,
                );
            } elseif (is_array($value) && !empty($value['url'])) {
                $url = esc_url_raw($value['url']);
                if ($url) {
                    $sanitized[$key] = array(
                        'value' => array(
                            'id' => isset($value['id']) ? absint($value['id']) : 0,
                            'url' => $url,
                        ),
                        'library' => $library,
                    );
                }
            }
        }

        return $sanitized;
    }

    private function render_vote_icons($vote_type, $vote_icons)
    {
        $vote_icons = $this->sanitize_vote_icons($vote_icons);
        $outline_key = $vote_type . '_outline';
        $fill_key = $vote_type . '_fill';

        $outline = !empty($vote_icons[$outline_key]) ? $vote_icons[$outline_key] : null;
        $fill = !empty($vote_icons[$fill_key]) ? $vote_icons[$fill_key] : null;

        if (!$outline && !$fill) {
            return '<span class="icon"></span>';
        }

        // اگر فقط یکی از دو حالت تنظیم شده باشد، همان آیکون برای هر دو حالت به کار می‌رود
        // تا دکمه در حالت دیگر خالی نماند.
        $outline = $outline ? $outline : $fill;
        $fill = $fill ? $fill : $outline;

        $output = '';
        foreach (array('outline' => $outline, 'fill' => $fill) as $state => $icon) {
            $output .= sprintf(
                '<span class="icon elinweb-custom-vote-icon elinweb-vote-icon-%1$s">%2$s</span>',
                esc_attr($state),
                $this->render_elementor_icon($icon)
            );
        }

        return $output;
    }

    private function sanitize_avatar_size($size)
    {
        $size = absint($size);

        return $size >= 20 && $size <= 320 ? $size : self::DEFAULT_AVATAR_SIZE;
    }

    /**
     * عمق یک دیدگاه را با دنبال‌کردن زنجیرهٔ والدها حساب می‌کند.
     */
    private function comment_depth($comment)
    {
        $depth = 0;
        $parent_id = (int) $comment->comment_parent;

        while ($parent_id > 0 && $depth < self::MAX_DEPTH) {
            $parent = get_comment($parent_id);
            if (!$parent) {
                break;
            }
            $depth++;
            $parent_id = (int) $parent->comment_parent;
        }

        return $depth;
    }

    /**
     * IP و شناسهٔ مرورگر فرستنده را برای مدیریت و بررسی هرزنامه برمی‌گرداند.
     */
    private function request_meta()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        return array(
            'comment_author_IP' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '',
            'comment_agent' => substr($agent, 0, 255),
        );
    }

    private function render_elementor_icon($icon)
    {
        if (class_exists('\\Elementor\\Icons_Manager')) {
            ob_start();
            \Elementor\Icons_Manager::render_icon($icon, array('aria-hidden' => 'true'));
            $icon_html = ob_get_clean();
            if ($icon_html) {
                return $icon_html;
            }
        }

        if (is_array($icon['value'])) {
            return sprintf('<img src="%s" alt="" aria-hidden="true">', esc_url($icon['value']['url']));
        }

        return sprintf('<i class="%s" aria-hidden="true"></i>', esc_attr($icon['value']));
    }

    public function submit_comment()
    {
        if (!isset($_POST['elinweb_agax_nonce']) || !wp_verify_nonce($_POST['elinweb_agax_nonce'], 'elinweb-agax-comment-nonce')) {
            wp_send_json_error('خطای امنیتی', 403);
            return;
        }

        $name = isset($_POST['name']) ? trim(sanitize_text_field(wp_unslash($_POST['name']))) : '';

        // فیلد نظر معمولاً «comment» است. اگر قالب نام دیگری داشت، اولین فیلد متنیِ
        // خارج از فهرست فیلدهای شناخته‌شده استفاده می‌شود.
        $reserved = array('name', 'email', 'post_id', 'action', 'elinweb_agax_nonce', '_wpnonce', '_wp_http_referer', 'vote_icons', 'avatar_size');
        $comment = '';

        if (isset($_POST['comment']) && is_string($_POST['comment'])) {
            $comment = trim(wp_kses_post(wp_unslash($_POST['comment'])));
        } else {
            foreach ($_POST as $key => $value) {
                if (in_array($key, $reserved, true) || !is_string($value)) {
                    continue;
                }
                $comment = trim(wp_kses_post(wp_unslash($value)));
                break;
            }
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        // اعتبارسنجی پیشرفته
        if (empty($name) || $this->string_length($name) < 2) {
            wp_send_json_error('نام باید حداقل ۲ کاراکتر داشته باشد');
            return;
        }

        if (empty($comment) || $this->string_length($comment) < 5) {
            wp_send_json_error('نظر باید حداقل ۵ کاراکتر داشته باشد');
            return;
        }

        if ($post_id <= 0 || !get_post($post_id)) {
            wp_send_json_error('پست معتبر نیست');
            return;
        }

        if (!comments_open($post_id)) {
            wp_send_json_error('ارسال نظر برای این پست بسته است.', 403);
            return;
        }

        // پردازش موفقیت‌آمیز
        $user_id = get_current_user_id();
        $is_moderator = $user_id && user_can($user_id, 'moderate_comments');
        $comment_data = array_merge(array(
            'comment_post_ID' => $post_id,
            'comment_author' => $name,
            'comment_content' => $comment,
            'user_id' => $user_id,
            'comment_approved' => $is_moderator ? 1 : 0,
        ), $this->request_meta());

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if ($email && is_email($email)) {
            $comment_data['comment_author_email'] = $email;
        }

        $comment_id = wp_insert_comment(wp_slash($comment_data));

        if ($comment_id) {
            $vote_icons = isset($_POST['vote_icons']) ? $this->sanitize_vote_icons(wp_unslash($_POST['vote_icons'])) : array();
            $avatar_size = isset($_POST['avatar_size']) ? $this->sanitize_avatar_size($_POST['avatar_size']) : self::DEFAULT_AVATAR_SIZE;

            wp_send_json_success(array(
                'approved' => (bool) $is_moderator,
                'message' => $is_moderator ? 'نظر شما ثبت و منتشر شد.' : 'نظر شما ثبت شد و پس از تأیید نمایش داده می‌شود.',
                'html' => $is_moderator ? $this->render_single_comment(get_comment($comment_id), 0, $vote_icons, $avatar_size) : '',
            ));
        } else {
            wp_send_json_error('خطا در ثبت نظر');
        }
    }

    public function load_comments()
    {
        check_ajax_referer('elinweb-agax-comment-nonce', '_wpnonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $offset = isset($_POST['offset']) ? max(0, intval($_POST['offset'])) : 0;
        $items = isset($_POST['items']) ? min(100, max(1, intval($_POST['items']))) : 5;

        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error('پست معتبر نیست.', 400);
            return;
        }

        $args = array(
            'post_id' => $post_id,
            'status' => 'approve',
            'number' => $items,
            'offset' => $offset,
            'order' => 'DESC',
            'parent' => 0
        );

        $comments = get_comments($args);
        $output = '';

        $vote_icons = isset($_POST['vote_icons']) ? $this->sanitize_vote_icons(wp_unslash($_POST['vote_icons'])) : array();
        $avatar_size = isset($_POST['avatar_size']) ? $this->sanitize_avatar_size($_POST['avatar_size']) : self::DEFAULT_AVATAR_SIZE;

        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $output .= $this->render_single_comment($comment, 0, $vote_icons, $avatar_size);
            }
        }

        wp_send_json_success(array(
            'html' => $output,
            'count' => count($comments)
        ));
    }

    public function handle_vote()
    {
        check_ajax_referer('elinweb-agax-comment-nonce', '_wpnonce');

        if (!isset($_POST['comment_id']) || !isset($_POST['vote_type'])) {
            wp_send_json_error('پارامترهای ضروری ارسال نشده‌اند', 400);
            return;
        }

        $comment_id = intval($_POST['comment_id']);
        $vote_type = sanitize_text_field($_POST['vote_type']);
        if (!in_array($vote_type, array('like', 'dislike'), true) || !get_comment($comment_id)) {
            wp_send_json_error('رأی یا نظر معتبر نیست.', 400);
            return;
        }
        $cookie_name = 'elinweb_agax_vote_' . $comment_id;
        $current_vote = isset($_COOKIE[$cookie_name]) ? sanitize_key(wp_unslash($_COOKIE[$cookie_name])) : '';
        $current_vote = in_array($current_vote, array('like', 'dislike'), true) ? $current_vote : '';

        try {
            if ($current_vote) {
                if ($current_vote === $vote_type) {
                    $this->update_vote_count($comment_id, $vote_type, -1);
                    setcookie($cookie_name, '', time() - 3600, '/');
                    wp_send_json_success($this->get_vote_response($comment_id, 'removed', $vote_type));
                    return;
                } else {
                    $this->update_vote_count($comment_id, $current_vote, -1);
                    $this->update_vote_count($comment_id, $vote_type, 1);
                    setcookie($cookie_name, $vote_type, time() + (30 * DAY_IN_SECONDS), '/');
                    wp_send_json_success($this->get_vote_response($comment_id, 'switched', $vote_type));
                    return;
                }
            } else {
                $this->update_vote_count($comment_id, $vote_type, 1);
                setcookie($cookie_name, $vote_type, time() + (30 * DAY_IN_SECONDS), '/');
                wp_send_json_success($this->get_vote_response($comment_id, 'added', $vote_type));
                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('خطا در پردازش رای: ' . $e->getMessage(), 500);
        }
    }

    private function update_vote_count($comment_id, $vote_type, $change)
    {
        $meta_key = 'elinweb_agax_' . ($vote_type === 'like' ? 'likes' : 'dislikes');
        $current = get_comment_meta($comment_id, $meta_key, true) ?: 0;
        $new = max(0, $current + $change);
        update_comment_meta($comment_id, $meta_key, $new);
    }

    private function get_vote_response($comment_id, $status, $vote_type)
    {
        return array(
            'status' => $status,
            'vote_type' => $vote_type,
            'likes' => get_comment_meta($comment_id, 'elinweb_agax_likes', true) ?: 0,
            'dislikes' => get_comment_meta($comment_id, 'elinweb_agax_dislikes', true) ?: 0,
            'comment_id' => $comment_id
        );
    }

    public function handle_reply()
    {
        check_ajax_referer('elinweb-agax-comment-nonce', '_wpnonce');

        $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $comment = isset($_POST['comment']) ? wp_kses_post(wp_unslash($_POST['comment'])) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (empty(trim($name)) || empty(trim($comment))) {
            wp_send_json_error('لطفا نام و پاسخ خود را وارد کنید.');
            return;
        }

        if (!empty($email) && !is_email($email)) {
            wp_send_json_error('فرمت ایمیل وارد شده صحیح نیست.');
            return;
        }

        $parent_comment = get_comment($parent_id);
        if (!$parent_comment || !$post_id || (int) $parent_comment->comment_post_ID !== $post_id || !comments_open($post_id)) {
            wp_send_json_error('نظر والد یا پست معتبر نیست.', 400);
            return;
        }

        // پاسخ عمیق‌تر از حد مجاز پذیرفته نمی‌شود، چون در فهرست قابل نمایش نیست.
        if ($this->comment_depth($parent_comment) + 1 > self::MAX_DEPTH) {
            wp_send_json_error('امکان پاسخ در این سطح وجود ندارد.', 400);
            return;
        }

        $comment = wp_kses(
            $comment,
            array(
                'br' => array(),
                'p' => array(),
                'strong' => array(),
                'em' => array(),
                'a' => array('href' => array(), 'title' => array())
            )
        );

        $user_id = get_current_user_id();
        $is_moderator = $user_id && user_can($user_id, 'moderate_comments');
        $comment_data = array_merge(array(
            'comment_post_ID' => $post_id,
            'comment_author' => $name,
            'comment_author_email' => $email,
            'comment_content' => $comment,
            'comment_type' => '',
            'comment_parent' => $parent_id,
            'user_id' => $user_id,
            'comment_approved' => $is_moderator ? 1 : 0,
        ), $this->request_meta());

        $comment_id = wp_insert_comment(wp_slash($comment_data));

        if ($comment_id) {
            $reply = get_comment($comment_id);
            $vote_icons = isset($_POST['vote_icons']) ? $this->sanitize_vote_icons(wp_unslash($_POST['vote_icons'])) : array();
            $avatar_size = isset($_POST['avatar_size']) ? $this->sanitize_avatar_size($_POST['avatar_size']) : self::DEFAULT_AVATAR_SIZE;

            wp_send_json_success(array(
                'approved' => (bool) $is_moderator,
                'message' => $is_moderator ? 'پاسخ شما ثبت و منتشر شد.' : 'پاسخ شما ثبت شد و پس از تأیید نمایش داده می‌شود.',
                'html' => $is_moderator ? $this->render_single_comment($reply, $this->comment_depth($reply), $vote_icons, $avatar_size) : '',
            ));
        } else {
            wp_send_json_error('خطا در ثبت پاسخ. لطفا مجددا تلاش کنید.');
        }
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            'تنظیمات Elin Agax Comment',
            'Elin Agax Comment',
            'manage_options',
            'elin-agax-comment-settings',
            array($this, 'render_settings_page')
        );
    }

    private function string_length($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    public function register_elementor_widgets($widgets_manager)
    {
        if (!class_exists('\\Elementor\\Widget_Base')) {
            return;
        }

        require_once plugin_dir_path(__FILE__) . 'includes/class-elinweb-agax-elementor-form-widget.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-elinweb-agax-elementor-comments-widget.php';

        $widgets_manager->register(new Elinweb_Agax_Elementor_Form_Widget());
        $widgets_manager->register(new Elinweb_Agax_Elementor_Comments_Widget());
    }

    public function render_settings_page()
    {
    ?>
        <div class="wrap">
            <h1>تنظیمات Elin Agax Comment</h1>
            <div class="dadsoo-settings-content">
                <h2>راهنمای استفاده</h2>
                <p>برای نمایش فرم ثبت نظر از شورت کد زیر استفاده کنید:</p>
                <code>[elin-agax-comment-form]</code>

                <p>برای نمایش لیست نظرات از شورت کد زیر استفاده کنید:</p>
                <code>[elin-agax-comments]</code>
                <p>یا برای تعیین تعداد نظرات نمایش داده شده:</p>
                <code>[elin-agax-comments items="10"]</code>

                <h3>ویژگی‌های افزونه:</h3>
                <ul>
                    <li>سیستم ثبت نظر با Ajax</li>
                    <li>نمایش تو در توی کامنت‌ها</li>
                    <li>پشتیبانی از Enter در متن کامنت‌ها</li>
                    <li>سیستم لایک/دیسلایک با قابلیت برگشت‌پذیری</li>
                    <li>امکان پاسخ به نظرات</li>
                    <li>بارگذاری تدریجی نظرات</li>
                </ul>
            </div>
        </div>
<?php
    }
}

function elinweb_agax_comment()
{
    static $plugin = null;
    if (null === $plugin) {
        $plugin = new Elinweb_Agax_Comment();
    }

    return $plugin;
}

elinweb_agax_comment();
