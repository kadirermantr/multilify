=== Multilify ===
Contributors: kadirerman
Tags: multilingual, translation, language, i18n, localization
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Powerful multilingual content management for WordPress with custom slugs and SEO optimization.

== Description ==

**Multilify** is a lightweight yet powerful multilingual plugin for WordPress that allows you to create and manage content in multiple languages with ease.

= Key Features =

* **Unlimited Languages** - Add as many languages as you need
* **Custom Slugs** - Set a different URL for every language, including nested pages
* **hreflang Tags** - `rel="alternate"` tags in `<head>`, plus `x-default`, so search engines index each language correctly
* **Correct Page Language** - `<html lang>` and a `Content-Language` header follow the language being viewed
* **Browser Language Detection** - First-time visitors land in the language their browser asks for; their own choice is remembered afterwards
* **Translation Progress** - The settings page shows how many entries each language still needs
* **Flag Picker** - Choose a flag from a grid instead of hunting for the emoji
* **Performance First** - Object caching on the slug lookup, with an index to match
* **Visual Editor** - Translate content using the familiar WordPress editor
* **Language Switcher** - Built-in switcher with flag-only or flag + name display
* **Any Post Type** - Posts and pages out of the box, anything else through the `multilify_post_types` filter
* **Shortcode & Template Tag** - Drop the switcher anywhere with `[multilify_switcher]`
* **Developer Friendly** - Filters for the post type list, the translated title and content, the flag choices and the locale

= Perfect For =

* Blogs and magazines
* Business websites
* E-commerce stores (works with WooCommerce)
* Portfolio sites
* Any WordPress site that needs multilingual support

= Why Choose Multilify? =

Unlike bloated translation plugins, Multilify focuses on performance and simplicity:

* **Lightweight** - No impact on your site speed
* **Clean Database** - Efficient data storage with proper indexing
* **No External Services** - All translations stored locally
* **100% Free** - No premium features, no limitations
* **Privacy Focused** - Your content stays on your server

= How It Works =

1. Install and activate the plugin
2. Add your languages from the Multilify settings page
3. Edit any post or page to see translation meta boxes
4. Enter translations for each language
5. Add the language switcher to your theme

= Developer Features =

* Object caching on the slug lookup, invalidated when a slug changes
* Transient API for optimized rewrite rule flushing
* Filters: `multilify_post_types`, `multilify_translated_title`, `multilify_translated_content`, `multilify_flag_choices`, `multilify_locale`, `multilify_enable_browser_detection`
* Clean, documented code following the WordPress Coding Standards

= Translating Content =

When editing a post or page, you'll see meta boxes for each active language where you can:

* Enter translated title
* Add translated content using the WordPress editor
* Set custom URL slugs for each language
* All fields are optional - fallback to default language if not translated

= Language Switcher =

Add the language switcher to your theme using:

`<?php if ( function_exists( 'multilify_switcher' ) ) multilify_switcher(); ?>`

Or use the shortcode: `[multilify_switcher]`

To show flags only (no language name):

`<?php if ( function_exists( 'multilify_switcher' ) ) multilify_switcher( array( 'show_name' => false ) ); ?>`

Or with the shortcode:

`[multilify_switcher show_name="false"]`

The language name is optional. If you leave it empty when adding a language, the language code is used as a fallback. You can also edit a language's name and flag at any time from the Multilify settings page.

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "Multilify"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the zip file and click "Install Now"
5. Activate the plugin

= After Installation =

1. Go to Multilify in your WordPress admin menu
2. Add your languages (e.g., English, Turkish, Spanish)
3. Set your default language
4. Start translating your content!

== Frequently Asked Questions ==

= Is Multilify free? =

Yes! Multilify is 100% free with no premium version or hidden costs.

= How many languages can I add? =

Unlimited! Add as many languages as your site needs.

= Does it work with page builders? =

Multilify translates content that runs through the `the_content` filter, which covers the block editor and the classic editor. Builders that render from their own stored data, such as Elementor, bypass that filter, so their layouts are not translated.

= Will it slow down my site? =

No! Multilify is built with performance in mind. It uses caching and database indexing to ensure fast page loads.

= Can I use custom URLs for each language? =

Yes! You can set custom slugs for each language version of your content.

= Does it support RTL languages? =

Multilify sets `dir="rtl"` on the document when WordPress reports an RTL locale, so an RTL theme renders correctly. It does not ship RTL stylesheets of its own; that is your theme's job.

= Can I translate menus and widgets? =

Currently, Multilify focuses on post and page content. Menu and widget translation support is planned for future releases.

= Is it compatible with WooCommerce? =

Translation panels appear on posts and pages by default. To add them to products, or any other custom post type, use the `multilify_post_types` filter:

`add_filter( 'multilify_post_types', function ( $types ) { $types[] = 'product'; return $types; } );`

This translates the product title and description. Prices, attributes and variations are WooCommerce's own data and are not covered.

= How do I get support? =

You can get support through the WordPress.org support forums or by contacting us directly.

== Screenshots ==

1. Language management page - Add and manage your languages
2. Translation meta boxes - Translate content directly in the editor
3. Language switcher - Display language options to your visitors
4. Settings page - Configure your multilingual setup

== Changelog ==

= 1.1.0 =
Fixes
* Language detection now works when WordPress is installed in a subdirectory; the install path is no longer mistaken for a language code
* `/{lang}/page/2/` and `/{lang}/feed/` return content instead of a 404; paged entries, entry feeds and search under a language prefix now route correctly
* Child pages keep their parent path in translated URLs, so `/{lang}/parent/child/` resolves instead of 404ing
* The document title on the front page and on archives is no longer overwritten with a post title from the loop
* Translation panels no longer save on revisions or on post types they were never added to
* Meta box fields no longer inherit the inline editor's layout, which shrank their labels and clipped their inputs

Added
* `rel="alternate"` hreflang tags, including `x-default`, in `<head>`
* `<html lang>` and a `Content-Language` header that follow the language being viewed
* Browser language detection for first-time visitors, remembered per visitor and overridable with the `multilify_enable_browser_detection` filter
* Translation progress per language on the settings page
* A flag picker in place of the free text emoji field
* Custom post type support through the `multilify_post_types` filter
* Filters: `multilify_translated_title`, `multilify_translated_content`, `multilify_flag_choices`, `multilify_locale`

Changed
* Rebuilt the settings screen: language entries read as a list with their code, name, default state and progress
* Corrected readme claims that the code did not support

= 1.0.5 =
* The default language no longer gets a URL prefix, so each post has a single canonical address
* Added uninstall cleanup that removes plugin options, translation meta and the lookup index
* Made the plugin translatable: interface strings now use the multilify text domain and a .pot template ships in /languages
* The default language can no longer be deleted, and only an existing language can be set as default
* Language switcher is now a nav list with hreflang, aria-current and a screen-reader label when only flags are shown
* Fixed the delete confirmation appearing twice
* Added a LICENSE file (GPL v2)

= 1.0.4 =
* Tested up to WordPress 7.1
* Verified compatibility with the always-iframed post editor introduced in WordPress 7.1
* Verified compatibility with the jQuery UI 1.14.2 update shipped in WordPress 7.1
* No functional changes

= 1.0.3 =
* Added the ability to edit existing languages (name and flag) from the admin
* Prevented adding a language with a code that already exists (shows an error)
* Registered the [multilify_switcher] shortcode (supports show_name and show_flag)
* Fixed a blank settings page after saving (form handling moved to admin_init so redirects work)
* Fixed PHP warnings in the language switcher when a post object was unavailable
* Tested up to WordPress 6.9

= 1.0.2 =
* Added flag-only language switcher support via show_name and show_flag arguments
* Made the language name optional (falls back to the language code when left empty)
* Fixed an empty/underlined language label appearing in the switcher

= 1.0.1 =
* Added plugin icon for WordPress.org directory
* Added Plugin URI (https://multilify.vercel.app)
* Updated support section with website and GitHub links

= 1.0.0 =
* Initial release
* Unlimited language support
* Custom slug functionality
* Performance caching system
* Database indexing
* Language switcher
* SEO optimization
* Browser language detection
* Admin interface
* Translation meta boxes

== Upgrade Notice ==

= 1.1.0 =
Fixes 404s on paged, feed and child-page URLs under a language prefix, and on subdirectory installs where language detection never worked. Adds hreflang tags and browser language detection. Visit Settings > Permalinks once after updating so the new routes register.

= 1.0.5 =
URLs in the default language lose their /{lang}/ prefix in this release. If you rely on the prefixed form, add redirects before updating.

= 1.0.4 =
Compatibility release for WordPress 7.1. No functional changes.

= 1.0.3 =
Adds language editing, duplicate-code protection, the [multilify_switcher] shortcode, and fixes a blank settings page after saving.

= 1.0.2 =
Adds flag-only switcher support and makes the language name optional.

= 1.0.1 =
Minor update: Added plugin icon and website links.

= 1.0.0 =
Initial release of Multilify.

== Support ==

* **Website:** [https://multilify.vercel.app](https://multilify.vercel.app)
* **Support Forums:** [WordPress.org support forums](https://wordpress.org/support/plugin/multilify/)
* **GitHub:** [github.com/kadirermantr/multilify](https://github.com/kadirermantr/multilify)

== Contributing ==

Multilify is open source! Contribute on [GitHub](https://github.com/kadirermantr/multilify).
