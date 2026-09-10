<?php
/**
 * GD-based image helpers: EXIF orientation normalization and crop rendering.
 *
 * @package wc-print-cart
 */

defined( 'ABSPATH' ) || exit;

class WCPC_Image {

	/**
	 * Load an image file into a GD resource.
	 *
	 * @param string $path Absolute file path.
	 * @return resource|GdImage|false
	 */
	public static function load( $path ) {
		$info = @getimagesize( $path ); // phpcs:ignore
		if ( ! $info ) {
			return false;
		}

		wp_raise_memory_limit( 'image' );

		switch ( $info['mime'] ) {
			case 'image/jpeg':
				return @imagecreatefromjpeg( $path ); // phpcs:ignore
			case 'image/png':
				return @imagecreatefrompng( $path ); // phpcs:ignore
			case 'image/webp':
				return function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false; // phpcs:ignore
		}

		return false;
	}

	/**
	 * Rotate/flip a JPEG so its pixels match its EXIF orientation, then strip
	 * the need for orientation handling downstream (browser preview and GD
	 * rendering then agree on the same pixel grid).
	 *
	 * @param string $path Absolute file path.
	 * @return bool True on success or nothing to do.
	 */
	public static function normalize_orientation( $path ) {
		$info = @getimagesize( $path ); // phpcs:ignore
		if ( ! $info || 'image/jpeg' !== $info['mime'] || ! function_exists( 'exif_read_data' ) ) {
			return true;
		}

		$exif = @exif_read_data( $path ); // phpcs:ignore
		$orientation = isset( $exif['Orientation'] ) ? (int) $exif['Orientation'] : 1;
		if ( $orientation <= 1 ) {
			return true;
		}

		$im = self::load( $path );
		if ( ! $im ) {
			return false;
		}

		switch ( $orientation ) {
			case 2:
				imageflip( $im, IMG_FLIP_HORIZONTAL );
				break;
			case 3:
				$im = imagerotate( $im, 180, 0 );
				break;
			case 4:
				imageflip( $im, IMG_FLIP_VERTICAL );
				break;
			case 5:
				imageflip( $im, IMG_FLIP_HORIZONTAL );
				$im = imagerotate( $im, 90, 0 );
				break;
			case 6:
				$im = imagerotate( $im, -90, 0 );
				break;
			case 7:
				imageflip( $im, IMG_FLIP_HORIZONTAL );
				$im = imagerotate( $im, -90, 0 );
				break;
			case 8:
				$im = imagerotate( $im, 90, 0 );
				break;
		}

		$ok = $im && imagejpeg( $im, $path, 92 );
		if ( $im ) {
			imagedestroy( $im );
		}

		return (bool) $ok;
	}

	/**
	 * Render a cropped, rotated JPEG from a source image.
	 *
	 * The crop rectangle is expressed in the coordinate space of the source
	 * image AFTER applying $rotation quarter-turns clockwise — exactly the
	 * space the front-end editor works in.
	 *
	 * @param string $src      Absolute path of the source image.
	 * @param array  $crop     Crop rect: x, y, w, h (floats, rotated space).
	 * @param int    $rotation Quarter turns clockwise (0–3).
	 * @param int    $max_w    Target max width in px (no upscaling).
	 * @param int    $max_h    Target max height in px.
	 * @param string $dest     Absolute destination path (.jpg).
	 * @return bool
	 */
	public static function render( $src, $crop, $rotation, $max_w, $max_h, $dest ) {
		$im = self::load( $src );
		if ( ! $im ) {
			return false;
		}

		$rotation = ( (int) $rotation ) % 4;
		if ( $rotation ) {
			$rotated = imagerotate( $im, -90 * $rotation, 0 );
			imagedestroy( $im );
			if ( ! $rotated ) {
				return false;
			}
			$im = $rotated;
		}

		$iw = imagesx( $im );
		$ih = imagesy( $im );

		$x = max( 0, (int) round( $crop['x'] ) );
		$y = max( 0, (int) round( $crop['y'] ) );
		$w = (int) round( $crop['w'] );
		$h = (int) round( $crop['h'] );
		$w = max( 1, min( $w, $iw - $x ) );
		$h = max( 1, min( $h, $ih - $y ) );

		$cropped = imagecrop( $im, array( 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h ) );
		imagedestroy( $im );
		if ( ! $cropped ) {
			return false;
		}

		// Never upscale past the cropped pixels.
		$scale = min( 1, min( $max_w / $w, $max_h / $h ) );
		$tw    = max( 1, (int) round( $w * $scale ) );
		$th    = max( 1, (int) round( $h * $scale ) );

		$out = imagecreatetruecolor( $tw, $th );
		// Flatten any transparency (PNG/WebP) onto white for print.
		$white = imagecolorallocate( $out, 255, 255, 255 );
		imagefill( $out, 0, 0, $white );
		imagecopyresampled( $out, $cropped, 0, 0, 0, 0, $tw, $th, $w, $h );
		imagedestroy( $cropped );

		$dir = dirname( $dest );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$ok = imagejpeg( $out, $dest, 92 );
		imagedestroy( $out );

		return (bool) $ok;
	}
}
