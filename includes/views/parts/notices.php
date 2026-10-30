<?php
/**
 * Notices after a language action: saved, or why it did not go through.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

	<?php
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['multilify_updated'] ) && '1' === $_GET['multilify_updated'] ) :
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Changes saved.', 'multilify' ); ?></p>
	</div>
<?php endif; ?>

<?php
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['multilify_error'] ) ) :
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$multilify_error          = sanitize_key( wp_unslash( $_GET['multilify_error'] ) );
	$multilify_error_messages = array(
		'duplicate_code' => __( 'That language code is already in use. Pick a code no other language uses.', 'multilify' ),
		'invalid_code'   => __( 'Use 2 to 5 lowercase letters for a language code, such as tr, en or de.', 'multilify' ),
		'not_found'      => __( 'That language is no longer in the list. Reload the page and try again.', 'multilify' ),
		'delete_default' => __( 'Set another language as the default before deleting this one.', 'multilify' ),
		'reserved_code'  => __( 'WordPress already serves that address, so a language cannot take it. Pick another code.', 'multilify' ),
		'code_in_use'    => __( 'A post or page already lives at that address. Change its slug first, or pick another code.', 'multilify' ),
		'duplicate_flag' => __( 'Another language already uses that flag. Pick a different one.', 'multilify' ),
	);
	$multilify_error_text     = isset( $multilify_error_messages[ $multilify_error ] )
		? $multilify_error_messages[ $multilify_error ]
		: __( 'That did not go through. Try again.', 'multilify' );
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( $multilify_error_text ); ?></p>
	</div>
<?php endif; ?>
