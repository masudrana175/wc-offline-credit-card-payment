<?php
/**
 * Product-level settings: "Print Designer" product data tab.
 *
 * @package wc-print-cart
 */

defined( 'ABSPATH' ) || exit;

class WCPC_Product_Settings {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
	}

	public static function add_tab( $tabs ) {
		$tabs['wcpc'] = array(
			'label'    => __( 'Print Designer', 'wc-print-cart' ),
			'target'   => 'wcpc_product_data',
			'class'    => array(),
			'priority' => 75,
		);
		return $tabs;
	}

	public static function render_panel() {
		global $post;

		$enabled = get_post_meta( $post->ID, '_wcpc_enabled', true );
		$sizes   = get_post_meta( $post->ID, '_wcpc_sizes_raw', true );
		$papers  = get_post_meta( $post->ID, '_wcpc_papers_raw', true );

		$sizes_placeholder  = "4×6|4|6|2.99\n5×7|5|7|4.99\n8×10|8|10|9.99\n11×14|11|14|19.99\n16×20|16|20|34.99";
		$papers_placeholder = "Glossy|0\nLuster|0\nPearl|1.50\nFine Art|4.00";
		?>
		<div id="wcpc_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_checkbox( array(
					'id'          => '_wcpc_enabled',
					'value'       => $enabled,
					'label'       => __( 'Enable print designer', 'wc-print-cart' ),
					'description' => __( 'Show the photo upload & crop editor on this product page.', 'wc-print-cart' ),
				) );
				?>
			</div>
			<div class="options_group">
				<p class="form-field">
					<label for="_wcpc_sizes_raw"><?php esc_html_e( 'Print sizes', 'wc-print-cart' ); ?></label>
					<textarea id="_wcpc_sizes_raw" name="_wcpc_sizes_raw" rows="6" style="width:100%;font-family:monospace;"
						placeholder="<?php echo esc_attr( $sizes_placeholder ); ?>"><?php echo esc_textarea( $sizes ); ?></textarea>
				</p>
				<p class="description" style="padding:0 12px;">
					<?php esc_html_e( 'One size per line: Label|width inches|height inches|price. Leave empty to use the defaults shown in the placeholder.', 'wc-print-cart' ); ?>
				</p>
				<p class="form-field">
					<label for="_wcpc_papers_raw"><?php esc_html_e( 'Paper / finish options', 'wc-print-cart' ); ?></label>
					<textarea id="_wcpc_papers_raw" name="_wcpc_papers_raw" rows="5" style="width:100%;font-family:monospace;"
						placeholder="<?php echo esc_attr( $papers_placeholder ); ?>"><?php echo esc_textarea( $papers ); ?></textarea>
				</p>
				<p class="description" style="padding:0 12px;">
					<?php esc_html_e( 'One paper per line: Label|surcharge. The surcharge is added to the size price. Leave empty to use the defaults.', 'wc-print-cart' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	public static function save( $post_id ) {
		update_post_meta( $post_id, '_wcpc_enabled', isset( $_POST['_wcpc_enabled'] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( isset( $_POST['_wcpc_sizes_raw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, '_wcpc_sizes_raw', sanitize_textarea_field( wp_unslash( $_POST['_wcpc_sizes_raw'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['_wcpc_papers_raw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, '_wcpc_papers_raw', sanitize_textarea_field( wp_unslash( $_POST['_wcpc_papers_raw'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}
}
