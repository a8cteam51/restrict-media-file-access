<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Allows application passwords to authenticate protected file requests.
 *
 * @since   1.2.0
 * @version 1.2.0
 */
class ApplicationPasswords {

	/**
	 * Initialize the application password integration.
	 *
	 * @since   1.2.0
	 * @version 1.2.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_filter( 'application_password_is_api_request', array( $this, 'allow_protected_file_requests' ) );
	}

	/**
	 * Treat protected file requests as API requests so application passwords authenticate them.
	 *
	 * WordPress core only honors application passwords on REST API and XML-RPC
	 * requests. Protected files are served through a front-end rewrite, so
	 * without this filter a valid application password is silently ignored and
	 * the request is treated as anonymous.
	 *
	 * @since   1.2.0
	 * @version 1.2.0
	 *
	 * @param bool $is_api_request Whether the request is an API request.
	 *
	 * @return bool
	 */
	public function allow_protected_file_requests( $is_api_request ): bool {
		if ( true === (bool) $is_api_request ) {
			return true;
		}

		/**
		 * Filter whether application passwords may authenticate protected file requests.
		 *
		 * @since 1.2.0
		 *
		 * @param bool $enabled Whether application password access is enabled.
		 */
		if ( false === apply_filters( 'rmfa_enable_application_password_file_access', true ) ) {
			return false;
		}

		return $this->is_protected_file_request();
	}

	/**
	 * Check if the current request is a GET request for a protected file.
	 *
	 * Restricted to GET so authentication is only broadened for the read-only
	 * file-serving route, keeping state-changing front-end requests out of scope.
	 *
	 * @since   1.2.0
	 * @version 1.2.0
	 *
	 * @return bool
	 */
	private function is_protected_file_request(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';
		if ( 'GET' !== $method ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only used for a path prefix comparison.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return false;
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? trailingslashit( $home_path ) : '/';
		$prefix    = $home_path . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/';

		return str_starts_with( $path, $prefix );
	}
}
