<?php
/**
 * The less frequent actions of a language, behind a "more" button.
 *
 * Expects $multilify_code, $multilify_name and $multilify_nonce. Not drawn for
 * the default language, which can be neither deleted nor made default again.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<button type="button" class="mfy-iconbtn" popovertarget="mfy-menu-<?php echo esc_attr( $multilify_code ); ?>"
		aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'More actions for %s', 'multilify' ), $multilify_name ) ); ?>">
	<span class="dashicons dashicons-ellipsis" aria-hidden="true"></span>
</button>
<div class="mfy-menu" id="mfy-menu-<?php echo esc_attr( $multilify_code ); ?>" popover>
	<form method="post">
		<?php echo $multilify_nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
		<input type="hidden" name="multilify_action" value="set_default">
		<input type="hidden" name="default_language" value="<?php echo esc_attr( $multilify_code ); ?>">
		<button type="submit" class="mfy-menu__item"><?php esc_html_e( 'Make default', 'multilify' ); ?></button>
	</form>
	<form method="post" data-multilify-confirm data-language="<?php echo esc_attr( $multilify_name ); ?>">
		<?php echo $multilify_nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
		<input type="hidden" name="multilify_action" value="delete_language">
		<input type="hidden" name="lang_code" value="<?php echo esc_attr( $multilify_code ); ?>">
		<button type="submit" class="mfy-menu__item mfy-menu__item--danger"><?php esc_html_e( 'Delete', 'multilify' ); ?></button>
	</form>
</div>
