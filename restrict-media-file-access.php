<?php
/**
 * The restrict-media-file-access bootstrap file.
 *
 * @since       1.0.0
 * @version     1.0.0
 * @package     A8C\SpecialProjects\Plugins
 * @author      WordPress.com Special Projects
 * @license     GPL-3.0-or-later
 *
 * @noinspection    ALL
 *
 * @wordpress-plugin
 * Plugin Name:             Restrict Media File Access
 * Plugin URI:              https://wpspecialprojects.wordpress.com
 * Description:             Restrict access to media files by custom access control.
 * Version:                 1.0.0
 * Requires at least:       6.0
 * Tested up to:            6.8
 * Requires PHP:            8.3
 * Author:                  WordPress.com Special Projects
 * Author URI:              https://wpspecialprojects.wordpress.com
 * License:                 GPL v3 or later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             restrict-media-file-access
 * Domain Path:             /languages
 **/

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'RESTRICT_MEDIA_FILE_ACCESS_BASENAME', plugin_basename( __FILE__ ) );
define( 'RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'RESTRICT_MEDIA_FILE_ACCESS_DIR_URL', plugin_dir_url( __FILE__ ) );

// Load the rest of the bootstrap functions.
require_once RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH . '/functions-bootstrap.php';

// Load plugin translations so they are available even for the error admin notices.
add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			restrict_media_file_access_get_plugin_metadata( 'TextDomain' ),
			false,
			dirname( RESTRICT_MEDIA_FILE_ACCESS_BASENAME ) . restrict_media_file_access_get_plugin_metadata( 'DomainPath' )
		);
	}
);

// Declare compatibility with WC features.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Load the autoloader.
if ( ! is_file( RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH . '/vendor/autoload.php' ) ) {
	restrict_media_file_access_output_requirements_error( new WP_Error( 'missing_autoloader' ) );
	return;
}
require_once RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH . '/vendor/autoload.php';

// Bootstrap the plugin (maybe)!
define( 'RESTRICT_MEDIA_FILE_ACCESS_REQUIREMENTS', restrict_media_file_access_validate_requirements() );
if ( is_wp_error( RESTRICT_MEDIA_FILE_ACCESS_REQUIREMENTS ) ) {
	restrict_media_file_access_output_requirements_error( RESTRICT_MEDIA_FILE_ACCESS_REQUIREMENTS );
} else {
	require_once RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH . '/functions.php';
	add_action( 'plugins_loaded', array( restrict_media_file_access_get_plugin_instance(), 'maybe_initialize' ) );
}

// Activation hook
register_activation_hook(
	__FILE__,
	function () {
		// Create protected directory if it doesn't exist
		$upload_dir    = wp_upload_dir();
		$protected_dir = $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR;

		if ( ! file_exists( $protected_dir ) ) {
			wp_mkdir_p( $protected_dir );
		}

		// Check if we need to run the initial attachment tracking setup
		$has_run_initial_setup = get_option( '_rmfa_initial_setup_completed', false );

		if ( false === $has_run_initial_setup ) {
			// Schedule the bulk operation to run after activation
			$scheduled_event = wp_schedule_single_event( time() + 5, 'rmfa_run_initial_setup' );
			if ( ! $scheduled_event ) {
				rmfa_log_error( 'Failed to schedule initial setup' );
			}
		}

		// Flush rewrite rules to ensure the new rewrite rules are loaded.
		flush_rewrite_rules();
	}
);
