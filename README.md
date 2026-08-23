# Multilify

[![Version](https://img.shields.io/wordpress/plugin/v/multilify.svg?color=blue)](https://wordpress.org/plugins/multilify/)
[![WordPress](https://img.shields.io/wordpress/plugin/wp-version/multilify.svg?color=brightgreen)](https://wordpress.org/plugins/multilify/)
[![Tested](https://img.shields.io/wordpress/plugin/tested/multilify.svg?color=brightgreen)](https://wordpress.org/plugins/multilify/)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-orange.svg)

A powerful, lightweight, and **100% free** multilingual content management system for WordPress. Built with performance and simplicity in mind.

## Features

- **Unlimited Languages** - Add as many languages as you need
- **Custom Slugs** - Set unique URLs for each language version
- **Performance First** - Advanced caching system with database indexing
- **Security Hardened** - Built with WordPress security best practices
- **Visual Editor** - Translate content using the familiar WordPress editor
- **Language Switcher** - Built-in customizable switcher, as a `[multilify_switcher]` shortcode or a `multilify_switcher()` template tag
- **Auto Detection** - Browser language detection for first-time visitors, remembered per visitor
- **SEO Optimized** - Clean URLs, `rel="alternate"` hreflang tags with `x-default`, and a `<html lang>` attribute and `Content-Language` header that follow the language being viewed
- **Translation Progress** - See how complete each language is from the settings screen
- **Custom Post Types** - Translate any post type through the `multilify_post_types` filter
- **No External Services** - All translations stored locally on your server
- **Privacy Focused** - Your content stays on your server

## Why Multilify?

Unlike bloated translation plugins, Multilify focuses on three core principles:

1. **Performance** - Optimized database queries, caching, and minimal overhead
2. **Simplicity** - Clean codebase, easy to understand and extend
3. **Freedom** - 100% free and open source, no premium upsells

## How It Works

A translation is not a second post. Each post carries all of its translations in post meta, keyed by language code, so there is no duplicated content and no separate query to serve a translated page. Per-language rewrite rules resolve `/{lang}/{slug}/` back to the original post, and the default language keeps WordPress' own prefix-free URLs so every post has a single canonical address.

## Filters

| Filter | Purpose |
| --- | --- |
| `multilify_post_types` | Post types that get translation panels |
| `multilify_translated_title` | Filter a translated title before output |
| `multilify_translated_content` | Filter translated content before output |
| `multilify_enable_browser_detection` | Turn browser language detection off |
| `multilify_flag_choices` | Extend the flag picker |
| `multilify_locale` | Map a language code to a WordPress locale |

## Technical Highlights

- **Performance** - Database indexing, object caching support, optimized queries
- **Security** - XSS/CSRF protection, SQL injection prevention, capability checks
- **Standards Compliant** - Follows WordPress Coding Standards and Plugin Review Guidelines

## Contributing

We welcome contributions from the community! Whether it's bug reports, feature requests, or code contributions, please see our [Contributing Guidelines](CONTRIBUTING.md) for details on how to get started.

## Sponsors

Multilify is free and has no paid tier, so development runs on evenings and weekends. If the plugin saves you the cost of a commercial licence, [sponsoring the project](https://github.com/sponsors/kadirermantr) helps keep it maintained.

Sponsors at $25 a month and above are listed here.

<!-- sponsors -->
<!-- No sponsors yet. -->
<!-- /sponsors -->

## Bug Reports & Feature Requests

Found a bug or have a feature idea? Please [open an issue](https://github.com/kadirermantr/multilify/issues) on GitHub.

## Acknowledgments

- Thanks to the WordPress community
- Inspired by Polylang and WPML
- Built with ❤️ for the open source community
