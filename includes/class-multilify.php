<?php
/**
 * Multilify - Multilingual System for WordPress
 *
 * A powerful multilingual content management system
 * Supports unlimited languages with custom slugs per language
 *
 * @package Multilify
 * @version 1.0.0
 * @author Your Name
 * @link https://multilify.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Multilify {

    private static $instance = null;
    private $current_language = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Admin hooks
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Meta boxes for posts and pages
        add_action( 'add_meta_boxes', array( $this, 'add_translation_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_translation_meta' ) );

        // Frontend hooks
        add_action( 'init', array( $this, 'setup_rewrite_rules' ) );
        add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ) );
        add_action( 'init', array( $this, 'maybe_create_db_indexes' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_filter( 'request', array( $this, 'filter_request' ), 10, 1 );
        add_filter( 'pre_get_posts', array( $this, 'detect_language' ) );
        add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
        add_filter( 'the_content', array( $this, 'filter_content' ) );

        // Permalink filters - multiple hooks for all link types
        add_filter( 'post_link', array( $this, 'filter_permalink' ), 10, 2 );
        add_filter( 'page_link', array( $this, 'filter_permalink' ), 10, 2 );
        add_filter( 'post_type_link', array( $this, 'filter_permalink' ), 10, 2 );

        // Language detection
        add_action( 'template_redirect', array( $this, 'handle_language_redirect' ) );
    }

    /**
     * Get all configured languages
     */
    public function get_languages() {
        $languages = get_option( 'multilify_languages', array() );
        if ( empty( $languages ) ) {
            // Default languages
            $languages = array(
                array(
                    'code' => 'tr',
                    'name' => 'Türkçe',
                    'flag' => '🇹🇷'
                ),
                array(
                    'code' => 'en',
                    'name' => 'English',
                    'flag' => '🇬🇧'
                )
            );
            update_option( 'multilify_languages', $languages );
        }
        return $languages;
    }

    /**
     * Get default language
     */
    public function get_default_language() {
        $default = get_option( 'multilify_default_language', 'tr' );
        return $default;
    }

    /**
     * Get current language
     */
    public function get_current_language() {
        if ( null !== $this->current_language ) {
            return $this->current_language;
        }

        // Check URL for language code - sanitize REQUEST_URI for security
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '';
        $url_path = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );
        $path_parts = explode( '/', $url_path );

        $languages = $this->get_languages();
        $language_codes = wp_list_pluck( $languages, 'code' );

        // Validate language code format (2-5 lowercase letters)
        if ( ! empty( $path_parts[0] ) &&
             preg_match( '/^[a-z]{2,5}$/', $path_parts[0] ) &&
             in_array( $path_parts[0], $language_codes, true ) ) {
            $this->current_language = sanitize_key( $path_parts[0] );
        } else {
            $this->current_language = $this->get_default_language();
        }

        return $this->current_language;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Multilify - Language Management',
            'Multilify',
            'manage_options',
            'multilify',
            array( $this, 'render_admin_page' ),
            'dashicons-translation',
            30
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'multilify_settings', 'multilify_languages', array(
            'sanitize_callback' => array( $this, 'sanitize_languages' )
        ) );
        register_setting( 'multilify_settings', 'multilify_default_language', array(
            'sanitize_callback' => 'sanitize_key'
        ) );
    }

    /**
     * Sanitize languages array
     */
    public function sanitize_languages( $languages ) {
        if ( ! is_array( $languages ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $languages as $language ) {
            if ( is_array( $language ) && isset( $language['code'], $language['name'], $language['flag'] ) ) {
                $sanitized[] = array(
                    'code' => sanitize_key( $language['code'] ),
                    'name' => sanitize_text_field( $language['name'] ),
                    'flag' => sanitize_text_field( $language['flag'] )
                );
            }
        }

        return $sanitized;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_multilify' === $hook || 'post.php' === $hook || 'post-new.php' === $hook ) {
            wp_enqueue_style( 'multilify-admin', MULTILIFY_ASSETS_URL . 'css/multilify-admin.css', array(), MULTILIFY_VERSION );
            wp_enqueue_script( 'multilify-admin', MULTILIFY_ASSETS_URL . 'js/multilify-admin.js', array( 'jquery' ), MULTILIFY_VERSION, true );
        }
    }

    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle form submissions
        if ( isset( $_POST['multilify_action'] ) ) {
            $this->handle_admin_actions();
        }

        $languages = $this->get_languages();
        $default_language = $this->get_default_language();

        include MULTILIFY_INCLUDES_DIR . 'views/admin-page.php';
    }

    /**
     * Handle admin actions (add, edit, delete languages)
     */
    private function handle_admin_actions() {
        check_admin_referer( 'multilify_action' );

        $action = sanitize_text_field( $_POST['multilify_action'] );
        $languages = $this->get_languages();
        $needs_flush = false;

        switch ( $action ) {
            case 'add_language':
                $new_lang = array(
                    'code' => sanitize_key( $_POST['lang_code'] ),
                    'name' => sanitize_text_field( $_POST['lang_name'] ),
                    'flag' => sanitize_text_field( $_POST['lang_flag'] )
                );

                // Validate language code
                if ( preg_match( '/^[a-z]{2,5}$/', $new_lang['code'] ) ) {
                    $languages[] = $new_lang;
                    update_option( 'multilify_languages', $languages );
                    $needs_flush = true;
                }
                break;

            case 'delete_language':
                $lang_code = sanitize_key( $_POST['lang_code'] );
                $languages = array_filter( $languages, function( $lang ) use ( $lang_code ) {
                    return $lang['code'] !== $lang_code;
                });
                update_option( 'multilify_languages', array_values( $languages ) );
                $needs_flush = true;
                break;

            case 'set_default':
                $default_lang = sanitize_key( $_POST['default_language'] );
                update_option( 'multilify_default_language', $default_lang );
                // Default language change doesn't need rewrite flush
                break;
        }

        // Only flush rewrite rules when languages are added/deleted
        if ( $needs_flush ) {
            // Set a transient flag instead of immediate flush for better performance
            set_transient( 'multilify_flush_rewrite_rules', 1, 60 );
        }
    }

    /**
     * Add translation meta boxes
     */
    public function add_translation_meta_boxes() {
        $post_types = array( 'post', 'page' );
        $languages = $this->get_languages();

        foreach ( $post_types as $post_type ) {
            foreach ( $languages as $language ) {
                add_meta_box(
                    'multilify_' . $language['code'],
                    $language['flag'] . ' ' . $language['name'] . ' Translation',
                    array( $this, 'render_translation_meta_box' ),
                    $post_type,
                    'normal',
                    'high',
                    array( 'language' => $language )
                );
            }
        }
    }

    /**
     * Render translation meta box
     */
    public function render_translation_meta_box( $post, $metabox ) {
        $language = $metabox['args']['language'];
        $lang_code = $language['code'];

        // Get saved translations
        $title = get_post_meta( $post->ID, '_multilang_title_' . $lang_code, true );
        $content = get_post_meta( $post->ID, '_multilang_content_' . $lang_code, true );
        $slug = get_post_meta( $post->ID, '_multilang_slug_' . $lang_code, true );

        wp_nonce_field( 'multilify_save_' . $lang_code, 'multilify_nonce_' . $lang_code );

        include MULTILIFY_INCLUDES_DIR . 'views/meta-box.php';
    }

    /**
     * Save translation meta
     */
    public function save_translation_meta( $post_id ) {
        // Check if autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $languages = $this->get_languages();

        foreach ( $languages as $language ) {
            $lang_code = $language['code'];

            // Verify nonce
            if ( ! isset( $_POST['multilify_nonce_' . $lang_code] ) ||
                 ! wp_verify_nonce( $_POST['multilify_nonce_' . $lang_code], 'multilify_save_' . $lang_code ) ) {
                continue;
            }

            // Save title
            if ( isset( $_POST['multilang_title_' . $lang_code] ) ) {
                update_post_meta( $post_id, '_multilang_title_' . $lang_code, sanitize_text_field( $_POST['multilang_title_' . $lang_code] ) );
            }

            // Save content
            if ( isset( $_POST['multilang_content_' . $lang_code] ) ) {
                update_post_meta( $post_id, '_multilang_content_' . $lang_code, wp_kses_post( $_POST['multilang_content_' . $lang_code] ) );
            }

            // Save slug and clear cache
            if ( isset( $_POST['multilang_slug_' . $lang_code] ) ) {
                $new_slug = sanitize_title( $_POST['multilang_slug_' . $lang_code] );
                $old_slug = get_post_meta( $post_id, '_multilang_slug_' . $lang_code, true );

                update_post_meta( $post_id, '_multilang_slug_' . $lang_code, $new_slug );

                // Clear cache for both old and new slugs
                if ( $old_slug ) {
                    $old_cache_key = 'multilang_slug_' . md5( $lang_code . '_' . $old_slug );
                    wp_cache_delete( $old_cache_key, 'multilify' );
                }
                if ( $new_slug ) {
                    $new_cache_key = 'multilang_slug_' . md5( $lang_code . '_' . $new_slug );
                    wp_cache_delete( $new_cache_key, 'multilify' );
                }
            }
        }
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'lang';
        return $vars;
    }

    /**
     * Filter request to convert custom slugs to real post slugs
     */
    public function filter_request( $query_vars ) {
        global $wpdb;

        // Check if we have a language and a slug
        if ( isset( $query_vars['lang'] ) && ( isset( $query_vars['name'] ) || isset( $query_vars['pagename'] ) ) ) {
            $lang = sanitize_key( $query_vars['lang'] );
            $slug = isset( $query_vars['name'] ) ? sanitize_title( $query_vars['name'] ) : sanitize_title( $query_vars['pagename'] );

            // Create cache key
            $cache_key = 'multilang_slug_' . md5( $lang . '_' . $slug );
            $post_id = wp_cache_get( $cache_key, 'multilify' );

            // If not in cache, query database
            if ( false === $post_id ) {
                $post_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = %s AND meta_value = %s
                    LIMIT 1",
                    '_multilang_slug_' . $lang,
                    $slug
                ) );

                // Handle database errors
                if ( $wpdb->last_error ) {
                    error_log( 'Multilify DB Error: ' . $wpdb->last_error );
                    return $query_vars;
                }

                // Cache the result (even if null) for 1 hour
                wp_cache_set( $cache_key, $post_id ? $post_id : 'not_found', 'multilify', HOUR_IN_SECONDS );
            }

            // Handle cached "not found"
            if ( 'not_found' === $post_id ) {
                $post_id = null;
            }

            if ( $post_id ) {
                // Get the real post
                $post = get_post( $post_id );

                if ( $post && 'publish' === $post->post_status ) {
                    // Replace the slug with the real post slug
                    if ( isset( $query_vars['name'] ) ) {
                        $query_vars['name'] = $post->post_name;
                    }
                    if ( isset( $query_vars['pagename'] ) ) {
                        $query_vars['pagename'] = $post->post_name;
                    }
                }
            }
        }

        return $query_vars;
    }

    /**
     * Setup rewrite rules
     */
    public function setup_rewrite_rules() {
        $languages = $this->get_languages();

        foreach ( $languages as $language ) {
            $lang_code = $language['code'];

            // Home page with language
            add_rewrite_rule(
                '^' . $lang_code . '/?$',
                'index.php?lang=' . $lang_code,
                'top'
            );

            // Pages with language prefix
            add_rewrite_rule(
                '^' . $lang_code . '/(.+?)/?$',
                'index.php?pagename=$matches[1]&lang=' . $lang_code,
                'top'
            );

            // Posts with language prefix
            add_rewrite_rule(
                '^' . $lang_code . '/([^/]+)/?$',
                'index.php?name=$matches[1]&lang=' . $lang_code,
                'top'
            );
        }

        // Add lang query var
        add_rewrite_tag( '%lang%', '([^&]+)' );
    }

    /**
     * Maybe flush rewrite rules if flag is set
     */
    public function maybe_flush_rewrite_rules() {
        if ( get_transient( 'multilify_flush_rewrite_rules' ) ) {
            flush_rewrite_rules();
            delete_transient( 'multilify_flush_rewrite_rules' );
        }
    }

    /**
     * Create database indexes for better performance
     * Only runs once after first activation
     */
    public function maybe_create_db_indexes() {
        global $wpdb;

        // Check if indexes already created
        if ( get_option( 'multilify_db_indexes_created' ) ) {
            return;
        }

        // Create index on meta_key and meta_value for faster slug lookups
        $index_name = 'multilify_slug_lookup';

        // Check if index exists
        $index_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = DATABASE()
            AND table_name = %s
            AND index_name = %s",
            $wpdb->postmeta,
            $index_name
        ) );

        if ( ! $index_exists ) {
            // Create composite index for meta_key and meta_value (first 191 chars for utf8mb4)
            $wpdb->query(
                "ALTER TABLE {$wpdb->postmeta}
                ADD INDEX {$index_name} (meta_key(191), meta_value(191))"
            );

            if ( ! $wpdb->last_error ) {
                update_option( 'multilify_db_indexes_created', true );
            } else {
                error_log( 'Multilify Index Creation Error: ' . $wpdb->last_error );
            }
        } else {
            // Index already exists, mark as created
            update_option( 'multilify_db_indexes_created', true );
        }
    }

    /**
     * Detect language from URL
     */
    public function detect_language( $query ) {
        if ( ! is_admin() && $query->is_main_query() ) {
            $lang = get_query_var( 'lang' );
            if ( $lang ) {
                $this->current_language = $lang;

                // If only language is set (no pagename or name), show home page
                if ( ! get_query_var( 'pagename' ) && ! get_query_var( 'name' ) && ! get_query_var( 'p' ) ) {
                    $query->is_home = true;
                    $query->is_front_page = true;
                    $query->is_404 = false;
                }
            }
        }
        return $query;
    }

    /**
     * Handle language redirect based on browser
     */
    public function handle_language_redirect() {
        // Disable automatic redirect for now to prevent issues
        // Users can manually select language from switcher
        return;

        /* Optional: Enable browser-based redirect
        if ( ! is_front_page() && ! is_home() ) {
            return;
        }

        $current_lang = $this->get_current_language();
        $default_lang = $this->get_default_language();

        if ( $current_lang !== $default_lang ) {
            return;
        }

        if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) && ! isset( $_COOKIE['multilify_preference'] ) ) {
            $browser_lang = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
            $languages = $this->get_languages();
            $language_codes = wp_list_pluck( $languages, 'code' );

            if ( in_array( $browser_lang, $language_codes ) && $browser_lang !== $default_lang ) {
                wp_redirect( home_url( '/' . $browser_lang . '/' ) );
                exit;
            }
        }
        */
    }

    /**
     * Filter post title
     */
    public function filter_title( $title, $post_id = null ) {
        if ( ! $post_id || is_admin() ) {
            return $title;
        }

        $lang = $this->get_current_language();
        $translated_title = get_post_meta( $post_id, '_multilang_title_' . $lang, true );

        if ( ! empty( $translated_title ) ) {
            return $translated_title;
        }

        return $title;
    }

    /**
     * Filter post content
     */
    public function filter_content( $content ) {
        if ( is_admin() ) {
            return $content;
        }

        $post_id = get_the_ID();
        $lang = $this->get_current_language();
        $translated_content = get_post_meta( $post_id, '_multilang_content_' . $lang, true );

        if ( ! empty( $translated_content ) ) {
            return $translated_content;
        }

        return $content;
    }

    /**
     * Filter permalink
     */
    public function filter_permalink( $url, $post ) {
        if ( is_admin() ) {
            return $url;
        }

        $lang = $this->get_current_language();
        $default_lang = $this->get_default_language();

        // Get custom slug for this language
        $custom_slug = get_post_meta( $post->ID, '_multilang_slug_' . $lang, true );

        if ( ! empty( $custom_slug ) ) {
            // Use custom slug with language prefix
            $url = home_url( '/' . $lang . '/' . $custom_slug . '/' );
        } else {
            // Use default slug with language prefix
            $url = home_url( '/' . $lang . '/' . $post->post_name . '/' );
        }

        return $url;
    }

    /**
     * Get language switcher HTML
     */
    public function get_language_switcher() {
        $languages = $this->get_languages();
        $current_lang = $this->get_current_language();
        $current_post_id = get_the_ID();

        ob_start();
        ?>
        <div class="wp-multilang-switcher">
            <?php foreach ( $languages as $language ) :
                $lang_code = $language['code'];
                $is_current = ( $lang_code === $current_lang );

                // Build URL for this language
                if ( $current_post_id ) {
                    $slug = get_post_meta( $current_post_id, '_multilang_slug_' . $lang_code, true );
                    if ( empty( $slug ) ) {
                        $post = get_post( $current_post_id );
                        $slug = $post->post_name;
                    }
                    $url = home_url( '/' . $lang_code . '/' . $slug . '/' );
                } else {
                    $url = home_url( '/' . $lang_code . '/' );
                }
                ?>
                <a href="<?php echo esc_url( $url ); ?>"
                   class="lang-link <?php echo $is_current ? 'active' : ''; ?>"
                   data-lang="<?php echo esc_attr( $lang_code ); ?>">
                    <span class="flag"><?php echo esc_html( $language['flag'] ); ?></span>
                    <span class="name"><?php echo esc_html( $language['name'] ); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
