<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main rewrite rules class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class RewriteRules {
	/**
	 * Initialize the rewrite rules.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Setup rewrite rules
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );

		// Setup query vars
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

		// Flush rewrite rules after init if activation flag is set.
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 999 );
	}

	/**
	 * Add rewrite rules for protected files.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/([^/]+)',
			'index.php?protected_file=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query vars for protected files
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string[] $query_vars Existing query vars.
	 *
	 * @return string[] Modified query vars
	 */
	public function add_query_vars( array $query_vars ): array {
		$query_vars[] = 'protected_file';

		return $query_vars;
	}

	/**
	 * Flush rewrite rules if activation flag is set.
	 *
	 * This ensures rewrite rules are registered before flushing.
	 *
	 * @since   1.0.3
	 * @version 1.0.3
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( false !== get_transient( '_rmfa_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( '_rmfa_flush_rewrite_rules' );
		}
	}
}
