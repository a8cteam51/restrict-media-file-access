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
}
