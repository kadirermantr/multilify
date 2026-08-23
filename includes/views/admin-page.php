<?php
/**
 * Admin page for language management.
 *
 * @package Multilify
 *
 * @var array  $languages        Configured languages.
 * @var string $default_language Default language code.
 * @var int    $translatable     Published entries that can carry a translation.
 * @var array  $progress         Translated entry count per language code.
 * @var array  $flag_choices     Flags offered by the picker, keyed by language code.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$multilify_total = isset( $translatable ) ? (int) $translatable : 0;
$multilify_done  = isset( $progress ) && is_array( $progress ) ? $progress : array();
$multilify_flags = isset( $flag_choices ) && is_array( $flag_choices ) ? $flag_choices : array();
?>

<div class="wrap multilify-admin">

	<div class="multilify-masthead">
		<div>
			<h1><?php esc_html_e( 'Languages', 'multilify' ); ?></h1>
			<p class="multilify-masthead__lede">
				<?php esc_html_e( 'Every language you add gets its own URL prefix and its own set of translation fields on each post and page.', 'multilify' ); ?>
			</p>
		</div>
		<p class="multilify-tally">
			<?php
			printf(
				/* translators: 1: number of languages, 2: number of published entries. */
				esc_html( _n( '%1$s language', '%1$s languages', count( $languages ), 'multilify' ) ) . ' &middot; ' .
				esc_html( _n( '%2$s entry to translate', '%2$s entries to translate', $multilify_total, 'multilify' ) ),
				'<strong>' . esc_html( number_format_i18n( count( $languages ) ) ) . '</strong>',
				'<strong>' . esc_html( number_format_i18n( $multilify_total ) ) . '</strong>'
			);
			?>
		</p>
	</div>

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
		);
		$multilify_error_text     = isset( $multilify_error_messages[ $multilify_error ] )
			? $multilify_error_messages[ $multilify_error ]
			: __( 'That did not go through. Try again.', 'multilify' );
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $multilify_error_text ); ?></p>
		</div>
	<?php endif; ?>

	<div class="multilify-section">
		<h2><?php esc_html_e( 'Active languages', 'multilify' ); ?></h2>
		<p class="multilify-section__note">
			<?php esc_html_e( 'The default language keeps your existing URLs. Every other language serves its content under its own prefix.', 'multilify' ); ?>
		</p>

		<div class="multilify-entries">
			<?php if ( empty( $languages ) ) : ?>
				<p class="multilify-empty">
					<?php esc_html_e( 'No languages yet. Add one below to start translating.', 'multilify' ); ?>
				</p>
			<?php else : ?>
				<?php
				foreach ( $languages as $multilify_language ) :
					$multilify_code       = $multilify_language['code'];
					$multilify_is_default = ( $multilify_code === $default_language );
					$multilify_name       = '' !== trim( (string) $multilify_language['name'] )
						? $multilify_language['name']
						: $multilify_code;
					$multilify_count      = isset( $multilify_done[ $multilify_code ] ) ? (int) $multilify_done[ $multilify_code ] : 0;
					?>
					<div class="multilify-entry<?php echo $multilify_is_default ? ' multilify-entry--default' : ''; ?>">

						<div class="multilify-entry__headword">
							<span class="multilify-code"><?php echo esc_html( $multilify_code ); ?></span>
							<span class="multilify-entry__gloss">
								<span class="multilify-entry__name"><?php echo esc_html( $multilify_name ); ?></span>
								<span class="multilify-entry__meta">
									<?php if ( '' !== trim( (string) $multilify_language['flag'] ) ) : ?>
										<span class="multilify-flag" aria-hidden="true"><?php echo esc_html( $multilify_language['flag'] ); ?></span>
									<?php endif; ?>
									<?php if ( $multilify_is_default ) : ?>
										<span class="multilify-badge"><?php esc_html_e( 'Default', 'multilify' ); ?></span>
									<?php else : ?>
										<span>
											<?php
											printf(
												/* translators: 1: translated entry count, 2: total entry count. */
												esc_html__( '%1$s of %2$s entries translated', 'multilify' ),
												esc_html( number_format_i18n( $multilify_count ) ),
												esc_html( number_format_i18n( $multilify_total ) )
											);
											?>
										</span>
									<?php endif; ?>
								</span>
							</span>
						</div>

						<div class="multilify-entry__actions">
							<?php if ( ! $multilify_is_default ) : ?>
								<form method="post">
									<?php wp_nonce_field( 'multilify_action' ); ?>
									<input type="hidden" name="multilify_action" value="set_default">
									<input type="hidden" name="default_language" value="<?php echo esc_attr( $multilify_code ); ?>">
									<button type="submit" class="button button-small">
										<?php esc_html_e( 'Make default', 'multilify' ); ?>
									</button>
								</form>
							<?php endif; ?>

							<button type="button" class="button button-small multilify-edit-toggle"
									data-code="<?php echo esc_attr( $multilify_code ); ?>"
									aria-expanded="false"
									aria-controls="multilify-edit-<?php echo esc_attr( $multilify_code ); ?>">
								<?php esc_html_e( 'Edit', 'multilify' ); ?>
							</button>

							<?php if ( ! $multilify_is_default ) : ?>
								<form method="post" data-multilify-confirm data-language="<?php echo esc_attr( $multilify_name ); ?>">
									<?php wp_nonce_field( 'multilify_action' ); ?>
									<input type="hidden" name="multilify_action" value="delete_language">
									<input type="hidden" name="lang_code" value="<?php echo esc_attr( $multilify_code ); ?>">
									<button type="submit" class="button button-small button-link-delete">
										<?php esc_html_e( 'Delete', 'multilify' ); ?>
									</button>
								</form>
							<?php endif; ?>
						</div>
					</div>

					<div class="multilify-editrow" id="multilify-edit-<?php echo esc_attr( $multilify_code ); ?>" hidden>
						<form method="post" class="multilify-editform">
							<?php wp_nonce_field( 'multilify_action' ); ?>
							<input type="hidden" name="multilify_action" value="edit_language">
							<input type="hidden" name="lang_code" value="<?php echo esc_attr( $multilify_code ); ?>">

							<div class="multilify-field multilify-field--code">
								<label for="multilify-edit-code-<?php echo esc_attr( $multilify_code ); ?>">
									<?php esc_html_e( 'Code', 'multilify' ); ?>
								</label>
								<input type="text"
										id="multilify-edit-code-<?php echo esc_attr( $multilify_code ); ?>"
										value="<?php echo esc_attr( $multilify_code ); ?>"
										disabled>
							</div>

							<div class="multilify-field multilify-field--name">
								<label for="multilify-edit-name-<?php echo esc_attr( $multilify_code ); ?>">
									<?php esc_html_e( 'Name', 'multilify' ); ?>
								</label>
								<input type="text"
										id="multilify-edit-name-<?php echo esc_attr( $multilify_code ); ?>"
										name="lang_name"
										value="<?php echo esc_attr( $multilify_language['name'] ); ?>"
										placeholder="<?php esc_attr_e( 'Leave empty to use the code', 'multilify' ); ?>">
							</div>

							<div class="multilify-field multilify-field--flag">
								<span class="multilify-field__label" id="multilify-edit-flaglabel-<?php echo esc_attr( $multilify_code ); ?>">
									<?php esc_html_e( 'Flag', 'multilify' ); ?>
								</span>
								<div class="multilify-flagpicker" data-multilify-flagpicker>
									<div class="multilify-flagpicker__grid" role="radiogroup"
											aria-labelledby="multilify-edit-flaglabel-<?php echo esc_attr( $multilify_code ); ?>">
										<?php foreach ( $multilify_flags as $multilify_flag_code => $multilify_flag_emoji ) : ?>
											<button type="button"
													class="multilify-flagpicker__option<?php echo ( $multilify_flag_emoji === $multilify_language['flag'] ) ? ' is-selected' : ''; ?>"
													role="radio"
													aria-checked="<?php echo ( $multilify_flag_emoji === $multilify_language['flag'] ) ? 'true' : 'false'; ?>"
													data-flag="<?php echo esc_attr( $multilify_flag_emoji ); ?>"
													title="<?php echo esc_attr( $multilify_flag_code ); ?>">
												<span aria-hidden="true"><?php echo esc_html( $multilify_flag_emoji ); ?></span>
												<span class="screen-reader-text"><?php echo esc_html( $multilify_flag_code ); ?></span>
											</button>
										<?php endforeach; ?>
									</div>
									<label class="multilify-flagpicker__custom" for="multilify-edit-flag-<?php echo esc_attr( $multilify_code ); ?>">
										<span><?php esc_html_e( 'Or type your own', 'multilify' ); ?></span>
										<input type="text"
												id="multilify-edit-flag-<?php echo esc_attr( $multilify_code ); ?>"
												name="lang_flag"
												value="<?php echo esc_attr( $multilify_language['flag'] ); ?>"
												maxlength="10"
												data-multilify-flaginput>
									</label>
								</div>
							</div>

							<div class="multilify-field multilify-field--actions">
								<button type="submit" class="button button-primary button-small">
									<?php esc_html_e( 'Save language', 'multilify' ); ?>
								</button>
								<button type="button" class="button button-small multilify-edit-cancel"
										data-code="<?php echo esc_attr( $multilify_code ); ?>">
									<?php esc_html_e( 'Cancel', 'multilify' ); ?>
								</button>
							</div>
						</form>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="multilify-section">
		<h2><?php esc_html_e( 'Add a language', 'multilify' ); ?></h2>
		<p class="multilify-section__note">
			<?php esc_html_e( 'A language code cannot be changed later, because your translations are stored under it.', 'multilify' ); ?>
		</p>

		<form method="post" class="multilify-addform">
			<?php wp_nonce_field( 'multilify_action' ); ?>
			<input type="hidden" name="multilify_action" value="add_language">

			<div class="multilify-field">
				<label for="lang_code"><?php esc_html_e( 'Language code', 'multilify' ); ?></label>
				<input type="text"
						id="lang_code"
						name="lang_code"
						placeholder="de"
						required
						maxlength="5"
						pattern="[a-z]{2,5}">
				<p class="description"><?php esc_html_e( 'Two to five lowercase letters. This becomes the URL prefix.', 'multilify' ); ?></p>
			</div>

			<div class="multilify-field">
				<label for="lang_name"><?php esc_html_e( 'Language name', 'multilify' ); ?></label>
				<input type="text"
						id="lang_name"
						name="lang_name"
						placeholder="Deutsch">
				<p class="description">
					<?php
					printf(
						/* translators: %s: the show_name="false" shortcode attribute. */
						esc_html__( 'Shown in the switcher. Leave it empty to show the code instead, or hide it with %s.', 'multilify' ),
						'<code>show_name="false"</code>'
					);
					?>
				</p>
			</div>

			<div class="multilify-field multilify-field--flagpicker">
				<span class="multilify-field__label" id="multilify-add-flaglabel"><?php esc_html_e( 'Flag', 'multilify' ); ?></span>
				<div class="multilify-flagpicker" data-multilify-flagpicker>
					<div class="multilify-flagpicker__grid" role="radiogroup" aria-labelledby="multilify-add-flaglabel">
						<?php foreach ( $multilify_flags as $multilify_flag_code => $multilify_flag_emoji ) : ?>
							<button type="button"
									class="multilify-flagpicker__option"
									role="radio"
									aria-checked="false"
									data-flag="<?php echo esc_attr( $multilify_flag_emoji ); ?>"
									title="<?php echo esc_attr( $multilify_flag_code ); ?>">
								<span aria-hidden="true"><?php echo esc_html( $multilify_flag_emoji ); ?></span>
								<span class="screen-reader-text"><?php echo esc_html( $multilify_flag_code ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
					<label class="multilify-flagpicker__custom" for="lang_flag">
						<span><?php esc_html_e( 'Or type your own', 'multilify' ); ?></span>
						<input type="text"
								id="lang_flag"
								name="lang_flag"
								placeholder="🏴"
								required
								maxlength="10"
								data-multilify-flaginput>
					</label>
				</div>
				<p class="description"><?php esc_html_e( 'Pick a flag, or type any emoji. Screen readers skip it.', 'multilify' ); ?></p>
			</div>

			<p class="multilify-addform__submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Add language', 'multilify' ); ?></button>
			</p>
		</form>
	</div>

	<div class="multilify-section" id="usage-guide">
		<h2><?php esc_html_e( 'How translating works', 'multilify' ); ?></h2>

		<div class="multilify-guide">
			<div>
				<h3><?php esc_html_e( 'Translate a post or page', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'Open any post or page. Each language gets its own panel where you can set a title, the content, and the slug that language uses in the URL.', 'multilify' ); ?></p>
				<p><?php esc_html_e( 'Leave a field empty and visitors see the default language instead, so a half-finished translation never shows a blank page.', 'multilify' ); ?></p>
			</div>

			<div>
				<h3><?php esc_html_e( 'What the URLs look like', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'The default language keeps the address it already has. Every other language serves the same entry under its own prefix:', 'multilify' ); ?></p>
				<ul>
					<li><code>example.com/about/</code></li>
					<li><code>example.com/tr/hakkimizda/</code></li>
					<li><code>example.com/de/ueber-uns/</code></li>
				</ul>
			</div>

			<div>
				<h3><?php esc_html_e( 'Show the language switcher', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'Drop the shortcode into any post, page or block:', 'multilify' ); ?></p>
				<div class="multilify-snippet">
					<pre><code>[multilify_switcher]</code></pre>
					<button type="button" class="multilify-copy" data-multilify-copy="[multilify_switcher]">
						<span class="multilify-copy__label"><?php esc_html_e( 'Copy', 'multilify' ); ?></span>
					</button>
				</div>
				<p><?php esc_html_e( 'Or call it from a theme template:', 'multilify' ); ?></p>
				<div class="multilify-snippet">
					<pre><code>&lt;?php multilify_switcher(); ?&gt;</code></pre>
					<button type="button" class="multilify-copy" data-multilify-copy="&lt;?php multilify_switcher(); ?&gt;">
						<span class="multilify-copy__label"><?php esc_html_e( 'Copy', 'multilify' ); ?></span>
					</button>
				</div>
				<p>
					<?php
					printf(
						/* translators: %s: the show_name="false" shortcode attribute. */
						esc_html__( 'Add %s to show flags only.', 'multilify' ),
						'<code>show_name="false"</code>'
					);
					?>
				</p>
			</div>

			<div>
				<h3><?php esc_html_e( 'Search engines', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'Multilify adds hreflang tags to every page so search engines know which languages an entry exists in, and sets the page language so screen readers pronounce it correctly.', 'multilify' ); ?></p>
				<p><?php esc_html_e( 'First-time visitors are sent to the language their browser asks for. Once someone picks a language from the switcher, that choice is remembered.', 'multilify' ); ?></p>
			</div>
		</div>
	</div>

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
</div>
