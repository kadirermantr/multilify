<?php
/**
 * Sitemap provider for translated addresses.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * One sitemap per language other than the default: its home, then every
 * published entry that has a title or content of its own in that language.
 *
 * Entries that only fall back to the default text are left out, the same
 * rule the hreflang tags follow, so a sitemap never offers a search engine
 * the default text under a second address.
 */
class Multilify_Sitemaps extends WP_Sitemaps_Provider {

	/**
	 * Plugin instance the languages and addresses come from.
	 *
	 * @var Multilify
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Multilify $plugin Plugin instance.
	 */
	public function __construct( Multilify $plugin ) {
		$this->name        = 'multilify';
		$this->object_type = 'multilify';
		$this->plugin      = $plugin;
	}

	/**
	 * Languages that get a sitemap, keyed by code.
	 *
	 * @return array Map of language code to subtype object.
	 */
	public function get_object_subtypes() {
		$subtypes = array();

		foreach ( $this->plugin->get_languages() as $language ) {
			if ( $language['code'] === $this->plugin->get_default_language() ) {
				continue;
			}

			$subtypes[ $language['code'] ] = (object) array( 'name' => $language['code'] );
		}

		return $subtypes;
	}

	/**
	 * Addresses on one page of a language's sitemap.
	 *
	 * @param int    $page_num       Page of the sitemap, from 1.
	 * @param string $object_subtype Language code.
	 * @return array List of entries, each with a 'loc' key.
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( ! isset( $this->get_object_subtypes()[ $object_subtype ] ) ) {
			return array();
		}

		$page_num = max( 1, (int) $page_num );
		$per_page = wp_sitemaps_get_max_urls( $this->object_type );
		$urls     = array();

		// The language home opens the first page, so that page holds one entry
		// fewer and every later page starts one entry earlier.
		if ( 1 === $page_num ) {
			$urls[] = array( 'loc' => $this->plugin->get_language_url( $object_subtype ) );
		}

		$offset = 1 === $page_num ? 0 : ( $page_num - 1 ) * $per_page - 1;
		$limit  = 1 === $page_num ? $per_page - 1 : $per_page;

		foreach ( $this->entry_ids( $object_subtype, $offset, $limit ) as $post_id ) {
			$urls[] = array( 'loc' => $this->plugin->get_language_url( $object_subtype, (int) $post_id ) );
		}

		return $urls;
	}

	/**
	 * Number of pages a language's sitemap runs to.
	 *
	 * @param string $object_subtype Language code.
	 * @return int Page count.
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		if ( ! isset( $this->get_object_subtypes()[ $object_subtype ] ) ) {
			return 0;
		}

		$total = count( $this->entry_ids( $object_subtype, 0, PHP_INT_MAX ) ) + 1;

		return (int) ceil( $total / wp_sitemaps_get_max_urls( $this->object_type ) );
	}

	/**
	 * Published entries with text in a language, oldest first.
	 *
	 * @param string $lang   Language code.
	 * @param int    $offset Rows to skip.
	 * @param int    $limit  Rows to return.
	 * @return array List of post IDs.
	 */
	private function entry_ids( $lang, $offset, $limit ) {
		global $wpdb;

		$cache_key = 'sitemap_ids_' . $lang;
		$ids       = wp_cache_get( $cache_key, 'multilify' );

		if ( ! is_array( $ids ) ) {
			$ids = array();

			// Only the types this plugin translates have an address under a
			// prefix. One query per type keeps every value a prepared one.
			foreach ( $this->plugin->get_translatable_post_types() as $post_type ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
				$found = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT DISTINCT p.ID
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
						WHERE pm.meta_key IN ( %s, %s )
						AND pm.meta_value <> ''
						AND p.post_status = 'publish'
						AND p.post_password = ''
						AND p.post_type = %s",
						'_multilang_title_' . $lang,
						'_multilang_content_' . $lang,
						$post_type
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

				$ids = array_merge( $ids, array_map( 'intval', $found ) );
			}

			sort( $ids );

			wp_cache_set( $cache_key, $ids, 'multilify', HOUR_IN_SECONDS );
		}

		return array_slice( $ids, $offset, $limit );
	}
}
