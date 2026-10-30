<?php
/**
 * How translating works: posts, addresses, the switcher and search engines.
 *
 * @package Multilify
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="mfy-card mfy-guide">
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
				<button type="button" class="multilify-copy" data-multilify-copy="[multilify_switcher]"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: the snippet the button copies. */ __( 'Copy %s', 'multilify' ), '[multilify_switcher]' ) ); ?>">
					<span class="multilify-copy__label"><?php esc_html_e( 'Copy', 'multilify' ); ?></span>
				</button>
				<span class="screen-reader-text" role="status" data-multilify-copy-status></span>
			</div>
			<p><?php esc_html_e( 'Or call it from a theme template:', 'multilify' ); ?></p>
			<div class="multilify-snippet">
				<pre><code>&lt;?php multilify_switcher(); ?&gt;</code></pre>
				<button type="button" class="multilify-copy" data-multilify-copy="&lt;?php multilify_switcher(); ?&gt;"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: the snippet the button copies. */ __( 'Copy %s', 'multilify' ), '<?php multilify_switcher(); ?>' ) ); ?>">
					<span class="multilify-copy__label"><?php esc_html_e( 'Copy', 'multilify' ); ?></span>
				</button>
				<span class="screen-reader-text" role="status" data-multilify-copy-status></span>
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
			<p><?php esc_html_e( 'Multilify adds hreflang tags to each entry and to the homepage so search engines know which languages an entry exists in, and sets the page language so screen readers pronounce it correctly.', 'multilify' ); ?></p>
			<p><?php esc_html_e( 'Visitors who open the homepage are sent to the language their browser asks for. Once someone picks a language from the switcher, that choice is remembered. Logged-in users and search engines are never redirected.', 'multilify' ); ?></p>
		</div>
	</div>
</div>
