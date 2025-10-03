<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main attachments removal class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class AttachmentsRemoval {

	/**
	 * Attachments tracking instance.
	 *
	 * @var AttachmentsTracking
	 */
	public AttachmentsTracking $attachments_tracking;

	/**
	 * Initialize the attachments removal.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->attachments_tracking = new AttachmentsTracking();

		// Handle attachment deletion
		add_action( 'delete_attachment', array( $this, 'handle_attachment_deletion' ), 10, 1 );
	}

	/**
	 * Handle cleanup of protected files when an attachment is deleted.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The ID of the attachment being deleted.
	 *
	 * @return void
	 */
	public function handle_attachment_deletion( int $attachment_id ): void {
		// Get posts that use this attachment before deletion
		$affected_posts = rmfa_get_posts_with_media_url( $attachment_id );

		// Remove this attachment from all posts that use it
		if ( ! empty( $affected_posts ) ) {
			foreach ( $affected_posts as $post_id ) {
				$this->attachments_tracking->remove_attachment_from_post( $attachment_id, $post_id );
			}
		}

		// Check if this was a protected file
		$is_restricted = rmfa_is_media_restricted( $attachment_id );
		if ( $is_restricted ) {
			$this->delete_protected_files( $attachment_id );
		}

		// Clear any cached data
		$this->delete_cache( $attachment_id );
	}

	/**
	 * Delete protected files.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The ID of the attachment being deleted.
	 *
	 * @return void
	 */
	private function delete_protected_files( int $attachment_id ): void {
		$upload_dir    = wp_upload_dir();
		$protected_dir = $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR;

		// Get file path and metadata
		$file_path = get_attached_file( $attachment_id );

		// If the file path is false, the file doesn't exist, so we can't delete it.
		if ( false === $file_path ) {
			return;
		}

		// Delete the main file from protected directory if it exists
		Filesystem::delete( $file_path );

		$metadata = wp_get_attachment_metadata( $attachment_id, true );

		// If the metadata is false, the files don't exist, so we can't delete them.
		if ( false === $metadata ) {
			return;
		}

		$this->delete_size_variations( $file_path, $metadata, $protected_dir );
	}

	/**
	 * Delete size variations.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string       $file_path      The file path.
	 * @param array<mixed> $metadata       The attachment metadata.
	 * @param string       $protected_dir  The protected directory.
	 *
	 * @return void
	 */
	private function delete_size_variations( string $file_path, array $metadata, string $protected_dir ): void {
		if ( empty( $metadata['sizes'] ) ) {
			return;
		}

		$upload_dir    = wp_upload_dir();
		$relative_path = str_replace(
			$upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/',
			'',
			$file_path
		);
		$relative_dir  = dirname( $relative_path );

		// Handle flat structure
		$protected_base_path = '.' === $relative_dir ?
			$protected_dir :
			$protected_dir . '/' . $relative_dir;

		foreach ( $metadata['sizes'] as $size_info ) {
			$this->delete_size_variation( $size_info, $protected_base_path, $protected_dir, $relative_dir );
		}

		// Clean up empty directories
		if ( '.' !== $relative_dir ) {
			$dir_to_check = $protected_dir . '/' . $relative_dir;
			if ( Filesystem::exists( $dir_to_check ) && $this->is_directory_empty( $dir_to_check ) ) {
				Filesystem::delete_recursive( $dir_to_check );
			}
		}
	}

	/**
	 * Delete size variation.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<mixed> $size_info          The size information.
	 * @param string       $protected_base_path The protected base path.
	 * @param string       $protected_dir      The protected directory.
	 * @param string       $relative_dir       The relative directory.
	 *
	 * @return void
	 */
	private function delete_size_variation( array $size_info, string $protected_base_path, string $protected_dir, string $relative_dir ): void {
		// Try all possible locations
		$possible_locations = array(
			// New structured path
			$protected_base_path . '/' . $size_info['file'],
			// Old flat structure
			$protected_dir . '/' . $size_info['file'],
			// Just year structure
			$protected_dir . '/' . substr( $relative_dir, 0, 4 ) . '/' . $size_info['file'],
		);

		foreach ( $possible_locations as $size_file ) {
			if ( Filesystem::exists( $size_file ) ) {
				Filesystem::delete( $size_file );
			}
		}
	}

	/**
	 * Delete cache.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The ID of the attachment being deleted.
	 *
	 * @return void
	 */
	private function delete_cache( int $attachment_id ): void {
		$file_hash = rmfa_get_media_protected_file_hash( $attachment_id );
		if ( null !== $file_hash ) {
			rmfa_delete_cache( 'rmfa_attachment_id_' . $file_hash );
		}
		rmfa_delete_cache( 'rmfa_media_post_ids_' . $attachment_id );
	}

	/**
	 * Check if a directory is empty.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $dir Directory path to check.
	 *
	 * @return bool True if directory is empty or doesn't exist, false otherwise.
	 */
	private function is_directory_empty( string $dir ): bool {
		if ( ! Filesystem::exists( $dir ) ) {
			return true;
		}

		$files = Filesystem::dirlist( $dir, array( 'include_hidden' => false ) );

		return false === $files || 0 === count( $files );
	}
}
