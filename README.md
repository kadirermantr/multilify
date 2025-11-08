# Multilify

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/wordpress-5.8%2B-brightgreen.svg)
![PHP](https://img.shields.io/badge/php-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-orange.svg)

A powerful, lightweight, and **100% free** multilingual content management system for WordPress. Built with performance and simplicity in mind.

## Features

- **Unlimited Languages** - Add as many languages as you need
- **Custom Slugs** - Set unique URLs for each language version
- **Performance First** - Advanced caching system with database indexing
- **Security Hardened** - Built with WordPress security best practices
- **Visual Editor** - Translate content using the familiar WordPress editor
- **Language Switcher** - Built-in customizable language switcher
- **Auto Detection** - Automatic browser language detection
- **SEO Optimized** - Clean URLs and SEO-friendly structure
- **No External Services** - All translations stored locally on your server
- **Privacy Focused** - Your content stays on your server

## Why Multilify?

Unlike bloated translation plugins, Multilify focuses on three core principles:

1. **Performance** - Optimized database queries, caching, and minimal overhead
2. **Simplicity** - Clean codebase, easy to understand and extend
3. **Freedom** - 100% free and open source, no premium upsells

## Installation

### From WordPress.org (Recommended)

1. Log in to your WordPress admin panel
2. Go to **Plugins > Add New**
3. Search for **"Multilify"**
4. Click **Install Now** and then **Activate**

### Manual Installation (Upload ZIP)

1. Download the latest release from [GitHub Releases](https://github.com/kadirerman/multilify/releases)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the `multilify.zip` file
4. Click **Install Now** and then **Activate**

### From Source (For Developers)

```bash
cd /wp-content/plugins/
git clone https://github.com/kadirerman/multilify.git
```

Then activate the plugin through the WordPress admin panel.

## Quick Start

1. **Add Languages**
   - Go to **Multilify** in your WordPress admin menu
   - Click **Add New Language**
   - Enter language code (e.g., `en`, `tr`, `de`), name, and flag emoji

2. **Translate Content**
   - Edit any Post or Page
   - You'll see translation meta boxes for each language
   - Enter translated title, content, and custom slug (optional)

3. **Add Language Switcher**
   ```php
   <?php if ( function_exists( 'multilify_switcher' ) ) multilify_switcher(); ?>
   ```

## Documentation

### URL Structure

Multilify automatically creates clean URLs for each language:

```
yoursite.com/           → Default language
yoursite.com/en/        → English
yoursite.com/tr/        → Turkish
yoursite.com/de/        → German
```

### Custom Slugs

Set unique slugs for each language:

```
yoursite.com/en/about-us/        → English
yoursite.com/tr/hakkimizda/      → Turkish
yoursite.com/de/uber-uns/        → German
```

### Language Switcher

Display a language switcher in your theme:

```php
// Simple usage
multilify_switcher();

// Or get the HTML
$switcher = multilify()->get_language_switcher();
echo $switcher;
```

### Programmatic Usage

```php
// Get current language
$current_lang = multilify()->get_current_language();

// Get all languages
$languages = multilify()->get_languages();

// Get default language
$default_lang = multilify()->get_default_language();

// Get translated content
$translated_title = get_post_meta( $post_id, '_multilang_title_en', true );
$translated_content = get_post_meta( $post_id, '_multilang_content_en', true );
```

## Technical Features

### Performance Optimizations

- **Database Indexing** - Composite indexes for faster slug lookups
- **Object Caching** - Full support for Redis, Memcached, and other caching systems
- **Transient API** - Optimized rewrite rule flushing
- **Lazy Loading** - Assets loaded only when needed

### Security Features

- **XSS Protection** - All user inputs properly sanitized
- **CSRF Protection** - Nonce verification on all forms
- **SQL Injection Prevention** - Prepared statements for all database queries
- **Capability Checks** - Proper permission verification

## Contributing

We welcome contributions from the community! Whether it's bug reports, feature requests, or code contributions, please see our [Contributing Guidelines](CONTRIBUTING.md) for details on how to get started.

## Bug Reports & Feature Requests

Found a bug or have a feature idea? Please [open an issue](https://github.com/kadirerman/multilify/issues) on GitHub.

## Acknowledgments

- Thanks to the WordPress community
- Inspired by Polylang and WPML
- Built with ❤️ for the open source community
