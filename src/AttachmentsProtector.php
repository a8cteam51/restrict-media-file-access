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
		// Setup file protection — priority 5 to run before redirect_canonical (priority 10).
		add_action( 'template_redirect', array( $this, 'handle_protected_file' ), 5 );

		// Prevent WordPress canonical redirect from adding trailing slashes to protected file URLs.
		add_filter( 'redirect_canonical', array( $this, 'prevent_protected_file_redirect' ), 10, 2 );
	}

	/**
	 * Prevent WordPress canonical redirect for protected file URLs.
	 *
	 * WordPress's redirect_canonical() runs on template_redirect at priority 10
	 * and adds trailing slashes to URLs, causing a 301 redirect before the
	 * protected file handler can serve the file. This breaks email image proxies
	 * (Gmail, Outlook, etc.) which may not follow redirects reliably, and also
	 * strips query string parameters like access_token during the redirect.
	 *
	 * @since   1.0.7
	 * @version 1.0.7
	 *
	 * @param string $redirect_url  The redirect URL.
	 * @param string $requested_url The requested URL.
	 *
	 * @return string|false The redirect URL, or false to cancel the redirect.
	 */
	public function prevent_protected_file_redirect( $redirect_url, $requested_url ) {
		if ( '' !== get_query_var( 'protected_file' ) ) {
			return false;
		}

		return $redirect_url;
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

		// The same URL serves either the placeholder or real file bytes depending on authentication.
		header( 'Vary: Authorization', false );

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
		$mime_type = $this->determine_mime_type( $file_path );

		/**
		 * Filter the bytes served for a protected file, allowing an integration to
		 * substitute the on-disk contents on a per-request basis (e.g. watermarking).
		 *
		 * Returning a non-string (the default `null`) leaves serving unchanged.
		 * Returning a string serves those bytes verbatim instead of the file, with
		 * range serving disabled — the substitute is per-request and differs from
		 * the on-disk file. Note that $file_path is already resolved to the requested
		 * size variant, so the substitute applies to the exact bytes being served.
		 *
		 * @since 1.1.0
		 *
		 * @param string|null $substitute_contents Substitute bytes, or null to serve the file unchanged.
		 * @param int         $attachment_id       The attachment ID.
		 * @param string      $file_path           Absolute path to the file being served.
		 * @param string      $mime_type           The resolved MIME type.
		 */
		$substitute_contents = apply_filters(
			'restrict_media_file_access_serve_contents',
			null,
			$attachment_id,
			$file_path,
			$mime_type
		);

		if ( is_string( $substitute_contents ) ) {
			$this->serve_substitute_contents( $substitute_contents, $mime_type, $attachment_id, $file_path );
			return;
		}

		$filesize_raw = filesize( $file_path );
		$filesize     = false === $filesize_raw ? 0 : $filesize_raw;
		$start        = 0;
		$end          = $filesize > 0 ? $filesize - 1 : 0;
		$is_partial   = false;

		$range_result = $this->parse_http_range( $filesize );
		if ( $range_result instanceof \WP_Error ) {
			$this->respond_with_416( $filesize );
			// phpcs:ignore
			exit;
		}

		if ( null !== $range_result ) {
			$start      = (int) $range_result['start'];
			$end        = (int) $range_result['end'];
			$is_partial = true;
		}

		$this->send_download_headers(
			$mime_type,
			$file_path,
			$filesize,
			$start,
			$end,
			$is_partial,
			$attachment_id
		);

		do_action( 'restrict_media_file_access_before_serve', $attachment_id, $file_path );

		// Clear any output buffers
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$this->output_file_range( $file_path, $start, $end );
		// phpcs:ignore
		exit;
	}

	/**
	 * Serve substitute contents in place of the on-disk file.
	 *
	 * The substitute bytes are produced per-request and differ from the file on
	 * disk, so range serving is disabled (Accept-Ranges: none) — email image
	 * proxies issue full GETs, and a Range against mismatched bytes would corrupt
	 * the response.
	 *
	 * @since   1.1.0
	 * @version 1.1.0
	 *
	 * @param string $contents      The substitute bytes to serve.
	 * @param string $mime_type     The MIME type to report.
	 * @param int    $attachment_id The attachment ID.
	 * @param string $file_path     Absolute path to the file being substituted.
	 *
	 * @return void
	 */
	private function serve_substitute_contents( string $contents, string $mime_type, int $attachment_id, string $file_path ): void {
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
		header( 'Accept-Ranges: none' );
		header( 'Content-Length: ' . strlen( $contents ) );
		$this->send_private_cache_headers( $attachment_id, $file_path );

		do_action( 'restrict_media_file_access_before_serve', $attachment_id, $file_path );

		// Clear any output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// phpcs:ignore
		echo $contents;
		exit;
	}

	/**
	 * Determine the MIME type for a file path.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param string $file_path File path.
	 *
	 * @return string
	 */
	private function determine_mime_type( string $file_path ): string {
		$mime_type = mime_content_type( $file_path );
		if ( false === $mime_type ) {
			return 'application/octet-stream';
		}
		return $mime_type;
	}

	/**
	 * Parse HTTP Range header and compute start/end.
	 * Returns empty array for no/ignored range, or array with keys start,end on success.
	 * Returns WP_Error if the range is invalid and a 416 should be sent.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param int $filesize File size in bytes.
	 *
	 * @return array{start:int,end:int}|null|\WP_Error
	 */
	private function parse_http_range( int $filesize ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$range_header = isset( $_SERVER['HTTP_RANGE'] ) ? (string) $_SERVER['HTTP_RANGE'] : '';
		if ( '' === $range_header ) {
			return null;
		}

		$matches = $this->extract_range_matches( $range_header );
		if ( null === $matches ) {
			return null;
		}

		$range_start = $matches['start'];
		$range_end   = $matches['end'];

		if ( null !== $range_start && null !== $range_end ) {
			return $this->range_from_start_end( $range_start, $range_end, $filesize );
		}

		if ( null !== $range_start ) {
			return $this->range_from_start( $range_start, $filesize );
		}

		if ( null !== $range_end ) {
			return $this->range_from_suffix_end( $range_end, $filesize );
		}

		return null;
	}

	/**
	 * Extract start/end indices from a Range header.
	 * Returns empty array when header does not match expected format.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param string $range_header Raw Range header value.
	 *
	 * @return array{start:int|null,end:int|null}|null
	 */
	private function extract_range_matches( string $range_header ): ?array {
		if ( 1 !== preg_match( '/^bytes=([0-9]*)-([0-9]*)$/', $range_header, $matches ) ) {
			return null;
		}
		$range_start = '' !== $matches[1] ? (int) $matches[1] : null;
		$range_end   = '' !== $matches[2] ? (int) $matches[2] : null;
		return array(
			'start' => $range_start,
			'end'   => $range_end,
		);
	}

	/**
	 * Build a range when both start and end are provided.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param int $range_start Start byte (inclusive).
	 * @param int $range_end   End byte (inclusive).
	 * @param int $filesize    File size in bytes.
	 *
	 * @return array{start:int,end:int}|\WP_Error
	 */
	private function range_from_start_end( int $range_start, int $range_end, int $filesize ) {
		if ( $range_start > $range_end || $range_end >= $filesize ) {
			return new \WP_Error( 'range_not_satisfiable' );
		}
		return array(
			'start' => $range_start,
			'end'   => $range_end,
		);
	}

	/**
	 * Build a range when only start is provided.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param int $range_start Start byte (inclusive).
	 * @param int $filesize    File size in bytes.
	 *
	 * @return array{start:int,end:int}|\WP_Error
	 */
	private function range_from_start( int $range_start, int $filesize ) {
		if ( $range_start >= $filesize ) {
			return new \WP_Error( 'range_not_satisfiable' );
		}
		return array(
			'start' => $range_start,
			'end'   => $filesize - 1,
		);
	}

	/**
	 * Build a range when only end suffix is provided.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param int $range_end End suffix from header (e.g., bytes=-500).
	 * @param int $filesize  File size in bytes.
	 *
	 * @return array{start:int,end:int}|\WP_Error
	 */
	private function range_from_suffix_end( int $range_end, int $filesize ) {
		if ( 0 === $filesize ) {
			return new \WP_Error( 'range_not_satisfiable' );
		}
		$length = $range_end;
		if ( $length > $filesize ) {
			$length = $filesize;
		}
		$start = $filesize - $length;
		$end   = $filesize - 1;
		return array(
			'start' => $start,
			'end'   => $end,
		);
	}

	/**
	 * Send headers for full or partial content responses.
	 *
	 * @since   1.0.1
	 * @version 1.3.0
	 *
	 * @param string $mime_type     MIME type.
	 * @param string $file_path     File path.
	 * @param int    $filesize      File size.
	 * @param int    $start         Start byte.
	 * @param int    $end           End byte.
	 * @param bool   $is_partial    Whether partial content.
	 * @param int    $attachment_id The attachment ID being served.
	 *
	 * @return void
	 */
	private function send_download_headers(
		string $mime_type,
		string $file_path,
		int $filesize,
		int $start,
		int $end,
		bool $is_partial,
		int $attachment_id
	): void {
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: inline; filename="' . basename( $file_path ) . '"' );
		header( 'Accept-Ranges: bytes' );

		if ( true === $is_partial ) {
			header( 'HTTP/1.1 206 Partial Content' );
			header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $filesize );
		}

		$content_length = ( $end - $start ) + 1;
		header( 'Content-Length: ' . $content_length );
		$this->send_private_cache_headers( $attachment_id, $file_path );
	}

	/**
	 * Send headers preventing shared caches from storing served file responses.
	 *
	 * The same protected file URL returns either the real file bytes or a
	 * placeholder depending on the requester's authentication, so a shared or
	 * edge cache storing an authenticated response would leak restricted
	 * content to anonymous visitors.
	 *
	 * @since   1.2.0
	 * @version 1.3.0
	 *
	 * @param int    $attachment_id The attachment ID being served.
	 * @param string $file_path     Absolute path to the file being served.
	 *
	 * @return void
	 */
	private function send_private_cache_headers( int $attachment_id, string $file_path ): void {
		/**
		 * Filter the Cache-Control header sent with successfully served file responses.
		 *
		 * The default forbids all caching because the same URL returns either the
		 * real bytes or a placeholder depending on the requester's authentication.
		 * Only relax this when every credential involved is part of the URL itself
		 * (e.g. an access token query argument), so caches cannot serve one
		 * requester's response to a differently-authorized requester. This filter
		 * also runs for substitute-content responses, whose bytes may be
		 * personalized per request (e.g. watermarks from a
		 * `restrict_media_file_access_serve_contents` callback) — keep the
		 * no-store default in that case regardless of how credentials are carried.
		 *
		 * @since 1.3.0
		 *
		 * @param string $cache_control The Cache-Control header value.
		 * @param int    $attachment_id The attachment ID being served.
		 * @param string $file_path     Absolute path to the file being served.
		 */
		$cache_control = apply_filters(
			'restrict_media_file_access_serve_cache_control',
			'private, no-store, max-age=0',
			$attachment_id,
			$file_path
		);

		header( 'Cache-Control: ' . $cache_control );
		header( 'Vary: Authorization', false );
	}

	/**
	 * Output a file range to the client.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param string $file_path File path.
	 * @param int    $start     Start byte.
	 * @param int    $end       End byte.
	 *
	 * @return void
	 */
	private function output_file_range( string $file_path, int $start, int $end ): void {
		$bytes_remaining = ( $end - $start ) + 1;
		$chunk_size      = 8192; // 8KB

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Binary file data should not be escaped
		$handle = fopen( $file_path, 'rb' );
		if ( false === $handle ) {
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fseek
		fseek( $handle, $start );

		while ( $bytes_remaining > 0 && ! feof( $handle ) ) {
			$read_length = $bytes_remaining > $chunk_size ? $chunk_size : $bytes_remaining;
			// phpcs:ignore -- Binary file data should not be escaped
			echo fread( $handle, $read_length );
			$bytes_remaining -= $read_length;
			flush();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
	}

	/**
	 * Send a 416 Range Not Satisfiable response and exit.
	 *
	 * @since   1.0.1
	 * @version 1.0.1
	 *
	 * @param int $filesize File size.
	 *
	 * @return void
	 */
	private function respond_with_416( int $filesize ): void {
		header( 'HTTP/1.1 416 Range Not Satisfiable' );
		header( 'Content-Range: bytes */' . $filesize );
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
			\batcache_cancel();
		}
	}
}
