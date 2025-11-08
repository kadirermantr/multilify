<?php
/**
 * Admin page for language management
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap multilify-admin">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['multilify_updated'] ) && '1' === $_GET['multilify_updated'] ) :
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Changes saved successfully!</p>
        </div>
    <?php endif; ?>

    <div class="multilify-container">
        <!-- Current Languages Section -->
        <div class="multilify-section">
            <h2>Current Languages</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="60">Flag</th>
                        <th width="100">Code</th>
                        <th>Name</th>
                        <th width="120">Default</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $languages ) ) : ?>
                        <tr>
                            <td colspan="5">No languages added yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $languages as $multilify_language ) : ?>
                            <tr>
                                <td><?php echo esc_html( $multilify_language['flag'] ); ?></td>
                                <td><strong><?php echo esc_html( $multilify_language['code'] ); ?></strong></td>
                                <td><?php echo esc_html( $multilify_language['name'] ); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field( 'multilify_action' ); ?>
                                        <input type="hidden" name="multilify_action" value="set_default">
                                        <input type="hidden" name="default_language" value="<?php echo esc_attr( $multilify_language['code'] ); ?>">
                                        <?php if ( $multilify_language['code'] === $default_language ) : ?>
                                            <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                            <strong>Default</strong>
                                        <?php else : ?>
                                            <button type="submit" class="button button-small">Set as Default</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this language?');">
                                        <?php wp_nonce_field( 'multilify_action' ); ?>
                                        <input type="hidden" name="multilify_action" value="delete_language">
                                        <input type="hidden" name="lang_code" value="<?php echo esc_attr( $multilify_language['code'] ); ?>">
                                        <button type="submit" class="button button-small button-link-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add New Language Section -->
        <div class="multilify-section">
            <h2>Add New Language</h2>
            <form method="post" class="multilify-form">
                <?php wp_nonce_field( 'multilify_action' ); ?>
                <input type="hidden" name="multilify_action" value="add_language">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="lang_code">Language Code</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lang_code"
                                   name="lang_code"
                                   class="regular-text"
                                   placeholder="tr, en, de, es..."
                                   required
                                   maxlength="5"
                                   pattern="[a-z]{2,5}">
                            <p class="description">2-5 characters (lowercase). Example: tr, en, de</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lang_name">Language Name</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lang_name"
                                   name="lang_name"
                                   class="regular-text"
                                   placeholder="Türkçe, English, Deutsch..."
                                   required>
                            <p class="description">Full language name</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="lang_flag">Flag Emoji</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="lang_flag"
                                   name="lang_flag"
                                   class="regular-text"
                                   placeholder="🇹🇷 🇬🇧 🇩🇪..."
                                   required
                                   maxlength="10">
                            <p class="description">Flag emoji or icon. Example: 🇹🇷, 🇬🇧, 🇩🇪</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Add Language</button>
                </p>
            </form>
        </div>

        <!-- Instructions Section -->
        <div class="multilify-section" id="usage-guide">
            <h2>Usage Guide</h2>
            <div class="multilify-instructions">
                <h3>1. Adding Languages</h3>
                <p>Use the form above to add new languages. Each language must have a unique code (e.g., tr, en, de).</p>

                <h3>2. Content Translation</h3>
                <p>When editing a Post or Page, you'll see meta boxes for each language where you can:</p>
                <ul>
                    <li>Enter the translated title</li>
                    <li>Enter the translated content</li>
                    <li>Enter a custom slug/URL (optional)</li>
                </ul>

                <h3>3. URL Structure</h3>
                <p>Language codes are automatically used in URLs:</p>
                <ul>
                    <li>Turkish: <code>yoursite.com/tr/page-name/</code></li>
                    <li>English: <code>yoursite.com/en/page-name/</code></li>
                    <li>German: <code>yoursite.com/de/page-name/</code></li>
                </ul>

                <h3>4. Language Switcher</h3>
                <p>Add this code to your theme files to display a language switcher:</p>
                <pre><code>&lt;?php if ( function_exists( 'multilify_switcher' ) ) multilify_switcher(); ?&gt;</code></pre>

                <h3>5. Default Language</h3>
                <p>When a translation is not available, the default language content will be displayed. You can change the default language above.</p>
            </div>
        </div>
    </div>
</div>
