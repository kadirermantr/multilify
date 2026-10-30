<?php
/**
 * Languages screen: the languages in a table, the guide on its own tab.
 *
 * @package Multilify
 *
 * @var array  $languages        Configured languages.
 * @var string $default_language Default language code.
 * @var int    $translatable     Published entries that can carry a translation.
 * @var array  $progress         Translated entry count per language code.
 * @var array  $flag_choices     Flags offered by the picker, keyed by language code.
 * @var array  $catalog          Languages offered when adding one.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$multilify_total = (int) $translatable;
$multilify_done  = is_array( $progress ) ? $progress : array();
$multilify_flags = is_array( $flag_choices ) ? $flag_choices : array();
$multilify_host  = untrailingslashit( preg_replace( '#^https?://#', '', home_url() ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$multilify_tab   = isset( $_GET['tab'] ) && 'usage' === $_GET['tab'] ? 'usage' : 'languages';
$multilify_base  = admin_url( 'admin.php?page=multilify' );

// One nonce for every form on the page, so the id _wpnonce is not repeated.
$multilify_nonce = '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'multilify_action' ) ) . '">' . wp_referer_field( false );
?>

<div class="wrap multilify-admin mfy">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Languages', 'multilify' ); ?></h1>
	<button type="button" class="page-title-action" data-multilify-add-open><?php esc_html_e( 'Add a language', 'multilify' ); ?></button>
	<?php // Core moves admin notices here, right under the title line. ?>
	<hr class="wp-header-end">

	<div class="mfy-intro">
		<p class="mfy-lede"><?php esc_html_e( 'Every language you add gets its own URL prefix and its own set of translation fields on each post and page.', 'multilify' ); ?></p>
	</div>

	<?php include __DIR__ . '/parts/notices.php'; ?>

	<?php // The two views, linked the way core lists its own views ("All (13) | Mine (1)"). ?>
	<ul class="subsubsub mfy-views">
		<li>
			<a href="<?php echo esc_url( $multilify_base ); ?>"<?php echo 'languages' === $multilify_tab ? ' class="current" aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'Languages', 'multilify' ); ?>
				<span class="count">(<?php echo esc_html( number_format_i18n( count( $languages ) ) ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'usage', $multilify_base ) ); ?>"<?php echo 'usage' === $multilify_tab ? ' class="current" aria-current="page"' : ''; ?>>
				<?php esc_html_e( 'How translating works', 'multilify' ); ?>
			</a>
		</li>
	</ul>
	<div class="clear"></div>

	<?php if ( 'languages' === $multilify_tab ) : ?>
		<div class="mfy-grid">
			<div class="mfy-card mfy-card--flush">
				<?php if ( empty( $languages ) ) : ?>
					<p class="mfy-empty"><?php esc_html_e( 'No languages yet. Add one to start translating.', 'multilify' ); ?></p>
				<?php else : ?>
					<table class="mfy-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Language', 'multilify' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Address', 'multilify' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Translated', 'multilify' ); ?></th>
								<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'multilify' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $languages as $multilify_language ) :
								$multilify_code       = $multilify_language['code'];
								$multilify_is_default = ( $multilify_code === $default_language );
								$multilify_name       = '' !== trim( (string) $multilify_language['name'] ) ? $multilify_language['name'] : $multilify_code;
								$multilify_count      = isset( $multilify_done[ $multilify_code ] ) ? (int) $multilify_done[ $multilify_code ] : 0;
								$multilify_share      = $multilify_total > 0 ? min( 100, round( ( $multilify_count / $multilify_total ) * 100 ) ) : 0;
								?>
								<tr>
									<td class="mfy-td-lang">
										<span class="mfy-lang">
											<span class="mfy-lang__flag" aria-hidden="true"><?php echo esc_html( $multilify_language['flag'] ); ?></span>
											<span class="mfy-lang__name"><?php echo esc_html( $multilify_name ); ?></span>
											<?php if ( $multilify_is_default ) : ?>
												<span class="mfy-badge"><?php esc_html_e( 'Default', 'multilify' ); ?></span>
											<?php endif; ?>
										</span>
									</td>
									<td class="mfy-td-addr"><code class="mfy-chip"><?php echo esc_html( $multilify_is_default ? $multilify_host . '/' : '/' . $multilify_code . '/' ); ?></code></td>
									<td class="mfy-td-cov">
										<?php if ( $multilify_is_default ) : ?>
											<span class="mfy-muted"><?php esc_html_e( 'Original', 'multilify' ); ?></span>
										<?php else : ?>
											<span class="mfy-cov">
												<span class="mfy-bar" aria-hidden="true"><span style="width: <?php echo esc_attr( $multilify_share ); ?>%"></span></span>
												<span>
													<?php
													printf(
														/* translators: 1: translated entry count, 2: total entry count. */
														esc_html( _n( '%1$s of %2$s entry translated', '%1$s of %2$s entries translated', $multilify_total, 'multilify' ) ),
														esc_html( number_format_i18n( $multilify_count ) ),
														esc_html( number_format_i18n( $multilify_total ) )
													);
													?>
												</span>
											</span>
										<?php endif; ?>
									</td>
									<td class="mfy-table__actions mfy-td-act">
										<button type="button" class="button button-link multilify-edit-toggle"
												data-code="<?php echo esc_attr( $multilify_code ); ?>"
												aria-haspopup="dialog"
												aria-label="<?php echo esc_attr( sprintf( /* translators: %s: language name. */ __( 'Edit %s', 'multilify' ), $multilify_name ) ); ?>">
											<?php esc_html_e( 'Edit', 'multilify' ); ?>
										</button>
										<?php
										if ( $multilify_is_default ) {
											// The default has no menu; the gap keeps Edit in line with the rows below.
											echo '<span class="mfy-iconbtn mfy-iconbtn--blank" aria-hidden="true"></span>';
										} else {
											include __DIR__ . '/parts/row-menu.php';
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<aside class="mfy-aside">
				<div class="mfy-card">
					<h2 class="mfy-card__title"><?php esc_html_e( 'Show the language switcher', 'multilify' ); ?></h2>
					<p><?php esc_html_e( 'Drop the shortcode into any post, page or block:', 'multilify' ); ?></p>
					<div class="multilify-snippet mfy-snippet">
						<pre><code>[multilify_switcher]</code></pre>
						<button type="button" class="multilify-copy" data-multilify-copy="[multilify_switcher]"
								aria-label="<?php echo esc_attr( sprintf( /* translators: %s: the snippet the button copies. */ __( 'Copy %s', 'multilify' ), '[multilify_switcher]' ) ); ?>">
							<span class="multilify-copy__label"><?php esc_html_e( 'Copy', 'multilify' ); ?></span>
						</button>
						<span class="screen-reader-text" role="status" data-multilify-copy-status></span>
					</div>
				</div>
				<div class="mfy-card">
					<h2 class="mfy-card__title"><?php esc_html_e( 'Search engines', 'multilify' ); ?></h2>
					<p><?php esc_html_e( 'Multilify adds hreflang tags to each entry and to the homepage so search engines know which languages an entry exists in, and sets the page language so screen readers pronounce it correctly.', 'multilify' ); ?></p>
				</div>
			</aside>
		</div>
	<?php else : ?>
		<?php include __DIR__ . '/parts/guide.php'; ?>
	<?php endif; ?>

	<?php
	// One edit dialog per language, outside the table they are opened from.
	foreach ( $languages as $multilify_language ) {
		$multilify_code = $multilify_language['code'];
		$multilify_name = '' !== trim( (string) $multilify_language['name'] ) ? $multilify_language['name'] : $multilify_code;
		include __DIR__ . '/parts/edit-form.php';
	}
	?>

	<?php include __DIR__ . '/parts/add-dialog.php'; ?>
	<?php include __DIR__ . '/parts/confirm-dialog.php'; ?>
</div>
