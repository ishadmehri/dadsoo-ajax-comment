<?php
/*
Plugin Name: Elin Agax Comment
Plugin URI: https://elinweb.ir
Description: سیستم کامنت‌گذاری پیشرفته Elinweb با پاسخ‌های تو در تو و مدیریت کامل
Version: 2.2
Author: ایمان شادمهری
Author URI: https://elinweb.ir
Requires at least: 5.8
Requires PHP: 7.4
Text Domain: elin-agax-comment
*/

if (!defined('ABSPATH')) {
    exit;
}

class Elinweb_Agax_Comment
{
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
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('elinweb-agax-comment-style', plugins_url('assets/css/style.css', __FILE__), array(), '2.2');

        // استایل داینامیک برای رنگ‌ها
        $custom_css = "
            :root {
                --like-color: #0073aa;
                --dislike-color: #e74c3c;
                --reply-color: #6c757d;
            }
        ";
        wp_add_inline_style('elinweb-agax-comment-style', $custom_css);

        wp_enqueue_script('elinweb-agax-comment-script', plugins_url('assets/js/script.js', __FILE__), array('jquery'), '2.2', true);

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
            'items' => 5
        ), $atts);

        $post_id = get_the_ID();
        if (!$post_id) {
            return '';
        }

        ob_start();
    ?>
        <div class="dadsoo-comments-container" data-items="<?php echo esc_attr($atts['items']); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
            <div class="dadsoo-comments-list"></div>
            <button class="dadsoo-load-more" style="display:none;">بارگذاری نظرات بیشتر</button>
        </div>
    <?php
        return ob_get_clean();
    }

    private function render_single_comment($comment, $depth = 0, $is_reply = false)
    {
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
                            <?php echo get_avatar($comment->comment_author_email, 42); ?>
                        </div>
                        <span class="dadsoo-comment-author"><?php echo esc_html($comment->comment_author); ?></span>
                    </div>
                    <span class="dadsoo-comment-date"><?php echo get_comment_date('j F Y', $comment->comment_ID); ?></span>
                </header>
            <footer class="dadsoo-comment-footer">
                    <div class="dadsoo-comment-actions">
                        <button type="button" class="dadsoo-reply-btn"
                            data-comment-id="<?php echo $comment->comment_ID; ?>"
                            data-comment-unique="<?php echo $comment_unique_id; ?>">
                            پاسخ به نظر
                        </button>
                        <button class="dadsoo-vote-btn <?php echo ($user_vote === 'like') ? 'active' : ''; ?>"
                            data-comment-id="<?php echo $comment->comment_ID; ?>"
                            data-vote="like"
                            aria-label="لایک">
                            <span class="icon"></span>
                            <span class="dadsoo-like-count"><?php echo $likes; ?></span>
                        </button>
                        <button class="dadsoo-vote-btn <?php echo ($user_vote === 'dislike') ? 'active' : ''; ?>"
                            data-comment-id="<?php echo $comment->comment_ID; ?>"
                            data-vote="dislike"
                            aria-label="دیسلایک">
                            <span class="icon"></span>
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

                

                <div class="dadsoo-reply-form" id="reply-form-<?php echo $comment_unique_id; ?>" style="display:none;"></div>

                <?php if (!$is_reply): ?>
                    <div class="dadsoo-replies">
                        <?php
                        $replies = get_comments(array(
                            'parent' => $comment->comment_ID,
                            'status' => 'approve',
                            'order' => 'ASC'
                        ));

                        foreach ($replies as $reply) {
                            echo $this->render_single_comment($reply, $depth + 1, true);
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    <?php
        return ob_get_clean();
    }

    public function submit_comment()
    {
        if (!isset($_POST['elinweb_agax_nonce']) || !wp_verify_nonce($_POST['elinweb_agax_nonce'], 'elinweb-agax-comment-nonce')) {
            wp_send_json_error('خطای امنیتی', 403);
            return;
        }

        // دریافت تمام فیلدهای ارسالی برای دیباگ
        //error_log('Received POST data: ' . print_r($_POST, true));

        $name = isset($_POST['name']) ? trim(sanitize_text_field($_POST['name'])) : '';

        // پیدا کردن خودکار فیلد نظر بدون توجه به نام آن
        $comment = '';
        foreach ($_POST as $key => $value) {
            if (!in_array($key, ['name', 'email', 'post_id', 'action', 'elinweb_agax_nonce'])) {
                $comment = trim(wp_kses_post($value));
                break;
            }
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        // اعتبارسنجی پیشرفته
        if (empty($name) || wp_strlen($name) < 2) {
            wp_send_json_error('نام باید حداقل ۲ کاراکتر داشته باشد');
            return;
        }

        if (empty($comment) || wp_strlen($comment) < 5) {
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
        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_author' => $name,
            'comment_content' => $comment,
            'comment_approved' => 0,
        ];

        if (!empty($_POST['email']) && is_email($_POST['email'])) {
            $comment_data['comment_author_email'] = sanitize_email($_POST['email']);
        }

        $comment_id = wp_insert_comment(wp_slash($comment_data));

        if ($comment_id) {
            wp_send_json_success('نظر با موفقیت ثبت شد');
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

        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $output .= $this->render_single_comment($comment);
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

        $parent_id = intval($_POST['parent_id']);
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $comment = isset($_POST['comment']) ? wp_kses_post($_POST['comment']) : '';
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

        $comment_data = array(
            'comment_post_ID' => $post_id,
            'comment_author' => $name,
            'comment_author_email' => $email,
            'comment_content' => $comment,
            'comment_type' => '',
            'comment_parent' => $parent_id,
            'user_id' => get_current_user_id(),
            'comment_approved' => 0,
        );

        $comment_id = wp_insert_comment(wp_slash($comment_data));

        if ($comment_id) {
            $reply = get_comment($comment_id);
            wp_send_json_success($this->render_single_comment($reply));
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

new Elinweb_Agax_Comment();
