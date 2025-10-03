<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class Plugin {
	/**
	 * The services.
	 *
	 * @var array<string, object>
	 */
	private $services = array();

	/**
	 * Plugin constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	protected function __construct() {
		/* Empty on purpose. */
	}

	/**
	 * Prevent cloning.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	private function __clone() {
		/* Empty on purpose. */
	}

	/**
	 * Prevent unserializing.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function __wakeup() {
		/* Empty on purpose. */
	}

	/**
	 * Returns the singleton instance of the plugin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  Plugin
	 */
	public static function get_instance(): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Returns true if all the plugin's dependencies are met.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  true|\WP_Error
	 */
	public function is_active(): bool|\WP_Error {
		return true;
	}

	/**
	 * Initializes the plugin components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	protected function initialize(): void {
		$this->services = array(
			'self-update'           => new SelfUpdate(),
			'cron'                  => new Cron(),
			'settings'              => new Settings(),
			'attachments-protector' => new AttachmentsProtector(),
			'jetpack-compatibility' => new JetpackCompatibility(),
			'attachments'           => new Attachments(),
			'attachments-admin'     => new AttachmentsAdmin(),
			'attachments-upload'    => new AttachmentsUpload(),
			'attachments-removal'   => new AttachmentsRemoval(),
			'rewrite-rules'         => new RewriteRules(),
			'cache-cleanup'         => new CacheCleanup(),
			'attachments-tracking'  => new AttachmentsTracking(),
			'rest-api'              => new RestApi(),
		);

		foreach ( $this->services as $service ) {
			$service->initialize();
		}
	}

	/**
	 * Initializes the plugin components if WooCommerce is activated.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function maybe_initialize(): void {
		$is_active = $this->is_active();
		if ( is_wp_error( $is_active ) ) {
			restrict_media_file_access_output_requirements_error( $is_active );
			return;
		}

		$this->initialize();
	}
}
