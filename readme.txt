=== Multilify ===
Contributors: kadirerman
Tags: multilingual, translation, language, i18n, localization
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.8.1
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

= 1.8.1 =
Fixes
* The translation editors could open empty and take no typing when the block editor moved the Translations box while they were still starting. They are now rebuilt with their content, as they already were when the move came after they had started

= 1.8.0 =
Admin
* The Languages screen is redesigned: each language with its address and progress, an Add language button beside the title, and Make default and Delete in a menu on each row
* Adding a language opens a dialog that lists 32 languages to pick from or search, by their own name or the English one, and fills in the code, name and flag
* Editing a language opens a dialog with the same fields, and its Save button stays off until something changes
* A flag can belong to one language only
* The editor shows one Translations box with a tab for each language, in place of a box per language
* The guide to addresses, the switcher and search engines moved to its own tab, How translating works

Earlier versions are listed in the [release notes](https://multilify.com/en/releases).

== Upgrade Notice ==

= 1.8.1 =
Fixes translation editors that could open empty in the block editor. Nothing to do after updating.
