# Contributing to Multilify

Thank you for considering contributing to Multilify! We welcome contributions from the community.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples to demonstrate the steps**
- **Describe the behavior you observed and what you expected**
- **Include screenshots if possible**
- **Include your environment details:**
  - WordPress version
  - PHP version
  - Theme being used
  - Other active plugins

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- **Use a clear and descriptive title**
- **Provide a detailed description of the proposed feature**
- **Explain why this enhancement would be useful**
- **List any alternative solutions you've considered**

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Make your changes** following our coding standards
3. **Test your changes** thoroughly
4. **Update documentation** if needed
5. **Commit your changes** with clear commit messages
6. **Push to your fork** and submit a pull request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/your-username/multilify.git
cd multilify

# Create a feature branch
git checkout -b feature/your-feature-name

# Make your changes
# Test thoroughly

# Commit your changes
git add .
git commit -m "Add your feature description"

# Push to your fork
git push origin feature/your-feature-name
```

## Coding Standards

### WordPress Coding Standards

We follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

- Use tabs for indentation
- Use single quotes for strings (unless escaping is needed)
- Add spaces after commas and around operators
- Brace style: opening braces on the same line
- Always use braces for control structures

### PHP Standards

- **PHP Version**: Support PHP 7.4+
- **Naming Conventions**:
  - Functions: `multilify_function_name()`
  - Classes: `class Multilify_Class_Name`
  - Variables: `$variable_name`
- **Documentation**: Add PHPDoc comments to all functions and classes
- **Security**: Always sanitize input and escape output

### Example Code

```php
/**
 * Get translated content for a specific language.
 *
 * @param int    $post_id  The post ID.
 * @param string $lang     The language code.
 * @return string The translated content.
 */
function multilify_get_translated_content( $post_id, $lang ) {
    if ( ! $post_id || ! $lang ) {
        return '';
    }

    $content = get_post_meta( $post_id, '_multilang_content_' . $lang, true );
    return wp_kses_post( $content );
}
```

## Testing

Before submitting a pull request:

1. **Test on multiple WordPress versions** (5.8+)
2. **Test on multiple PHP versions** (7.4, 8.0, 8.1, 8.2)
3. **Test with different themes** (at least default WordPress themes)
4. **Test with common plugins** (especially page builders)
5. **Check for PHP errors and warnings**
6. **Test on both single and multisite installations**

## Commit Messages

Write clear and meaningful commit messages:

- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit the first line to 72 characters
- Reference issues and pull requests when relevant

### Good Examples

```
Add custom slug support for translations
Fix language switcher display on mobile devices
Update documentation for installation process
Improve performance of language detection
```

### Bad Examples

```
Fixed stuff
Updates
WIP
asdfgh
```

## Documentation

- Update the README.md if you change functionality
- Update code comments and PHPDoc blocks
- Add inline comments for complex logic
- Update the changelog in readme.txt

## Security

- **Never commit sensitive data** (API keys, passwords, etc.)
- **Sanitize all user inputs** using WordPress functions
- **Escape all outputs** using WordPress functions
- **Use nonces** for form submissions
- **Check capabilities** before performing actions
- **Use prepared statements** for database queries

### Security Functions to Use

```php
// Sanitization
sanitize_text_field()
sanitize_textarea_field()
wp_kses_post()
absint()

// Escaping
esc_html()
esc_attr()
esc_url()
wp_kses_post()

// Nonces
wp_nonce_field()
wp_verify_nonce()

// Capabilities
current_user_can()
```

## File Structure

When adding new files, follow this structure:

```
multilify/
├── assets/
│   ├── css/
│   └── js/
├── includes/
│   ├── class-*.php
│   └── views/
├── languages/
├── CONTRIBUTING.md
├── LICENSE
├── README.md
├── readme.txt
└── multilify.php
```

## Questions?

If you have questions about contributing:

- Open a [GitHub Discussion](https://github.com/kadirerman/multilify/discussions)
- Check existing [Issues](https://github.com/kadirerman/multilify/issues)
- Review the [README.md](README.md)

## License

By contributing to Multilify, you agree that your contributions will be licensed under the GPL v2 or later license.

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inspiring community for all.

### Our Standards

- Be respectful and inclusive
- Accept constructive criticism gracefully
- Focus on what is best for the community
- Show empathy towards other community members

### Unacceptable Behavior

- Harassment or discriminatory language
- Personal attacks or trolling
- Publishing others' private information
- Any conduct that could be considered inappropriate

Thank you for contributing to Multilify! 🎉
