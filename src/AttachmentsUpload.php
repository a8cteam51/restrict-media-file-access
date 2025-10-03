<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main attachments upload class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class AttachmentsUpload {

	/**
	 * Initialize the attachments admin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Check for unique filename in protected directory
		add_filter( 'wp_unique_filename', array( $this, 'check_protected_filename' ), 99, 3 );
	}

	/**
	 * Check if filename exists in protected directory and make it unique if needed.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $filename                 The proposed filename.
	 * @param string $ext                      The file extension.
	 * @param string $dir                      The directory path.
	 *
	 * @return string Modified filename if needed
	 */
	public function check_protected_filename( string $filename, string $ext, string $dir ): string {
		// Get the upload directory info
		$upload_dir = wp_upload_dir();

		// Build the protected directory path matching the current upload path structure
		$protected_base = $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR;

		// Get relative path from upload directory to current directory
		$relative_path = str_replace( $upload_dir['basedir'], '', $dir );

		// Build the corresponding protected directory path
		$protected_path = $protected_base . $relative_path;

		// Check if file exists in protected location
		$protected_file = $protected_path . '/' . $filename;

		if ( file_exists( $protected_file ) ) {
			$name   = wp_basename( $filename, $ext );
			$number = 1;

			// If the filename already ends in -n, start from that number
			if ( 1 === preg_match( '/-(\d+)$/', $name, $matches ) ) {
				$number = intval( $matches[1] ) + 1;
				$name   = preg_replace( '/-\d+$/', '', $name );
			}

			do {
				$new_name           = sprintf( '%s-%d%s', $name, $number, $ext );
				$new_file           = $dir . '/' . $new_name;
				$protected_new_file = $protected_path . '/' . $new_name;
				++$number;
			} while ( file_exists( $new_file ) || file_exists( $protected_new_file ) );

			return $new_name;
		}

		return $filename;
	}
}
