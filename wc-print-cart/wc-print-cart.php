<?php
/**
 * Plugin Name: WC Print Cart — Photo Print Designer
 * Plugin URI:  https://github.com/masudrana175/wc-offline-credit-card-payment
 * Description: Photo upload, crop/zoom/rotate design editor, print size and paper options with live pricing, resolution check, and print-ready file generation for WooCommerce products (Colorplak-style print ordering).
 * Version:     1.0.0
 * Author:      Masud Rana
 * Text Domain: wc-print-cart
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * WC requires at least: 5.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPC_VERSION', '1.0.0' );
define( 'WCPC_FILE', __FILE__ );
define( 'WCPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce HPOS (custom order tables).
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WCPC_FILE, true );
	}
} );

add_action( 'plugins_loaded', 'wcpc_bootstrap' );

function wcpc_bootstrap() {
	load_plugin_textdomain( 'wc-print-cart', false, dirname( plugin_basename( WCPC_FILE ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'WC Print Cart requires WooCommerce to be installed and active.', 'wc-print-cart' ) .
				'</p></div>';
		} );
		return;
	}

	require_once WCPC_DIR . 'includes/class-wcpc-image.php';
	require_once WCPC_DIR . 'includes/class-wcpc-product-settings.php';
	require_once WCPC_DIR . 'includes/class-wcpc-frontend.php';
	require_once WCPC_DIR . 'includes/class-wcpc-ajax.php';
	require_once WCPC_DIR . 'includes/class-wcpc-cart.php';

	WCPC_Product_Settings::init();
	WCPC_Frontend::init();
	WCPC_Ajax::init();
	WCPC_Cart::init();
}

/**
 * Whether the print designer is enabled for a product.
 *
 * @param int|WC_Product $product Product or product ID.
 * @return bool
 */
function wcpc_is_enabled( $product ) {
	$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
	return $product instanceof WC_Product && 'yes' === $product->get_meta( '_wcpc_enabled' );
}

/**
 * Print sizes configured for a product.
 *
 * @param int $product_id Product ID.
 * @return array[] Each: label, w (inches), h (inches), price.
 */
function wcpc_get_sizes( $product_id ) {
	$raw   = get_post_meta( $product_id, '_wcpc_sizes_raw', true );
	$sizes = array();

	foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( count( $parts ) < 4 ) {
			continue;
		}
		$w = (float) $parts[1];
		$h = (float) $parts[2];
		if ( $w <= 0 || $h <= 0 ) {
			continue;
		}
		$sizes[] = array(
			'label' => sanitize_text_field( $parts[0] ),
			'w'     => $w,
			'h'     => $h,
			'price' => (float) $parts[3],
		);
	}

	if ( empty( $sizes ) ) {
		$sizes = array(
			array( 'label' => '4×6',   'w' => 4,  'h' => 6,  'price' => 2.99 ),
			array( 'label' => '5×7',   'w' => 5,  'h' => 7,  'price' => 4.99 ),
			array( 'label' => '8×10',  'w' => 8,  'h' => 10, 'price' => 9.99 ),
			array( 'label' => '11×14', 'w' => 11, 'h' => 14, 'price' => 19.99 ),
			array( 'label' => '16×20', 'w' => 16, 'h' => 20, 'price' => 34.99 ),
		);
	}

	return $sizes;
}

/**
 * Paper / finish options configured for a product.
 *
 * @param int $product_id Product ID.
 * @return array[] Each: label, surcharge.
 */
function wcpc_get_papers( $product_id ) {
	$raw    = get_post_meta( $product_id, '_wcpc_papers_raw', true );
	$papers = array();

	foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line ) );
		if ( count( $parts ) < 2 ) {
			continue;
		}
		$papers[] = array(
			'label'     => sanitize_text_field( $parts[0] ),
			'surcharge' => (float) $parts[1],
		);
	}

	if ( empty( $papers ) ) {
		$papers = array(
			array( 'label' => 'Glossy',   'surcharge' => 0 ),
			array( 'label' => 'Luster',   'surcharge' => 0 ),
			array( 'label' => 'Pearl',    'surcharge' => 1.50 ),
			array( 'label' => 'Fine Art', 'surcharge' => 4.00 ),
		);
	}

	return $papers;
}

/**
 * Upload directory used for customer photos and generated print files.
 *
 * @param string $subdir Optional sub-directory (e.g. 'orders', 'previews').
 * @return array { path: string, url: string }
 */
function wcpc_upload_dir( $subdir = '' ) {
	$uploads = wp_upload_dir();
	$path    = trailingslashit( $uploads['basedir'] ) . 'wcpc-prints';
	$url     = trailingslashit( $uploads['baseurl'] ) . 'wcpc-prints';

	if ( $subdir ) {
		$path .= '/' . ltrim( $subdir, '/' );
		$url  .= '/' . ltrim( $subdir, '/' );
	}

	if ( ! is_dir( $path ) ) {
		wp_mkdir_p( $path );
	}

	// Prevent directory listing.
	$index = trailingslashit( $path ) . 'index.html';
	if ( ! file_exists( $index ) ) {
		@file_put_contents( $index, '' ); // phpcs:ignore
	}

	return array(
		'path' => $path,
		'url'  => $url,
	);
}

/**
 * Convert a path relative to the wcpc-prints dir into an absolute path / URL.
 */
function wcpc_file_path( $relative ) {
	$base = wcpc_upload_dir();
	return trailingslashit( $base['path'] ) . ltrim( $relative, '/' );
}

function wcpc_file_url( $relative ) {
	$base = wcpc_upload_dir();
	return trailingslashit( $base['url'] ) . ltrim( $relative, '/' );
}
