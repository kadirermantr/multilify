<?php
/**
 * Confirmation before a language is deleted.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="multilify-dialog" id="multilify-confirm" role="dialog" aria-modal="true"
		aria-labelledby="multilify-confirm-title" aria-describedby="multilify-confirm-body" hidden>
	<div class="multilify-dialog__panel">
		<h2 class="multilify-dialog__title" id="multilify-confirm-title">
			<?php esc_html_e( 'Delete this language?', 'multilify' ); ?>
		</h2>
		<p class="multilify-dialog__body" id="multilify-confirm-body">
			<?php esc_html_e( 'Its translations stay in the database, so adding the same code again brings them back.', 'multilify' ); ?>
		</p>
		<div class="multilify-dialog__actions">
			<button type="button" class="button" data-multilify-dialog-cancel>
				<?php esc_html_e( 'Keep language', 'multilify' ); ?>
			</button>
			<button type="button" class="button button-primary multilify-dialog__delete" data-multilify-dialog-confirm>
				<?php esc_html_e( 'Delete language', 'multilify' ); ?>
			</button>
		</div>
	</div>
</div>
