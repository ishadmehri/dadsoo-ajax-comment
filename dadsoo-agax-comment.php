<?php
/*
Plugin Name: Dadsoo Agax Comment
Plugin URI: https://elinweb.ir
Description: Advanced AJAX comment system with threaded replies, like/dislike voting, and full moderation control.
Version: 3.2.0
Author: Iman Shadmehri
Author URI: https://elinweb.ir
Requires at least: 5.8
Requires PHP: 7.4
Text Domain: dadsoo-agax-comment
Domain Path: /languages
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit;
}

define('DADSOO_AGAX_COMMENT_VERSION', '3.2.0');
define('DADSOO_AGAX_COMMENT_FILE', __FILE__);

class Dadsoo_Agax_Comment
{
    /** بیشترین عمق تو در تویی که استایل‌ها و رندر پشتیبانی می‌کنند. */
    const MAX_DEPTH = 3;

    /** اندازهٔ پیش‌فرض آواتار بر حسب پیکسل. */
    const DEFAULT_AVATAR_SIZE = 42;

    const NONCE_ACTION = 'dadsoo-agax-comment-nonce';
    const NONCE_FIELD = 'dadsoo_agax_nonce';

    const META_LIKES = 'dadsoo_agax_likes';
    const META_DISLIKES = 'dadsoo_agax_dislikes';

    /** به‌ازای هر رأی‌دهنده یک ردیف متا با این پیشوند ساخته می‌شود. */
    const META_VOTE_PREFIX = 'dadsoo_agax_vote_';

    const SCHEMA_OPTION = 'dadsoo_agax_comment_schema';

    /** نام فیلد تلهٔ ربات؛ باید همیشه خالی بماند. */
    const HONEYPOT_FIELD = 'dadsoo_agax_confirm';

    /** شمارندهٔ نمونه‌های فرم در یک صفحه، برای ساخت idهای یکتا. */
    private $form_instance = 0;

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'maybe_upgrade'));

        add_action('wp_enqueue_scripts', array($this, 'register_assets'));

        add_shortcode('dadsoo-agax-comment-form', array($this, 'render_comment_form'));
        add_shortcode('dadsoo-agax-comments', array($this, 'render_comments_list'));

        // شورت‌کدهای نسخه‌های پیشین تا محتوای قدیمی از کار نیفتد.
        add_shortcode('elin-agax-comment-form', array($this, 'render_comment_form'));
        add_shortcode('elin-agax-comments', array($this, 'render_comments_list'));

        foreach (array('submit_comment', 'load_comments', 'comment_vote', 'reply_comment') as $endpoint) {
            $callback = array($this, 'ajax_' . $endpoint);
            add_action('wp_ajax_dadsoo_agax_' . $endpoint, $callback);
            add_action('wp_ajax_nopriv_dadsoo_agax_' . $endpoint, $callback);
        }

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('elementor/widgets/register', array($this, 'register_elementor_widgets'));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('dadsoo-agax-comment', false, dirname(plugin_basename(DADSOO_AGAX_COMMENT_FILE)) . '/languages');
    }

    /**
     * متاهای نسخه‌های elinweb را به پیشوند dadsoo منتقل می‌کند تا شمارش رأی‌های
     * پیشین از دست نرود.
     */
    public function maybe_upgrade()
    {
        if (get_option(self::SCHEMA_OPTION) === DADSOO_AGAX_COMMENT_VERSION) {
            return;
        }

        global $wpdb;

        $renamed = 0;
        foreach (array('elinweb_agax_likes' => self::META_LIKES, 'elinweb_agax_dislikes' => self::META_DISLIKES) as $old => $new) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-time bulk meta-key rename; no core API renames a meta key across all rows.
            $renamed += (int) $wpdb->update($wpdb->commentmeta, array('meta_key' => $new), array('meta_key' => $old));
        }

        // wp_cache_flush_group() و wp_cache_supports() هر دو از وردپرس ۶.۱ اضافه شده‌اند؛
        // بررسی وجود خودِ تابع (نه فقط wp_cache_supports) روی نسخه‌های قدیمی‌تر ایمن است.
        if ($renamed && function_exists('wp_cache_flush_group') && function_exists('wp_cache_supports') && wp_cache_supports('flush_group')) {
            wp_cache_flush_group('comment_meta');
        }

        update_option(self::SCHEMA_OPTION, DADSOO_AGAX_COMMENT_VERSION, true);
    }

    // ---------------------------------------------------------------- assets

    /**
     * دارایی‌ها فقط ثبت می‌شوند؛ صف‌شدنشان به رندر واقعی شورت‌کد یا ویجت موکول است
     * تا در صفحاتی که دیدگاهی ندارند بارگذاری نشوند.
     */
    public function register_assets()
    {
        wp_register_style(
            'dadsoo-agax-comment',
            plugins_url('assets/css/style.css', DADSOO_AGAX_COMMENT_FILE),
            array(),
            DADSOO_AGAX_COMMENT_VERSION
        );

        wp_register_script(
            'dadsoo-agax-comment',
            plugins_url('assets/js/script.js', DADSOO_AGAX_COMMENT_FILE),
            array('jquery'),
            DADSOO_AGAX_COMMENT_VERSION,
            true
        );

        wp_localize_script('dadsoo-agax-comment', 'dadsooAgaxComment', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'i18n' => array(
                'commentSaved' => __('Your comment has been saved.', 'dadsoo-agax-comment'),
                'commentFailed' => __('There was an error saving your comment.', 'dadsoo-agax-comment'),
                'replySaved' => __('Your reply has been saved.', 'dadsoo-agax-comment'),
                'replyFailed' => __('There was an error saving your reply.', 'dadsoo-agax-comment'),
                'serverError' => __('A server error occurred. Please try again.', 'dadsoo-agax-comment'),
                'name' => __('Full name', 'dadsoo-agax-comment'),
                'email' => __('Email', 'dadsoo-agax-comment'),
                'reply' => __('Your reply', 'dadsoo-agax-comment'),
                'send' => __('Post reply', 'dadsoo-agax-comment'),
                'cancel' => __('Cancel', 'dadsoo-agax-comment'),
            ),
            'honeypot' => self::HONEYPOT_FIELD,
        ));

        // اگر شورت‌کد در محتوای همین نوشته باشد، استایل زودتر صف می‌شود تا در head برود.
        $post = get_post();
        if ($post instanceof WP_Post) {
            foreach (array('dadsoo-agax-comments', 'dadsoo-agax-comment-form', 'elin-agax-comments', 'elin-agax-comment-form') as $tag) {
                if (has_shortcode($post->post_content, $tag)) {
                    $this->enqueue_assets();
                    break;
                }
            }
        }
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('dadsoo-agax-comment');
        wp_enqueue_script('dadsoo-agax-comment');
    }

    // --------------------------------------------------------------- render

    public function render_comment_form()
    {
        $this->enqueue_assets();

        $post_id = $this->current_post_id();
        $uid = 'dadsoo-form-' . (++$this->form_instance);

        ob_start();
?>
        <div class="dadsoo-comment-form">
            <form class="dadsoo-comment-form-inner" method="post">
                <div class="form-group">
                    <label for="<?php echo esc_attr($uid); ?>-name"><?php esc_html_e('Full name*', 'dadsoo-agax-comment'); ?></label>
                    <input type="text" id="<?php echo esc_attr($uid); ?>-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="<?php echo esc_attr($uid); ?>-email"><?php esc_html_e('Email', 'dadsoo-agax-comment'); ?></label>
                    <input type="email" id="<?php echo esc_attr($uid); ?>-email" name="email">
                </div>
                <div class="form-group">
                    <label for="<?php echo esc_attr($uid); ?>-comment"><?php esc_html_e('Your comment*', 'dadsoo-agax-comment'); ?></label>
                    <textarea
                        class="dadsoo-comment-textarea"
                        id="<?php echo esc_attr($uid); ?>-comment"
                        name="comment"
                        rows="5"
                        required
                        autocomplete="off"></textarea>
                </div>
                <?php $this->render_honeypot($uid); ?>
                <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
                <input type="hidden" name="action" value="dadsoo_agax_submit_comment">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <button type="submit" class="dadsoo-submit-btn">
                    <span class="dadsoo-spinner" aria-hidden="true"></span>
                    <span class="dadsoo-submit-btn-text"><?php esc_html_e('Post comment', 'dadsoo-agax-comment'); ?></span>
                </button>
                <div class="dadsoo-message" role="status" aria-live="polite"></div>
            </form>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * تلهٔ ربات: فیلدی که فقط با CSS پنهان شده و برای کاربر واقعی همیشه خالی می‌ماند.
     */
    private function render_honeypot($uid)
    {
    ?>
        <div class="dadsoo-hp" aria-hidden="true">
            <label for="<?php echo esc_attr($uid); ?>-hp"><?php esc_html_e('Leave this field empty', 'dadsoo-agax-comment'); ?></label>
            <input type="text" id="<?php echo esc_attr($uid); ?>-hp" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" value="" tabindex="-1" autocomplete="off">
        </div>
    <?php
    }

    public function render_comments_list($atts)
    {
        $atts = shortcode_atts(array(
            'items' => 5,
            'vote_icons' => array(),
            'avatar_size' => self::DEFAULT_AVATAR_SIZE,
            'load_more_text' => __('Load more comments', 'dadsoo-agax-comment'),
            'loading_text' => __('Loading comments…', 'dadsoo-agax-comment'),
            'empty_text' => __('No comments yet.', 'dadsoo-agax-comment'),
        ), $atts, 'dadsoo-agax-comments');

        $post_id = $this->current_post_id();
        if (!$post_id) {
            return '';
        }

        $this->enqueue_assets();

        $loading_text = $this->sanitize_label($atts['loading_text'], __('Loading comments…', 'dadsoo-agax-comment'));
        $empty_text = $this->sanitize_label($atts['empty_text'], __('No comments yet.', 'dadsoo-agax-comment'));
        $load_more_text = $this->sanitize_label($atts['load_more_text'], __('Load more comments', 'dadsoo-agax-comment'));

        ob_start();
    ?>
        <div class="dadsoo-comments-container"
            data-items="<?php echo esc_attr($this->sanitize_items($atts['items'])); ?>"
            data-post-id="<?php echo esc_attr($post_id); ?>"
            data-avatar-size="<?php echo esc_attr($this->sanitize_avatar_size($atts['avatar_size'])); ?>"
            data-loading-text="<?php echo esc_attr($loading_text); ?>"
            data-empty-text="<?php echo esc_attr($empty_text); ?>"
            data-vote-icons="<?php echo esc_attr(wp_json_encode($this->sanitize_vote_icons($atts['vote_icons']))); ?>">
            <div class="dadsoo-comments-list"></div>

            <?php // هم حالت «در حال بارگذاری» و هم «نظری نیست» را نشان می‌دهد؛ متن هر دو از data-attribute بالا می‌آید. ?>
            <div class="dadsoo-comments-status is-loading" role="status" aria-live="polite">
                <span class="dadsoo-spinner" aria-hidden="true"></span>
                <span class="dadsoo-comments-status-text"><?php echo esc_html($loading_text); ?></span>
            </div>

            <button type="button" class="dadsoo-load-more" style="display:none;">
                <span class="dadsoo-spinner" aria-hidden="true"></span>
                <span class="dadsoo-load-more-text"><?php echo esc_html($load_more_text); ?></span>
            </button>
        </div>
    <?php
        return ob_get_clean();
    }

    /**
     * متن‌های قابل تنظیم از ویجت یا شورت‌کد می‌آیند؛ خالی‌بودن به مقدار پیش‌فرض برمی‌گردد
     * تا کنترلِ پاک‌شده، دکمه یا پیام را بی‌متن نگذارد.
     */
    private function sanitize_label($value, $fallback)
    {
        $value = is_string($value) ? trim(sanitize_text_field($value)) : '';

        return '' !== $value ? $value : $fallback;
    }

    /**
     * درخت دیدگاه‌ها را با تعداد ثابتی کوئری می‌گیرد (یکی برای هر سطح) به‌جای یک
     * کوئری به‌ازای هر دیدگاه.
     *
     * @return array{0: WP_Comment[], 1: array<int, WP_Comment[]>, 2: bool}
     */
    private function fetch_comment_tree($post_id, $offset, $items)
    {
        // یکی بیشتر می‌گیریم تا بدون کوئری شمارش جداگانه بدانیم صفحهٔ بعدی وجود دارد یا نه.
        $roots = get_comments(array(
            'post_id' => $post_id,
            'status' => 'approve',
            'type' => 'comment',
            'parent' => 0,
            'number' => $items + 1,
            'offset' => $offset,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC',
        ));

        $has_more = count($roots) > $items;
        if ($has_more) {
            array_pop($roots);
        }

        $children = array();
        $all_ids = array();
        $parent_ids = array();

        foreach ($roots as $root) {
            $parent_ids[] = (int) $root->comment_ID;
            $all_ids[] = (int) $root->comment_ID;
        }

        for ($depth = 1; $depth <= self::MAX_DEPTH && !empty($parent_ids); $depth++) {
            $level = get_comments(array(
                'post_id' => $post_id,
                'status' => 'approve',
                'type' => 'comment',
                'parent__in' => $parent_ids,
                'orderby' => 'comment_date_gmt',
                'order' => 'ASC',
            ));

            $parent_ids = array();
            foreach ($level as $child) {
                $children[(int) $child->comment_parent][] = $child;
                $parent_ids[] = (int) $child->comment_ID;
                $all_ids[] = (int) $child->comment_ID;
            }
        }

        // یک کوئری برای متای همهٔ دیدگاه‌ها، تا رندر هرکدام کوئری جدا نزند.
        if ($all_ids) {
            update_meta_cache('comment', $all_ids);
        }

        return array($roots, $children, $has_more);
    }

    private function render_comment_tree($comments, $children, $depth, $vote_icons, $avatar_size)
    {
        $output = '';
        foreach ($comments as $comment) {
            $output .= $this->render_single_comment($comment, $depth, $children, $vote_icons, $avatar_size);
        }

        return $output;
    }

    private function render_single_comment($comment, $depth, $children, $vote_icons, $avatar_size)
    {
        $depth = max(0, min(self::MAX_DEPTH, (int) $depth));
        $avatar_size = $this->sanitize_avatar_size($avatar_size);
        $comment_id = (int) $comment->comment_ID;
        $can_reply = $depth < self::MAX_DEPTH;
        $likes = (int) get_comment_meta($comment_id, self::META_LIKES, true);
        $dislikes = (int) get_comment_meta($comment_id, self::META_DISLIKES, true);
        $user_vote = $this->current_vote($comment_id);
        $replies = isset($children[$comment_id]) ? $children[$comment_id] : array();

        ob_start();
    ?>
        <div class="dadsoo-comment depth-<?php echo esc_attr($depth); ?>" id="dadsoo-comment-<?php echo esc_attr($comment_id); ?>" data-comment-id="<?php echo esc_attr($comment_id); ?>">
            <article class="dadsoo-comment-body">
                <header class="dadsoo-comment-header">
                    <div class="dadsoo-comment-author-info">
                        <div class="dadsoo-comment-avatar">
                            <?php echo get_avatar($comment, $avatar_size); ?>
                        </div>
                        <span class="dadsoo-comment-author"><?php echo esc_html($comment->comment_author); ?></span>
                    </div>
                    <span class="dadsoo-comment-date"><?php echo esc_html(get_comment_date('j F Y', $comment_id)); ?></span>
                </header>
                <footer class="dadsoo-comment-footer">
                    <div class="dadsoo-comment-actions">
                        <?php if ($can_reply): ?>
                            <button type="button" class="dadsoo-reply-btn" data-comment-id="<?php echo esc_attr($comment_id); ?>">
                                <?php esc_html_e('Reply', 'dadsoo-agax-comment'); ?>
                            </button>
                        <?php endif; ?>
                        <?php
                        $this->render_vote_button('like', $comment_id, $likes, $user_vote, $vote_icons);
                        $this->render_vote_button('dislike', $comment_id, $dislikes, $user_vote, $vote_icons);
                        ?>
                    </div>
                </footer>
                <div class="dadsoo-comment-text">
                    <?php echo wp_kses(nl2br($comment->comment_content), $this->allowed_tags()); ?>
                </div>

                <?php if ($can_reply): ?>
                    <div class="dadsoo-reply-form" style="display:none;"></div>
                <?php endif; ?>

                <div class="dadsoo-replies">
                    <?php echo $this->render_comment_tree($replies, $children, $depth + 1, $vote_icons, $avatar_size); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </article>
        </div>
    <?php
        return ob_get_clean();
    }

    private function render_vote_button($vote_type, $comment_id, $count, $user_vote, $vote_icons)
    {
        $is_active = ($user_vote === $vote_type);
        $label = 'like' === $vote_type
            ? __('Like', 'dadsoo-agax-comment')
            : __('Dislike', 'dadsoo-agax-comment');
    ?>
        <button type="button" class="dadsoo-vote-btn<?php echo $is_active ? ' active' : ''; ?>"
            data-comment-id="<?php echo esc_attr($comment_id); ?>"
            data-vote="<?php echo esc_attr($vote_type); ?>"
            aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
            aria-label="<?php echo esc_attr($label); ?>">
            <?php echo $this->render_vote_icons($vote_type, $vote_icons); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php // شمارنده بدون قالب‌بندی محلی چاپ می‌شود تا با عددی که جاوااسکریپت پس از رأی جایگزین می‌کند یکسان بماند. ?>
            <span class="dadsoo-<?php echo esc_attr($vote_type); ?>-count"><?php echo (int) $count; ?></span>
        </button>
    <?php
    }

    /** تگ‌های مجاز در متن دیدگاه؛ هم موقع ذخیره و هم موقع نمایش استفاده می‌شود. */
    private function allowed_tags()
    {
        return array(
            'br' => array(),
            'p' => array(),
            'strong' => array(),
            'em' => array(),
            'a' => array('href' => array(), 'title' => array(), 'rel' => array()),
        );
    }

    // ---------------------------------------------------------------- icons

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
                $url = $this->sanitize_local_url($value['url']);
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

    /**
     * آدرس آیکون از سمت مرورگر برمی‌گردد، پس فقط فایل‌های همین سایت پذیرفته می‌شوند
     * تا کسی نتواند SVG دلخواهی از دامنهٔ دیگر داخل صفحه رندر کند.
     */
    private function sanitize_local_url($url)
    {
        $url = esc_url_raw((string) $url);
        if (!$url) {
            return '';
        }

        $host = wp_parse_url($url, PHP_URL_HOST);
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);

        if ($host && strtolower($host) !== strtolower((string) $site_host)) {
            return '';
        }

        return $url;
    }

    private function render_vote_icons($vote_type, $vote_icons)
    {
        $vote_icons = $this->sanitize_vote_icons($vote_icons);

        $outline = !empty($vote_icons[$vote_type . '_outline']) ? $vote_icons[$vote_type . '_outline'] : null;
        $fill = !empty($vote_icons[$vote_type . '_fill']) ? $vote_icons[$vote_type . '_fill'] : null;

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
                '<span class="icon dadsoo-custom-vote-icon dadsoo-vote-icon-%1$s">%2$s</span>',
                esc_attr($state),
                $this->render_elementor_icon($icon)
            );
        }

        return $output;
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

    // --------------------------------------------------------------- voting

    /**
     * شناسهٔ پایدار رأی‌دهنده. برای کاربر واردشده شناسهٔ کاربری و برای مهمان،
     * هش IP و مرورگر با نمک سایت — تا پاک‌کردن کوکی، رأی را تکرارپذیر نکند.
     */
    private function voter_key()
    {
        $user_id = get_current_user_id();
        if ($user_id) {
            return 'u' . $user_id;
        }

        $meta = $this->request_meta();
        $fingerprint = $meta['comment_author_IP'] . '|' . $meta['comment_agent'];

        return 'g' . substr(hash_hmac('sha256', $fingerprint, wp_salt('nonce')), 0, 24);
    }

    private function vote_meta_key()
    {
        return self::META_VOTE_PREFIX . $this->voter_key();
    }

    /**
     * رأی فعلی این بازدیدکننده روی یک دیدگاه. مرجع، متای سمت سرور است؛ کوکی فقط
     * برای رأی‌هایی خوانده می‌شود که پیش از نسخهٔ ۳ فقط در مرورگر ثبت شده بودند.
     */
    private function current_vote($comment_id)
    {
        $vote = get_comment_meta($comment_id, $this->vote_meta_key(), true);
        if (in_array($vote, array('like', 'dislike'), true)) {
            return $vote;
        }

        foreach (array('dadsoo_agax_vote_', 'elinweb_agax_vote_') as $prefix) {
            $cookie = $prefix . $comment_id;
            if (!isset($_COOKIE[$cookie])) {
                continue;
            }

            $legacy = sanitize_key(wp_unslash($_COOKIE[$cookie]));
            if (in_array($legacy, array('like', 'dislike'), true)) {
                return $legacy;
            }
        }

        return '';
    }

    public function ajax_comment_vote()
    {
        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        $comment_id = isset($_POST['comment_id']) ? absint($_POST['comment_id']) : 0;
        $vote_type = isset($_POST['vote_type']) ? sanitize_key(wp_unslash($_POST['vote_type'])) : '';

        if (!in_array($vote_type, array('like', 'dislike'), true) || !$comment_id || !get_comment($comment_id)) {
            wp_send_json_error(__('Invalid vote or comment.', 'dadsoo-agax-comment'), 400);
        }

        $meta_key = $this->vote_meta_key();
        $current_vote = $this->current_vote($comment_id);

        if ($current_vote === $vote_type) {
            delete_comment_meta($comment_id, $meta_key);
            $this->update_vote_count($comment_id, $vote_type, -1);
            $status = 'removed';
        } elseif ($current_vote) {
            update_comment_meta($comment_id, $meta_key, $vote_type);
            $this->update_vote_count($comment_id, $current_vote, -1);
            $this->update_vote_count($comment_id, $vote_type, 1);
            $status = 'switched';
        } else {
            update_comment_meta($comment_id, $meta_key, $vote_type);
            $this->update_vote_count($comment_id, $vote_type, 1);
            $status = 'added';
        }

        $this->sync_vote_cookie($comment_id, 'removed' === $status ? '' : $vote_type);

        wp_send_json_success(array(
            'status' => $status,
            'vote_type' => $vote_type,
            'likes' => (int) get_comment_meta($comment_id, self::META_LIKES, true),
            'dislikes' => (int) get_comment_meta($comment_id, self::META_DISLIKES, true),
            'comment_id' => $comment_id,
        ));
    }

    /**
     * کوکی فقط نمای مرورگر را با سرور همگام نگه می‌دارد و در تصمیم‌گیری نقشی ندارد.
     */
    private function sync_vote_cookie($comment_id, $vote_type)
    {
        if (headers_sent()) {
            return;
        }

        $expiry = $vote_type ? time() + (30 * DAY_IN_SECONDS) : time() - YEAR_IN_SECONDS;
        setcookie('dadsoo_agax_vote_' . $comment_id, $vote_type, $expiry, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false);
        setcookie('elinweb_agax_vote_' . $comment_id, '', time() - YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false);
    }

    private function update_vote_count($comment_id, $vote_type, $change)
    {
        $meta_key = 'like' === $vote_type ? self::META_LIKES : self::META_DISLIKES;
        $current = (int) get_comment_meta($comment_id, $meta_key, true);

        update_comment_meta($comment_id, $meta_key, max(0, $current + $change));
    }

    // ----------------------------------------------------------------- ajax

    public function ajax_submit_comment()
    {
        // فیلد نانس این فرم به‌جای «_wpnonce» نام سفارشی دارد، پس نام آن هم به‌عنوان
        // آرگومان دوم داده می‌شود؛ $die=false تا پیام خطای خودمان به‌جای wp_die() برگردد.
        if (!check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD, false)) {
            wp_send_json_error(__('Security check failed. Please refresh the page and try again.', 'dadsoo-agax-comment'), 403);
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $name = isset($_POST['name']) ? trim(sanitize_text_field(wp_unslash($_POST['name']))) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $comment = isset($_POST['comment']) ? trim(wp_kses(wp_unslash($_POST['comment']), $this->allowed_tags())) : '';

        if ($this->honeypot_tripped()) {
            wp_send_json_error(__('Invalid request.', 'dadsoo-agax-comment'), 400);
        }

        if ($this->string_length($name) < 2) {
            wp_send_json_error(__('Name must be at least 2 characters.', 'dadsoo-agax-comment'));
        }

        if ($this->string_length($comment) < 5) {
            wp_send_json_error(__('Comment must be at least 5 characters.', 'dadsoo-agax-comment'));
        }

        if ($email && !is_email($email)) {
            wp_send_json_error(__('The email address is not valid.', 'dadsoo-agax-comment'));
        }

        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Invalid post.', 'dadsoo-agax-comment'), 400);
        }

        if (!comments_open($post_id)) {
            wp_send_json_error(__('Comments are closed for this post.', 'dadsoo-agax-comment'), 403);
        }

        $this->insert_and_respond(array(
            'comment_post_ID' => $post_id,
            'comment_author' => $name,
            'comment_author_email' => $email,
            'comment_content' => $comment,
            'comment_parent' => 0,
        ), 0, __('Your comment has been posted and published.', 'dadsoo-agax-comment'), __('Your comment has been submitted and will appear after approval.', 'dadsoo-agax-comment'));
    }

    public function ajax_reply_comment()
    {
        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $name = isset($_POST['name']) ? trim(sanitize_text_field(wp_unslash($_POST['name']))) : '';
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $comment = isset($_POST['comment']) ? trim(wp_kses(wp_unslash($_POST['comment']), $this->allowed_tags())) : '';

        if ($this->honeypot_tripped()) {
            wp_send_json_error(__('Invalid request.', 'dadsoo-agax-comment'), 400);
        }

        if ('' === $name || '' === $comment) {
            wp_send_json_error(__('Please enter your name and reply.', 'dadsoo-agax-comment'));
        }

        if ($email && !is_email($email)) {
            wp_send_json_error(__('The email address is not valid.', 'dadsoo-agax-comment'));
        }

        $parent = get_comment($parent_id);
        if (!$parent || !$post_id || (int) $parent->comment_post_ID !== $post_id || !comments_open($post_id)) {
            wp_send_json_error(__('Invalid parent comment or post.', 'dadsoo-agax-comment'), 400);
        }

        $depth = $this->comment_depth($parent) + 1;

        // پاسخ عمیق‌تر از حد مجاز پذیرفته نمی‌شود، چون در فهرست قابل نمایش نیست.
        if ($depth > self::MAX_DEPTH) {
            wp_send_json_error(__('Replies are not allowed at this depth.', 'dadsoo-agax-comment'), 400);
        }

        $this->insert_and_respond(array(
            'comment_post_ID' => $post_id,
            'comment_author' => $name,
            'comment_author_email' => $email,
            'comment_content' => $comment,
            'comment_parent' => $parent_id,
        ), $depth, __('Your reply has been posted and published.', 'dadsoo-agax-comment'), __('Your reply has been submitted and will appear after approval.', 'dadsoo-agax-comment'));
    }

    /**
     * ثبت دیدگاه از مسیر wp_new_comment انجام می‌شود تا آکیسمت، کنترل سیل درخواست،
     * فهرست کلمات ممنوع، قوانین بازبینی و ایمیل اطلاع‌رسانی هستهٔ وردپرس هم اجرا شوند.
     */
    private function insert_and_respond(array $data, $depth, $approved_message, $pending_message)
    {
        $data = array_merge(array(
            // هستهٔ وردپرس این کلید را بدون بررسی وجود می‌خواند، پس همیشه تعریف می‌شود.
            'comment_author_url' => '',
        ), $data, $this->request_meta(), array(
            'comment_type' => 'comment',
            'user_id' => get_current_user_id(),
        ));

        $comment_id = wp_new_comment(wp_slash($data), true);

        if (is_wp_error($comment_id)) {
            wp_send_json_error($comment_id->get_error_message(), 400);
        }

        if (!$comment_id) {
            wp_send_json_error(__('There was an error saving your comment. Please try again.', 'dadsoo-agax-comment'));
        }

        $comment = get_comment($comment_id);
        $is_approved = $comment && '1' === (string) $comment->comment_approved;

        // نانس در ابتدای هر دو تابع فراخوان‌کننده بررسی شده؛ sanitize_vote_icons() و
        // sanitize_avatar_size() هر مقدار را کامل اعتبارسنجی می‌کنند (کتابخانه با regex،
        // آدرس با sanitize_local_url، اندازه با absint)، phpcs فقط نام سفارشی را نمی‌شناسد.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $vote_icons = isset($_POST['vote_icons']) ? $this->sanitize_vote_icons(wp_unslash($_POST['vote_icons'])) : array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $avatar_size = isset($_POST['avatar_size']) ? $this->sanitize_avatar_size(wp_unslash($_POST['avatar_size'])) : self::DEFAULT_AVATAR_SIZE;

        wp_send_json_success(array(
            'approved' => $is_approved,
            'message' => $is_approved ? $approved_message : $pending_message,
            'html' => $is_approved ? $this->render_single_comment($comment, $depth, array(), $vote_icons, $avatar_size) : '',
        ));
    }

    public function ajax_load_comments()
    {
        check_ajax_referer(self::NONCE_ACTION, '_wpnonce');

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
        // sanitize_items() خودش absint() را اجرا می‌کند.
        $items = $this->sanitize_items(isset($_POST['items']) ? wp_unslash($_POST['items']) : 5); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if (!$post_id || !get_post($post_id)) {
            wp_send_json_error(__('Invalid post.', 'dadsoo-agax-comment'), 400);
        }

        // نانس در ابتدای هر دو تابع فراخوان‌کننده بررسی شده؛ sanitize_vote_icons() و
        // sanitize_avatar_size() هر مقدار را کامل اعتبارسنجی می‌کنند (کتابخانه با regex،
        // آدرس با sanitize_local_url، اندازه با absint)، phpcs فقط نام سفارشی را نمی‌شناسد.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $vote_icons = isset($_POST['vote_icons']) ? $this->sanitize_vote_icons(wp_unslash($_POST['vote_icons'])) : array();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $avatar_size = isset($_POST['avatar_size']) ? $this->sanitize_avatar_size(wp_unslash($_POST['avatar_size'])) : self::DEFAULT_AVATAR_SIZE;

        list($roots, $children, $has_more) = $this->fetch_comment_tree($post_id, $offset, $items);

        wp_send_json_success(array(
            'html' => $this->render_comment_tree($roots, $children, 0, $vote_icons, $avatar_size),
            'count' => count($roots),
            'has_more' => $has_more,
        ));
    }

    // -------------------------------------------------------------- helpers

    /**
     * هر دو فراخوان‌کننده (ajax_submit_comment و ajax_reply_comment) پیش از این
     * تابع نانس را بررسی کرده‌اند؛ خودِ این متد جدا تست می‌شود و به همین دلیل نانس
     * را دوباره بررسی نمی‌کند.
     */
    private function honeypot_tripped()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified by the caller before this runs.
        return isset($_POST[self::HONEYPOT_FIELD]) && '' !== trim(sanitize_text_field(wp_unslash($_POST[self::HONEYPOT_FIELD])));
    }

    private function current_post_id()
    {
        $post_id = (int) get_the_ID();

        return $post_id ? $post_id : (int) get_queried_object_id();
    }

    private function sanitize_items($items)
    {
        return min(100, max(1, absint($items)));
    }

    private function sanitize_avatar_size($size)
    {
        $size = absint($size);

        return ($size >= 20 && $size <= 320) ? $size : self::DEFAULT_AVATAR_SIZE;
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
            'comment_agent' => substr($agent, 0, 254),
        );
    }

    private function string_length($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    // ------------------------------------------------------------ integrations

    public function register_elementor_widgets($widgets_manager)
    {
        if (!class_exists('\\Elementor\\Widget_Base')) {
            return;
        }

        require_once plugin_dir_path(DADSOO_AGAX_COMMENT_FILE) . 'includes/class-dadsoo-agax-elementor-form-widget.php';
        require_once plugin_dir_path(DADSOO_AGAX_COMMENT_FILE) . 'includes/class-dadsoo-agax-elementor-comments-widget.php';
        require_once plugin_dir_path(DADSOO_AGAX_COMMENT_FILE) . 'includes/class-dadsoo-agax-legacy-widgets.php';

        $widgets_manager->register(new Dadsoo_Agax_Elementor_Form_Widget());
        $widgets_manager->register(new Dadsoo_Agax_Elementor_Comments_Widget());

        // صفحه‌هایی که با نسخه‌های elinweb ساخته شده‌اند هنوز نام قدیمی ویجت را ذخیره دارند.
        $widgets_manager->register(new Dadsoo_Agax_Legacy_Form_Widget());
        $widgets_manager->register(new Dadsoo_Agax_Legacy_Comments_Widget());
    }

    public function add_admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            __('Dadsoo Agax Comment Settings', 'dadsoo-agax-comment'),
            __('Dadsoo Agax Comment', 'dadsoo-agax-comment'),
            'manage_options',
            'dadsoo-agax-comment-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page()
    {
    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Dadsoo Agax Comment Settings', 'dadsoo-agax-comment'); ?></h1>
            <div class="dadsoo-settings-content">
                <h2><?php esc_html_e('How to use', 'dadsoo-agax-comment'); ?></h2>
                <p><?php esc_html_e('Use the following shortcode to display the comment form:', 'dadsoo-agax-comment'); ?></p>
                <code>[dadsoo-agax-comment-form]</code>

                <p><?php esc_html_e('Use the following shortcode to display the comments list:', 'dadsoo-agax-comment'); ?></p>
                <code>[dadsoo-agax-comments]</code>
                <p><?php esc_html_e('Or set the number of comments shown:', 'dadsoo-agax-comment'); ?></p>
                <code>[dadsoo-agax-comments items="10" avatar_size="72"]</code>

                <p><?php esc_html_e('The elin-agax-comment-form and elin-agax-comments shortcodes still work, but are deprecated.', 'dadsoo-agax-comment'); ?></p>
            </div>
        </div>
<?php
    }
}

function dadsoo_agax_comment()
{
    static $plugin = null;
    if (null === $plugin) {
        $plugin = new Dadsoo_Agax_Comment();
    }

    return $plugin;
}

dadsoo_agax_comment();

if (!function_exists('elinweb_agax_comment')) {
    /**
     * نام قدیمی تابع، برای کدهای بیرونی که هنوز آن را صدا می‌زنند.
     *
     * @deprecated 3.0.0 از dadsoo_agax_comment() استفاده کنید.
     */
    function elinweb_agax_comment()
    {
        return dadsoo_agax_comment();
    }
}
