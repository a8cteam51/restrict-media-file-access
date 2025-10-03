<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Cron operations for the plugin.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Cron {

	/**
	 * Initialize the cron operations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'rmfa_run_initial_setup', array( $this, 'run_initial_setup' ) );
	}

	/**
	 * Run the initial setup.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function run_initial_setup(): void {
		$bulk_operations = new BulkOperations();
		$result          = $bulk_operations->update_all_attachments();

		update_option( '_rmfa_initial_setup_completed', wp_json_encode( $result ) );
	}
}
