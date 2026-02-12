<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Handles auto-restriction of media files on upload.
 *
 * Provides a filter (`rmfa_auto_restrict_on_upload`) that external code
 * can use to decide whether a newly uploaded attachment should be
 * automatically restricted. The actual restriction is deferred until
 * attachment metadata has been fully saved.
 *
 * @since   1.0.5
 * @version 1.0.5
 */
class AttachmentsAutoRestrict {

	/**
	 * Whether a restriction operation is currently in progress.
	 *
	 * Prevents recursive calls when rmfa_set_file_as_protected()
	 * internally updates attachment metadata.
	 *
	 * @var bool
	 */
	private static bool $processing = false;

	/**
	 * Initialize the auto-restrict hooks.
	 *
	 * @since   1.0.5
	 * @version 1.0.5
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'add_attachment', array( $this, 'maybe_flag_for_auto_restrict' ) );
		add_action( 'added_post_meta', array( $this, 'maybe_auto_restrict_on_meta_add' ), 10, 4 );
	}

	/**
	 * Flag a newly uploaded attachment for auto-restriction if the filter allows it.
	 *
	 * Fires on `add_attachment`, which runs inside wp_insert_attachment()
	 * before metadata is generated. Stores a pending flag so that the
	 * actual restriction can happen after metadata is fully saved.
	 *
	 * @since   1.0.5
	 * @version 1.0.5
	 *
	 * @param int $attachment_id The new attachment ID.
	 *
	 * @return void
	 */
	public function maybe_flag_for_auto_restrict( int $attachment_id ): void {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment instanceof \WP_Post ) {
			return;
		}

		/**
		 * Filter whether a newly uploaded attachment should be auto-restricted.
		 *
		 * Return true to automatically restrict the file after upload completes.
		 * The restriction will be applied once attachment metadata (image sizes
		 * etc.) has been fully generated and saved.
		 *
		 * @since   1.0.5
		 * @version 1.0.5
		 *
		 * @param bool     $should_restrict Whether to auto-restrict. Default false.
		 * @param int      $attachment_id   The attachment ID.
		 * @param \WP_Post $attachment      The attachment post object.
		 *
		 * @return bool Whether to auto-restrict.
		 */
		$should_restrict = apply_filters( 'rmfa_auto_restrict_on_upload', false, $attachment_id, $attachment );

		if ( true === $should_restrict ) {
			update_post_meta( $attachment_id, '_rmfa_pending_auto_restrict', '1' );
		}
	}

	/**
	 * Perform auto-restriction after attachment metadata has been saved.
	 *
	 * Listens on `added_post_meta` for the `_wp_attachment_metadata` key,
	 * which fires after wp_update_attachment_metadata() saves the generated
	 * sizes for a new upload. At this point it is safe to call
	 * rmfa_set_file_as_protected() because all size variations exist on disk.
	 *
	 * @since   1.0.5
	 * @version 1.0.5
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $object_id  The attachment (post) ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 *
	 * @return void
	 */
	public function maybe_auto_restrict_on_meta_add( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		if ( '_wp_attachment_metadata' !== $meta_key ) {
			return;
		}

		if ( self::$processing ) {
			return;
		}

		$pending = get_post_meta( $object_id, '_rmfa_pending_auto_restrict', true );

		if ( '1' !== $pending ) {
			return;
		}

		self::$processing = true;

		delete_post_meta( $object_id, '_rmfa_pending_auto_restrict' );
		rmfa_set_file_as_protected( $object_id );

		self::$processing = false;
	}
}
