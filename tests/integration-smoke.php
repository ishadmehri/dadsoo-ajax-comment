<?php
/**
 * Integration smoke test for Elin Agax Comment.
 *
 * Run with:
 * php wp-cli.phar eval-file tests/integration-smoke.php --path=C:\laragon\www\chatgpt
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
function elinweb_agax_run_handler($method, array $post)
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
        elinweb_agax_comment()->$method();
    } catch (Throwable $e) {
        // wp_send_json_* always ends in wp_die().
    }

    return json_decode(trim(ob_get_clean()), true);
}

// --- Elementor widgets -------------------------------------------------------

$widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
$results['form_widget_registered'] = isset($widgets['elinweb-agax-comment-form']);
$results['comments_widget_registered'] = isset($widgets['elinweb-agax-comments']);

// --- Fixture -----------------------------------------------------------------

$post_id = wp_insert_post(array(
    'post_title' => 'Elin Agax AJAX Test',
    'post_content' => '[elin-agax-comment-form][elin-agax-comments items="2"]',
    'post_status' => 'publish',
    'comment_status' => 'open',
));

if (is_wp_error($post_id) || !$post_id) {
    fwrite(STDERR, "Could not create the test post.\n");
    exit(1);
}

$first_comment = wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'First Test',
    'comment_author_email' => 'first@example.test',
    'comment_content' => 'First test comment',
    'comment_approved' => 1,
));
wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'Second Test',
    'comment_author_email' => 'second@example.test',
    'comment_content' => 'Second test comment',
    'comment_approved' => 1,
));

// --- Shortcodes --------------------------------------------------------------

$form_html = do_shortcode('[elin-agax-comment-form]');
$GLOBALS['post'] = get_post($post_id);
setup_postdata($GLOBALS['post']);
$list_html = do_shortcode('[elin-agax-comments items="2"]');

$results['custom_icon_renders'] = false !== strpos(
    elinweb_agax_comment()->render_comments_list(array(
        'items' => 2,
        'vote_icons' => array('like_outline' => array('value' => 'far fa-thumbs-up')),
    )),
    'far fa-thumbs-up'
);
$results['form_shortcode_renders'] = false !== strpos($form_html, 'elinweb_agax_submit_comment');
$results['list_shortcode_renders'] = false !== strpos($list_html, 'data-items="2"');
$results['avatar_size_attribute'] = false !== strpos(
    elinweb_agax_comment()->render_comments_list(array('items' => 2, 'avatar_size' => 96)),
    'data-avatar-size="96"'
);

// --- Nested replies render at every supported depth ---------------------------

$level_1 = wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'Depth One',
    'comment_content' => 'Depth one reply',
    'comment_parent' => $first_comment,
    'comment_approved' => 1,
));
$level_2 = wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'Depth Two',
    'comment_content' => 'Depth two reply',
    'comment_parent' => $level_1,
    'comment_approved' => 1,
));
$level_3 = wp_insert_comment(array(
    'comment_post_ID' => $post_id,
    'comment_author' => 'Depth Three',
    'comment_content' => 'Depth three reply',
    'comment_parent' => $level_2,
    'comment_approved' => 1,
));

$nonce = wp_create_nonce('elinweb-agax-comment-nonce');
$loaded = elinweb_agax_run_handler('load_comments', array(
    'post_id' => $post_id,
    'offset' => 0,
    'items' => 10,
    '_wpnonce' => $nonce,
));
$loaded_html = isset($loaded['data']['html']) ? $loaded['data']['html'] : '';

$results['load_comments_succeeds'] = !empty($loaded['success']);
$results['depth_1_rendered'] = false !== strpos($loaded_html, 'data-comment-id="' . $level_1 . '"');
$results['depth_2_rendered'] = false !== strpos($loaded_html, 'data-comment-id="' . $level_2 . '"');
$results['depth_3_rendered'] = false !== strpos($loaded_html, 'data-comment-id="' . $level_3 . '"');
// Every rendered comment carries a reply button except those at the deepest level,
// which cannot be replied to at all.
$rendered_comments = substr_count($loaded_html, 'dadsoo-comment depth-');
$deepest_comments = substr_count($loaded_html, 'dadsoo-comment depth-3');
$results['depth_3_has_no_reply_button'] = $rendered_comments > 0
    && $deepest_comments > 0
    && substr_count($loaded_html, 'dadsoo-reply-btn') === $rendered_comments - $deepest_comments;

// --- Guest comments stay unapproved and are not echoed back -------------------

wp_set_current_user(0);
$guest = elinweb_agax_run_handler('submit_comment', array(
    'name' => 'Guest Tester',
    'email' => 'guest@example.test',
    'comment' => 'Guest comment awaiting moderation.',
    'post_id' => $post_id,
    'action' => 'elinweb_agax_submit_comment',
    'elinweb_agax_nonce' => $nonce,
    '_wp_http_referer' => '/',
));
$results['guest_comment_accepted'] = !empty($guest['success']);
$results['guest_comment_not_approved'] = isset($guest['data']['approved']) && false === $guest['data']['approved'];
$results['guest_comment_html_withheld'] = isset($guest['data']['html']) && '' === $guest['data']['html'];

// --- Moderators publish immediately ------------------------------------------

$admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ID'));
$results['administrator_found'] = !empty($admins);

if (!empty($admins)) {
    wp_set_current_user((int) $admins[0]);
    $mod_nonce = wp_create_nonce('elinweb-agax-comment-nonce');

    $mod = elinweb_agax_run_handler('submit_comment', array(
        'name' => 'Moderator Tester',
        'email' => 'moderator@example.test',
        'comment' => 'Moderator comment published immediately.',
        'post_id' => $post_id,
        'action' => 'elinweb_agax_submit_comment',
        'elinweb_agax_nonce' => $mod_nonce,
    ));
    $results['moderator_comment_approved'] = !empty($mod['data']['approved']);
    $results['moderator_comment_html_returned'] = !empty($mod['data']['html']);

    $mod_reply = elinweb_agax_run_handler('handle_reply', array(
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
    $too_deep = elinweb_agax_run_handler('handle_reply', array(
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
$results['nonce'] = $nonce;

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
