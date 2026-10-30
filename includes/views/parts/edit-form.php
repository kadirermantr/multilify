<?php
/**
 * Dialog that renames a language or changes its flag, opened from its Edit button.
 *
 * Expects $languages, $multilify_language, $multilify_code, $multilify_name, $multilify_flags and $multilify_nonce.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Flags other languages show: two languages with one flag look the same in the switcher.
$multilify_taken = array();

foreach ( $languages as $multilify_other ) {
	if ( $multilify_other['code'] !== $multilify_code && '' !== (string) $multilify_other['flag'] ) {
		$multilify_taken[ $multilify_other['flag'] ] = '' !== trim( (string) $multilify_other['name'] ) ? $multilify_other['name'] : $multilify_other['code'];
	}
}
?>

<dialog class="mfy-add mfy-edit" id="multilify-edit-<?php echo esc_attr( $multilify_code ); ?>"
		aria-labelledby="multilify-edit-title-<?php echo esc_attr( $multilify_code ); ?>" data-multilify-edit>
	<form method="post" class="multilify-editform">
		<?php echo $multilify_nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
		<input type="hidden" name="multilify_action" value="edit_language">
		<input type="hidden" name="lang_code" value="<?php echo esc_attr( $multilify_code ); ?>">

		<header class="mfy-add__head">
			<h2 id="multilify-edit-title-<?php echo esc_attr( $multilify_code ); ?>">
				<?php echo esc_html( sprintf( /* translators: %s: language name. */ __( 'Edit %s', 'multilify' ), $multilify_name ) ); ?>
			</h2>
			<button type="button" class="mfy-iconbtn" data-multilify-edit-close aria-label="<?php esc_attr_e( 'Cancel', 'multilify' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</header>

		<?php // Laid out like the add dialog; the flag grid under the fields keeps the flag field in step. ?>
		<div class="mfy-add__body" data-multilify-flagpicker>
			<div class="mfy-add__fields">
				<div class="mfy-field">
					<label class="mfy-label" for="multilify-edit-code-<?php echo esc_attr( $multilify_code ); ?>"><?php esc_html_e( 'Language code', 'multilify' ); ?></label>
					<input type="text" class="mfy-input mfy-input--mono"
							id="multilify-edit-code-<?php echo esc_attr( $multilify_code ); ?>"
							value="<?php echo esc_attr( $multilify_code ); ?>"
							aria-describedby="multilify-edit-code-hint-<?php echo esc_attr( $multilify_code ); ?>"
							disabled>
					<p class="mfy-hint" id="multilify-edit-code-hint-<?php echo esc_attr( $multilify_code ); ?>"><?php esc_html_e( 'The code cannot be changed: your translations are stored under it.', 'multilify' ); ?></p>
				</div>
				<div class="mfy-field">
					<label class="mfy-label" for="multilify-edit-name-<?php echo esc_attr( $multilify_code ); ?>"><?php esc_html_e( 'Language name', 'multilify' ); ?></label>
					<input type="text" class="mfy-input"
							id="multilify-edit-name-<?php echo esc_attr( $multilify_code ); ?>"
							name="lang_name"
							value="<?php echo esc_attr( $multilify_language['name'] ); ?>">
				</div>
				<div class="mfy-field mfy-field--flag">
					<label class="mfy-label" for="multilify-edit-flag-<?php echo esc_attr( $multilify_code ); ?>"><?php esc_html_e( 'Flag', 'multilify' ); ?></label>
					<input type="text" class="mfy-input mfy-input--flag"
							id="multilify-edit-flag-<?php echo esc_attr( $multilify_code ); ?>"
							name="lang_flag"
							value="<?php echo esc_attr( $multilify_language['flag'] ); ?>"
							maxlength="10"
							aria-describedby="multilify-edit-flag-hint-<?php echo esc_attr( $multilify_code ); ?>"
							data-multilify-flaginput>
				</div>
				<p class="mfy-hint mfy-add__flaghint" id="multilify-edit-flag-hint-<?php echo esc_attr( $multilify_code ); ?>"><?php esc_html_e( 'Pick a flag, or type any emoji. Screen readers skip it.', 'multilify' ); ?></p>
			</div>

			<div class="multilify-flagpicker__grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Flag', 'multilify' ); ?>">
				<?php // The emoji is the option's name: a screen reader reads a flag as its country, where the key it was filed under named a language. ?>
				<?php foreach ( $multilify_flags as $multilify_flag_emoji ) : ?>
					<button type="button"
							class="multilify-flagpicker__option<?php echo ( $multilify_flag_emoji === $multilify_language['flag'] ) ? ' is-selected' : ''; ?>"
							role="radio"
							aria-checked="<?php echo ( $multilify_flag_emoji === $multilify_language['flag'] ) ? 'true' : 'false'; ?>"
							data-flag="<?php echo esc_attr( $multilify_flag_emoji ); ?>"
							<?php if ( isset( $multilify_taken[ $multilify_flag_emoji ] ) ) : ?>
								disabled
								title="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Used by %s', 'multilify' ), $multilify_taken[ $multilify_flag_emoji ] ) ); ?>"
							<?php endif; ?>>
						<span><?php echo esc_html( $multilify_flag_emoji ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<footer class="mfy-add__foot">
			<button type="button" class="button" data-multilify-edit-close><?php esc_html_e( 'Cancel', 'multilify' ); ?></button>
			<?php // Enabled by the script once the name or the flag differs from what is saved. ?>
			<button type="submit" class="button button-primary" data-multilify-edit-save disabled><?php esc_html_e( 'Save language', 'multilify' ); ?></button>
		</footer>
	</form>
</dialog>
