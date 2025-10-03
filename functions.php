<?php declare( strict_types=1 );

use A8C\SpecialProjects\RestrictMediaFileAccess\Plugin;

defined( 'ABSPATH' ) || exit;

// region META

/**
 * Returns the plugin's main class instance.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  Plugin
 */
function restrict_media_file_access_get_plugin_instance(): Plugin {
	return Plugin::get_instance();
}

// endregion

// region OTHERS

$restrict_media_file_access_files = glob( constant( 'RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH' ) . 'includes/*.php' );
if ( false !== $restrict_media_file_access_files ) {
	foreach ( $restrict_media_file_access_files as $restrict_media_file_access_file ) {
		if ( 1 === preg_match( '#/includes/_#i', $restrict_media_file_access_file ) ) {
			continue; // Ignore files prefixed with an underscore.
		}

		require_once $restrict_media_file_access_file;
	}
}

/**
 * Log an error message.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param string $message The message to log.
 */
function rmfa_log_error( string $message ): void {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( 'RMFA: ' . $message );
}

// endregion
