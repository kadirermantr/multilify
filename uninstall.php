<?php
/**
 * Uninstall Multilify.
 *
 * Removes every option, translation meta row and index the plugin created.
 * Only runs when the user deletes the plugin from the WordPress admin.
 *
 * @package Multilify
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all Multilify data from a single site.
 */
function multilify_uninstall_site() {
	global $wpdb;

	// Translation meta is keyed by language, so match the shared prefix.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_multilang_' ) . '%'
		)
	);

	// Drop the lookup index added by maybe_create_db_indexes().
	$index_exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS
			WHERE table_schema = DATABASE()
			AND table_name = %s
			AND index_name = %s",
			$wpdb->postmeta,
			'multilify_slug_lookup'
		)
	);

	if ( $index_exists ) {
		// Index name is a fixed literal, so there is nothing to interpolate unsafely.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "ALTER TABLE {$wpdb->postmeta} DROP INDEX multilify_slug_lookup" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

	delete_option( 'multilify_languages' );
	delete_option( 'multilify_default_language' );
	delete_option( 'multilify_db_indexes_created' );
	delete_transient( 'multilify_flush_rewrite_rules' );
}

if ( is_multisite() ) {
	$multilify_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $multilify_site_ids as $multilify_site_id ) {
		switch_to_blog( $multilify_site_id );
		multilify_uninstall_site();
		restore_current_blog();
	}
} else {
	multilify_uninstall_site();
}
