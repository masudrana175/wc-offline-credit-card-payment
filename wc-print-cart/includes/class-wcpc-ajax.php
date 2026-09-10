<?php
/**
 * AJAX handlers: customer photo upload.
 *
 * @package wc-print-cart
 */

defined( 'ABSPATH' ) || exit;

class WCPC_Ajax {

	const MAX_BYTES = 41943040; // 40 MB.

	public static function init() {
		add_action( 'wp_ajax_wcpc_upload', array( __CLASS__, 'upload' ) );
		add_action( 'wp_ajax_nopriv_wcpc_upload', array( __CLASS__, 'upload' ) );
	}

	/**
	 * Handle a customer photo upload. Stores the file under
	 * uploads/wcpc-prints/ with a random name and returns a token the
	 * front end passes back at add-to-cart time.
	 */
	public static function upload() {
		check_ajax_referer( 'wcpc_designer', 'nonce' );

		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file received.', 'wc-print-cart' ) ) );
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Upload failed, please try again.', 'wc-print-cart' ) ) );
		}

		if ( $file['size'] > self::MAX_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'File is too large (max 40 MB).', 'wc-print-cart' ) ) );
		}

		$info = @getimagesize( $file['tmp_name'] ); // phpcs:ignore
		$allowed = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		if ( ! $info || ! isset( $allowed[ $info['mime'] ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please upload a JPEG, PNG or WebP image.', 'wc-print-cart' ) ) );
		}

		$dir      = wcpc_upload_dir( gmdate( 'Y/m' ) );
		$filename = wp_generate_password( 24, false, false ) . '.' . $allowed[ $info['mime'] ];
		$dest     = trailingslashit( $dir['path'] ) . $filename;

		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => __( 'Could not store the uploaded file.', 'wc-print-cart' ) ) );
		}
		@chmod( $dest, 0644 ); // phpcs:ignore

		// Bake EXIF rotation into the pixels so the editor and the print
		// renderer agree on the pixel grid.
		WCPC_Image::normalize_orientation( $dest );

		$info = @getimagesize( $dest ); // phpcs:ignore
		if ( ! $info ) {
			@unlink( $dest ); // phpcs:ignore
			wp_send_json_error( array( 'message' => __( 'The image could not be processed.', 'wc-print-cart' ) ) );
		}

		$relative = gmdate( 'Y/m' ) . '/' . $filename;
		$token    = wp_generate_password( 32, false, false );

		set_transient( 'wcpc_up_' . $token, array(
			'file'   => $relative,
			'width'  => (int) $info[0],
			'height' => (int) $info[1],
		), WEEK_IN_SECONDS );

		wp_send_json_success( array(
			'token'  => $token,
			'url'    => wcpc_file_url( $relative ),
			'width'  => (int) $info[0],
			'height' => (int) $info[1],
		) );
	}
}
