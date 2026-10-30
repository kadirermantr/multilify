<?php
/**
 * Dialog that adds a language: pick one from the catalog, or type it in.
 *
 * Expects $catalog, $languages, $multilify_nonce and $multilify_host.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$multilify_used    = wp_list_pluck( $languages, 'code' );
$multilify_flagged = wp_list_pluck( $languages, 'flag' );
$multilify_offered = array_filter(
	array_diff_key( $catalog, array_flip( $multilify_used ) ),
	function ( $entry ) use ( $multilify_flagged ) {
		return ! in_array( $entry['flag'], $multilify_flagged, true );
	}
);
$multilify_popular = array_slice( $multilify_offered, 0, 8, true );
?>

<dialog class="mfy-add" id="multilify-add" aria-labelledby="multilify-add-title" data-multilify-add>
	<form method="post">
		<?php echo $multilify_nonce; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts. ?>
		<input type="hidden" name="multilify_action" value="add_language">

		<header class="mfy-add__head">
			<h2 id="multilify-add-title"><?php esc_html_e( 'Add a language', 'multilify' ); ?></h2>
			<button type="button" class="mfy-iconbtn" data-multilify-add-close aria-label="<?php esc_attr_e( 'Cancel', 'multilify' ); ?>">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
			</button>
		</header>

		<div class="mfy-add__body">
			<?php if ( ! empty( $multilify_popular ) ) : ?>
				<div class="mfy-tiles" role="group" aria-label="<?php esc_attr_e( 'Languages', 'multilify' ); ?>">
					<?php foreach ( $multilify_popular as $multilify_code => $multilify_entry ) : ?>
						<button type="button" class="mfy-tile"
								data-code="<?php echo esc_attr( $multilify_code ); ?>"
								data-name="<?php echo esc_attr( $multilify_entry['name'] ); ?>"
								data-flag="<?php echo esc_attr( $multilify_entry['flag'] ); ?>">
							<span class="mfy-tile__flag" aria-hidden="true"><?php echo esc_html( $multilify_entry['flag'] ); ?></span>
							<span><?php echo esc_html( $multilify_entry['name'] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="mfy-search" id="multilify-add-search">
				<label class="mfy-label" for="multilify-add-filter"><?php esc_html_e( 'Language', 'multilify' ); ?></label>
				<input type="search" id="multilify-add-filter" class="mfy-input" autocomplete="off"
						placeholder="<?php esc_attr_e( 'Search languages', 'multilify' ); ?>" data-multilify-add-filter>
				<?php // Shown only while a search is typed; the tiles above cover the common picks. ?>
				<div class="mfy-search__list" data-multilify-add-list hidden>
					<?php foreach ( $multilify_offered as $multilify_code => $multilify_entry ) : ?>
						<button type="button" class="mfy-option"
								data-code="<?php echo esc_attr( $multilify_code ); ?>"
								data-name="<?php echo esc_attr( $multilify_entry['name'] ); ?>"
								data-english="<?php echo esc_attr( isset( $multilify_entry['english'] ) ? $multilify_entry['english'] : '' ); ?>"
								data-flag="<?php echo esc_attr( $multilify_entry['flag'] ); ?>">
							<span aria-hidden="true"><?php echo esc_html( $multilify_entry['flag'] ); ?></span>
							<span class="mfy-option__name"><?php echo esc_html( $multilify_entry['name'] ); ?></span>
							<code><?php echo esc_html( $multilify_code ); ?></code>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="mfy-add__fields">
				<div class="mfy-field">
					<label class="mfy-label" for="lang_code"><?php esc_html_e( 'Language code', 'multilify' ); ?></label>
					<input type="text" id="lang_code" name="lang_code" class="mfy-input mfy-input--mono" required maxlength="5" pattern="[a-z]{2,5}" aria-describedby="multilify-add-code-hint">
					<?php // The address is part of the hint from the start, so the dialog does not grow when a code is typed. ?>
					<p class="mfy-hint" id="multilify-add-code-hint">
						<?php
						printf(
							/* translators: %s: address of the language's home page, such as example.com/it/. */
							esc_html__( 'Two to five lowercase letters, used in the address: %s', 'multilify' ),
							'<code class="mfy-addr">' . esc_html( $multilify_host ) . '/<span data-multilify-add-code>&hellip;</span>/</code>'
						);
						?>
					</p>
				</div>
				<div class="mfy-field">
					<label class="mfy-label" for="lang_name"><?php esc_html_e( 'Language name', 'multilify' ); ?></label>
					<input type="text" id="lang_name" name="lang_name" class="mfy-input">
				</div>
				<div class="mfy-field mfy-field--flag">
					<label class="mfy-label" for="lang_flag"><?php esc_html_e( 'Flag', 'multilify' ); ?></label>
					<input type="text" id="lang_flag" name="lang_flag" class="mfy-input mfy-input--flag" required maxlength="10" aria-describedby="multilify-add-flag-hint">
				</div>
				<?php // Under both fields, so the narrow flag column does not wrap it over three lines. ?>
				<p class="mfy-hint mfy-add__flaghint" id="multilify-add-flag-hint"><?php esc_html_e( 'Pick a flag, or type any emoji. Screen readers skip it.', 'multilify' ); ?></p>
			</div>
		</div>

		<footer class="mfy-add__foot">
			<button type="button" class="button" data-multilify-add-close><?php esc_html_e( 'Cancel', 'multilify' ); ?></button>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Add language', 'multilify' ); ?></button>
		</footer>
	</form>
</dialog>
