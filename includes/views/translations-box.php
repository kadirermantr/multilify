<?php
/**
 * Translations box: one tab per language across the top, the fields of one at a time.
 *
 * @package Multilify
 *
 * @var WP_Post $post      Entry being edited.
 * @var array   $languages Configured languages.
 * @var string  $default   Default language code.
 * @var string  $active    Code of the language shown first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="mfy-tr" data-multilify-tabs>
	<div class="mfy-tr__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Translations', 'multilify' ); ?>">
		<?php
		foreach ( $languages as $multilify_language ) :
			$multilify_code   = $multilify_language['code'];
			$multilify_label  = '' !== trim( (string) $multilify_language['name'] ) ? $multilify_language['name'] : $multilify_code;
			$multilify_is_on  = ( $multilify_code === $active );
			$multilify_is_def = ( $multilify_code === $default );
			$multilify_done   = ! $multilify_is_def && $this->has_translation( $post->ID, $multilify_code );
			?>
			<button type="button" role="tab" class="mfy-tr__tab"
					id="mfy-tab-<?php echo esc_attr( $multilify_code ); ?>"
					aria-controls="mfy-panel-<?php echo esc_attr( $multilify_code ); ?>"
					aria-selected="<?php echo $multilify_is_on ? 'true' : 'false'; ?>"
					tabindex="<?php echo $multilify_is_on ? '0' : '-1'; ?>">
				<span class="mfy-tr__flag" aria-hidden="true"><?php echo esc_html( $multilify_language['flag'] ); ?></span>
				<span class="mfy-tr__name"><?php echo esc_html( $multilify_label ); ?></span>
				<span class="mfy-tr__state">
					<?php
					if ( $multilify_is_def ) {
						esc_html_e( 'Original', 'multilify' );
					} elseif ( $multilify_done ) {
						esc_html_e( 'translated', 'multilify' );
					} else {
						esc_html_e( 'Not translated', 'multilify' );
					}
					?>
				</span>
				<?php if ( ! $multilify_is_def ) : ?>
					<span class="mfy-dot<?php echo $multilify_done ? ' is-done' : ''; ?>" aria-hidden="true"></span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>

	<div class="mfy-tr__panels">
		<?php
		foreach ( $languages as $multilify_language ) :
			$multilify_code = $multilify_language['code'];
			?>
			<?php // Hidden off screen rather than with display: none, so the visual editor in it keeps its size. ?>
			<div role="tabpanel" class="mfy-tr__panel<?php echo ( $multilify_code === $active ) ? ' is-active' : ''; ?>"
					id="mfy-panel-<?php echo esc_attr( $multilify_code ); ?>"
					aria-labelledby="mfy-tab-<?php echo esc_attr( $multilify_code ); ?>">
				<?php $this->render_translation_meta_box( $post, array( 'args' => array( 'language' => $multilify_language ) ) ); ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
