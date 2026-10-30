=== Multilify ===
Contributors: kadirerman
Tags: multilingual, translation, language, i18n, localization
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your WordPress site multilingual without copying posts. Each translation lives in the original post, with its own address.

== Description ==

Multilify keeps every translation inside the post it translates. There is no copy per language to keep in sync: one post, and an address for each language.

* `example.com/hello/` is the original
* `example.com/de/hallo/` and `example.com/es/hola/` are the same post in German and Spanish

= What it does =

* Unlimited languages, each with its own address prefix
* A slug of its own for every language, nested pages included
* hreflang tags with x-default, a `lang` attribute that follows the page, and a sitemap for each language
* One Translations box on the post, in the block and the classic editor, with a title, slug and content for each language
* A language switcher as a shortcode, `[multilify_switcher]`, or a template tag
* No outbound requests, no account and no pro tier

= Learn more =

* [How it works](https://multilify.com/)
* [Documentation](https://multilify.com/en/docs): addresses, the switcher and the filters
* [Moving over from WPML or Polylang](https://multilify.com/en/migrate)
* [Questions and answers](https://multilify.com/en/faq)
* [Release notes](https://multilify.com/en/releases)

== Installation ==

1. Install Multilify from Plugins > Add New and activate it
2. Open Multilify in the admin menu and add your languages
3. Make the language your posts are written in the default, with Make default in its row's menu. A new install starts with English
4. Translate a post in its Translations box
5. Put `[multilify_switcher]` where the language switcher should appear

The [documentation](https://multilify.com/en/docs) covers the rest.

== Frequently Asked Questions ==

= Is it free? =

Yes. There is no pro version and nothing to sign up for.

= Does it work with page builders? =

Multilify translates content that passes through `the_content`, which covers the block editor and the classic editor. Builders that render from their own stored data, such as Elementor, are not translated.

= Where can I find more answers? =

On [multilify.com](https://multilify.com/en/faq): addresses, search engines, caching, WooCommerce, multisite and more. For anything else, open a topic in the [support forum](https://wordpress.org/support/plugin/multilify/).

== Screenshots ==

1. The Languages screen: every language with its address and how much of the site it already covers
2. Adding a language: pick one from the list or search for it, and the code, name and flag fill in
3. The Translations box in the editor: a tab for each language with its own title, slug and content
4. The language switcher on a published post

== Changelog ==

= 1.8.0 =
Admin
* The Languages screen is redesigned: each language with its address and progress, an Add language button beside the title, and Make default and Delete in a menu on each row
* Adding a language opens a dialog that lists 32 languages to pick from or search, by their own name or the English one, and fills in the code, name and flag
* Editing a language opens a dialog with the same fields, and its Save button stays off until something changes
* A flag can belong to one language only
* The editor shows one Translations box with a tab for each language, in place of a box per language
* The guide to addresses, the switcher and search engines moved to its own tab, How translating works

= 1.7.1 =
Fixes
* On a site whose front page is a static page, `/{lang}/` listed the latest posts instead of that page, and the switcher and hreflang tags sent every language to the page's slug. The language home now shows the front page, its translated title and content included, and `/{lang}/<front-page-slug>/` redirects to `/{lang}/`
* A search form on a language page searched the default language. Classic search forms and the Search block now submit to the language home
* `/en/?s=term` and other addresses carrying the default language's prefix lost their query string on the redirect to the bare address
* Rewrite rules were rebuilt before themes and plugins registered their post types on `init`, so those types' addresses could 404 until the next rebuild
* A translated slug removed with `delete_post_meta()` and no value, as WP-CLI does, kept resolving from a persistent object cache for up to an hour
* A private page's translated address returned a 404 to editors who can read it

Admin
* The Languages column marks a missing translation with a dashed badge and a translated one with a solid border, so the state no longer rests on color alone
* Inputs on the settings screen and in the editor panel are 40px tall with 16px text on phones, which also stops iOS from zooming in on focus
* The Copy buttons announce "Copied" through a status region screen readers pick up
* Labels under 12px were raised to 12px, and the edit form uses the same input size as the add form
* Code examples read left to right in a right-to-left admin, and the arrow keys in the flag picker follow the reading direction
* A flags-only switcher, `show_name="false"`, underlines the current language's flag

Earlier versions are listed in the [release notes](https://multilify.com/en/releases).

== Upgrade Notice ==

= 1.8.0 =
A redesigned Languages screen and one tabbed Translations box in the editor. Languages and translations stay as they are, so there is nothing to do after updating.
