=== Dadsoo Ajax Comment ===
Contributors: imansh
Tags: comments, ajax, threaded comments, elementor, voting
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 4.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AJAX-powered comments with threaded replies, like/dislike voting, and full Elementor widget support.

== Description ==

Dadsoo Ajax Comment replaces the default comment form and list with an AJAX-driven version: comments post without a page reload, replies nest up to three levels deep, and visitors can like or dislike any comment.

= Features =

* ✅ Post comments without reloading the page
* ✅ Progressive loading of approved comments, with a loading spinner and an empty state
* ✅ Threaded replies up to three levels deep
* ✅ Instant publishing for logged-in users with the `moderate_comments` capability
* ✅ Like/dislike voting that can be changed or undone, recorded server-side so clearing cookies can't be used to vote again
* ✅ After each "load more", the page scrolls to and highlights the first newly loaded comment
* ✅ Configurable button and status text (load more, loading, empty)
* ✅ Uses the WordPress avatar and displays the comment date
* ✅ Records IP address and user agent for moderation and spam review
* ✅ Nonce-verified AJAX requests, a honeypot field on both forms, and comments routed through WordPress's own `wp_new_comment()` so Akismet, comment flood control, and the disallowed-words list all still run
* ✅ Two independent Elementor widgets: a comment form and a comments list
* ✅ Elementor style controls for the avatar, author name, comment text, vote icons, and the load-more button

= Shortcodes =

`[dadsoo-ajax-comment-form]`
`[dadsoo-ajax-comments]`
`[dadsoo-ajax-comments items="10" avatar_size="72"]`
`[dadsoo-ajax-comments load_more_text="More comments" loading_text="One moment…" empty_text="Be the first to comment"]`

Guest comments and replies are saved as pending and can be moderated from the regular Comments screen. Users with the `moderate_comments` capability (Administrators and Editors) have their comments published immediately and shown in the list without a page reload.

= Requirements =

* WordPress 5.8 or newer
* PHP 7.4 or newer
* Elementor (only if you use the widgets — the shortcodes don't need it)

Author: Iman Shadmehri. Website: https://elinweb.ir

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/dadsoo-ajax-comment`, or install it through Plugins → Add New.
2. Activate the plugin through the Plugins screen.
3. Add the shortcodes above to any page or template, or drag the two Elementor widgets ("Dadsoo Ajax Comment Form" and "Dadsoo Ajax Comments") onto a page.

== Frequently Asked Questions ==

= Does this replace the built-in WordPress comment form? =

It's a separate form and list rendered by shortcode or widget, but it stores comments in the standard `wp_comments` table, so existing moderation tools keep working.

= How deep can replies go? =

Three levels. At the deepest level the reply button is hidden, and the server also rejects a deeper reply so no approved comment is ever created that the list couldn't display.

= Can people vote more than once by clearing their cookies? =

No. Votes are recorded against a stable per-visitor key — the user ID when logged in, or a salted hash of IP address and user agent for guests — not just a cookie. The cookie only mirrors the vote back to the browser.

= Does it work without Elementor? =

Yes. The shortcodes work with any theme or page builder. Elementor is only required for the two widgets.

= What language is the plugin in? =

Source strings are in English. Translations, including Persian (fa_IR), are managed through [translate.wordpress.org](https://translate.wordpress.org/) and delivered automatically by WordPress — no files to install.

= I have an older "Dadsoo Agax Comment" install. Is this the same plugin? =

Yes — versions before 4.0 shipped with "agax" instead of "ajax" in every identifier (a leftover typo from an earlier name). 4.0 corrects it. See the Upgrade Notice below; existing votes, comments, and content using the old shortcodes are carried over automatically.

== Changelog ==

= 4.0.1 =
* Removed the bundled `.po`/`.mo` translation files and the manual `load_plugin_textdomain()` call, per WordPress.org plugin review — WordPress.org-hosted plugins get this handled automatically via translate.wordpress.org.

= 4.0.0 =
* Corrected a long-standing "agax" typo (should have read "ajax") throughout every identifier: plugin slug and main file, class name, AJAX actions, nonce, meta keys, cookies, shortcodes, Elementor widget names, text domain, and the JS global. Existing data (vote records, like/dislike counts) is migrated automatically, and the old `dadsoo-agax-*` shortcodes/widgets keep working.

= 3.2.0 =
* All strings now ship in English by default; a Persian (fa_IR) translation is bundled in `languages/`.
* Added the GPL license header required for the WordPress.org plugin directory, and a `readme.txt`.

= 3.1.0 =
* Added a loading spinner, an empty state, and scroll-to-and-highlight on newly loaded comments.
* Added configurable load-more/loading/empty text, plus matching Elementor style controls.
* Fixed silently-ignored smooth scrolling in some browsers by falling back to an instant scroll.

= 3.0.0 =
* Renamed every `elinweb`/`elin` identifier to `dadsoo` (file, classes, actions, meta keys, cookies, shortcodes, widget names). Old shortcodes and widget names still work.
* Votes were previously controlled by a cookie alone, so clearing it allowed unlimited voting. Votes are now recorded against a stable per-visitor key.
* Comments are now submitted through `wp_new_comment()` so Akismet, flood control, and moderation rules run.
* Fixed an N+1 query pattern when rendering nested replies (one query per depth level instead of one per comment).
* CSS/JS now load only on pages that actually use the shortcode or widget.
* Fixed the "load more" button never hiding when the comment count was an exact multiple of the page size.

= 2.3.1 =
* Fixed uploaded SVG vote icons ignoring the icon color control.

= 2.3.0 =
* Added padding and border-radius controls for the like/dislike buttons.
* Added the `avatar_size` shortcode attribute.
* Added IP and user-agent logging for spam review.
* Fixed the reply form never opening, replies below the first level never rendering, and reply indentation on RTL sites.

= 2.2 =
* Initial public documentation and Elementor widgets.

== Upgrade Notice ==

= 4.0.0 =
The plugin folder and main file are renamed (agax → ajax typo fix). Deactivate the old "Dadsoo Agax Comment" install, install this one, and activate it — votes, comment data, and old shortcodes/widgets carry over automatically.

= 3.2.0 =
Default plugin strings are now in English; a Persian translation ships in languages/. No action needed if you don't customize the plugin's text.

= 3.0.0 =
The main plugin file was renamed, so WordPress deactivates the plugin on update — reactivate it once from the Plugins screen. Old shortcodes, widgets, and votes keep working automatically.
