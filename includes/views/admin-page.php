<?php
/**
 * Admin page for language management.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap multilify-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['multilify_updated'] ) && '1' === $_GET['multilify_updated'] ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Changes saved successfully!', 'multilify' ); ?></p>
		</div>
	<?php endif; ?>

	<?php
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['multilify_error'] ) ) :
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$multilify_error          = sanitize_key( wp_unslash( $_GET['multilify_error'] ) );
		$multilify_error_messages = array(
			'duplicate_code' => __( 'A language with this code already exists. Each language code must be unique.', 'multilify' ),
			'invalid_code'   => __( 'Invalid language code. Use 2-5 lowercase letters (e.g. tr, en, de).', 'multilify' ),
			'not_found'      => __( 'The language you tried to edit was not found.', 'multilify' ),
			'delete_default' => __( 'You cannot delete the default language. Set another language as default first.', 'multilify' ),
		);
		$multilify_error_text     = isset( $multilify_error_messages[ $multilify_error ] )
			? $multilify_error_messages[ $multilify_error ]
			: __( 'Something went wrong. Please try again.', 'multilify' );
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $multilify_error_text ); ?></p>
		</div>
	<?php endif; ?>

	<div class="multilify-container">
		<!-- Current Languages Section -->
		<div class="multilify-section">
			<h2><?php esc_html_e( 'Current Languages', 'multilify' ); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th width="60"><?php esc_html_e( 'Flag', 'multilify' ); ?></th>
						<th width="100"><?php esc_html_e( 'Code', 'multilify' ); ?></th>
						<th><?php esc_html_e( 'Name', 'multilify' ); ?></th>
						<th width="120"><?php esc_html_e( 'Default', 'multilify' ); ?></th>
						<th width="100"><?php esc_html_e( 'Actions', 'multilify' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $languages ) ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No languages added yet. Add your first language using the form below.', 'multilify' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $languages as $multilify_language ) : ?>
							<tr>
								<td><?php echo esc_html( $multilify_language['flag'] ); ?></td>
								<td><strong><?php echo esc_html( $multilify_language['code'] ); ?></strong></td>
								<td><?php echo esc_html( $multilify_language['name'] ); ?></td>
								<td>
									<form method="post" style="display: inline;">
										<?php wp_nonce_field( 'multilify_action' ); ?>
										<input type="hidden" name="multilify_action" value="set_default">
										<input type="hidden" name="default_language" value="<?php echo esc_attr( $multilify_language['code'] ); ?>">
										<?php if ( $multilify_language['code'] === $default_language ) : ?>
											<span class="dashicons dashicons-yes" style="color: #46b450;" aria-hidden="true"></span>
											<strong><?php esc_html_e( 'Default', 'multilify' ); ?></strong>
										<?php else : ?>
											<button type="submit" class="button button-small">
												<?php esc_html_e( 'Set as Default', 'multilify' ); ?>
											</button>
										<?php endif; ?>
									</form>
								</td>
								<td>
									<button type="button" class="button button-small multilify-edit-toggle" data-code="<?php echo esc_attr( $multilify_language['code'] ); ?>" aria-expanded="false" aria-controls="multilify-edit-<?php echo esc_attr( $multilify_language['code'] ); ?>">
										<?php esc_html_e( 'Edit', 'multilify' ); ?>
									</button>
									<?php if ( $multilify_language['code'] !== $default_language ) : ?>
										<form method="post" style="display: inline;">
											<?php wp_nonce_field( 'multilify_action' ); ?>
											<input type="hidden" name="multilify_action" value="delete_language">
											<input type="hidden" name="lang_code" value="<?php echo esc_attr( $multilify_language['code'] ); ?>">
											<button type="submit" class="button button-small button-link-delete">
												<?php esc_html_e( 'Delete', 'multilify' ); ?>
											</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
							<tr class="multilify-edit-row" id="multilify-edit-<?php echo esc_attr( $multilify_language['code'] ); ?>" style="display: none;">
								<td colspan="5">
									<form method="post" class="multilify-edit-form">
										<?php wp_nonce_field( 'multilify_action' ); ?>
										<input type="hidden" name="multilify_action" value="edit_language">
										<input type="hidden" name="lang_code" value="<?php echo esc_attr( $multilify_language['code'] ); ?>">

										<div class="multilify-field multilify-field--code">
											<label for="multilify-edit-code-<?php echo esc_attr( $multilify_language['code'] ); ?>">
												<?php esc_html_e( 'Code', 'multilify' ); ?>
											</label>
											<input type="text" id="multilify-edit-code-<?php echo esc_attr( $multilify_language['code'] ); ?>" value="<?php echo esc_attr( $multilify_language['code'] ); ?>" disabled>
										</div>

										<div class="multilify-field multilify-field--name">
											<label for="multilify-edit-name-<?php echo esc_attr( $multilify_language['code'] ); ?>">
												<?php esc_html_e( 'Name', 'multilify' ); ?>
											</label>
											<input type="text" id="multilify-edit-name-<?php echo esc_attr( $multilify_language['code'] ); ?>" name="lang_name" value="<?php echo esc_attr( $multilify_language['name'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty to use the code', 'multilify' ); ?>">
										</div>

										<div class="multilify-field multilify-field--flag">
											<label for="multilify-edit-flag-<?php echo esc_attr( $multilify_language['code'] ); ?>">
												<?php esc_html_e( 'Flag', 'multilify' ); ?>
											</label>
											<input type="text" id="multilify-edit-flag-<?php echo esc_attr( $multilify_language['code'] ); ?>" name="lang_flag" value="<?php echo esc_attr( $multilify_language['flag'] ); ?>" maxlength="10">
										</div>

										<div class="multilify-field multilify-field--actions">
											<button type="submit" class="button button-primary button-small">
												<?php esc_html_e( 'Save', 'multilify' ); ?>
											</button>
											<button type="button" class="button button-small multilify-edit-cancel" data-code="<?php echo esc_attr( $multilify_language['code'] ); ?>">
												<?php esc_html_e( 'Cancel', 'multilify' ); ?>
											</button>
										</div>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- Add New Language Section -->
		<div class="multilify-section">
			<h2><?php esc_html_e( 'Add New Language', 'multilify' ); ?></h2>
			<form method="post" class="multilify-form">
				<?php wp_nonce_field( 'multilify_action' ); ?>
				<input type="hidden" name="multilify_action" value="add_language">

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="lang_code"><?php esc_html_e( 'Language Code', 'multilify' ); ?></label>
						</th>
						<td>
							<input type="text"
									id="lang_code"
									name="lang_code"
									class="regular-text"
									placeholder="tr, en, de, es..."
									required
									maxlength="5"
									pattern="[a-z]{2,5}">
							<p class="description"><?php esc_html_e( '2-5 characters (lowercase). Example: tr, en, de', 'multilify' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lang_name"><?php esc_html_e( 'Language Name', 'multilify' ); ?></label>
						</th>
						<td>
							<input type="text"
									id="lang_name"
									name="lang_name"
									class="regular-text"
									placeholder="Türkçe, English, Deutsch...">
							<p class="description">
								<?php
								printf(
									/* translators: %s: the show_name="false" shortcode attribute. */
									esc_html__( 'Full language name (optional). Leave empty to use the language code, or hide it in the switcher with %s.', 'multilify' ),
									'<code>show_name="false"</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="lang_flag"><?php esc_html_e( 'Flag Emoji', 'multilify' ); ?></label>
						</th>
						<td>
							<input type="text"
									id="lang_flag"
									name="lang_flag"
									class="regular-text"
									placeholder="🇹🇷 🇬🇧 🇩🇪..."
									required
									maxlength="10">
							<p class="description"><?php esc_html_e( 'Flag emoji or icon. Example: 🇹🇷, 🇬🇧, 🇩🇪', 'multilify' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Language', 'multilify' ); ?></button>
				</p>
			</form>
		</div>

		<!-- Instructions Section -->
		<div class="multilify-section" id="usage-guide">
			<h2><?php esc_html_e( 'Usage Guide', 'multilify' ); ?></h2>
			<div class="multilify-instructions">
				<h3><?php esc_html_e( '1. Adding Languages', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'Use the form above to add new languages. Each language must have a unique code (e.g., tr, en, de).', 'multilify' ); ?></p>

				<h3><?php esc_html_e( '2. Content Translation', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'When editing a Post or Page, you will see meta boxes for each language where you can:', 'multilify' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'Enter the translated title', 'multilify' ); ?></li>
					<li><?php esc_html_e( 'Enter the translated content', 'multilify' ); ?></li>
					<li><?php esc_html_e( 'Enter a custom slug/URL (optional)', 'multilify' ); ?></li>
				</ul>

				<h3><?php esc_html_e( '3. URL Structure', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'Language codes are automatically used in URLs:', 'multilify' ); ?></p>
				<ul>
					<li>Turkish: <code>yoursite.com/tr/page-name/</code></li>
					<li>English: <code>yoursite.com/en/page-name/</code></li>
					<li>German: <code>yoursite.com/de/page-name/</code></li>
				</ul>

				<h3><?php esc_html_e( '4. Language Switcher', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'Add this code to your theme files to display a language switcher:', 'multilify' ); ?></p>
				<pre><code>&lt;?php if ( function_exists( 'multilify_switcher' ) ) multilify_switcher(); ?&gt;</code></pre>

				<h3><?php esc_html_e( '5. Default Language', 'multilify' ); ?></h3>
				<p><?php esc_html_e( 'When a translation is not available, the default language content will be displayed. You can change the default language above.', 'multilify' ); ?></p>
			</div>
		</div>
	</div>
</div>
