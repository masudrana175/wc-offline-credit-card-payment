<?php
/**
 * Front-end: designer UI on the single product page.
 *
 * @package wc-print-cart
 */

defined( 'ABSPATH' ) || exit;

class WCPC_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_designer' ) );
	}

	protected static function current_enabled_product() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return null;
		}
		$product = wc_get_product( get_queried_object_id() );
		return ( $product && wcpc_is_enabled( $product ) ) ? $product : null;
	}

	public static function enqueue() {
		$product = self::current_enabled_product();
		if ( ! $product ) {
			return;
		}

		wp_enqueue_style( 'wcpc-designer', WCPC_URL . 'assets/css/designer.css', array(), WCPC_VERSION );
		wp_enqueue_script( 'wcpc-designer', WCPC_URL . 'assets/js/designer.js', array(), WCPC_VERSION, true );

		$product_id = $product->get_id();

		wp_localize_script( 'wcpc-designer', 'wcpcData', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'wcpc_designer' ),
			'sizes'     => wcpc_get_sizes( $product_id ),
			'papers'    => wcpc_get_papers( $product_id ),
			'basePrice' => (float) $product->get_price(),
			'currency'  => array(
				'symbol'      => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'decimals'    => wc_get_price_decimals(),
				'decimalSep'  => wc_get_price_decimal_separator(),
				'thousandSep' => wc_get_price_thousand_separator(),
				'position'    => get_option( 'woocommerce_currency_pos', 'left' ),
			),
			'i18n'      => array(
				'uploading'   => __( 'Uploading…', 'wc-print-cart' ),
				'uploadError' => __( 'Upload failed. Please try again.', 'wc-print-cart' ),
				'replace'     => __( 'Replace photo', 'wc-print-cart' ),
				'dpiGood'     => __( 'Excellent print quality', 'wc-print-cart' ),
				'dpiOk'       => __( 'Good print quality', 'wc-print-cart' ),
				'dpiLow'      => __( 'Low resolution — print may look blurry at this size', 'wc-print-cart' ),
				'each'        => __( 'each', 'wc-print-cart' ),
			),
		) );
	}

	public static function render_designer() {
		global $product;

		if ( ! $product || ! wcpc_is_enabled( $product ) ) {
			return;
		}

		$sizes  = wcpc_get_sizes( $product->get_id() );
		$papers = wcpc_get_papers( $product->get_id() );
		?>
		<div id="wcpc-designer" class="wcpc-designer">
			<?php wp_nonce_field( 'wcpc_designer', 'wcpc_nonce' ); ?>
			<input type="hidden" name="wcpc_token" id="wcpc_token" value="" />
			<input type="hidden" name="wcpc_crop" id="wcpc_crop" value="" />
			<input type="hidden" name="wcpc_size" id="wcpc_size" value="0" />
			<input type="hidden" name="wcpc_paper" id="wcpc_paper" value="0" />
			<input type="hidden" name="wcpc_orientation" id="wcpc_orientation" value="portrait" />

			<div class="wcpc-upload-row">
				<label class="wcpc-upload-btn button">
					<span class="wcpc-upload-label"><?php esc_html_e( 'Upload your photo', 'wc-print-cart' ); ?></span>
					<input type="file" id="wcpc-file" accept="image/jpeg,image/png,image/webp" />
				</label>
				<div class="wcpc-progress" hidden><div class="wcpc-progress-bar"></div></div>
				<p class="wcpc-upload-hint"><?php esc_html_e( 'JPEG, PNG or WebP — max 40 MB. Drag to reposition, scroll or use the slider to zoom.', 'wc-print-cart' ); ?></p>
			</div>

			<div class="wcpc-editor" hidden>
				<div class="wcpc-canvas-wrap">
					<canvas id="wcpc-canvas"></canvas>
					<span class="wcpc-dpi" id="wcpc-dpi" hidden></span>
				</div>
				<div class="wcpc-toolbar">
					<input type="range" id="wcpc-zoom" min="0" max="100" value="0" aria-label="<?php esc_attr_e( 'Zoom', 'wc-print-cart' ); ?>" />
					<button type="button" class="button wcpc-btn" id="wcpc-rotate" title="<?php esc_attr_e( 'Rotate 90°', 'wc-print-cart' ); ?>">⟳</button>
					<button type="button" class="button wcpc-btn" id="wcpc-orient" title="<?php esc_attr_e( 'Portrait / landscape', 'wc-print-cart' ); ?>">▭</button>
				</div>
			</div>

			<div class="wcpc-options">
				<p class="wcpc-field">
					<label for="wcpc-size-select"><?php esc_html_e( 'Print size', 'wc-print-cart' ); ?></label>
					<select id="wcpc-size-select">
						<?php foreach ( $sizes as $i => $size ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>">
								<?php echo esc_html( $size['label'] . ' — ' . wp_strip_all_tags( wc_price( $size['price'] ) ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="wcpc-field">
					<label for="wcpc-paper-select"><?php esc_html_e( 'Paper / finish', 'wc-print-cart' ); ?></label>
					<select id="wcpc-paper-select">
						<?php foreach ( $papers as $i => $paper ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>">
								<?php
								echo esc_html( $paper['label'] );
								if ( $paper['surcharge'] > 0 ) {
									echo esc_html( ' (+' . wp_strip_all_tags( wc_price( $paper['surcharge'] ) ) . ')' );
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<p class="wcpc-price" id="wcpc-price"></p>
		</div>
		<?php
	}
}
