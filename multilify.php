<?php
/**
 * Plugin Name: Multilify
 * Description: A powerful multilingual content management system for WordPress. Supports unlimited languages with custom slugs, SEO optimization, and performance caching.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Kadir Erman
 * Author URI: https://kadirerman.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multilify
 * Domain Path: /languages
 *
 * @package Multilify
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'MULTILIFY_VERSION', '1.0.0' );
define( 'MULTILIFY_PLUGIN_FILE', __FILE__ );
define( 'MULTILIFY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MULTILIFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MULTILIFY_INCLUDES_DIR', MULTILIFY_PLUGIN_DIR . 'includes/' );
define( 'MULTILIFY_ASSETS_URL', MULTILIFY_PLUGIN_URL . 'assets/' );

/**
 * Load the main plugin class.
 */
require_once MULTILIFY_INCLUDES_DIR . 'class-multilify.php';

/**
 * Initialize the plugin.
 */
function multilify() {
    return Multilify::get_instance();
}

// Start the plugin.
multilify();

/**
 * Helper function for language switcher.
 */
function multilify_switcher() {
    // Output is already escaped in get_language_switcher method
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo multilify()->get_language_switcher();
}

/**
 * Activation hook.
 */
function multilify_activate() {
    // Set default options if they don't exist.
    if ( ! get_option( 'multilify_languages' ) ) {
        $default_languages = array(
            array(
                'code' => 'en',
                'name' => 'English',
                'flag' => '🇬🇧'
            ),
            array(
                'code' => 'tr',
                'name' => 'Türkçe',
                'flag' => '🇹🇷'
            )
        );
        update_option( 'multilify_languages', $default_languages );
    }

    if ( ! get_option( 'multilify_default_language' ) ) {
        update_option( 'multilify_default_language', 'en' );
    }

    // Flush rewrite rules on activation.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'multilify_activate' );

/**
 * Deactivation hook.
 */
function multilify_deactivate() {
    // Flush rewrite rules on deactivation.
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'multilify_deactivate' );

/**
 * Add plugin action links (Settings, View details).
 */
function multilify_plugin_action_links( $links, $file ) {
    if ( plugin_basename( __FILE__ ) === $file ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=multilify' ) . '">Settings</a>';
        array_unshift( $links, $settings_link );
    }
    return $links;
}
add_filter( 'plugin_action_links', 'multilify_plugin_action_links', 10, 2 );

