=== Multilify ===
Contributors: Kadir Erman
Tags: multilingual, translation, language, i18n, localization, seo, wpml alternative
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful multilingual content management system for WordPress. Create unlimited language versions with custom slugs, SEO optimization, and performance caching.

== Description ==

**Multilify** is a lightweight yet powerful multilingual plugin for WordPress that allows you to create and manage content in multiple languages with ease.

= Key Features =

* **Unlimited Languages** - Add as many languages as you need
* **Custom Slugs** - Set unique URLs for each language version
* **SEO Optimized** - Built-in support for multilingual SEO best practices
* **Performance First** - Advanced caching system for fast page loads
* **Database Indexed** - Optimized database queries for better performance
* **Visual Editor** - Translate content using familiar WordPress editor
* **Language Switcher** - Built-in language switcher widget
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

= 1.0.0 =
Initial release of Multilify.

== Support ==

For support, please visit the [WordPress.org support forums](https://wordpress.org/support/plugin/multilify/).

== Contributing ==

Multilify is open source! Contribute on [GitHub](https://github.com/yourname/multilify).
