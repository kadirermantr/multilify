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
	 * Address the request arrived at, kept while it is rewritten for matching.
	 *
	 * @var array|null
	 */
	private $original_request = null;

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

		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Handle form submissions before any output so redirects work (avoids "headers already sent").
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Meta boxes for posts and pages.
		add_action( 'add_meta_boxes', array( $this, 'add_translation_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_translation_meta' ) );

		// Cached routes belong to a published entry, so they have to go when one
		// stops being published or leaves altogether.
		add_action( 'transition_post_status', array( $this, 'forget_translated_routes' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'forget_routes_for_post' ) );
		// A rename is not a status change, so it needs its own hook.
		add_action( 'post_updated', array( $this, 'forget_renamed_slug' ), 10, 3 );

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_shortcode( 'multilify_switcher', array( $this, 'switcher_shortcode' ) );
		add_action( 'init', array( $this, 'setup_rewrite_rules' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ) );
		add_action( 'init', array( $this, 'register_sitemap_provider' ) );
		// Schema work and the first flush after an update happen in the admin,
		// never inside a visitor's request.
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		// Runs before core matches rewrite rules, because verbose page rules
		// validate pagename against get_page_by_path() and would reject a
		// translated path outright.
		add_filter( 'do_parse_request', array( $this, 'resolve_translated_request' ), 10, 3 );
		// And puts the real address back once matching is done.
		add_action( 'parse_request', array( $this, 'restore_request_uri' ) );
		add_filter( 'request', array( $this, 'filter_request' ), 10, 1 );
		add_filter( 'pre_get_posts', array( $this, 'detect_language' ) );
		add_filter( 'posts_search', array( $this, 'filter_search' ), 10, 2 );
		add_filter( 'the_title', array( $this, 'filter_title' ), 10, 2 );
		// Ahead of do_blocks, wpautop, wptexturize and the embeds, so a
		// translation goes through the same pipeline as the post's own content.
		// At the default priority it ran after them and came out unformatted.
		add_filter( 'the_content', array( $this, 'filter_content' ), 1 );

		// Permalink filters - multiple hooks for all link types.
		add_filter( 'post_link', array( $this, 'filter_permalink' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'filter_permalink' ), 10, 2 );
		add_filter( 'post_type_link', array( $this, 'filter_permalink' ), 10, 2 );

		// Title filters for the <title> tag. wp_title() builds its title from
		// single_post_title, and themes with title-tag support from these parts.
		add_filter( 'single_post_title', array( $this, 'filter_single_post_title' ), 10, 2 );
		add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ), 10, 1 );

		// SEO and accessibility: document language, alternates and content negotiation.
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 10, 2 );
		add_action( 'wp', array( $this, 'switch_to_language_locale' ) );
		add_action( 'wp_head', array( $this, 'render_hreflang_tags' ), 1 );
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical_url' ), 10, 2 );
		add_filter( 'wp_headers', array( $this, 'filter_content_language_header' ) );
		add_filter( 'oembed_request_post_id', array( $this, 'resolve_oembed_post_id' ), 10, 2 );

		// Redirect first-time visitors to the language their browser asks for.
		add_action( 'template_redirect', array( $this, 'maybe_redirect_to_browser_language' ) );
		// And send an entry's own name to the address its default language gave it.
		add_action( 'template_redirect', array( $this, 'redirect_to_translated_permalink' ) );
	}

	/**
	 * The languages a fresh install starts with.
	 *
	 * Activation and the runtime fallback both read the list here, so a site can
	 * never end up with a default language that has no entry of its own.
	 *
	 * @return array List of languages.
	 */
	public static function starter_languages() {
		return array(
			array(
				'code' => 'en',
				'name' => 'English',
				'flag' => '🇬🇧',
			),
			array(
				'code' => 'tr',
				'name' => 'Türkçe',
				'flag' => '🇹🇷',
			),
		);
	}

	/**
	 * The default language a fresh install starts with.
	 *
	 * @return string Language code.
	 */
	public static function starter_default_language() {
		$starter = self::starter_languages();

		return $starter[0]['code'];
	}

	/**
	 * Get all configured languages
	 */
	public function get_languages() {
		$languages = get_option( 'multilify_languages', array() );

		if ( empty( $languages ) ) {
			$languages = self::starter_languages();
			update_option( 'multilify_languages', $languages );
		}

		return $languages;
	}

	/**
	 * Get default language
	 */
	public function get_default_language() {
		return get_option( 'multilify_default_language', self::starter_default_language() );
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
		$seen      = array();

		foreach ( $languages as $language ) {
			if ( ! is_array( $language ) || ! isset( $language['code'], $language['name'], $language['flag'] ) ) {
				continue;
			}

			$code = sanitize_key( $language['code'] );

			// Same shape the admin form enforces: a code is a URL prefix and
			// every translation meta key is derived from it.
			if ( ! preg_match( '/^[a-z]{2,5}$/', $code ) || in_array( $code, $seen, true ) ) {
				continue;
			}

			$seen[]      = $code;
			$sanitized[] = array(
				'code' => $code,
				'name' => sanitize_text_field( $language['name'] ),
				'flag' => sanitize_text_field( $language['flag'] ),
			);
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
					/* translators: %s: language name. */
					'confirmDeleteTitle' => __( 'Delete %s?', 'multilify' ),
					'copied'             => __( 'Copied', 'multilify' ),
					'copyFailed'         => __( 'Press Ctrl+C to copy', 'multilify' ),
				)
			);
		}
	}

	/**
	 * Enqueue frontend assets (language switcher styles).
	 *
	 * The stylesheet is a kilobyte, so WordPress prints it inline in the head
	 * (wp_maybe_inline_styles reads the path set below) instead of costing every
	 * page a render-blocking request. It stays in the head because a switcher
	 * printed by a theme template has to be styled from its first paint. The
	 * script only remembers a choice made in the switcher, so it is enqueued by
	 * the switcher itself and pages without one never load it.
	 */
	public function enqueue_frontend_assets() {
		// One language has no switcher to style, and nothing to remember.
		if ( count( $this->get_languages() ) < 2 ) {
			return;
		}

		wp_register_style( 'multilify', MULTILIFY_ASSETS_URL . 'css/multilify.css', array(), MULTILIFY_VERSION );
		wp_style_add_data( 'multilify', 'path', MULTILIFY_PLUGIN_DIR . 'assets/css/multilify.css' );
		wp_enqueue_style( 'multilify' );
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

				// A code becomes a top level URL prefix whose rewrite rules sit
				// above the core ones, so a code WordPress already answers to
				// would quietly take that address over.
				$conflict = $this->language_code_conflict( $new_lang['code'] );

				if ( '' !== $conflict ) {
					$error = $conflict;
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
			$this->request_rewrite_flush();
		}

		// The settings page reads completion per language, so a changed list
		// invalidates the figures it was holding.
		if ( '' === $error ) {
			wp_cache_delete( 'translation_progress', 'multilify' );
			wp_cache_delete( 'translatable_total', 'multilify' );
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
	 * Find what a language code would take the address of.
	 *
	 * The per-language rewrite rules are registered at the top, so /{code}/ wins
	 * over whatever WordPress served there before. Both cases are refused rather
	 * than warned about, because the loser goes quiet with no sign of why.
	 *
	 * @param string $code Language code being added.
	 * @return string Error key, or an empty string when the code is free.
	 */
	private function language_code_conflict( $code ) {
		global $wp_rewrite;

		$reserved = array( 'feed', 'page', 'embed', 'wp', 'index', 'tag', 'date', 'type', 'order', 'paged', 'cpage', 'rest' );

		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$reserved = array_merge(
				$reserved,
				array_filter(
					array(
						$wp_rewrite->author_base,
						$wp_rewrite->search_base,
						$wp_rewrite->comments_base,
						$wp_rewrite->pagination_base,
						$wp_rewrite->feed_base,
					)
				)
			);
		}

		if ( in_array( $code, $reserved, true ) ) {
			return 'reserved_code';
		}

		foreach ( $this->get_translatable_post_types() as $post_type ) {
			if ( get_page_by_path( $code, OBJECT, $post_type ) instanceof WP_Post ) {
				return 'code_in_use';
			}
		}

		return '';
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
	 *
	 * @param string       $post_type Post type of the screen being built. Unused; every
	 *                                translatable type is registered in one pass.
	 * @param WP_Post|null $post      Entry being edited, when there is one.
	 */
	public function add_translation_meta_boxes( $post_type = '', $post = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature set by the add_meta_boxes action.
		$post_types = $this->get_translatable_post_types();
		$languages  = $this->get_languages();
		$entry_id   = $post instanceof WP_Post ? $post->ID : 0;

		foreach ( $post_types as $type ) {
			foreach ( $languages as $language ) {
				// Name is optional; fall back to the code so the meta box title is never blank.
				$language_label = isset( $language['name'] ) ? trim( (string) $language['name'] ) : '';
				if ( '' === $language_label ) {
					$language_label = $language['code'];
				}

				$title = sprintf(
					/* translators: 1: language flag emoji, 2: language name. */
					__( '%1$s %2$s Translation', 'multilify' ),
					$language['flag'],
					$language_label
				);

				// A collapsed panel shows only its title, so which languages are
				// already done has to be readable from there. Plain text, because
				// the same title is also listed in the editor's own preferences
				// panel and in Screen Options, and those render it as text: any
				// markup here shows up as markup.
				if ( $entry_id && $this->has_translation( $entry_id, $language['code'] ) ) {
					$title .= ' · ' . esc_html__( 'translated', 'multilify' );
				}

				add_meta_box(
					'multilify_' . $language['code'],
					$title,
					array( $this, 'render_translation_meta_box' ),
					$type,
					'normal',
					'high',
					array( 'language' => $language )
				);
			}
		}
	}

	/**
	 * Whether an entry carries anything at all in a language.
	 *
	 * @param int    $post_id Entry to check.
	 * @param string $code    Language code.
	 * @return bool
	 */
	private function has_translation( $post_id, $code ) {
		foreach ( array( 'title', 'content', 'slug' ) as $field ) {
			if ( '' !== trim( (string) get_post_meta( $post_id, '_multilang_' . $field . '_' . $code, true ) ) ) {
				return true;
			}
		}

		return false;
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

			// The sitemap lists the entries that have text in a language.
			wp_cache_delete( 'sitemap_ids_' . $lang_code, 'multilify' );

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

				if ( '' !== $new_slug ) {
					$new_slug = $this->unique_translated_slug( $new_slug, $lang_code, $post_id );
				}

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
	 * Make a translated slug unique within its language.
	 *
	 * Two entries sharing one translated slug leave the second unreachable,
	 * because the lookup answers with the first row it finds. WordPress
	 * disambiguates a duplicate post slug the same way.
	 *
	 * Another entry's own name counts as taken too. An entry with no slug in a
	 * language answers under its own name there, and a translated slug wins
	 * over that, so an author could otherwise move somebody else's address.
	 *
	 * @param string $slug    Slug the editor asked for.
	 * @param string $lang    Language the slug belongs to.
	 * @param int    $post_id Entry being saved.
	 * @return string Slug no other entry uses in this language.
	 */
	private function unique_translated_slug( $slug, $lang, $post_id ) {
		global $wpdb;

		$like = $wpdb->esc_like( $slug ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$taken = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id <> %d AND meta_value LIKE %s",
				'_multilang_slug_' . $lang,
				$post_id,
				$like
			)
		);

		foreach ( $this->get_translatable_post_types() as $post_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_name FROM {$wpdb->posts} WHERE ID <> %d AND post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' ) AND post_name LIKE %s",
					$post_id,
					$post_type,
					$like
				)
			);

			$taken = array_merge( $taken, $names );
		}

		$unique = $slug;
		$suffix = 2;

		while ( in_array( $unique, $taken, true ) ) {
			$unique = $slug . '-' . $suffix;
			++$suffix;
		}

		return $unique;
	}

	/**
	 * Drop the cached routes of an entry whose status changed.
	 *
	 * The slug lookup only answers for published entries and caches misses for
	 * an hour, so an entry that is trashed, restored or published has to forget
	 * its routes or a persistent object cache keeps serving the old answer.
	 *
	 * @param string  $new_status Status the entry moved to.
	 * @param string  $old_status Status the entry came from.
	 * @param WP_Post $post       Entry being transitioned.
	 */
	public function forget_translated_routes( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status ) {
			return;
		}

		$this->forget_routes_for_post( $post );
	}

	/**
	 * Drop the cached ownership of both addresses when an entry is renamed.
	 *
	 * Whether a real entry already lives at an address is cached, and a rename
	 * changes that answer for two addresses at once: the one the entry leaves
	 * and the one it takes. Neither is a status change, so nothing else clears
	 * them. Against a persistent object cache the stale answer stands for an
	 * hour, and an entry whose own translated slug is the freed address returns
	 * a 404 for that hour.
	 *
	 * @param int     $post_id     Entry being updated. Unused; both posts carry it.
	 * @param WP_Post $post_after  Entry as it now is.
	 * @param WP_Post $post_before Entry as it was.
	 */
	public function forget_renamed_slug( $post_id, $post_after, $post_before ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature set by the post_updated action.
		if ( ! $post_after instanceof WP_Post || ! $post_before instanceof WP_Post ) {
			return;
		}

		if ( $post_after->post_name === $post_before->post_name ) {
			return;
		}

		foreach ( array( $post_before->post_name, $post_after->post_name ) as $slug ) {
			if ( '' !== (string) $slug ) {
				wp_cache_delete( $this->own_slug_cache_key( $slug ), 'multilify' );
			}
		}

		// The lookup rows of the entry's translated slugs carry its post_name
		// too, and a rename made through Quick Edit, the REST API or WP-CLI
		// never posts the meta boxes that would have cleared them.
		$this->forget_routes_for_post( $post_after );
	}

	/**
	 * Cache key holding whether an entry answers to an address under its own name.
	 *
	 * @param string $slug Slug the key is for.
	 * @return string Cache key.
	 */
	private function own_slug_cache_key( $slug ) {
		return 'multilify_own_slug_' . md5( (string) $slug );
	}

	/**
	 * Drop every cached route an entry answers to.
	 *
	 * @param int|WP_Post $post Entry, or its ID.
	 */
	public function forget_routes_for_post( $post ) {
		$post = get_post( $post );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( '' !== (string) $post->post_name ) {
			wp_cache_delete( $this->own_slug_cache_key( $post->post_name ), 'multilify' );
		}

		foreach ( $this->get_languages() as $language ) {
			$slug = (string) get_post_meta( $post->ID, '_multilang_slug_' . $language['code'], true );

			if ( '' !== $slug ) {
				wp_cache_delete( 'multilang_slug_' . md5( $language['code'] . '_' . $slug ), 'multilify' );
			}
		}

		// The settings page reports completion from these counts.
		wp_cache_delete( 'translation_progress', 'multilify' );
		wp_cache_delete( 'translatable_total', 'multilify' );
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
		// Nothing is about to be matched, so there is nothing to steer, and the
		// parse_request action that puts the address back would never fire.
		if ( ! $continue ) {
			return $continue;
		}

		$segments = $this->get_request_path_segments();
		$codes    = wp_list_pluck( $this->get_languages(), 'code' );
		$prefixed = ! empty( $segments[0] ) && in_array( $segments[0], $codes, true );
		$lang     = $prefixed ? $segments[0] : $this->get_default_language();

		if ( $prefixed ) {
			array_shift( $segments );
		}

		// Only a nested path needs this; a single segment is resolved later on
		// the request filter, and rewriting it here would fight the canonical
		// redirect and loop.
		if ( count( $segments ) < 2 ) {
			return $continue;
		}

		// Archives, feeds and the REST API are WordPress' own addresses. Every
		// segment of one cost a postmeta lookup on each request, a REST call
		// from the block editor included, and none of them can be an entry.
		if ( $this->is_core_route_segment( $segments[0] ) ) {
			return $continue;
		}

		$requested = implode( '/', array_map( 'sanitize_title', $segments ) );
		$path      = $this->translate_request_path( $lang, $requested, $prefixed );

		if ( '' === $path ) {
			return $continue;
		}

		// Rewrite what core is about to match, keeping the language prefix.
		// PATH_INFO takes precedence over REQUEST_URI, so both have to move.
		$translated = ( $prefixed ? '/' . $lang : '' ) . '/' . $path . '/';

		$this->original_request = array(
			'REQUEST_URI' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Kept verbatim to be restored; sanitising would corrupt the address.
			'PATH_INFO'   => isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : null, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- As above.
		);

		if ( ! empty( $_SERVER['PATH_INFO'] ) ) {
			$_SERVER['PATH_INFO'] = $translated;
		}

		$_SERVER['REQUEST_URI'] = $translated;

		return $continue;
	}

	/**
	 * Whether a first path segment opens an address WordPress itself serves.
	 *
	 * @param string $segment First segment of the request path, after any language prefix.
	 * @return bool
	 */
	private function is_core_route_segment( $segment ) {
		global $wp_rewrite;

		$segment = (string) $segment;

		// A year opens the date archives.
		if ( preg_match( '/^[0-9]{4}$/', $segment ) ) {
			return true;
		}

		$bases = array(
			rest_get_url_prefix(),
			get_option( 'category_base' ) ? get_option( 'category_base' ) : 'category',
			get_option( 'tag_base' ) ? get_option( 'tag_base' ) : 'tag',
			'type',
		);

		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$bases = array_merge(
				$bases,
				array(
					$wp_rewrite->author_base,
					$wp_rewrite->search_base,
					$wp_rewrite->comments_base,
					$wp_rewrite->pagination_base,
					$wp_rewrite->feed_base,
				)
			);
		}

		// Custom taxonomies and post types that are not translated keep their
		// own base, /genre/ or /product/, so that is never an entry either.
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			if ( ! empty( $taxonomy->rewrite['slug'] ) ) {
				$bases[] = $taxonomy->rewrite['slug'];
			}
		}

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( ! empty( $post_type->rewrite['slug'] ) && ! in_array( $post_type->name, $this->get_translatable_post_types(), true ) ) {
				$bases[] = $post_type->rewrite['slug'];
			}
		}

		// A base can carry its own slashes, "/topics" or "shop/category", and
		// only its first segment can match the first segment of a request.
		$first = array();

		foreach ( array_filter( $bases ) as $base ) {
			$parts   = explode( '/', trim( (string) $base, '/' ) );
			$first[] = $parts[0];
		}

		return in_array( $segment, $first, true );
	}

	/**
	 * Put the address the visitor asked for back, once matching is done.
	 *
	 * The rewrite above exists only to steer rule matching. Everything after it
	 * reads REQUEST_URI as the real address, and redirect_canonical() in
	 * particular compares it against the permalink it builds, which is the
	 * translated one. Leaving the rewritten value in place therefore sends a
	 * paged entry into a redirect loop with itself, and hands every other plugin
	 * an address the visitor never asked for.
	 */
	public function restore_request_uri() {
		if ( null === $this->original_request ) {
			return;
		}

		foreach ( $this->original_request as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );

				continue;
			}

			$_SERVER[ $key ] = $value;
		}

		$this->original_request = null;
	}

	/**
	 * Work out the real path a translated request should be matched against.
	 *
	 * @param string $lang      Language the path is written in.
	 * @param string $requested Requested path, without surrounding slashes.
	 * @param bool   $prefixed  Whether the address carried a language prefix.
	 * @return string Path to match instead, or an empty string to leave the request alone.
	 */
	private function translate_request_path( $lang, $requested, $prefixed ) {
		foreach ( $this->request_path_readings( $requested ) as $reading ) {
			list( $head, $tail ) = $reading;

			$resolved = $this->resolve_translated_path( $lang, $head );

			if ( '' === $resolved['path'] || $resolved['path'] === $head ) {
				continue;
			}

			if ( $resolved['entry'] ) {
				if ( ! is_post_type_hierarchical( $resolved['entry']->post_type ) ) {
					continue;
				}
			} elseif ( ! $this->path_names_a_page( $resolved['path'] ) ) {
				// Only ancestors were mapped back, so the result is a guess until
				// a real page answers to it.
				continue;
			}

			// Without a prefix the address belongs to the default language, so an
			// entry that answers to it under its own name keeps it.
			if ( ! $prefixed && $this->slug_belongs_to_an_entry( $resolved['slug'] ) ) {
				continue;
			}

			return $resolved['path'] . ( '' !== $tail ? '/' . $tail : '' );
		}

		return '';
	}

	/**
	 * The ways a request path can be read, most literal first.
	 *
	 * Pagination, feeds and embeds sit after the entry's own path, so they are
	 * set aside before it is mapped and put back afterward. Without that,
	 * /{lang}/parent/entry/page/2/ reads page as an ancestor segment.
	 *
	 * A bare trailing number is the harder case, because /{lang}/parent/entry/2/
	 * is both how WordPress addresses the second page of an entry and how a
	 * child page called 2 would be addressed. The whole path is therefore tried
	 * first, and the number is only read as pagination once nothing answers to
	 * the literal reading. Only a nested head qualifies: a flat one is already
	 * routed by its own rewrite rule and rewriting it here would send a page
	 * through the rule meant for posts.
	 *
	 * @param string $requested Requested path, without surrounding slashes.
	 * @return array List of head and tail pairs.
	 */
	private function request_path_readings( $requested ) {
		$readings = array( $this->split_request_tail( $requested ) );

		if ( preg_match( '#^(.+/.+)/([0-9]+)$#', $requested, $matches ) ) {
			$readings[] = array( $matches[1], $matches[2] );
		}

		return $readings;
	}

	/**
	 * Filter request to convert custom slugs to real post slugs
	 *
	 * @param array $query_vars Query variables for the current request.
	 * @return array Query variables with translated slugs resolved.
	 */
	public function filter_request( $query_vars ) {
		if ( ! isset( $query_vars['name'] ) && ! isset( $query_vars['pagename'] ) ) {
			return $query_vars;
		}

		// An address with no prefix belongs to the default language, which
		// carries translated slugs of its own like any other language.
		$prefixed = isset( $query_vars['lang'] );
		$lang     = $prefixed ? sanitize_key( $query_vars['lang'] ) : $this->get_default_language();

		$path = isset( $query_vars['name'] ) ? $query_vars['name'] : $query_vars['pagename'];

		// Public query vars arrive from the query string as they were sent, so
		// ?name[]= is an array here, and casting it raised a PHP warning.
		if ( ! is_scalar( $path ) ) {
			return $query_vars;
		}

		$path = trim( (string) $path, '/' );

		if ( '' === $path ) {
			return $query_vars;
		}

		$resolved = $this->resolve_translated_path( $lang, $path );
		$entry    = $resolved['entry'];

		if ( ! $entry ) {
			// A partially translated tree keeps an untranslated entry under
			// translated ancestors, so only the ancestors are mapped back.
			if ( isset( $query_vars['pagename'] ) && $resolved['path'] !== $path ) {
				$query_vars['pagename'] = $resolved['path'];
			}

			// The flat rule asks for a post by this name, so an untranslated
			// page or entry of another type under a prefix was a 404, against
			// the promise that an empty field shows the default language.
			if ( $prefixed && isset( $query_vars['name'] ) ) {
				$query_vars = $this->route_untranslated_entry( $query_vars, $resolved['path'] );
			}

			return $query_vars;
		}

		// An entry that answers to this address under its own name keeps it.
		if ( ! $prefixed && $this->slug_belongs_to_an_entry( $resolved['slug'] ) ) {
			return $query_vars;
		}

		if ( is_post_type_hierarchical( $entry->post_type ) ) {
			$query_vars['pagename'] = $resolved['path'];
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
	 * Point a single-segment request with no translation at the entry it names.
	 *
	 * Pages are tried first, which is the order WordPress resolves an
	 * unprefixed address in when a post and a page share a name.
	 *
	 * @param array  $query_vars Query variables for the current request.
	 * @param string $path       Requested slug, without surrounding slashes.
	 * @return array Query variables, aimed at the entry when one answers to the slug.
	 */
	private function route_untranslated_entry( $query_vars, $path ) {
		$types = $this->get_translatable_post_types();

		usort(
			$types,
			function ( $a, $b ) {
				return (int) is_post_type_hierarchical( $b ) - (int) is_post_type_hierarchical( $a );
			}
		);

		foreach ( $types as $type ) {
			// The rule already asks for a post by this name.
			if ( 'post' === $type ) {
				continue;
			}

			$entry = get_page_by_path( $path, OBJECT, $type );

			if ( ! $entry instanceof WP_Post || ! in_array( $entry->post_status, array( 'publish', 'private' ), true ) ) {
				continue;
			}

			if ( is_post_type_hierarchical( $type ) ) {
				$query_vars['pagename'] = $path;
				unset( $query_vars['name'] );
			}

			if ( 'page' !== $type ) {
				$query_vars['post_type'] = $type;
			}

			return $query_vars;
		}

		return $query_vars;
	}

	/**
	 * Map a path written in one language back to the path WordPress stores.
	 *
	 * The entry is resolved from its own translated slug when it has one, which
	 * is authoritative. A partially translated tree has no such anchor on its
	 * last segment, so every segment is mapped on its own and the ancestors that
	 * do carry a translation still resolve.
	 *
	 * @param string $lang Language the path is written in.
	 * @param string $path Requested path, without surrounding slashes.
	 * @return array Resolved path, the matched entry when there is one, and the last segment.
	 */
	private function resolve_translated_path( $lang, $path ) {
		$segments = array_values( array_filter( array_map( 'sanitize_title', explode( '/', trim( $path, '/' ) ) ) ) );
		$slug     = $segments ? end( $segments ) : '';
		$resolved = array(
			'path'  => implode( '/', $segments ),
			'entry' => null,
			'slug'  => $slug,
		);

		if ( '' === $slug ) {
			return $resolved;
		}

		$entry = $this->lookup_translated_slug( $lang, $slug );

		if ( $entry ) {
			$real_path = is_post_type_hierarchical( $entry->post_type )
				? (string) get_page_uri( $entry->ID )
				: (string) $entry->post_name;

			$resolved['entry'] = $entry;
			$resolved['path']  = '' !== $real_path ? $real_path : $resolved['path'];

			return $resolved;
		}

		// A single segment has no ancestry to map back.
		if ( count( $segments ) < 2 ) {
			return $resolved;
		}

		$mapped = array();
		$last   = count( $segments ) - 1;

		foreach ( $segments as $index => $segment ) {
			if ( $index === $last ) {
				$mapped[] = $segment;
				continue;
			}

			$ancestor = $this->lookup_translated_slug( $lang, $segment );
			$mapped[] = $ancestor ? $ancestor->post_name : $segment;
		}

		$resolved['path'] = implode( '/', $mapped );

		return $resolved;
	}

	/**
	 * Split a request path into the entry's own path and the tail WordPress adds.
	 *
	 * Pagination, feeds and embeds come after the entry, so they must not be
	 * mistaken for ancestry when a translated path is mapped back.
	 *
	 * @param string $path Path without surrounding slashes.
	 * @return array Entry path first, tail second, both without surrounding slashes.
	 */
	private function split_request_tail( $path ) {
		$tail = '(?:page|comment-page)/[0-9]+|feed(?:/(?:feed|rdf|rss|rss2|atom))?|embed|trackback';

		if ( preg_match( '#^(.+?)/(' . $tail . ')$#', $path, $matches ) ) {
			return array( $matches[1], $matches[2] );
		}

		return array( $path, '' );
	}

	/**
	 * Whether a real hierarchical entry lives at this path.
	 *
	 * @param string $path Path without surrounding slashes.
	 * @return bool
	 */
	private function path_names_a_page( $path ) {
		if ( '' === $path ) {
			return false;
		}

		foreach ( $this->get_translatable_post_types() as $post_type ) {
			if ( ! is_post_type_hierarchical( $post_type ) ) {
				continue;
			}

			if ( get_page_by_path( $path, OBJECT, $post_type ) instanceof WP_Post ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a published entry already answers to this slug under its own name.
	 *
	 * Only consulted for an address with no language prefix, so an entry never
	 * loses its own URL to another entry's translated slug.
	 *
	 * @param string $slug Slug taken from the request.
	 * @return bool
	 */
	private function slug_belongs_to_an_entry( $slug ) {
		global $wpdb;

		if ( '' === $slug ) {
			return false;
		}

		$cache_key = $this->own_slug_cache_key( $slug );
		$cached    = wp_cache_get( $cache_key, 'multilify' );

		if ( false === $cached ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$cached = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' LIMIT 1",
					$slug
				)
			);

			wp_cache_set( $cache_key, $cached, 'multilify', HOUR_IN_SECONDS );
		}

		return $cached > 0;
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
			//
			// Two things core gets right here and an earlier single rule did not.
			// A single entry paginates on page, not on paged, which is the
			// archive's variable and leaves a post querying past its own only
			// row. And a flat slug has to go through name: with verbose page
			// rules a pagename rule is validated against get_page_by_path() while
			// matching, so a post is rejected and the request falls through to a
			// 404. Only a nested path, which can only be hierarchical, uses
			// pagename.
			add_rewrite_rule(
				'^' . $lang_code . '/([^/]+)/page/([0-9]{1,})/?$',
				'index.php?name=$matches[1]&page=$matches[2]&lang=' . $lang_code,
				'top'
			);
			add_rewrite_rule(
				'^' . $lang_code . '/(.+)/page/([0-9]{1,})/?$',
				'index.php?pagename=$matches[1]&page=$matches[2]&lang=' . $lang_code,
				'top'
			);

			// Single entry feed: /{lang}/slug/feed/, split the same way.
			add_rewrite_rule(
				'^' . $lang_code . '/([^/]+)/feed/?$',
				'index.php?name=$matches[1]&feed=feed&lang=' . $lang_code,
				'top'
			);
			add_rewrite_rule(
				'^' . $lang_code . '/(.+)/feed/?$',
				'index.php?pagename=$matches[1]&feed=feed&lang=' . $lang_code,
				'top'
			);

			// Embeds, comment pages and trackbacks of an entry, split the same
			// way. Without these the catch-all below took the tail as part of
			// the path, and core then read /{lang}/entry/embed/ as an
			// attachment called entry: all three answered 404.
			$tails = array(
				'embed/?'                    => 'embed=true',
				'comment-page-([0-9]{1,})/?' => 'cpage=$matches[2]',
				'trackback/?'                => 'tb=1',
			);

			foreach ( $tails as $tail => $query ) {
				add_rewrite_rule(
					'^' . $lang_code . '/([^/]+)/' . $tail . '$',
					'index.php?name=$matches[1]&' . $query . '&lang=' . $lang_code,
					'top'
				);
				add_rewrite_rule(
					'^' . $lang_code . '/(.+?)/' . $tail . '$',
					'index.php?pagename=$matches[1]&' . $query . '&lang=' . $lang_code,
					'top'
				);
			}

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

			// /{lang}/slug/2/, which is the address WordPress itself considers
			// canonical for a paged entry and where it redirects /page/2/ to.
			// Both come after the rule above so a child page whose own slug is a
			// number still wins: that rule is validated while matching and only
			// falls through to these when no such page exists.
			add_rewrite_rule(
				'^' . $lang_code . '/([^/]+)/([0-9]{1,})/?$',
				'index.php?name=$matches[1]&page=$matches[2]&lang=' . $lang_code,
				'top'
			);
			add_rewrite_rule(
				'^' . $lang_code . '/(.+)/([0-9]{1,})/?$',
				'index.php?pagename=$matches[1]&page=$matches[2]&lang=' . $lang_code,
				'top'
			);
		}

		// Add lang query var.
		add_rewrite_tag( '%lang%', '([^&]+)' );
	}

	/**
	 * Ask for the rewrite rules to be rebuilt on the next request.
	 *
	 * The flag is an autoloaded option that stays in place at 0 once used.
	 * WordPress serves autoloaded options from memory, so checking it costs
	 * nothing, where the transient this replaced cost every request a query.
	 */
	public function request_rewrite_flush() {
		update_option( 'multilify_flush_rewrite_rules', 1, true );
	}

	/**
	 * Flush the rewrite rules when a change has asked for it.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_option( 'multilify_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			update_option( 'multilify_flush_rewrite_rules', 0, true );
		}
	}

	/**
	 * Bring a site up to the running version once, after an update.
	 *
	 * A plugin update never runs the activation hook, so new rewrite rules
	 * would wait for someone to save the permalinks. Runs on admin_init, so the
	 * index creation below happens in an admin's request, never a visitor's.
	 */
	public function maybe_upgrade() {
		if ( MULTILIFY_VERSION === get_option( 'multilify_version' ) ) {
			return;
		}

		// A failed attempt from an earlier version gets one more try.
		if ( 'failed' === get_option( 'multilify_db_indexes_created' ) ) {
			delete_option( 'multilify_db_indexes_created' );
		}

		$this->maybe_create_db_indexes();
		$this->request_rewrite_flush();

		// The rewrite flag replaced a transient; an old one would just expire.
		delete_transient( 'multilify_flush_rewrite_rules' );

		update_option( 'multilify_version', MULTILIFY_VERSION, true );
	}

	/**
	 * Create database indexes for better performance.
	 *
	 * Runs from activation and from maybe_upgrade(), once per version. A
	 * failure is recorded rather than retried: it used to run on every init
	 * and repeat a failing ALTER TABLE on every request of the site.
	 */
	public function maybe_create_db_indexes() {
		global $wpdb;

		// Check if indexes already created, or already tried and failed.
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

			update_option( 'multilify_db_indexes_created', $wpdb->last_error ? 'failed' : true );
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
		if ( is_admin() || ! $query->is_main_query() ) {
			return $query;
		}

		$lang = $query->get( 'lang' );

		if ( ! $lang ) {
			return $query;
		}

		// lang is a public query variable, so any value at all can arrive on any
		// address. Anything that is not a configured code is dropped rather than
		// carried into the document language and the Content-Language header.
		if ( ! in_array( $lang, wp_list_pluck( $this->get_languages(), 'code' ), true ) ) {
			$query->set( 'lang', '' );

			return $query;
		}

		$this->current_language = $lang;

		// Only a bare language home stands in for the front page. A language in
		// front of a search, an archive or a taxonomy is still that archive, and
		// forcing is_home there sends themes to the wrong template.
		$carried = array_diff( array_keys( (array) $query->query ), array( 'lang', 'paged', 'page', 'feed' ) );

		if ( empty( $carried ) ) {
			$query->is_home       = true;
			$query->is_front_page = true;
			$query->is_404        = false;
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
			return $this->format_title_like_core( $translated_title, $post_id );
		}

		return $title;
	}

	/**
	 * Mark a translated title the way WordPress marks the post's own one.
	 *
	 * WordPress prefixes a password-protected or private entry in get_the_title()
	 * before the the_title filter runs, so replacing the title dropped
	 * "Protected:" from every translation of such an entry.
	 *
	 * @param string $title   Translated title.
	 * @param int    $post_id Post the title belongs to.
	 * @return string Title carrying the same prefix the original would have.
	 */
	private function format_title_like_core( $title, $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return $title;
		}

		if ( ! empty( $post->post_password ) ) {
			/* translators: %s: Protected post title. */
			$prepend = __( 'Protected: %s' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress' own string, so the prefix reads the same as on the original title.

			/** This filter is documented in wp-includes/post-template.php */
			return sprintf( apply_filters( 'protected_title_format', $prepend, $post ), $title );
		}

		if ( 'private' === $post->post_status ) {
			/* translators: %s: Private post title. */
			$prepend = __( 'Private: %s' ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress' own string, as above.

			/** This filter is documented in wp-includes/post-template.php */
			return sprintf( apply_filters( 'private_title_format', $prepend, $post ), $title );
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

		// A protected entry hands this filter WordPress' password form in
		// place of its content. Returning the translation instead served the
		// full text in every other language without the password.
		if ( post_password_required( $post_id ) ) {
			return $content;
		}

		// Feeds are translated like pages: a /{lang}/feed/ item already
		// carried the translated title, and the source body under it made
		// every item half one language and half another.
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

		// Only a translated type has an address under a prefix. Rebuilt from
		// the bare slug, any other type lost its base, so a WooCommerce
		// product linked from a Turkish page went to a 404 at /tr/<slug>/.
		if ( ! in_array( $post->post_type, $this->get_translatable_post_types(), true ) ) {
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

		$languages    = $this->get_languages();
		$current_lang = $this->get_current_language();
		// Only a single entry has counterparts. Off one, get_the_ID() is
		// whichever post the loop holds, so the template tag on a blog index
		// or an archive linked every language to that post.
		$current_post_id = is_singular() ? get_queried_object_id() : null;

		// The script that remembers the choice is needed only where there is
		// a switcher to choose from. In the body, WordPress prints it in the footer.
		if ( ! wp_script_is( 'multilify', 'registered' ) ) {
			wp_register_script( 'multilify', MULTILIFY_ASSETS_URL . 'js/multilify.js', array(), MULTILIFY_VERSION, true );
		}

		wp_enqueue_script( 'multilify' );

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
						<?php echo $is_current ? ' aria-current="page"' : ''; ?>>
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
	 * Send a visitor at the site root to their language.
	 *
	 * Only the root redirects: a deep link or a prefixed address is always
	 * served as typed. A language picked from the switcher is kept in a cookie
	 * and wins over the browser's header, so a visitor who chose German lands
	 * on German again rather than on the default; one who chose the default
	 * language stays there. Logged-in users and crawlers are never redirected,
	 * so an editor can reach the root and a search engine indexes it.
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

		// Only the site root redirects; deep links are always honored as typed.
		if ( is_admin() || ! is_front_page() || is_paged() || is_feed() || is_robots() ) {
			return;
		}

		// A language-prefixed URL is already an explicit choice.
		$segments = $this->get_request_path_segments();
		$codes    = wp_list_pluck( $this->get_languages(), 'code' );

		if ( ! empty( $segments[0] ) && in_array( $segments[0], $codes, true ) ) {
			return;
		}

		if ( is_user_logged_in() || $this->is_crawler() ) {
			return;
		}

		// From here on the answer at the root depends on the visitor, so no
		// shared cache may keep one visitor's answer for the next.
		header( 'Vary: Accept-Language, Cookie', false );

		$chosen = isset( $_COOKIE['multilify_language'] ) ? sanitize_key( wp_unslash( $_COOKIE['multilify_language'] ) ) : '';
		$target = in_array( $chosen, $codes, true ) ? $chosen : '';

		if ( '' === $chosen ) {
			$target = $this->get_browser_preferred_language();
		}

		if ( '' === $target || $target === $this->get_default_language() ) {
			return;
		}

		nocache_headers();
		wp_safe_redirect( $this->get_language_url( $target ), 302 );
		exit;
	}

	/**
	 * Whether the request comes from a crawler or a link preview.
	 *
	 * Such clients send whatever Accept-Language they were built with, and a
	 * redirect would hide the root from them.
	 *
	 * @return bool
	 */
	private function is_crawler() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$is    = '' === $agent || (bool) preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|lighthouse|headless/i', $agent );

		/**
		 * Filter whether a request counts as a crawler, which browser language
		 * detection never redirects.
		 *
		 * @param bool   $is    Whether the user agent looks like a crawler.
		 * @param string $agent The request's user agent.
		 */
		return (bool) apply_filters( 'multilify_is_crawler', $is, $agent );
	}

	/**
	 * Send an entry's own name to the address its language gave it.
	 *
	 * A translated slug in the default language renames the entry's public
	 * address while post_name stays as its internal one. That is the same shape
	 * as a WordPress rename, and WordPress answers a rename with a permanent
	 * redirect, so this does too. Without it the entry answered on two addresses
	 * at once, and inconsistently: the paged form already redirected, because
	 * core's canonical check only covers a request carrying a page number.
	 *
	 * Like core's own old-slug redirect this keeps the path and drops the query
	 * string.
	 *
	 * A prefixed language gets the same treatment. Its entry was reachable
	 * under the post's own name as well as under its translated slug, so
	 * /tr/the-harbour/ and /tr/liman/ both answered, and so did a nested path
	 * written with an untranslated parent.
	 */
	public function redirect_to_translated_permalink() {
		// A trackback is a POST and must not be bounced. Everything else that
		// hangs off an entry, a feed, an embed, a page number, travels with it.
		if ( is_admin() || is_preview() || is_trackback() || ! is_singular() ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}

		$lang       = $this->get_current_language();
		$is_default = ( $lang === $this->get_default_language() );
		$custom     = (string) get_post_meta( $post->ID, '_multilang_slug_' . $lang, true );

		// The default language keeps WordPress' own address unless it was
		// given a slug of its own, and then there is nothing to redirect.
		if ( $is_default && '' === $custom ) {
			return;
		}

		$canonical = $this->build_translated_path_for( $post, $custom, $lang );

		if ( '' === $canonical ) {
			return;
		}

		$segments = array_values( array_filter( array_map( 'sanitize_title', $this->get_request_path_segments() ) ) );

		// The prefix is not part of the entry's path; it goes back on below.
		if ( ! $is_default && isset( $segments[0] ) && $segments[0] === $lang ) {
			array_shift( $segments );
		}

		$prefix     = $is_default ? '' : '/' . $lang;
		$requested  = implode( '/', $segments );
		$candidates = array( $this->split_request_tail( $requested ) );

		// /entry/2/ is how WordPress addresses a paged entry. Here, unlike on
		// the request filter, the entry is already known, so a trailing number
		// can be read as pagination without having to rule out a page named 2.
		if ( preg_match( '#^(.+)/([0-9]+)$#', $requested, $matches ) ) {
			$candidates[] = array( $matches[1], $matches[2] );
		}

		foreach ( $candidates as $candidate ) {
			$parts = explode( '/', $candidate[0] );

			// The split is the right one only when what is left names the entry.
			if ( end( $parts ) !== $post->post_name && end( $parts ) !== $custom ) {
				continue;
			}

			if ( $canonical === $candidate[0] ) {
				return;
			}

			$path = $canonical . ( '' !== $candidate[1] ? '/' . $candidate[1] : '' );

			wp_safe_redirect( home_url( $prefix . '/' . $path . '/' ), 301 );
			exit;
		}
	}

	/**
	 * Resolve the visitor's preferred language from the Accept-Language header.
	 *
	 * Quality values are honored, and a regional tag such as de-AT matches the
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

			// q=0 means "not this language", not "this one last".
			if ( $quality <= 0 ) {
				continue;
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
	 * Switch WordPress to the locale of the language being viewed.
	 *
	 * Themes, plugins and WordPress itself then print their own strings, the
	 * comment form, dates, the admin bar, in the language of the address. The
	 * locale used to be left alone unless something hooked multilify_locale,
	 * so a German page carried the site language's "Leave a Reply".
	 *
	 * switch_to_locale() rather than a locale filter: WordPress loaded its own
	 * translations before the request was parsed, and only a switch reloads them.
	 */
	public function switch_to_language_locale() {
		if ( is_admin() ) {
			return;
		}

		$lang = $this->get_current_language();

		if ( '' === $lang || $lang === $this->get_default_language() ) {
			return;
		}

		$site_locale = get_locale();

		/**
		 * Filter the WordPress locale used for a Multilify language.
		 *
		 * Defaults to an installed WordPress language matching the code (de
		 * becomes de_DE), and to the site locale when none is installed. Return
		 * $site_locale to keep a language on the site locale.
		 *
		 * @param string $locale      Locale for the language.
		 * @param string $lang        Active Multilify language code.
		 * @param string $site_locale Locale the site runs in.
		 */
		$locale = (string) apply_filters( 'multilify_locale', $this->installed_locale_for( $lang, $site_locale ), $lang, $site_locale );

		if ( '' !== $locale && $locale !== $site_locale ) {
			switch_to_locale( $locale );
		}
	}

	/**
	 * Find the installed WordPress language a code stands for.
	 *
	 * @param string $lang        Multilify language code.
	 * @param string $site_locale Locale to fall back to.
	 * @return string Locale such as de_DE.
	 */
	private function installed_locale_for( $lang, $site_locale ) {
		// strtr, not strtoupper: the C library maps i to İ under a Turkish locale.
		$preferred = $lang . '_' . strtr( $lang, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' );
		$installed = get_available_languages();

		if ( in_array( $preferred, $installed, true ) ) {
			return $preferred;
		}

		foreach ( $installed as $candidate ) {
			if ( $candidate === $lang || 0 === strpos( $candidate, $lang . '_' ) ) {
				return $candidate;
			}
		}

		// English needs no language pack: it is the locale WordPress is written in.
		return 'en' === $lang ? 'en_US' : $site_locale;
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

		$post_id = is_singular() ? get_queried_object_id() : null;

		// Only an entry and the language homes have a counterpart in every
		// language. An archive or a second page of posts has none the plugin
		// can address, and declaring the homes as its alternates told search
		// engines that a category page was the home page in other languages.
		if ( ! $post_id && ( ! is_front_page() || is_paged() ) ) {
			return;
		}

		$default = $this->get_default_language();

		foreach ( $languages as $language ) {
			// A language with no text of its own for this entry only falls
			// back to the default one, so it has no version to declare.
			if ( $post_id && $language['code'] !== $default && ! $this->has_translated_text( $post_id, $language['code'] ) ) {
				continue;
			}

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
	 * Whether an entry carries a title or content of its own in a language.
	 *
	 * A translated slug alone does not count: the page under it still shows
	 * the default language's text.
	 *
	 * @param int    $post_id Entry to check.
	 * @param string $code    Language code.
	 * @return bool
	 */
	private function has_translated_text( $post_id, $code ) {
		foreach ( array( 'title', 'content' ) as $field ) {
			if ( '' !== trim( (string) get_post_meta( $post_id, '_multilang_' . $field . '_' . $code, true ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Point an untranslated view's canonical at the default language.
	 *
	 * /de/entry/ of an entry with no German text serves the default text under
	 * a second address. It used to name itself as canonical, which offered
	 * search engines the same text under every prefix.
	 *
	 * @param string  $canonical_url Canonical URL WordPress built.
	 * @param WP_Post $post          Entry the URL belongs to.
	 * @return string Canonical URL.
	 */
	public function filter_canonical_url( $canonical_url, $post ) {
		if ( is_admin() || ! $post instanceof WP_Post ) {
			return $canonical_url;
		}

		$lang    = $this->get_current_language();
		$default = $this->get_default_language();

		if ( $lang === $default || $this->has_translated_text( $post->ID, $lang ) ) {
			return $canonical_url;
		}

		return $this->get_language_url( $default, $post->ID );
	}

	/**
	 * Resolve a translated address for the oEmbed endpoint.
	 *
	 * Core maps an embed URL to a post with url_to_postid(), which only knows
	 * WordPress' own addresses, so a translated entry advertised an oEmbed link
	 * that answered "invalid URL".
	 *
	 * @param int    $post_id Post ID core found, 0 when none.
	 * @param string $url     URL being embedded.
	 * @return int Post ID.
	 */
	public function resolve_oembed_post_id( $post_id, $url ) {
		if ( $post_id ) {
			return $post_id;
		}

		$path      = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );

		if ( '' !== $home_path && 0 === strpos( $path . '/', $home_path . '/' ) ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		}

		$segments = array_values( array_filter( array_map( 'sanitize_title', explode( '/', $path ) ) ) );
		$codes    = wp_list_pluck( $this->get_languages(), 'code' );
		$lang     = ( $segments && in_array( $segments[0], $codes, true ) ) ? array_shift( $segments ) : $this->get_default_language();

		if ( ! $segments ) {
			return $post_id;
		}

		list( $head ) = $this->split_request_tail( implode( '/', $segments ) );
		$resolved     = $this->resolve_translated_path( $lang, $head );

		if ( $resolved['entry'] ) {
			return (int) $resolved['entry']->ID;
		}

		// An entry with no slug in that language answers under its own path.
		$found = url_to_postid( home_url( '/' . $resolved['path'] . '/' ) );

		return $found ? $found : $post_id;
	}

	/**
	 * List the translated addresses in WordPress' sitemaps.
	 *
	 * Core's providers list each entry once, at its default address, so a
	 * translation could only be found through the hreflang tags and the
	 * switcher.
	 */
	public function register_sitemap_provider() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) || count( $this->get_languages() ) < 2 ) {
			return;
		}

		require_once MULTILIFY_INCLUDES_DIR . 'class-multilify-sitemaps.php';

		wp_register_sitemap_provider( 'multilify', new Multilify_Sitemaps( $this ) );
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
	 * Translate the title wp_title() and single_post_title() print.
	 *
	 * Classic themes build the <title> from single_post_title. The wp_title
	 * filter this replaced looked for the title through get_the_title(), which
	 * the the_title filter had already translated, so it never found anything
	 * to replace and those themes kept the source title.
	 *
	 * @param string       $title Title of the entry being viewed.
	 * @param WP_Post|null $post  Entry being viewed.
	 * @return string Translated title when there is one.
	 */
	public function filter_single_post_title( $title, $post = null ) {
		if ( is_admin() || ! $post instanceof WP_Post ) {
			return $title;
		}

		$lang             = $this->get_current_language();
		$translated_title = get_post_meta( $post->ID, '_multilang_title_' . $lang, true );

		/** This filter is documented in includes/class-multilify.php */
		$translated_title = apply_filters( 'multilify_translated_title', $translated_title, $title, $post->ID, $lang );

		return ! empty( $translated_title ) ? $translated_title : $title;
	}

	/**
	 * Search the language being viewed as well as the post itself.
	 *
	 * Core searches post_title and post_content, so /tr/?s=liman found nothing
	 * while an entry's Turkish title read "Ekimde liman". Entries whose title
	 * or content in this language holds every search term are added to the
	 * matches. Excluded terms (-word) are left to core.
	 *
	 * @param string   $search Search SQL WordPress built.
	 * @param WP_Query $query  Query being run.
	 * @return string Search SQL that also matches translations.
	 */
	public function filter_search( $search, $query ) {
		global $wpdb;

		if ( '' === $search || is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! $query->is_search() ) {
			return $search;
		}

		$terms = array_filter(
			(array) $query->get( 'search_terms' ),
			function ( $term ) {
				return is_string( $term ) && '' !== $term && '-' !== $term[0];
			}
		);

		if ( ! $terms ) {
			return $search;
		}

		$lang = $this->get_current_language();
		$ids  = null;

		foreach ( $terms as $term ) {
			// A search answers once per request, so there is nothing to cache.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$matched = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s ) AND meta_value LIKE %s",
					'_multilang_title_' . $lang,
					'_multilang_content_' . $lang,
					'%' . $wpdb->esc_like( $term ) . '%'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			$ids = null === $ids ? $matched : array_intersect( $ids, $matched );

			if ( ! $ids ) {
				return $search;
			}
		}

		// Core wraps its clause as " AND ( ... ) ", so the IDs go in first and
		// an OR inside the parentheses keeps every other condition intact.
		$opening = strpos( $search, ' AND (' );

		if ( false === $opening ) {
			return $search;
		}

		$ids = implode( ',', array_slice( array_map( 'absint', $ids ), 0, 1000 ) );

		return substr_replace( $search, " AND ({$wpdb->posts}.ID IN ({$ids}) OR ", $opening, strlen( ' AND (' ) );
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
