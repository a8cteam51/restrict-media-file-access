<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main Jetpack compatibility class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class JetpackCompatibility {
	/**
	 * Initialize the Jetpack compatibility.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Skip Jetpack Photon for restricted images
		add_filter( 'jetpack_photon_skip_image', array( $this, 'skip_photon_for_restricted' ), 10, 2 );
	}

	/**
	 * Skip Jetpack Photon processing for restricted images.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param bool   $skip Whether to skip processing the image.
	 * @param string $src  Image URL.
	 *
	 * @return bool
	 */
	public function skip_photon_for_restricted( bool $skip, string $src ): bool {
		if ( strpos( $src, '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/' ) !== false || strpos( $src, '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' ) !== false ) {
			return true;
		}
		return $skip;
	}
}
