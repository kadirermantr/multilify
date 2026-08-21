=== Multilify ===
Contributors: kadirerman
Tags: multilingual, translation, language, i18n, localization
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Powerful multilingual content management for WordPress with custom slugs and SEO optimization.

== Description ==

**Multilify** is a lightweight yet powerful multilingual plugin for WordPress that allows you to create and manage content in multiple languages with ease.

= Key Features =

* **Unlimited Languages** - Add as many languages as you need
* **Custom Slugs** - Set unique URLs for each language version
* **SEO Optimized** - Built-in support for multilingual SEO best practices
* **Performance First** - Advanced caching system for fast page loads
* **Database Indexed** - Optimized database queries for better performance
* **Visual Editor** - Translate content using familiar WordPress editor
* **Language Switcher** - Built-in switcher with flag-only or flag + name display
* **Editable Languages** - Edit a language's name and flag anytime from the admin
* **Optional Names** - Leave the name empty to fall back to the language code
* **Shortcode & Template Tag** - Drop the switcher anywhere with `[multilify_switcher]`
* **Auto Detection** - Automatic browser language detection
* **Developer Friendly** - Clean code with hooks and filters

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

* Object caching support for better performance
* Transient API for optimized rewrite rule flushing
* Custom hooks and filters
* Clean, documented code
* PSR standards compliant

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

Yes, Multilify works with all major page builders including Elementor, Gutenberg, and Classic Editor.

= Will it slow down my site? =

No! Multilify is built with performance in mind. It uses caching and database indexing to ensure fast page loads.

= Can I use custom URLs for each language? =

Yes! You can set custom slugs for each language version of your content.

= Does it support RTL languages? =

Yes, Multilify supports both LTR and RTL languages.

= Can I translate menus and widgets? =

Currently, Multilify focuses on post and page content. Menu and widget translation support is planned for future releases.

= Is it compatible with WooCommerce? =

Yes, Multilify works with WooCommerce for translating product content.

= How do I get support? =

You can get support through the WordPress.org support forums or by contacting us directly.

== Screenshots ==

1. Language management page - Add and manage your languages
2. Translation meta boxes - Translate content directly in the editor
3. Language switcher - Display language options to your visitors
4. Settings page - Configure your multilingual setup

== Changelog ==

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
