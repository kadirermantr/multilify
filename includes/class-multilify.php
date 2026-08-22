<?php
/**
 * Multilify - Multilingual System for WordPress
 *
 * A powerful multilingual content management system
 * Supports unlimited languages with custom slugs per language
 *
 * @package Multilify
 * @version 1.0.0
 * @author Kadir Erman
 * @link https://kadirerman.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Core plugin class handling languages, routing and translated content.
 */
class Multilify {

	/**
	 * Singleton instance.
	 *
	 * @var Multilify|null
	 */
	private static $instance = null;

	/**
	 * Language code detected for the current request.
	 *
	 * @var string|null
	 */
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
		// Translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Handle form submissions before any output so redirects work (avoids "headers already sent").
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Meta boxes for posts and pages.
		add_action( 'add_meta_boxes', array( $this, 'add_translation_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_translation_meta' ) );

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_shortcode( 'multilify_switcher', array( $this, 'switcher_shortcode' ) );
		add_action( 'init', array( $this, 'setup_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_create_db_indexes' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		// Runs before core matches rewrite rules, because verbose page rules
		// validate pagename against get_page_by_path() and would reject a
		// translated path outright.
		add_filter( 'do_parse_request', array( $this, 'resolve_translated_request' ), 10, 3 );
		add_filter( 'request', array( $this, 'filter_request' ), 10, 1 );
		add_filter( 'pre_get_posts', array( $this, 'detect_language' ) );
		add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'filter_content' ) );

		// Permalink filters - multiple hooks for all link types.
		add_filter( 'post_link', array( $this, 'filter_permalink' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'filter_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'filter_permalink' ), 10, 2 );

		// Title filters for <title> tag.
		add_filter( 'wp_title', array( $this, 'filter_wp_title' ), 10, 3 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ), 10, 1 );

		// SEO and accessibility: document language, alternates and content negotiation.
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 10, 2 );
		add_filter( 'locale', array( $this, 'filter_locale' ) );
		add_action( 'wp_head', array( $this, 'render_hreflang_tags' ), 1 );
		add_filter( 'wp_headers', array( $this, 'filter_content_language_header' ) );

		// Redirect first-time visitors to the language their browser asks for.
		add_action( 'template_redirect', array( $this, 'maybe_redirect_to_browser_language' ) );
	}

	/**
	 * Load the plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'multilify', false, dirname( plugin_basename( MULTILIFY_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Get all configured languages
	 */
	public function get_languages() {
		$languages = get_option( 'multilify_languages', array() );
		if ( empty( $languages ) ) {
			// Default languages.
			$languages = array(
				array(
					'code' => 'tr',
					'name' => 'Türkçe',
					'flag' => '🇹🇷',
				),
				array(
					'code' => 'en',
					'name' => 'English',
					'flag' => '🇬🇧',
				),
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

		$path_parts = $this->get_request_path_segments();

		$languages      = $this->get_languages();
		$language_codes = wp_list_pluck( $languages, 'code' );

		// Validate language code format (2-5 lowercase letters).
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
	 * Split the current request path into segments, relative to the WordPress root.
	 *
	 * On a subdirectory install the request path carries the install directory
	 * (e.g. /blog/tr/post/), so that prefix is removed before the first segment
	 * is treated as a language code.
	 *
	 * @return array List of path segments.
	 */
	private function get_request_path_segments() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$url_path    = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		// Drop the subdirectory WordPress is installed in, when there is one.
		$home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$home_path = trim( $home_path, '/' );

		if ( '' !== $home_path ) {
			$trimmed = ltrim( $url_path, '/' );

			if ( 0 === strpos( $trimmed, $home_path . '/' ) ) {
				$url_path = substr( $trimmed, strlen( $home_path ) );
			} elseif ( $trimmed === $home_path ) {
				$url_path = '';
			}
		}

		return explode( '/', trim( $url_path, '/' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Multilify - Language Management', 'multilify' ),
			__( 'Multilify', 'multilify' ),
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
		register_setting(
			'multilify_settings',
			'multilify_languages',
			array(
				'sanitize_callback' => array( $this, 'sanitize_languages' ),
			)
		);
		register_setting(
			'multilify_settings',
			'multilify_default_language',
			array(
				'sanitize_callback' => 'sanitize_key',
			)
		);
	}

	/**
	 * Sanitize languages array
	 *
	 * @param mixed $languages Raw languages value submitted from the settings form.
	 * @return array Sanitized list of languages.
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
					'flag' => sanitize_text_field( $language['flag'] ),
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_multilify' === $hook || 'post.php' === $hook || 'post-new.php' === $hook ) {
			wp_enqueue_style( 'multilify-admin', MULTILIFY_ASSETS_URL . 'css/multilify-admin.css', array(), MULTILIFY_VERSION );
			wp_enqueue_script( 'multilify-admin', MULTILIFY_ASSETS_URL . 'js/multilify-admin.js', array( 'jquery' ), MULTILIFY_VERSION, true );
			wp_localize_script(
				'multilify-admin',
				'multilifyAdmin',
				array(
					'confirmDelete' => __( 'Delete this language? Its translations stay in the database, so re-adding the same code brings them back.', 'multilify' ),
					'copied'        => __( 'Copied', 'multilify' ),
					'copyFailed'    => __( 'Press Ctrl+C to copy', 'multilify' ),
				)
			);
		}
	}

	/**
	 * Enqueue frontend assets (language switcher styles).
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'multilify', MULTILIFY_ASSETS_URL . 'css/multilify.css', array(), MULTILIFY_VERSION );
		wp_enqueue_script( 'multilify', MULTILIFY_ASSETS_URL . 'js/multilify.js', array(), MULTILIFY_VERSION, true );
	}

	/**
	 * Render admin settings page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$languages        = $this->get_languages();
		$default_language = $this->get_default_language();
		$translatable     = $this->count_translatable_entries();
		$progress         = $this->get_translation_progress( $languages );
		$flag_choices     = $this->get_flag_choices();

		include MULTILIFY_INCLUDES_DIR . 'views/admin-page.php';
	}

	/**
	 * Handle admin actions (add, edit, delete languages)
	 */
	public function handle_admin_actions() {
		// Only act on our own form submissions; this runs on every admin_init.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['multilify_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'multilify_action' );

		$action      = sanitize_text_field( wp_unslash( $_POST['multilify_action'] ) );
		$languages   = $this->get_languages();
		$needs_flush = false;
		$error       = '';

		switch ( $action ) {
			case 'add_language':
				if ( ! isset( $_POST['lang_code'], $_POST['lang_name'], $_POST['lang_flag'] ) ) {
					break;
				}

				$new_lang = array(
					'code' => sanitize_key( wp_unslash( $_POST['lang_code'] ) ),
					'name' => sanitize_text_field( wp_unslash( $_POST['lang_name'] ) ),
					'flag' => sanitize_text_field( wp_unslash( $_POST['lang_flag'] ) ),
				);

				// Name is optional; fall back to the code so it is never an empty/whitespace string.
				$new_lang['name'] = trim( $new_lang['name'] );
				if ( '' === $new_lang['name'] ) {
					$new_lang['name'] = $new_lang['code'];
				}

				// Validate language code.
				if ( ! preg_match( '/^[a-z]{2,5}$/', $new_lang['code'] ) ) {
					$error = 'invalid_code';
					break;
				}

				// Reject duplicates: a language code must be unique.
				if ( $this->language_code_exists( $new_lang['code'], $languages ) ) {
					$error = 'duplicate_code';
					break;
				}

				$languages[] = $new_lang;
				update_option( 'multilify_languages', $languages );
				$needs_flush = true;
				break;

			case 'edit_language':
				if ( ! isset( $_POST['lang_code'], $_POST['lang_name'], $_POST['lang_flag'] ) ) {
					break;
				}

				$edit_code = sanitize_key( wp_unslash( $_POST['lang_code'] ) );
				$edit_name = trim( sanitize_text_field( wp_unslash( $_POST['lang_name'] ) ) );
				$edit_flag = sanitize_text_field( wp_unslash( $_POST['lang_flag'] ) );

				// Name is optional; fall back to the code.
				if ( '' === $edit_name ) {
					$edit_name = $edit_code;
				}

				// The code is immutable (translation meta is keyed by it); only name/flag change.
				$found = false;
				foreach ( $languages as &$lang ) {
					if ( $lang['code'] === $edit_code ) {
						$lang['name'] = $edit_name;
						$lang['flag'] = $edit_flag;
						$found        = true;
						break;
					}
				}
				unset( $lang );

				if ( $found ) {
					update_option( 'multilify_languages', $languages );
				} else {
					$error = 'not_found';
				}
				break;

			case 'delete_language':
				if ( ! isset( $_POST['lang_code'] ) ) {
					break;
				}

				$lang_code = sanitize_key( wp_unslash( $_POST['lang_code'] ) );

				// The default language must always resolve to a real entry.
				if ( $lang_code === $this->get_default_language() ) {
					$error = 'delete_default';
					break;
				}

				if ( ! $this->language_code_exists( $lang_code, $languages ) ) {
					$error = 'not_found';
					break;
				}

				$languages = array_filter(
					$languages,
					function ( $lang ) use ( $lang_code ) {
						return $lang['code'] !== $lang_code;
					}
				);
				update_option( 'multilify_languages', array_values( $languages ) );
				$needs_flush = true;
				break;

			case 'set_default':
				if ( ! isset( $_POST['default_language'] ) ) {
					break;
				}

				$default_lang = sanitize_key( wp_unslash( $_POST['default_language'] ) );

				// Only an existing language may become the default.
				if ( ! $this->language_code_exists( $default_lang, $languages ) ) {
					$error = 'not_found';
					break;
				}

				update_option( 'multilify_default_language', $default_lang );
				// Default language change doesn't need rewrite flush.
				break;
		}

		// Only flush rewrite rules when languages are added/deleted.
		if ( $needs_flush ) {
			// Set a transient flag instead of immediate flush for better performance.
			set_transient( 'multilify_flush_rewrite_rules', 1, 60 );
		}

		// Redirect to prevent form resubmission.
		if ( '' !== $error ) {
			$redirect = add_query_arg( 'multilify_error', $error, admin_url( 'admin.php?page=multilify' ) );
		} else {
			$redirect = add_query_arg( 'multilify_updated', '1', admin_url( 'admin.php?page=multilify' ) );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Check whether a language code already exists.
	 *
	 * @param string $code      Language code to look for.
	 * @param array  $languages Existing languages list.
	 * @return bool
	 */
	private function language_code_exists( $code, $languages ) {
		foreach ( $languages as $language ) {
			if ( isset( $language['code'] ) && $language['code'] === $code ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Flags offered by the picker, keyed by the language code they suit.
	 *
	 * A starting point rather than a closed list; the picker keeps a free text
	 * field so any other emoji or symbol can still be used.
	 *
	 * @return array Map of language code to flag emoji.
	 */
	public function get_flag_choices() {
		$choices = array(
			'en' => '🇬🇧',
			'us' => '🇺🇸',
			'tr' => '🇹🇷',
			'de' => '🇩🇪',
			'fr' => '🇫🇷',
			'es' => '🇪🇸',
			'it' => '🇮🇹',
			'pt' => '🇵🇹',
			'br' => '🇧🇷',
			'nl' => '🇳🇱',
			'pl' => '🇵🇱',
			'ru' => '🇷🇺',
			'ua' => '🇺🇦',
			'ar' => '🇸🇦',
			'zh' => '🇨🇳',
			'ja' => '🇯🇵',
			'ko' => '🇰🇷',
			'hi' => '🇮🇳',
			'id' => '🇮🇩',
			'gr' => '🇬🇷',
			'se' => '🇸🇪',
			'no' => '🇳🇴',
			'dk' => '🇩🇰',
			'fi' => '🇫🇮',
			'cz' => '🇨🇿',
			'ro' => '🇷🇴',
			'hu' => '🇭🇺',
			'bg' => '🇧🇬',
			'il' => '🇮🇱',
			'th' => '🇹🇭',
			'vn' => '🇻🇳',
			'az' => '🇦🇿',
		);

		/**
		 * Filter the flags offered in the language picker.
		 *
		 * @param array $choices Map of language code to flag emoji.
		 */
		return apply_filters( 'multilify_flag_choices', $choices );
	}

	/**
	 * Post types that get translation meta boxes.
	 *
	 * Filterable so custom post types, including WooCommerce products, can opt in.
	 *
	 * @return array List of post type slugs.
	 */
	public function get_translatable_post_types() {
		/**
		 * Filter which post types Multilify adds translation meta boxes to.
		 *
		 * @param array $post_types Post type slugs. Defaults to post and page.
		 */
		$post_types = apply_filters( 'multilify_post_types', array( 'post', 'page' ) );

		return array_values( array_filter( array_map( 'sanitize_key', (array) $post_types ) ) );
	}

	/**
	 * Count the published entries that can carry a translation.
	 *
	 * @return int Number of translatable entries.
	 */
	public function count_translatable_entries() {
		$cached = wp_cache_get( 'translatable_total', 'multilify' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$total = 0;

		foreach ( $this->get_translatable_post_types() as $post_type ) {
			$counts = wp_count_posts( $post_type );

			if ( isset( $counts->publish ) ) {
				$total += (int) $counts->publish;
			}
		}

		wp_cache_set( 'translatable_total', $total, 'multilify', 5 * MINUTE_IN_SECONDS );

		return $total;
	}

	/**
	 * Count how many entries carry a title translation per language.
	 *
	 * Gives the settings page a real completion figure instead of asking the
	 * user to open every post to find out.
	 *
	 * @param array $languages Languages to report on.
	 * @return array Map of language code to translated entry count.
	 */
	public function get_translation_progress( $languages ) {
		global $wpdb;

		$cached = wp_cache_get( 'translation_progress', 'multilify' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$progress   = array();
		$post_types = $this->get_translatable_post_types();

		if ( empty( $post_types ) ) {
			return $progress;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		foreach ( $languages as $language ) {
			$meta_key = '_multilang_title_' . $language['code'];

			// A per-language count over indexed meta; cached for five minutes.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count = $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->prepare(
					"SELECT COUNT(1)
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE pm.meta_key = %s
                    AND pm.meta_value <> ''
                    AND p.post_status = 'publish'
                    AND p.post_type IN ( {$placeholders} )",
					array_merge( array( $meta_key ), $post_types )
				)
			);

			$progress[ $language['code'] ] = (int) $count;
		}

		wp_cache_set( 'translation_progress', $progress, 'multilify', 5 * MINUTE_IN_SECONDS );

		return $progress;
	}

	/**
	 * Add translation meta boxes
	 */
	public function add_translation_meta_boxes() {
		$post_types = $this->get_translatable_post_types();
		$languages  = $this->get_languages();

		foreach ( $post_types as $post_type ) {
			foreach ( $languages as $language ) {
				// Name is optional; fall back to the code so the meta box title is never blank.
				$language_label = isset( $language['name'] ) ? trim( (string) $language['name'] ) : '';
				if ( '' === $language_label ) {
					$language_label = $language['code'];
				}

				add_meta_box(
					'multilify_' . $language['code'],
					sprintf(
						/* translators: 1: language flag emoji, 2: language name. */
						__( '%1$s %2$s Translation', 'multilify' ),
						$language['flag'],
						$language_label
					),
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
	 *
	 * @param WP_Post $post    Post being edited.
	 * @param array   $metabox Meta box registration arguments.
	 */
	public function render_translation_meta_box( $post, $metabox ) {
		$language  = $metabox['args']['language'];
		$lang_code = $language['code'];

		// Get saved translations.
		$title   = get_post_meta( $post->ID, '_multilang_title_' . $lang_code, true );
		$content = get_post_meta( $post->ID, '_multilang_content_' . $lang_code, true );
		$slug    = get_post_meta( $post->ID, '_multilang_slug_' . $lang_code, true );

		wp_nonce_field( 'multilify_save_' . $lang_code, 'multilify_nonce_' . $lang_code );

		include MULTILIFY_INCLUDES_DIR . 'views/meta-box.php';
	}

	/**
	 * Save translation meta
	 *
	 * @param int $post_id Post being saved.
	 */
	public function save_translation_meta( $post_id ) {
		// Check if autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Revisions carry no meta boxes of their own.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( get_post_type( $post_id ), $this->get_translatable_post_types(), true ) ) {
			return;
		}

		$languages = $this->get_languages();

		// The settings page reports completion from these counts.
		wp_cache_delete( 'translation_progress', 'multilify' );
		wp_cache_delete( 'translatable_total', 'multilify' );

		foreach ( $languages as $language ) {
			$lang_code = $language['code'];

			// Verify nonce.
			if ( ! isset( $_POST[ 'multilify_nonce_' . $lang_code ] ) ||
				! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'multilify_nonce_' . $lang_code ] ) ), 'multilify_save_' . $lang_code ) ) {
				continue;
			}

			// Save title.
			if ( isset( $_POST[ 'multilang_title_' . $lang_code ] ) ) {
				update_post_meta( $post_id, '_multilang_title_' . $lang_code, sanitize_text_field( wp_unslash( $_POST[ 'multilang_title_' . $lang_code ] ) ) );
			}

			// Save content.
			if ( isset( $_POST[ 'multilang_content_' . $lang_code ] ) ) {
				update_post_meta( $post_id, '_multilang_content_' . $lang_code, wp_kses_post( wp_unslash( $_POST[ 'multilang_content_' . $lang_code ] ) ) );
			}

			// Save slug and clear cache.
			if ( isset( $_POST[ 'multilang_slug_' . $lang_code ] ) ) {
				$new_slug = sanitize_title( wp_unslash( $_POST[ 'multilang_slug_' . $lang_code ] ) );
				$old_slug = get_post_meta( $post_id, '_multilang_slug_' . $lang_code, true );

				update_post_meta( $post_id, '_multilang_slug_' . $lang_code, $new_slug );

				// Clear cache for both old and new slugs.
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
	 *
	 * @param array $vars Registered public query variables.
	 * @return array Query variables including the language variable.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'lang';
		return $vars;
	}

	/**
	 * Swap a translated path for the real one before rewrite rules are matched.
	 *
	 * With verbose page rules WordPress validates a pagename rule against
	 * get_page_by_path() while matching, so a translated path has to be
	 * resolved before that check rather than on the request filter.
	 *
	 * @param bool         $continue Whether WordPress should parse the request.
	 * @param WP           $wp       Current WordPress environment instance.
	 * @param array|string $extra_query_vars Extra query variables.
	 * @return bool Unchanged $continue value.
	 */
	public function resolve_translated_request( $continue, $wp = null, $extra_query_vars = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature required by the do_parse_request filter.
		$segments = $this->get_request_path_segments();

		// Only a nested path needs this; a single segment is resolved later on
		// the request filter, and rewriting it here would fight the canonical
		// redirect and loop.
		if ( count( $segments ) < 3 ) {
			return $continue;
		}

		$codes = wp_list_pluck( $this->get_languages(), 'code' );
		$lang  = $segments[0];

		if ( ! in_array( $lang, $codes, true ) ) {
			return $continue;
		}

		// The last segment names the entry; anything before it is ancestry.
		$slug  = sanitize_title( end( $segments ) );
		$entry = $this->lookup_translated_slug( $lang, $slug );

		if ( ! $entry || ! is_post_type_hierarchical( $entry->post_type ) ) {
			return $continue;
		}

		$real_path = get_page_uri( $entry->ID );

		if ( ! $real_path ) {
			return $continue;
		}

		// Rewrite what core is about to match, keeping the language prefix.
		// PATH_INFO takes precedence over REQUEST_URI, so both have to move.
		$translated = '/' . $lang . '/' . $real_path . '/';

		if ( ! empty( $_SERVER['PATH_INFO'] ) ) {
			$_SERVER['PATH_INFO'] = $translated;
		}

		$_SERVER['REQUEST_URI'] = $translated;

		return $continue;
	}

	/**
	 * Filter request to convert custom slugs to real post slugs
	 *
	 * @param array $query_vars Query variables for the current request.
	 * @return array Query variables with translated slugs resolved.
	 */
	public function filter_request( $query_vars ) {
		// Check if we have a language and a slug.
		if ( ! isset( $query_vars['lang'] ) || ( ! isset( $query_vars['name'] ) && ! isset( $query_vars['pagename'] ) ) ) {
			return $query_vars;
		}

		$lang = sanitize_key( $query_vars['lang'] );
		$path = isset( $query_vars['name'] ) ? $query_vars['name'] : $query_vars['pagename'];
		$path = trim( (string) $path, '/' );

		if ( '' === $path ) {
			return $query_vars;
		}

		// A hierarchical URL carries ancestor segments; only the last one names the entry.
		$segments = array_map( 'sanitize_title', explode( '/', $path ) );
		$slug     = end( $segments );

		$entry = $this->lookup_translated_slug( $lang, $slug );

		if ( ! $entry ) {
			return $query_vars;
		}

		if ( is_post_type_hierarchical( $entry->post_type ) ) {
			$query_vars['pagename'] = get_page_uri( $entry->ID );
			unset( $query_vars['name'] );

			// A non-page hierarchical type needs its own query var to resolve.
			if ( 'page' !== $entry->post_type ) {
				$query_vars['post_type'] = $entry->post_type;
			}
		} else {
			$query_vars['name'] = $entry->post_name;
			unset( $query_vars['pagename'] );

			if ( 'post' !== $entry->post_type ) {
				$query_vars['post_type'] = $entry->post_type;
			}
		}

		return $query_vars;
	}

	/**
	 * Find the post a translated slug belongs to.
	 *
	 * Results are cached for an hour, misses included, so an unknown slug does
	 * not hit the database on every request.
	 *
	 * @param string $lang Language the slug belongs to.
	 * @param string $slug Translated slug.
	 * @return object|null Row with ID, post_name and post_type, or null when unmatched.
	 */
	private function lookup_translated_slug( $lang, $slug ) {
		global $wpdb;

		$cache_key   = 'multilang_slug_' . md5( $lang . '_' . $slug );
		$cached_data = wp_cache_get( $cache_key, 'multilify' );

		if ( false === $cached_data ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT p.ID, p.post_name, p.post_type, p.post_status
                    FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE pm.meta_key = %s AND pm.meta_value = %s
                    AND p.post_status = 'publish'
                    LIMIT 1",
					'_multilang_slug_' . $lang,
					$slug
				)
			);

			// Cache the result (even if null) for 1 hour.
			$cached_data = $result ? $result : 'not_found';
			wp_cache_set( $cache_key, $cached_data, 'multilify', HOUR_IN_SECONDS );
		}

		// Handle cached "not found".
		if ( 'not_found' === $cached_data ) {
			return null;
		}

		return $cached_data ? $cached_data : null;
	}

	/**
	 * Setup rewrite rules
	 */
	public function setup_rewrite_rules() {
		$languages = $this->get_languages();

		foreach ( $languages as $language ) {
			$lang_code = $language['code'];

			// Home page with language.
			add_rewrite_rule(
				'^' . $lang_code . '/?$',
				'index.php?lang=' . $lang_code,
				'top'
			);

			// Paged front page: /{lang}/page/2/.
			add_rewrite_rule(
				'^' . $lang_code . '/page/([0-9]{1,})/?$',
				'index.php?paged=$matches[1]&lang=' . $lang_code,
				'top'
			);

			// Front page feed: /{lang}/feed/.
			add_rewrite_rule(
				'^' . $lang_code . '/feed/(feed|rdf|rss|rss2|atom)/?$',
				'index.php?feed=$matches[1]&lang=' . $lang_code,
				'top'
			);
			add_rewrite_rule(
				'^' . $lang_code . '/feed/?$',
				'index.php?feed=feed&lang=' . $lang_code,
				'top'
			);

			// Search: /{lang}/?s=term keeps working, /{lang}/search/term/ is routed here.
			add_rewrite_rule(
				'^' . $lang_code . '/search/(.+)/?$',
				'index.php?s=$matches[1]&lang=' . $lang_code,
				'top'
			);

			// Paged single entry: /{lang}/slug/page/2/.
			add_rewrite_rule(
				'^' . $lang_code . '/(.+?)/page/([0-9]{1,})/?$',
				'index.php?pagename=$matches[1]&paged=$matches[2]&lang=' . $lang_code,
				'top'
			);

			// Single entry feed: /{lang}/slug/feed/.
			add_rewrite_rule(
				'^' . $lang_code . '/(.+?)/feed/?$',
				'index.php?pagename=$matches[1]&feed=feed&lang=' . $lang_code,
				'top'
			);

			// Single level slugs (posts) with language prefix.
			add_rewrite_rule(
				'^' . $lang_code . '/([^/]+)/?$',
				'index.php?name=$matches[1]&lang=' . $lang_code,
				'top'
			);

			// Multi-level slugs (pages/hierarchical) with language prefix.
			add_rewrite_rule(
				'^' . $lang_code . '/(.+)/?$',
				'index.php?pagename=$matches[1]&lang=' . $lang_code,
				'top'
			);
		}

		// Add lang query var.
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

		// Check if indexes already created.
		if ( get_option( 'multilify_db_indexes_created' ) ) {
			return;
		}

		// Create index on meta_key and meta_value for faster slug lookups.
		$index_name = 'multilify_slug_lookup';

		// Check if index exists.
		// Schema information queries must access INFORMATION_SCHEMA directly.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$index_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE table_schema = DATABASE()
            AND table_name = %s
            AND index_name = %s',
				$wpdb->postmeta,
				$index_name
			)
		);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $index_exists ) {
			// Create composite index for meta_key and meta_value (first 191 chars for utf8mb4).
			// Sanitize index name (alphanumeric and underscore only).
			$safe_index_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $index_name );

			// Index name is manually sanitized above (only alphanumeric and underscore allowed).
			// Schema changes require direct queries and cannot use prepared statements for DDL.
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->query(
				"ALTER TABLE {$wpdb->postmeta} ADD INDEX {$safe_index_name} (meta_key(191), meta_value(191))"
			);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( ! $wpdb->last_error ) {
				update_option( 'multilify_db_indexes_created', true );
			}
		} else {
			// Index already exists, mark as created.
			update_option( 'multilify_db_indexes_created', true );
		}
	}

	/**
	 * Detect language from URL
	 *
	 * @param WP_Query $query Query being prepared.
	 * @return WP_Query Query adjusted for the detected language.
	 */
	public function detect_language( $query ) {
		if ( ! is_admin() && $query->is_main_query() ) {
			$lang = get_query_var( 'lang' );
			if ( $lang ) {
				$this->current_language = $lang;

				// If only language is set (no pagename or name), show home page.
				if ( ! get_query_var( 'pagename' ) && ! get_query_var( 'name' ) && ! get_query_var( 'p' ) ) {
					$query->is_home       = true;
					$query->is_front_page = true;
					$query->is_404        = false;
				}
			}
		}
		return $query;
	}

	/**
	 * Filter post title
	 *
	 * @param string   $title   Original post title.
	 * @param int|null $post_id Post the title belongs to.
	 * @return string Translated title when available, original title otherwise.
	 */
	public function filter_title( $title, $post_id = null ) {
		if ( ! $post_id || is_admin() ) {
			return $title;
		}

		$lang             = $this->get_current_language();
		$translated_title = get_post_meta( $post_id, '_multilang_title_' . $lang, true );

		/**
		 * Filter the translated post title before it is displayed.
		 *
		 * @param string $translated_title Stored translation, empty when untranslated.
		 * @param string $title            Original post title.
		 * @param int    $post_id          Post being displayed.
		 * @param string $lang             Active language code.
		 */
		$translated_title = apply_filters( 'multilify_translated_title', $translated_title, $title, $post_id, $lang );

		if ( ! empty( $translated_title ) ) {
			return $translated_title;
		}

		return $title;
	}

	/**
	 * Filter post content
	 *
	 * @param string $content Original post content.
	 * @return string Translated content when available, original content otherwise.
	 */
	public function filter_content( $content ) {
		if ( is_admin() ) {
			return $content;
		}

		$post_id = get_the_ID();

		// Outside the loop there is no reliable post to translate.
		if ( ! $post_id ) {
			return $content;
		}

		// Feeds keep the source content so subscribers are not switched languages mid-stream.
		if ( is_feed() ) {
			return $content;
		}

		$lang               = $this->get_current_language();
		$translated_content = get_post_meta( $post_id, '_multilang_content_' . $lang, true );

		/**
		 * Filter the translated post content before it is displayed.
		 *
		 * @param string $translated_content Stored translation, empty when untranslated.
		 * @param string $content            Original post content.
		 * @param int    $post_id            Post being displayed.
		 * @param string $lang               Active language code.
		 */
		$translated_content = apply_filters( 'multilify_translated_content', $translated_content, $content, $post_id, $lang );

		if ( ! empty( $translated_content ) ) {
			return $translated_content;
		}

		return $content;
	}

	/**
	 * Filter permalink
	 *
	 * @param string      $url  Original post permalink.
	 * @param WP_Post|int $post Post the permalink belongs to. The page_link filter passes an ID.
	 * @return string Language-aware permalink.
	 */
	public function filter_permalink( $url, $post ) {
		if ( is_admin() ) {
			return $url;
		}

		// page_link passes a post ID while post_link and post_type_link pass a WP_Post.
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return $url;
		}

		$lang         = $this->get_current_language();
		$default_lang = $this->get_default_language();

		// Get custom slug for this language.
		$custom_slug = get_post_meta( $post->ID, '_multilang_slug_' . $lang, true );

		// The default language keeps WordPress' own URL so each post has a single canonical address.
		if ( $lang === $default_lang && empty( $custom_slug ) ) {
			return $url;
		}

		$path = $this->build_translated_path( $post, $custom_slug );

		if ( '' === $path ) {
			return $url;
		}

		if ( $lang === $default_lang ) {
			return home_url( '/' . $path . '/' );
		}

		return home_url( '/' . $lang . '/' . $path . '/' );
	}

	/**
	 * Build the path a translated permalink should point at.
	 *
	 * Keeps the ancestor segments of a hierarchical post so that a child page
	 * resolves, and only swaps the post's own segment for the translated slug.
	 *
	 * @param WP_Post $post        Post the permalink belongs to.
	 * @param string  $custom_slug Translated slug, empty when the post is untranslated.
	 * @return string Path without surrounding slashes.
	 */
	private function build_translated_path( $post, $custom_slug ) {
		return $this->build_translated_path_for( $post, $custom_slug, $this->get_current_language() );
	}

	/**
	 * Build the path for a post in an explicit language.
	 *
	 * @param WP_Post $post        Post the path is built for.
	 * @param string  $custom_slug Translated slug, empty when the post is untranslated.
	 * @param string  $lang_code   Language the ancestors should be resolved in.
	 * @return string Path without surrounding slashes.
	 */
	private function build_translated_path_for( $post, $custom_slug, $lang_code ) {
		$slug = ! empty( $custom_slug ) ? $custom_slug : $post->post_name;

		if ( '' === $slug ) {
			return '';
		}

		if ( ! is_post_type_hierarchical( $post->post_type ) ) {
			return $slug;
		}

		$segments = array( $slug );
		$parent   = (int) $post->post_parent;
		$guard    = 0;

		// Walk up the tree, keeping ancestors in their own translated form when there is one.
		while ( $parent > 0 && $guard < 20 ) {
			$ancestor = get_post( $parent );

			if ( ! $ancestor instanceof WP_Post ) {
				break;
			}

			$ancestor_slug = get_post_meta( $ancestor->ID, '_multilang_slug_' . $lang_code, true );
			array_unshift( $segments, ! empty( $ancestor_slug ) ? $ancestor_slug : $ancestor->post_name );

			$parent = (int) $ancestor->post_parent;
			++$guard;
		}

		return implode( '/', $segments );
	}

	/**
	 * Shortcode handler for [multilify_switcher].
	 *
	 * Supports show_name and show_flag attributes, e.g.
	 * [multilify_switcher show_name="false"].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function switcher_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'show_flag' => 'true',
				'show_name' => 'true',
			),
			$atts,
			'multilify_switcher'
		);

		return $this->get_language_switcher(
			array(
				'show_flag' => filter_var( $atts['show_flag'], FILTER_VALIDATE_BOOLEAN ),
				'show_name' => filter_var( $atts['show_name'], FILTER_VALIDATE_BOOLEAN ),
			)
		);
	}

	/**
	 * Get language switcher HTML
	 *
	 * @param array $args {
	 *     Optional. Display arguments.
	 *
	 *     @type bool $show_flag Whether to show the flag. Default true.
	 *     @type bool $show_name Whether to show the language name. Default true.
	 * }
	 * @return string Language switcher markup.
	 */
	public function get_language_switcher( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'show_flag' => true,
				'show_name' => true,
			)
		);

		$show_flag = ! empty( $args['show_flag'] );
		$show_name = ! empty( $args['show_name'] );

		// Always keep at least one visible element so links aren't empty.
		if ( ! $show_flag && ! $show_name ) {
			$show_name = true;
		}

		$languages       = $this->get_languages();
		$current_lang    = $this->get_current_language();
		$current_post_id = get_the_ID();

		ob_start();
		?>
		<nav class="wp-multilang-switcher" aria-label="<?php esc_attr_e( 'Language switcher', 'multilify' ); ?>">
			<ul>
			<?php
			foreach ( $languages as $language ) :
				$lang_code  = $language['code'];
				$is_current = ( $lang_code === $current_lang );

				// Name is optional; fall back to the language code so the label is never blank.
				$lang_flag = isset( $language['flag'] ) ? trim( (string) $language['flag'] ) : '';
				$lang_name = isset( $language['name'] ) ? trim( (string) $language['name'] ) : '';
				if ( '' === $lang_name ) {
					$lang_name = $lang_code;
				}

				// Build URL for this language; the default language has no prefix.
				$url = $this->get_language_url( $lang_code, $current_post_id );
				?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>"
						class="lang-link <?php echo $is_current ? 'active' : ''; ?>"
						hreflang="<?php echo esc_attr( $lang_code ); ?>"
						lang="<?php echo esc_attr( $lang_code ); ?>"
						data-lang="<?php echo esc_attr( $lang_code ); ?>"
						<?php echo $is_current ? ' aria-current="true"' : ''; ?>>
						<?php if ( $show_flag && '' !== $lang_flag ) : ?>
							<span class="flag" aria-hidden="true"><?php echo esc_html( $lang_flag ); ?></span>
						<?php endif; ?>
						<?php if ( $show_name ) : ?>
							<span class="name"><?php echo esc_html( $lang_name ); ?></span>
						<?php else : ?>
							<span class="screen-reader-text"><?php echo esc_html( $lang_name ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
			</ul>
		</nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send a first-time visitor to the language their browser asks for.
	 *
	 * Only the language home is redirected, only once per visitor, and never
	 * for bots, logged-in users or requests that already carry a language.
	 * The choice is remembered in a cookie so the redirect never fights a
	 * visitor who deliberately picked another language.
	 */
	public function maybe_redirect_to_browser_language() {
		/**
		 * Filter whether browser language detection may redirect this request.
		 *
		 * @param bool $enabled Whether detection is active. Default true.
		 */
		if ( ! apply_filters( 'multilify_enable_browser_detection', true ) ) {
			return;
		}

		// Only the site root redirects; deep links are always honoured as typed.
		if ( is_admin() || ! is_front_page() || is_paged() || is_feed() || is_robots() ) {
			return;
		}

		// A visitor who already chose a language is never overridden.
		if ( isset( $_COOKIE['multilify_language'] ) ) {
			return;
		}

		// A language-prefixed URL is already an explicit choice.
		$segments = $this->get_request_path_segments();
		$codes    = wp_list_pluck( $this->get_languages(), 'code' );

		if ( ! empty( $segments[0] ) && in_array( $segments[0], $codes, true ) ) {
			return;
		}

		$preferred = $this->get_browser_preferred_language();

		if ( '' === $preferred || $preferred === $this->get_default_language() ) {
			return;
		}

		wp_safe_redirect( $this->get_language_url( $preferred ), 302 );
		exit;
	}

	/**
	 * Resolve the visitor's preferred language from the Accept-Language header.
	 *
	 * Quality values are honoured, and a regional tag such as de-AT matches the
	 * de language when de is configured.
	 *
	 * @return string Matching language code, or an empty string when none matches.
	 */
	public function get_browser_preferred_language() {
		if ( empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return '';
		}

		$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
		$codes  = wp_list_pluck( $this->get_languages(), 'code' );
		$ranked = array();

		foreach ( explode( ',', $header ) as $chunk ) {
			$parts = explode( ';', trim( $chunk ) );
			$tag   = strtolower( trim( $parts[0] ) );

			if ( '' === $tag ) {
				continue;
			}

			$quality = 1.0;

			if ( isset( $parts[1] ) && 0 === strpos( trim( $parts[1] ), 'q=' ) ) {
				$quality = (float) substr( trim( $parts[1] ), 2 );
			}

			$ranked[] = array(
				'tag'     => $tag,
				'quality' => $quality,
			);
		}

		usort(
			$ranked,
			function ( $a, $b ) {
				if ( $a['quality'] === $b['quality'] ) {
					return 0;
				}

				return ( $a['quality'] < $b['quality'] ) ? 1 : -1;
			}
		);

		foreach ( $ranked as $entry ) {
			if ( in_array( $entry['tag'], $codes, true ) ) {
				return $entry['tag'];
			}

			// de-AT should still match a configured de.
			$base = strtok( $entry['tag'], '-' );

			if ( $base && in_array( $base, $codes, true ) ) {
				return $base;
			}
		}

		return '';
	}

	/**
	 * Align the WordPress locale with the language being viewed.
	 *
	 * Themes and plugins that translate their own strings then follow the
	 * language in the URL instead of the site-wide setting.
	 *
	 * @param string $locale Locale WordPress resolved.
	 * @return string Locale for the active language.
	 */
	public function filter_locale( $locale ) {
		if ( is_admin() || ! did_action( 'parse_request' ) ) {
			return $locale;
		}

		$lang = $this->get_current_language();

		if ( '' === $lang || $lang === $this->get_default_language() ) {
			return $locale;
		}

		/**
		 * Filter the WordPress locale used for a Multilify language.
		 *
		 * Return the unchanged locale to opt a language out of locale switching.
		 *
		 * @param string $locale Locale WordPress resolved.
		 * @param string $lang   Active Multilify language code.
		 */
		return apply_filters( 'multilify_locale', $locale, $lang );
	}

	/**
	 * Build the front-end URL of the current entry in a given language.
	 *
	 * Used by both the switcher and the hreflang tags so the two can never
	 * disagree about where a translation lives.
	 *
	 * @param string   $lang_code Language to build the URL for.
	 * @param int|null $post_id   Post to link to, or null for the language home.
	 * @return string Absolute URL.
	 */
	public function get_language_url( $lang_code, $post_id = null ) {
		$is_default = ( $lang_code === $this->get_default_language() );

		if ( ! $post_id ) {
			return $is_default ? home_url( '/' ) : home_url( '/' . $lang_code . '/' );
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return $is_default ? home_url( '/' ) : home_url( '/' . $lang_code . '/' );
		}

		$custom_slug = get_post_meta( $post->ID, '_multilang_slug_' . $lang_code, true );

		// An untranslated post in the default language keeps its canonical
		// WordPress URL. filter_permalink() would rewrite that into the language
		// being viewed, so it is suspended for the length of this call.
		if ( $is_default && empty( $custom_slug ) ) {
			remove_filter( 'post_link', array( $this, 'filter_permalink' ), 10 );
			remove_filter( 'page_link', array( $this, 'filter_permalink' ), 10 );
			remove_filter( 'post_type_link', array( $this, 'filter_permalink' ), 10 );

			$permalink = get_permalink( $post );

			add_filter( 'post_link', array( $this, 'filter_permalink' ), 10, 2 );
			add_filter( 'page_link', array( $this, 'filter_permalink' ), 10, 2 );
			add_filter( 'post_type_link', array( $this, 'filter_permalink' ), 10, 2 );

			return $permalink;
		}

		$path = $this->build_translated_path_for( $post, $custom_slug, $lang_code );

		if ( '' === $path ) {
			return $is_default ? home_url( '/' ) : home_url( '/' . $lang_code . '/' );
		}

		return $is_default
			? home_url( '/' . $path . '/' )
			: home_url( '/' . $lang_code . '/' . $path . '/' );
	}

	/**
	 * Output rel="alternate" hreflang tags for every configured language.
	 *
	 * Search engines read these from <head>; the hreflang attributes on the
	 * switcher links do not serve the same purpose.
	 */
	public function render_hreflang_tags() {
		if ( is_404() || is_search() ) {
			return;
		}

		$languages = $this->get_languages();

		if ( count( $languages ) < 2 ) {
			return;
		}

		$post_id = is_singular() ? get_the_ID() : null;

		foreach ( $languages as $language ) {
			printf(
				'<link rel="alternate" hreflang="%1$s" href="%2$s" />' . "\n",
				esc_attr( $language['code'] ),
				esc_url( $this->get_language_url( $language['code'], $post_id ) )
			);
		}

		// x-default points at the default language for unmatched visitors.
		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( $this->get_language_url( $this->get_default_language(), $post_id ) )
		);
	}

	/**
	 * Set the document language on <html> to the language being viewed.
	 *
	 * @param string $output Attribute string built by WordPress.
	 * @param string $doctype Document type the attributes are for.
	 * @return string Attribute string carrying the active language.
	 */
	public function filter_language_attributes( $output, $doctype = 'html' ) {
		if ( is_admin() ) {
			return $output;
		}

		$lang = $this->get_current_language();

		if ( '' === $lang ) {
			return $output;
		}

		$attributes = array( 'lang="' . esc_attr( $lang ) . '"' );

		if ( 'xhtml' === $doctype ) {
			$attributes[] = 'xml:lang="' . esc_attr( $lang ) . '"';
		}

		if ( is_rtl() ) {
			array_unshift( $attributes, 'dir="rtl"' );
		}

		return implode( ' ', $attributes );
	}

	/**
	 * Send a Content-Language header matching the language being served.
	 *
	 * @param array $headers Headers WordPress is about to send.
	 * @return array Headers including Content-Language.
	 */
	public function filter_content_language_header( $headers ) {
		if ( is_admin() ) {
			return $headers;
		}

		$lang = $this->get_current_language();

		if ( '' !== $lang ) {
			$headers['Content-Language'] = $lang;
		}

		return $headers;
	}

	/**
	 * Filter wp_title for <title> tag
	 *
	 * @param string $title       Original document title.
	 * @param string $sep         Title separator. Unused, required by the wp_title filter signature.
	 * @param string $seplocation Separator location. Unused, required by the wp_title filter signature.
	 * @return string Title with the translated post title substituted in.
	 */
	public function filter_wp_title( $title, $sep = '', $seplocation = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature required by the wp_title filter.
		// Outside a single entry get_the_ID() returns a loop post, which would
		// overwrite the site title on the front page and on archives.
		if ( is_admin() || ! is_singular() ) {
			return $title;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $title;
		}

		$lang             = $this->get_current_language();
		$translated_title = get_post_meta( $post_id, '_multilang_title_' . $lang, true );

		if ( ! empty( $translated_title ) ) {
			// Replace the post title part in wp_title.
			$original_title = get_the_title( $post_id );
			if ( ! empty( $original_title ) ) {
				$title = str_replace( $original_title, $translated_title, $title );
			}
		}

		return $title;
	}

	/**
	 * Filter document_title_parts for modern WordPress
	 *
	 * @param array $title_parts Parts making up the document title.
	 * @return array Title parts with the translated title substituted in.
	 */
	public function filter_document_title_parts( $title_parts ) {
		// Only a single entry has a title worth substituting; on archives
		// get_the_ID() would hand back an arbitrary loop post.
		if ( is_admin() || ! is_singular() ) {
			return $title_parts;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $title_parts;
		}

		$lang             = $this->get_current_language();
		$translated_title = get_post_meta( $post_id, '_multilang_title_' . $lang, true );

		if ( ! empty( $translated_title ) && isset( $title_parts['title'] ) ) {
			$title_parts['title'] = $translated_title;
		}

		return $title_parts;
	}
}
