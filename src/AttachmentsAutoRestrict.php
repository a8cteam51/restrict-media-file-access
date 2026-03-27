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
 * @version 1.0.6
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
	 * @version 1.0.6
	 *
	 * @return void
	 */
	public function initialize(): void {
		add_action( 'add_attachment', array( $this, 'maybe_flag_for_auto_restrict' ) );
		add_action( 'added_post_meta', array( $this, 'maybe_auto_restrict_on_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'maybe_auto_restrict_on_meta_change' ), 10, 4 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'fix_protected_metadata' ), 100, 2 );
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
	 * Listens on both `added_post_meta` and `updated_post_meta` for the
	 * `_wp_attachment_metadata` key. WordPress writes this meta multiple
	 * times during upload — first with an empty sizes array, then again
	 * after each sub-size is generated. The readiness check ensures we
	 * only restrict once all expected sizes exist.
	 *
	 * @since   1.0.5
	 * @version 1.0.6
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $object_id  The attachment (post) ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The meta value.
	 *
	 * @return void
	 */
	public function maybe_auto_restrict_on_meta_change( int $meta_id, int $object_id, string $meta_key, $meta_value ): void {
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

		if ( ! $this->is_attachment_metadata_ready( $object_id ) ) {
			return;
		}

		self::$processing = true;

		try {
			$result = rmfa_set_file_as_protected( $object_id );

			if ( true === $result ) {
				delete_post_meta( $object_id, '_rmfa_pending_auto_restrict' );
			} else {
				rmfa_log_error(
					sprintf(
						'rmfa_auto_restrict_on_upload_failed: Could not auto-restrict attachment %d. rmfa_set_file_as_protected() returned false.',
						$object_id
					)
				);
			}
		} catch ( \Throwable $e ) {
			rmfa_log_error(
				sprintf(
					'rmfa_auto_restrict_on_upload_exception: Exception while auto-restricting attachment %d: %s',
					$object_id,
					$e->getMessage()
				)
			);
		} finally {
			self::$processing = false;
		}
	}

	/**
	 * Determine whether attachment metadata is ready for protection.
	 *
	 * WordPress writes `_wp_attachment_metadata` before all intermediate
	 * sizes are generated, then updates it after each size. This method
	 * returns false until all expected sizes exist, preventing premature
	 * file moves that would leave sub-size files in the wrong directory.
	 *
	 * @since   1.0.5
	 * @version 1.0.6
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool Whether the attachment metadata is ready.
	 */
	private function is_attachment_metadata_ready( int $attachment_id ): bool {
		if ( wp_attachment_is_image( $attachment_id ) ) {
			if ( function_exists( 'wp_get_missing_image_subsizes' ) ) {
				return empty( wp_get_missing_image_subsizes( $attachment_id ) );
			}

			return true;
		}

		if ( 'application/pdf' === get_post_mime_type( $attachment_id ) ) {
			return $this->is_pdf_metadata_ready( $attachment_id );
		}

		return true;
	}

	/**
	 * Determine whether PDF attachment metadata is ready for protection.
	 *
	 * PDFs get a JPEG preview image and sub-sizes (thumbnail, medium, large)
	 * generated from that preview. The first metadata write contains only
	 * the 'full' preview, and sub-sizes are added one at a time. Wait until
	 * all expected fallback sizes have been generated.
	 *
	 * @since   1.0.6
	 * @version 1.0.6
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return bool Whether the PDF metadata is ready.
	 */
	private function is_pdf_metadata_ready( int $attachment_id ): bool {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) ) {
			return true;
		}

		$fallback_sizes   = array( 'thumbnail', 'medium', 'large' );
		$fallback_sizes   = apply_filters( 'fallback_intermediate_image_sizes', $fallback_sizes, $metadata );
		$registered_sizes = wp_get_registered_image_subsizes();
		$expected         = array_intersect_key( $registered_sizes, array_flip( $fallback_sizes ) );

		foreach ( array_keys( $expected ) as $size_name ) {
			if ( ! isset( $metadata['sizes'][ $size_name ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Ensure metadata stays consistent for protected attachments.
	 *
	 * When auto-restriction runs inside the sub-size generation stack, the
	 * outer wp_update_attachment_metadata() call in media_handle_upload()
	 * can overwrite the corrected file path with the original one. This
	 * filter re-applies the protected prefix by checking the actual stored
	 * attached file path, and backfills filesize when missing.
	 *
	 * @since   1.0.6
	 * @version 1.0.6
	 *
	 * @param array<mixed> $data          The attachment metadata.
	 * @param int          $attachment_id The attachment ID.
	 *
	 * @return array<mixed> Corrected metadata.
	 */
	public function fix_protected_metadata( $data, int $attachment_id ) {
		if ( ! is_array( $data ) || empty( $data['file'] ) ) {
			return $data;
		}

		$attached_file = get_attached_file( $attachment_id );

		if ( ! $attached_file ) {
			return $data;
		}

		$upload_dir    = wp_upload_dir();
		$protected_dir = $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/';

		if ( 0 !== strpos( $attached_file, $protected_dir ) ) {
			return $data;
		}

		$protected_prefix = RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/';

		if ( 0 !== strpos( $data['file'], $protected_prefix ) ) {
			$data['file'] = $protected_prefix . $data['file'];
		}

		if ( empty( $data['filesize'] ) && file_exists( $attached_file ) ) {
			$data['filesize'] = wp_filesize( $attached_file );
		}

		return $data;
	}
}
