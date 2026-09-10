<?php
/**
 * Cart, pricing and order integration for the print designer.
 *
 * @package wc-print-cart
 */

defined( 'ABSPATH' ) || exit;

class WCPC_Cart {

	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_thumbnail', array( __CLASS__, 'cart_thumbnail' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'set_prices' ), 20 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_line_item' ), 10, 4 );
		add_action( 'woocommerce_after_order_itemmeta', array( __CLASS__, 'admin_item_meta' ), 10, 2 );
	}

	/**
	 * Read and sanitize the designer fields posted with add-to-cart.
	 *
	 * @return array|WP_Error
	 */
	protected static function read_posted( $product_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$nonce = isset( $_POST['wcpc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wcpc_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wcpc_designer' ) ) {
			return new WP_Error( 'wcpc', __( 'Your session expired — please reload the page and try again.', 'wc-print-cart' ) );
		}

		$token = isset( $_POST['wcpc_token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', wp_unslash( $_POST['wcpc_token'] ) ) : '';
		$data  = $token ? get_transient( 'wcpc_up_' . $token ) : false;
		if ( ! $data ) {
			return new WP_Error( 'wcpc', __( 'Please upload a photo before adding this print to your cart.', 'wc-print-cart' ) );
		}

		$sizes  = wcpc_get_sizes( $product_id );
		$papers = wcpc_get_papers( $product_id );

		$size_idx  = isset( $_POST['wcpc_size'] ) ? absint( $_POST['wcpc_size'] ) : 0;
		$paper_idx = isset( $_POST['wcpc_paper'] ) ? absint( $_POST['wcpc_paper'] ) : 0;
		if ( ! isset( $sizes[ $size_idx ] ) || ! isset( $papers[ $paper_idx ] ) ) {
			return new WP_Error( 'wcpc', __( 'Invalid print options selected.', 'wc-print-cart' ) );
		}

		$orientation = ( isset( $_POST['wcpc_orientation'] ) && 'landscape' === $_POST['wcpc_orientation'] ) ? 'landscape' : 'portrait';

		$crop_raw = isset( $_POST['wcpc_crop'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['wcpc_crop'] ) ), true ) : null;
		if ( ! is_array( $crop_raw ) ) {
			return new WP_Error( 'wcpc', __( 'The crop selection is missing — please adjust your photo and try again.', 'wc-print-cart' ) );
		}
		foreach ( array( 'x', 'y', 'w', 'h' ) as $k ) {
			if ( ! isset( $crop_raw[ $k ] ) || ! is_numeric( $crop_raw[ $k ] ) ) {
				return new WP_Error( 'wcpc', __( 'The crop selection is invalid.', 'wc-print-cart' ) );
			}
		}
		$crop = array(
			'x' => (float) $crop_raw['x'],
			'y' => (float) $crop_raw['y'],
			'w' => max( 1.0, (float) $crop_raw['w'] ),
			'h' => max( 1.0, (float) $crop_raw['h'] ),
		);
		$rotation = isset( $crop_raw['rotation'] ) ? absint( $crop_raw['rotation'] ) % 4 : 0;
		// phpcs:enable

		$size  = $sizes[ $size_idx ];
		$paper = $papers[ $paper_idx ];

		// Apply orientation to the physical size.
		$w_in = 'landscape' === $orientation ? max( $size['w'], $size['h'] ) : min( $size['w'], $size['h'] );
		$h_in = 'landscape' === $orientation ? min( $size['w'], $size['h'] ) : max( $size['w'], $size['h'] );

		return array(
			'token'       => $token,
			'file'        => $data['file'],
			'size_label'  => $size['label'],
			'size_price'  => $size['price'],
			'w_in'        => $w_in,
			'h_in'        => $h_in,
			'paper_label' => $paper['label'],
			'surcharge'   => $paper['surcharge'],
			'orientation' => $orientation,
			'crop'        => $crop,
			'rotation'    => $rotation,
		);
	}

	public static function validate( $passed, $product_id ) {
		if ( ! wcpc_is_enabled( $product_id ) ) {
			return $passed;
		}

		$data = self::read_posted( $product_id );
		if ( is_wp_error( $data ) ) {
			wc_add_notice( $data->get_error_message(), 'error' );
			return false;
		}

		return $passed;
	}

	public static function add_cart_item_data( $cart_item_data, $product_id ) {
		if ( ! wcpc_is_enabled( $product_id ) ) {
			return $cart_item_data;
		}

		$data = self::read_posted( $product_id );
		if ( is_wp_error( $data ) ) {
			return $cart_item_data;
		}

		// Server-rendered preview thumbnail of the actual crop (never trusts
		// client-supplied image data).
		$preview_rel = 'previews/' . wp_generate_password( 20, false, false ) . '.jpg';
		$rendered    = WCPC_Image::render(
			wcpc_file_path( $data['file'] ),
			$data['crop'],
			$data['rotation'],
			400,
			400,
			wcpc_file_path( $preview_rel )
		);

		$data['preview'] = $rendered ? $preview_rel : '';
		$data['unique']  = md5( $data['token'] . microtime( true ) );

		$cart_item_data['wcpc'] = $data;
		return $cart_item_data;
	}

	public static function get_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['wcpc'] ) ) {
			return $item_data;
		}
		$v = $cart_item['wcpc'];

		$item_data[] = array(
			'key'   => __( 'Print size', 'wc-print-cart' ),
			'value' => sprintf( '%s (%s" × %s")', $v['size_label'], wc_format_localized_decimal( $v['w_in'] ), wc_format_localized_decimal( $v['h_in'] ) ),
		);
		$item_data[] = array(
			'key'   => __( 'Paper', 'wc-print-cart' ),
			'value' => $v['paper_label'],
		);

		return $item_data;
	}

	public static function cart_thumbnail( $thumbnail, $cart_item ) {
		if ( empty( $cart_item['wcpc']['preview'] ) ) {
			return $thumbnail;
		}
		return sprintf(
			'<img src="%s" alt="%s" class="wcpc-cart-thumb" style="max-width:80px;height:auto;border-radius:2px;" />',
			esc_url( wcpc_file_url( $cart_item['wcpc']['preview'] ) ),
			esc_attr__( 'Your print preview', 'wc-print-cart' )
		);
	}

	public static function set_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['wcpc'] ) ) {
				continue;
			}
			$v     = $cart_item['wcpc'];
			$base  = $v['size_price'] > 0 ? (float) $v['size_price'] : (float) $cart_item['data']->get_price( 'edit' );
			$price = $base + (float) $v['surcharge'];
			$cart_item['data']->set_price( $price );
		}
	}

	public static function order_line_item( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['wcpc'] ) ) {
			return;
		}
		$v = $values['wcpc'];

		$item->add_meta_data(
			__( 'Print size', 'wc-print-cart' ),
			sprintf( '%s (%s" × %s")', $v['size_label'], wc_format_localized_decimal( $v['w_in'] ), wc_format_localized_decimal( $v['h_in'] ) )
		);
		$item->add_meta_data( __( 'Paper', 'wc-print-cart' ), $v['paper_label'] );

		// Generate the print-ready file at 300 DPI (capped at source resolution).
		$target_w  = (int) round( $v['w_in'] * 300 );
		$target_h  = (int) round( $v['h_in'] * 300 );
		$print_rel = 'orders/' . wp_generate_password( 24, false, false ) . '.jpg';

		$rendered = WCPC_Image::render(
			wcpc_file_path( $v['file'] ),
			$v['crop'],
			$v['rotation'],
			$target_w,
			$target_h,
			wcpc_file_path( $print_rel )
		);

		$item->add_meta_data( '_wcpc_source_file', $v['file'] );
		$item->add_meta_data( '_wcpc_crop', wp_json_encode( array_merge( $v['crop'], array( 'rotation' => $v['rotation'] ) ) ) );
		if ( $rendered ) {
			$item->add_meta_data( '_wcpc_print_file', $print_rel );
		}
		if ( ! empty( $v['preview'] ) ) {
			$item->add_meta_data( '_wcpc_preview', $v['preview'] );
		}
	}

	/**
	 * Show download links for the print-ready file in the admin order screen.
	 */
	public static function admin_item_meta( $item_id, $item ) {
		if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! $item instanceof WC_Order_Item_Product ) {
			return;
		}

		$print_file = $item->get_meta( '_wcpc_print_file' );
		$source     = $item->get_meta( '_wcpc_source_file' );
		$preview    = $item->get_meta( '_wcpc_preview' );

		if ( ! $print_file && ! $source ) {
			return;
		}

		echo '<div class="wcpc-admin-files" style="margin-top:6px;">';
		if ( $preview ) {
			printf(
				'<img src="%s" alt="" style="max-width:60px;height:auto;vertical-align:middle;margin-right:8px;border-radius:2px;" />',
				esc_url( wcpc_file_url( $preview ) )
			);
		}
		if ( $print_file ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener" class="button button-small">%s</a> ',
				esc_url( wcpc_file_url( $print_file ) ),
				esc_html__( 'Download print-ready file', 'wc-print-cart' )
			);
		}
		if ( $source ) {
			printf(
				'<a href="%s" target="_blank" rel="noopener" class="button button-small">%s</a>',
				esc_url( wcpc_file_url( $source ) ),
				esc_html__( 'Original upload', 'wc-print-cart' )
			);
		}
		echo '</div>';
	}
}
