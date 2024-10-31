=== Published By ===
Contributors: coffee2code
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=6ARCFJ9TX3522
Tags: post, publish, publisher, editor, author, audit, auditing, tracking, users, coffee2code
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 4.6
Tested up to: 4.9
Stable tag: 1.3

Track which user actually published a post, separate from who created the post. Display that info as a column in admin post listings.

== Description ==

This plugin records which user actually published a post, which in a multi-author environment may not always be the original post author. This helps to maintain accountability for who was ultimately responsible for a post appearing live on a site.

The admin listing of posts is amended with a new "Published By" column that shows the name of the person who published the post (for those posts that have actually been published). A dropdown above admin post listings allows for the listing to be filtered by a particular publishing user (but only includes posts with a known publishing user).

For posts that were published prior to the use of this plugin (and thus the plugin could not directly record who published those posts), the plugin makes a best guess attempt to ascertain who published the post. After failing to find the publisher of the post as recorded by the plugin, it checks for who last edited the post, then who is responsible for the latest revision of the post, and finally failing those, it assumes it was the post author. In cases where it had to go through this process, the name of the person it deduced as the likely publisher appears italicized and with a question mark at the end. If you'd rather the plugin not make an attempt to guess the publisher, you can disable the checks by including this snippet in your theme's functions.php (or, ideally, a site-specific mu-plugin):

`<?php add_filter( 'c2c_published_by_skip_guessing', '__return_true' ); ?>`


Links: [Plugin Homepage](http://coffee2code.com/wp-plugins/published-by/) | [Plugin Directory Page](https://wordpress.org/plugins/published-by/) | [GitHub](https://github.com/coffee2code/published-by/) | [Author Homepage](http://coffee2code.com)


== Installation ==

1. Install via the built-in WordPress plugin installer. Or download and unzip `published-by.zip` inside the plugins directory for your site (typically `wp-content/plugins/`)
2. Activate the plugin through the 'Plugins' admin menu in WordPress


== Screenshots ==

1. A screenshot of the admin post listing showing the added "Published By" column. It demonstrates the mix of a post puublished by the current user, a post poblished by another user and two posts that existed before the plugin was activated (one guessed to be published by the current user and one guessed to be published by yet another user).
2. A screenshot of the Publish metabox for a published post showing the current user who published the post.
3. A screenshot of the Publish metabox for a published post showing another user who published the post.
4. A screenshot of the post listing dropdown for filtering posts by publishing user.


== Frequently Asked Questions ==

= If a post is published, then changed back to a draft, and then published a second time by a different user, who is noted as the publishing user? =

The user most recently responsible for a post getting published will be recorded as the publishing user. Editing a published post does not change the publishing user.

= How do I see (or hide) the "Published By" column in an admin listing of posts? =

In the upper-right of the page is a "Screen Options" link that reveals a panel of options. In the "Columns" section, check (to show) or uncheck (to hide) the "Published By" option.

= Why does the person's name listed as the "Published By" user appear in italics with a question mark at the end? =

It's an indication that for the given post the name shown is a guess by the plugin based on existing post data. For posts that were published prior to the use of this plugin (and thus the plugin could not directly record who published those posts), the plugin makes a best guess attempt to ascertain who published the post. After failing to find the publisher of the post as recorded by the plugin, it checks for who last edited the post, then who is responsible for the latest revision of the post, and finally failing those, it assumes it was the post author. It's likely that the guess is correct, but it's impossible to say for certain when the plugin isn't activated when posts were published.

= Does this plugin include unit tests? =

Yes.


== Changelog ==

= 1.3 (2018-04-24) =
* New: Add ability to filter the admin listing of posts by the publishing user
* New: Delete reference to user when user is deleted, or reassign to another user if deleted user's posts/comments get reassigned
* Fix: Show column even when post listing is being filtered
* Fix: Properly close a 'span' tag
* Change: Ensure user profile URL is sanitized before display (hardening)
* New: Add README.md
* Change: Minor whitespace tweaks to unit test bootstrap
* Change: Add GitHub link to readme
* Change: Note compatibility through WP 4.9+
* Change: Update copyright date (2018)
* Change: Update installation instruction to prefer built-in installer over .zip file

= 1.2 (2017-01-12) =
* New: When showing the 'Published by' user, link their display name to their profile page.
    * Add `get_user_url()` to get the link to the user's profile
    * Add styles for the 'Published by:' metabox appearance
* New: Visually indicate when the 'published by' user for a post is guessed
    * Add `is_publisher_id_guessed()` to determine if the publisher_id for a given post was guessed
    * Display guessed publisher's name in italics with question mark at the end
* Change: Register meta field via `register_meta()`
    * Add own `register_meta()`
    * Remove `hide_meta()` in favor of use of `register_meta()`
    * Include meta field and value in REST API responses for posts
* Change: Modify handling for 'c2c_published_by_post_status' filter
    * Add and internally use`get_post_statuses()` as getter for post statuses that should have the 'Published By' column
    * Allow more dynamic filtering by running the filter in `get_post_statuses()` rather than just once on 'init'
* Change: If the current user is the person who published the post, then simply state "you" as the name.
* Change: Add more unit tests.
* Change: Ensure `get_publisher_id()` returns an integer value.
* Change: Enable more error output for unit tests.
* Change: Default `WP_TESTS_DIR` to `/tmp/wordpress-tests-lib` rather than erroring out if not defined via environment variable.
* Change: Note compatibility through WP 4.7+.
* Change: Remove support for WordPress older than 4.6 (should still work for earlier versions)
* New: Add FAQ about showing or hiding the "Published By" column.
* Change: Update existing two screenshots and add a third.
* Change: Update copyright date (2017).

= 1.1 (2016-03-21) =
* Change: Add support for language packs:
    * Don't load plugin translations from file.
    * Remove 'Domain Path' from plugin header.
    * Remove .pot file and /lang subdirectory.
* Change: Explicitly declare methods in unit tests as public.
* New: Add LICENSE file.
* New: Add empty index.php to prevent files from being listed if web server has enabled directory listings.
* Change: Note compatibility through WP 4.4+.
* Change: Update copyright date (2016).

= 1.0.3 (2015-09-02) =
* Change: Use `dirname(__FILE__)` instead of `__DIR__` since the latter is only available on PHP 5.3+.
* Change: Minor tweaks to formatting for inline docs.
* Change: Note compatibility through WP 4.3+.

= 1.0.2 (2015-02-17) =
* Minor additions to unit tests
* Use __DIR__ instead of `dirname(__FILE__)`
* Note compatibility through WP 4.1+
* Update copyright date (2015)
* Regenerate .pot

= 1.0.1 (2014-08-25) =
* Minor amendment to documentation
* Minor tweak to an FAQ question
* Change documentation links to wp.org to be https
* Change donate link
* Note compatibility through WP 4.0+
* Add plugin icon

= 1.0 =
* Initial public release


== Upgrade Notice ==

= 1.3 =
Recommended feature update: added dropdown to filter post listings by published-by user, fixed bug preventing 'Published By' column from showing when post listing is filtered, delete meta field when user is deleted, noted compatibility through WP 4.9+, updated copyright date (2018), and more

= 1.2 =
Recommended feature update: linked usernames to profiles, noted guessed publisher with italics and question mark, referred to currenet user as "you", registered meta field for REST API compatibility, compatibility is now WP 4.6-4.7+, updated copyright date (2017), and more

= 1.1 =
Minor update: improved support for localization; verified compatibility through WP 4.4; updated copyright date (2016)

= 1.0.3 =
Minor bugfix release for users running PHP 5.2.x: reverted use of a constant only defined in PHP 5.3+. You really should upgrade your PHP or your host if this affects you. Also noted compatibility with WP 4.3+.

= 1.0.2 =
Trivial update: minor additions to unit tests; noted compatibility through WP 4.1+; updated copyright date (2015)

= 1.0.1 =
Trivial update: noted compatibility through WP 4.0+; added plugin icon.

= 1.0 =
Initial public release.
