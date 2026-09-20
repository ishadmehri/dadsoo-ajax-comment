<?php
/**
 * Integration smoke test for Dadsoo Agax Comment.
 *
 * Run with:
 * wp eval-file tests/integration-smoke.php --path=<wordpress-path>
 *
 * The test creates its own post and removes it again, so it can be run repeatedly.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this file through WP-CLI eval-file.\n");
    exit(1);
}

$results = array();

/**
 * Calls an AJAX handler in-process and returns whatever it echoed.
 */
function dadsoo_agax_run_handler($method, array $post)
{
    static $patched = false;

    if (!$patched) {
        $die = function () {
            return function () {
                throw new RuntimeException('wp_die');
            };
        };
        add_filter('wp_die_handler', $die);
        add_filter('wp_die_ajax_handler', $die);
        add_filter('wp_doing_ajax', '__return_true');
        $patched = true;
    }

    $_POST = $post;
    $_REQUEST = $post;

    ob_start();
    try {
        dadsoo_agax_comment()->$method();
    } catch (Throwable $e) {
        // wp_send_json_* always ends in wp_die().
    }

    return json_decode(trim(ob_get_clean()), true);
}

/**
 * Counts the database queries a callback triggers.
 */
function dadsoo_agax_count_queries(callable $callback)
{
    global $wpdb;

    $before = $wpdb->num_queries;
    $callback();

    return $wpdb->num_queries - $before;
}

// --- Backwards compatibility -------------------------------------------------

$results['legacy_function_alias'] = function_exists('elinweb_agax_comment')
    && elinweb_agax_comment() === dadsoo_agax_comment();
$results['legacy_shortcodes_registered'] = shortcode_exists('elin-agax-comments')
    && shortcode_exists('elin-agax-comment-form');
$results['new_shortcodes_registered'] = shortcode_exists('dadsoo-agax-comments')
    && shortcode_exists('dadsoo-agax-comment-form');

// --- Elementor widgets -------------------------------------------------------

$widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
$results['form_widget_registered'] = isset($widgets['dadsoo-agax-comment-form']);
$results['comments_widget_registered'] = isset($widgets['dadsoo-agax-comments']);
// Pages built with the old plugin still carry the old widgetType.
$results['legacy_form_widget_registered'] = isset($widgets['elinweb-agax-comment-form']);
$results['legacy_comments_widget_registered'] = isset($widgets['elinweb-agax-comments']);
$results['legacy_widgets_hidden_from_panel'] = isset($widgets['elinweb-agax-comments'])
    && false === $widgets['elinweb-agax-comments']->show_in_panel();

// --- Fixture -----------------------------------------------------------------

$post_id = wp_insert_post(array(
    'post_title' => 'Dadsoo Agax AJAX Test',
    'post_content' => '[dadsoo-agax-comment-form][dadsoo-agax-comments items="2"]',
    'post_status' => 'publish',
    'comment_status' => 'open',
));

if (is_wp_error($post_id) || !$post_id) {
    fwrite(STDERR, "Could not create the test post.\n");
    exit(1);
}

$root_ids = array();
foreach (range(1, 6) as $index) {
    $stamp = gmdate('Y-m-d H:i:s', time() - (10 - $index) * 60);
    $root_ids[] = wp_insert_comment(array(
        'comment_post_ID' => $post_id,
        'comment_author' => 'Root ' . $index,
        'comment_author_email' => 'root' . $index . '@example.test',
        'comment_content' => 'Root comment number ' . $index,
        'comment_approved' => 1,
        'comment_type' => 'comment',
        'comment_date' => $stamp,
        'comment_date_gmt' => $stamp,
    ));
}
$first_comment = $root_ids[0];

// --- Shortcodes --------------------------------------------------------------

$GLOBALS['post'] = get_post($post_id);
setup_postdata($GLOBALS['post']);

$form_html = do_shortcode('[dadsoo-agax-comment-form]');
$list_html = do_shortcode('[dadsoo-agax-comments items="2"]');

$results['form_shortcode_renders'] = false !== strpos($form_html, 'dadsoo_agax_submit_comment');
$results['form_shortcode_has_honeypot'] = false !== strpos($form_html, 'dadsoo_agax_confirm');
$results['list_shortcode_renders'] = false !== strpos($list_html, 'data-items="2"');
$results['legacy_shortcode_renders'] = false !== strpos(do_shortcode('[elin-agax-comments items="2"]'), 'data-items="2"');

// Two form widgets on one page must not produce duplicate element ids.
$two_forms = do_shortcode('[dadsoo-agax-comment-form]') . do_shortcode('[dadsoo-agax-comment-form]');
preg_match_all('/id="(dadsoo-form-\d+-comment)"/', $two_forms, $form_ids);
$results['form_ids_unique_per_instance'] = 2 === count($form_ids[1])
    && 2 === count(array_unique($form_ids[1]));

// Custom labels reach the markup, and a blank control falls back to the default.
$custom = dadsoo_agax_comment()->render_comments_list(array(
    'items' => 2,
    'load_more_text' => 'نظرات بیشتر',
    'loading_text' => 'کمی صبر کنید',
    'empty_text' => 'هیچ نظری نیست',
));
$results['custom_load_more_text'] = false !== strpos($custom, 'نظرات بیشتر');
$results['custom_loading_text'] = false !== strpos($custom, 'data-loading-text="کمی صبر کنید"');
$results['custom_empty_text'] = false !== strpos($custom, 'data-empty-text="هیچ نظری نیست"');

$blank = dadsoo_agax_comment()->render_comments_list(array(
    'items' => 2,
    'load_more_text' => '   ',
    'empty_text' => '',
));
// از خودِ __() برای مقایسه استفاده می‌شود تا تست مستقل از locale سایت باشد؛
// این سایت آزمایشی fa_IR است و ترجمهٔ بسته‌شده با افزونه همین‌جا هم اعمال می‌شود.
$results['blank_label_falls_back'] = false !== strpos($blank, __('Load more comments', 'dadsoo-agax-comment'))
    && false !== strpos($blank, __('No comments yet.', 'dadsoo-agax-comment'));

// The loading indicator and its spinner must be in the initial markup, because the
// list itself only arrives over AJAX.
$results['loading_status_rendered'] = false !== strpos($list_html, 'dadsoo-comments-status is-loading')
    && false !== strpos($list_html, 'dadsoo-spinner');
$results['submit_button_has_spinner'] = false !== strpos($form_html, 'dadsoo-spinner');

$results['custom_icon_renders'] = false !== strpos(
    dadsoo_agax_comment()->render_comments_list(array(
        'items' => 2,
        'vote_icons' => array('like_outline' => array('value' => 'far fa-thumbs-up')),
    )),
    'far fa-thumbs-up'
);
$results['avatar_size_attribute'] = false !== strpos(
    dadsoo_agax_comment()->render_comments_list(array('items' => 2, 'avatar_size' => 96)),
    'data-avatar-size="96"'
);

// --- Nested replies render at every supported depth ---------------------------

$level_1 = wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'Depth One',
    'comment_content' => 'Depth one reply',
    'comment_parent' => $first_comment,
    'comment_approved' => 1,
    'comment_type' => 'comment',
));
$level_2 = wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'Depth Two',
    'comment_content' => 'Depth two reply',
    'comment_parent' => $level_1,
    'comment_approved' => 1,
    'comment_type' => 'comment',
));
$level_3 = wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'Depth Three',
    'comment_content' => 'Depth three reply',
    'comment_parent' => $level_2,
    'comment_approved' => 1,
    'comment_type' => 'comment',
));

$nonce = wp_create_nonce('dadsoo-agax-comment-nonce');

$loaded = null;
$queries = dadsoo_agax_count_queries(function () use (&$loaded, $post_id, $nonce) {
    $loaded = dadsoo_agax_run_handler('ajax_load_comments', array(
        'post_id' => $post_id,
        'offset' => 0,
        'items' => 10,
        '_wpnonce' => $nonce,
    ));
});
$loaded_html = isset($loaded['data']['html']) ? $loaded['data']['html'] : '';

$results['load_comments_succeeds'] = !empty($loaded['success']);
$results['depth_1_rendered'] = false !== strpos($loaded_html, 'data-comment-id="' . $level_1 . '"');
$results['depth_2_rendered'] = false !== strpos($loaded_html, 'data-comment-id="' . $level_2 . '"');
$results['depth_3_rendered'] = false !== strpos($loaded_html, 'data-comment-id="' . $level_3 . '"');

// Nine comments used to mean one query per comment. Now it is one query per depth
// level plus one for the meta cache, so the count must stay well below that.
$results['load_comments_query_count'] = $queries;
$results['load_comments_avoids_n_plus_one'] = $queries <= 12;

// Every rendered comment carries a reply button except those at the deepest level,
// which cannot be replied to at all.
$rendered_comments = substr_count($loaded_html, 'dadsoo-comment depth-');
$deepest_comments = substr_count($loaded_html, 'dadsoo-comment depth-3');
$results['depth_3_has_no_reply_button'] = $rendered_comments > 0
    && $deepest_comments > 0
    && substr_count($loaded_html, 'dadsoo-reply-btn') === $rendered_comments - $deepest_comments;

// --- Pagination ---------------------------------------------------------------

// Six root comments, three per page: the first page has more behind it, and the
// second is full but final, so it must report that nothing follows.
$page_1 = dadsoo_agax_run_handler('ajax_load_comments', array('post_id' => $post_id, 'offset' => 0, 'items' => 3, '_wpnonce' => $nonce));
$page_2 = dadsoo_agax_run_handler('ajax_load_comments', array('post_id' => $post_id, 'offset' => 3, 'items' => 3, '_wpnonce' => $nonce));

$results['page_1_reports_more'] = isset($page_1['data']['has_more']) && true === $page_1['data']['has_more'];
$results['page_1_count'] = isset($page_1['data']['count']) && 3 === $page_1['data']['count'];
$results['last_full_page_reports_no_more'] = isset($page_2['data']['has_more']) && false === $page_2['data']['has_more'];

// --- Voting is not spoofable by clearing a cookie -----------------------------

wp_set_current_user(0);
$_COOKIE = array();

$vote_1 = dadsoo_agax_run_handler('ajax_comment_vote', array(
    'comment_id' => $first_comment,
    'vote_type' => 'like',
    '_wpnonce' => $nonce,
));
$results['vote_added'] = isset($vote_1['data']['status']) && 'added' === $vote_1['data']['status'];
$results['vote_count_incremented'] = isset($vote_1['data']['likes']) && 1 === $vote_1['data']['likes'];

// The browser "forgets" the vote. The server must not.
$_COOKIE = array();
$vote_2 = dadsoo_agax_run_handler('ajax_comment_vote', array(
    'comment_id' => $first_comment,
    'vote_type' => 'like',
    '_wpnonce' => $nonce,
));
$results['repeat_vote_toggles_instead_of_stacking'] = isset($vote_2['data']['status'])
    && 'removed' === $vote_2['data']['status']
    && 0 === $vote_2['data']['likes'];

// Switching sides must move the count, not add to both.
dadsoo_agax_run_handler('ajax_comment_vote', array('comment_id' => $first_comment, 'vote_type' => 'like', '_wpnonce' => $nonce));
$switched = dadsoo_agax_run_handler('ajax_comment_vote', array('comment_id' => $first_comment, 'vote_type' => 'dislike', '_wpnonce' => $nonce));
$results['vote_switch_moves_count'] = isset($switched['data']['status'])
    && 'switched' === $switched['data']['status']
    && 0 === $switched['data']['likes']
    && 1 === $switched['data']['dislikes'];

$invalid_vote = dadsoo_agax_run_handler('ajax_comment_vote', array(
    'comment_id' => $first_comment,
    'vote_type' => 'shrug',
    '_wpnonce' => $nonce,
));
$results['invalid_vote_rejected'] = isset($invalid_vote['success']) && false === $invalid_vote['success'];

// A vote recorded only in a pre-3.0 cookie is still honoured once.
$legacy_comment = $root_ids[1];
$_COOKIE = array('elinweb_agax_vote_' . $legacy_comment => 'like');
update_comment_meta($legacy_comment, 'dadsoo_agax_likes', 1);
$legacy_vote = dadsoo_agax_run_handler('ajax_comment_vote', array(
    'comment_id' => $legacy_comment,
    'vote_type' => 'like',
    '_wpnonce' => $nonce,
));
$results['legacy_cookie_vote_honoured'] = isset($legacy_vote['data']['status'])
    && 'removed' === $legacy_vote['data']['status'];
$_COOKIE = array();

// --- Guest comments stay unapproved and are not echoed back -------------------

$guest = dadsoo_agax_run_handler('ajax_submit_comment', array(
    'name' => 'Guest Tester',
    'email' => 'guest@example.test',
    'comment' => 'Guest comment awaiting moderation.',
    'post_id' => $post_id,
    'action' => 'dadsoo_agax_submit_comment',
    'dadsoo_agax_nonce' => $nonce,
    '_wp_http_referer' => '/',
));
$results['guest_comment_accepted'] = !empty($guest['success']);
$results['guest_comment_not_approved'] = isset($guest['data']['approved']) && false === $guest['data']['approved'];
$results['guest_comment_html_withheld'] = isset($guest['data']['html']) && '' === $guest['data']['html'];

// A filled honeypot is a bot, and must never reach the comments table.
$trapped = dadsoo_agax_run_handler('ajax_submit_comment', array(
    'name' => 'Spam Bot',
    'email' => 'bot@example.test',
    'comment' => 'Buy cheap things right now.',
    'post_id' => $post_id,
    'dadsoo_agax_confirm' => 'http://spam.example',
    'dadsoo_agax_nonce' => $nonce,
));
$results['honeypot_rejects_submission'] = isset($trapped['success']) && false === $trapped['success'];

// A bad nonce must be refused.
$bad_nonce = dadsoo_agax_run_handler('ajax_submit_comment', array(
    'name' => 'Nonce Tester',
    'comment' => 'This should never be stored.',
    'post_id' => $post_id,
    'dadsoo_agax_nonce' => 'not-a-real-nonce',
));
$results['bad_nonce_rejected'] = isset($bad_nonce['success']) && false === $bad_nonce['success'];

// --- Moderators publish immediately ------------------------------------------

$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
$results['administrator_found'] = !empty($admins);

if (!empty($admins)) {
    wp_set_current_user((int) $admins[0]);
    $mod_nonce = wp_create_nonce('dadsoo-agax-comment-nonce');

    $mod = dadsoo_agax_run_handler('ajax_submit_comment', array(
        'name' => 'Moderator Tester',
        'email' => 'moderator@example.test',
        'comment' => 'Moderator comment published immediately.',
        'post_id' => $post_id,
        'action' => 'dadsoo_agax_submit_comment',
        'dadsoo_agax_nonce' => $mod_nonce,
    ));
    $results['moderator_comment_approved'] = !empty($mod['data']['approved']);
    $results['moderator_comment_html_returned'] = !empty($mod['data']['html']);

    $mod_reply = dadsoo_agax_run_handler('ajax_reply_comment', array(
        'parent_id' => $first_comment,
        'post_id' => $post_id,
        'name' => 'Moderator Tester',
        'email' => 'moderator@example.test',
        'comment' => 'Moderator reply published immediately.',
        '_wpnonce' => $mod_nonce,
    ));
    $reply_html = isset($mod_reply['data']['html']) ? $mod_reply['data']['html'] : '';
    $results['moderator_reply_approved'] = !empty($mod_reply['data']['approved']);
    $results['moderator_reply_rendered_at_depth_1'] = false !== strpos($reply_html, 'dadsoo-comment depth-1');

    // A reply below the deepest supported level must be refused, not silently hidden.
    $too_deep = dadsoo_agax_run_handler('ajax_reply_comment', array(
        'parent_id' => $level_3,
        'post_id' => $post_id,
        'name' => 'Moderator Tester',
        'comment' => 'This reply is one level too deep.',
        '_wpnonce' => $mod_nonce,
    ));
    $results['reply_below_max_depth_rejected'] = isset($too_deep['success']) && false === $too_deep['success'];

    wp_set_current_user(0);
}

wp_reset_postdata();

$results['post_id'] = (int) $post_id;
$results['comment_id'] = (int) $first_comment;

// --- Cleanup -----------------------------------------------------------------

foreach (get_comments(array('post_id' => $post_id, 'status' => 'all', 'fields' => 'ids')) as $cid) {
    wp_delete_comment($cid, true);
}
wp_delete_post($post_id, true);
$results['fixture_cleaned_up'] = null === get_post($post_id);

$failed = array();
foreach ($results as $key => $value) {
    if (is_bool($value) && !$value) {
        $failed[] = $key;
    }
}
$results['failed_checks'] = $failed;

echo wp_json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
