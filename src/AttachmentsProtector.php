<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main attachments protector class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class AttachmentsProtector {

	/**
	 * Initialize the rewrite rules.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Setup file protection
		add_action( 'template_redirect', array( $this, 'handle_protected_file' ) );
	}

	/**
	 * Handle protected file access.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function handle_protected_file(): void {
		$protected_file = get_query_var( 'protected_file' );

		if ( empty( $protected_file ) ) {
			return;
		}

		if ( false === is_string( $protected_file ) ) {
			return;
		}

		$this->disable_caching();

		if ( $this->is_file_protected( $protected_file ) ) {
			$this->serve_file_as_protected( $protected_file );
			return;
		}

		$this->serve_file_as_unprotected( $protected_file );
	}

	/**
	 * Check if a file is protected.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $protected_file The protected file being accessed.
	 * @return bool
	 */
	private function is_file_protected( string $protected_file ): bool {
		return apply_filters( 'restrict_media_file_access_protect_file', ! is_user_logged_in(), $protected_file );
	}

	/**
	 * Serve a protected file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $protected_file The protected file being accessed.
	 * @return void
	 *
	 * @SuppressWarnings("PHPMD.ExitExpression")
	 */
	private function serve_file_as_protected( string $protected_file ): void {
		/**
		 * Filter the headers sent for protected files
		 *
		 * @param array $headers Array of headers to be sent
		 * @param string $protected_file The protected file being accessed
		 */
		$headers = apply_filters(
			'restrict_media_file_access_protected_headers',
			array(
				'Content-Type'        => 'image/gif',
				'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
				'Cache-Control-Extra' => 'post-check=0, pre-check=0',
				'Pragma'              => 'no-cache',
			),
			$protected_file
		);

		foreach ( $headers as $key => $value ) {
			if ( 'Cache-Control-Extra' === $key ) {
				header( 'Cache-Control: ' . $value, false );
				continue;
			}

			header( $key . ': ' . $value );
		}

		/**
		 * Filter the image data sent for protected files
		 *
		 * @param string $image_data Base64 encoded image data
		 * @param string $protected_file The protected file being accessed
		 */
		$image_data = apply_filters(
			'restrict_media_file_access_protected_image',
			'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
			$protected_file
		);

		// phpcs:ignore
		echo base64_decode( $image_data, true );
		// phpcs:ignore
		exit;
	}

	/**
	 * Serve an unprotected file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $protected_file The protected file being accessed.
	 * @return void
	 */
	private function serve_file_as_unprotected( string $protected_file ): void {
		$file_info = $this->resolve_file_info( $protected_file );

		if ( empty( $file_info['attachment_id'] ) ) {
			wp_die(
				esc_html__( 'File not found.', 'restrict-media-file-access' ),
				'404 Not Found',
				array( 'response' => 404 )
			);
		}

		if ( ! Filesystem::exists( $file_info['file_path'] ) ) {
			wp_die(
				esc_html__( 'File not found.', 'restrict-media-file-access' ),
				'404 Not Found',
				array( 'response' => 404 )
			);
		}

		$is_file_restricted = rmfa_is_media_restricted( $file_info['attachment_id'] );

		if ( false === $is_file_restricted && false === rmfa_should_old_restricted_file_urls_work_if_file_set_as_public() ) {
			wp_die(
				esc_html__( 'File not found.', 'restrict-media-file-access' ),
				'404 Not Found',
				array( 'response' => 404 )
			);
		}

		$this->serve_file( $file_info['attachment_id'], $file_info['file_path'] );
	}

	/**
	 * Resolve file information from protected file name.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $protected_file The protected file being accessed.
	 *
	 * @return array{attachment_id: int|null, file_path: string}
	 */
	private function resolve_file_info( string $protected_file ): array {
		[ $hash, $size_suffix ] = $this->extract_hash_and_size_suffix( $protected_file );

		$attachment_id = rmfa_find_attachment_id_by_hash( $hash );
		if ( empty( $attachment_id ) ) {
			return array(
				'attachment_id' => null,
				'file_path'     => '',
			);
		}

		$file_path = get_attached_file( $attachment_id );

		if ( false === $file_path ) {
			// Can't find the file path, so we return an empty array.
			return array(
				'attachment_id' => null,
				'file_path'     => '',
			);
		}

		$file_path = $this->resolve_file_path_with_suffix( $file_path, $size_suffix );

		return array(
			'attachment_id' => $attachment_id,
			'file_path'     => $file_path,
		);
	}

	/**
	 * Extract hash and size suffix from the protected file name.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $protected_file The protected file being accessed.
	 *
	 * @return array{string, string}
	 */
	private function extract_hash_and_size_suffix( string $protected_file ): array {
		$hash        = $protected_file;
		$size_suffix = '';

		if ( 1 === preg_match( '/^([a-f0-9]+)(?:-(\d+x\d+))?$/', $protected_file, $matches ) ) {
			$hash        = $matches[1];
			$size_suffix = isset( $matches[2] ) ? '-' . $matches[2] : '';
		}

		return array( $hash, $size_suffix );
	}

	/**
	 * Resolve the file path, handling type and size suffix.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $file_path The file path.
	 * @param string $size_suffix The size suffix.
	 *
	 * @return string
	 */
	private function resolve_file_path_with_suffix( string $file_path, string $size_suffix ): string {
		$file_extension = pathinfo( $file_path, PATHINFO_EXTENSION );
		$file_name      = pathinfo( $file_path, PATHINFO_FILENAME );

		if ( ! in_array( strtolower( $file_extension ), array( 'jpg', 'jpeg', 'png', 'gif' ), true ) ) {
			$file_name = $file_name . '-' . $file_extension;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['type'] ) && 'jpg' === $_GET['type'] ) {
			return dirname( $file_path ) . '/' . $file_name . '.jpg';
		}

		if ( ! empty( $size_suffix ) ) {
			$base_dir = dirname( $file_path );
			if ( ! in_array( strtolower( $file_extension ), array( 'jpg', 'jpeg', 'png', 'gif' ), true ) ) {
				$file_extension = 'jpg';
			}
			$sized_file = $base_dir . '/' . $file_name . $size_suffix . '.' . $file_extension;

			if ( Filesystem::exists( $sized_file ) ) {
				return $sized_file;
			}
		}

		return $file_path;
	}

	/**
	 * Serve the protected file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $file_path Path to the file to serve.
	 *
	 * @return void
	 *
	 * @SuppressWarnings("PHPMD.ExitExpression")
	 */
	private function serve_file( int $attachment_id, string $file_path ): void {
		$mime_type = mime_content_type( $file_path );
		if ( false === $mime_type ) {
			$mime_type = 'application/octet-stream';
		}

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );

		do_action( 'restrict_media_file_access_before_serve', $attachment_id, $file_path );

		// Clear any output buffers
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Stream the file in chunks
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Binary file data should not be escaped
		$handle = fopen( $file_path, 'rb' );
		if ( false !== $handle ) {
			while ( ! feof( $handle ) ) {
				// phpcs:ignore -- Binary file data should not be escaped
				echo fread( $handle, 8192 ); // Read in 8KB chunks
				flush();
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $handle );
		}
		// phpcs:ignore
		exit;
	}

	/**
	 * Disable caching for protected files.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	private function disable_caching(): void {
		// Disable caching for protected files
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'batcache_cancel' ) ) {
			batcache_cancel();
		}
	}
}
