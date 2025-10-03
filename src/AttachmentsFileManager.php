<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main attachments file manager class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class AttachmentsFileManager {

	/**
	 * Set file as protected.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int                $attachment_id The attachment ID.
	 * @param array<string,bool> $options The options: update_post (bool).
	 *
	 * @return bool True if the file was protected, false if it was already protected.
	 */
	public function set_file_as_protected( int $attachment_id, array $options = array() ): bool {
		if ( '1' !== get_post_meta( $attachment_id, '_restricted_file', true ) ) {
			// Move file to protected directory
			if ( false === $this->move_to_protected_directory( $attachment_id, $options ) ) {
				rmfa_log_error( 'rmfa_set_file_as_protected_move_to_protected_directory_failed:' . $attachment_id );
				return false;
			}

			// Set the file as restricted
			update_post_meta( $attachment_id, '_restricted_file', '1' );

			// Clear the cache for the media post IDs
			rmfa_delete_cache( 'rmfa_media_post_ids_' . $attachment_id );

			return true;
		}

		// File is already protected.
		return false;
	}

	/**
	 * Set file as unprotected.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int                $attachment_id The attachment ID.
	 * @param array<string,bool> $options The options: update_post (bool).
	 *
	 * @return bool True if the file was unprotected, false if it was already unprotected.
	 */
	public function set_file_as_unprotected( int $attachment_id, array $options = array() ): bool {
		if ( '1' === get_post_meta( $attachment_id, '_restricted_file', true ) ) {
			// Move file back to regular uploads directory
			if ( false === $this->move_back_to_uploads_directory( $attachment_id, $options ) ) {
				rmfa_log_error( 'rmfa_set_file_as_unprotected_move_back_to_uploads_directory_failed:' . $attachment_id );
				return false;
			}

			// Delete the restricted file meta
			delete_post_meta( $attachment_id, '_restricted_file' );

			// Clear the cache for the media post IDs
			rmfa_delete_cache( 'rmfa_media_post_ids_' . $attachment_id );

			return true;
		}

		// File is already unprotected.
		return false;
	}

	/**
	 * Move file to protected directory.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int                $attachment_id Attachment ID.
	 * @param array<string,bool> $options The options: update_post (bool).
	 *
	 * @return bool True if the file was moved to the protected directory, false otherwise.
	 */
	private function move_to_protected_directory( int $attachment_id, array $options = array() ): bool {
		$file_path = get_attached_file( $attachment_id );
		if ( false === $file_path ) {
			return false;
		}

		$this->ensure_file_hash( $attachment_id );
		$upload_dir    = wp_upload_dir();
		$protected_dir = $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR;

		// Get the relative path from uploads directory, maintaining original structure
		$relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );
		$relative_dir  = dirname( $relative_path );

		// If the relative directory is '.', it means flat structure
		$new_protected_dir = '.' === $relative_dir ?
			$protected_dir :
			$protected_dir . '/' . $relative_dir;

		// Create protected directory with same structure
		if ( ! Filesystem::exists( $new_protected_dir ) ) {
			wp_mkdir_p( $new_protected_dir );
		}

		// Get all sizes of the attachment
		$metadata = wp_get_attachment_metadata( $attachment_id, true );

		if ( false === $metadata ) {
			rmfa_log_error( 'rmfa_move_to_protected_directory_metadata_not_found:' . $attachment_id );
			return false;
		}

		$old_media_url = wp_get_attachment_url( $attachment_id );

		if ( false === $old_media_url ) {
			rmfa_log_error( 'rmfa_move_to_protected_directory_old_media_url_not_found:' . $attachment_id );
			return false;
		}

		$old_media_metadata = $metadata;

		$this->store_original_paths( $attachment_id, $file_path, $metadata );
		$this->move_files_to_protected_directory( $attachment_id, $file_path, $new_protected_dir, $metadata );
		$this->update_metadata_for_protected_file( $attachment_id, $metadata, $relative_path );

		$new_media_url = wp_get_attachment_url( $attachment_id );

		if ( false === $new_media_url ) {
			rmfa_log_error( 'rmfa_move_to_protected_directory_new_media_url_not_found:' . $attachment_id );
			return false;
		}

		$new_media_metadata = wp_get_attachment_metadata( $attachment_id, true );

		if ( false === $new_media_metadata ) {
			rmfa_log_error( 'rmfa_move_to_protected_directory_new_media_metadata_not_found:' . $attachment_id );
			return false;
		}

		$this->replace_file_urls( $attachment_id, $old_media_url, $old_media_metadata, $new_media_url, $new_media_metadata, 'protected', $options );

		return true;
	}

	/**
	 * Move file back to regular uploads directory.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int                $attachment_id Attachment ID.
	 * @param array<string,bool> $options The options: update_post (bool).
	 *
	 * @return bool True if the file was moved back to the uploads directory, false otherwise.
	 */
	private function move_back_to_uploads_directory( int $attachment_id, array $options = array() ): bool {
		$file_path = get_attached_file( $attachment_id );
		if ( false === $file_path ) {
			return false;
		}

		$upload_dir = wp_upload_dir();

		if ( strpos( $file_path, $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/' ) === 0 ) {
			// Get original file paths
			$original_file_path   = get_post_meta( $attachment_id, '_original_file_path', true );
			$original_sizes_paths = get_post_meta( $attachment_id, '_original_sizes_paths', true );

			if ( empty( $original_file_path ) ) {
				rmfa_log_error( 'rmfa_move_back_to_uploads_directory_original_file_path_not_found:' . $attachment_id );
				return false;
			}

			if ( empty( $original_sizes_paths ) ) {
				$original_sizes_paths = null;
			}

			// Get all sizes of the attachment
			$metadata = wp_get_attachment_metadata( $attachment_id, true );

			if ( false === $metadata ) {
				rmfa_log_error( 'rmfa_move_back_to_uploads_directory_metadata_not_found:' . $attachment_id );
				return false;
			}

			$old_media_url = wp_get_attachment_url( $attachment_id );

			if ( false === $old_media_url ) {
				rmfa_log_error( 'rmfa_move_back_to_uploads_directory_old_media_url_not_found:' . $attachment_id );
				return false;
			}

			$old_media_metadata = $metadata;

			$this->move_files_back_to_original_location( $attachment_id, $file_path, $original_file_path, $metadata, $original_sizes_paths );
			$this->update_metadata_for_unprotected_file( $attachment_id, $metadata, $original_file_path );
			$this->cleanup_original_paths( $attachment_id );

			$new_media_url = wp_get_attachment_url( $attachment_id );

			if ( false === $new_media_url ) {
				rmfa_log_error( 'rmfa_move_back_to_uploads_directory_new_media_url_not_found:' . $attachment_id );
				return false;
			}

			$new_media_metadata = wp_get_attachment_metadata( $attachment_id, true );

			if ( false === $new_media_metadata ) {
				rmfa_log_error( 'rmfa_move_back_to_uploads_directory_new_media_metadata_not_found:' . $attachment_id );
				return false;
			}

			$this->replace_file_urls( $attachment_id, $old_media_url, $old_media_metadata, $new_media_url, $new_media_metadata, 'unprotected', $options );

			return true;
		}

		return false;
	}

	/**
	 * Replace file URLs in post content using tracking system.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int                 $attachment_id      The attachment ID.
	 * @param string              $old_media_url      The old media URL.
	 * @param array<string,mixed> $old_media_metadata The old media metadata.
	 * @param string              $new_media_url      The new media URL.
	 * @param array<string,mixed> $new_media_metadata The new media metadata.
	 * @param string              $type               The type of file (protected or unprotected).
	 * @param array<string,bool>  $options The options: update_post (bool).
	 *
	 * @return void
	 */
	public function replace_file_urls( int $attachment_id, string $old_media_url, array $old_media_metadata, string $new_media_url, array $new_media_metadata, string $type, array $options = array() ): void {
		$urls_map    = array();
		$update_post = isset( $options['update_post'] ) ? $options['update_post'] : true;

		if ( $old_media_url === $new_media_url ) {
			return;
		}

		$urls_map = $this->build_urls_map( $attachment_id, $old_media_url, $new_media_url, $old_media_metadata, $new_media_metadata, $type );

		update_post_meta( $attachment_id, '_restricted_file_urls_map', $urls_map );

		$this->update_urls_in_posts( $attachment_id, $urls_map, $update_post );
	}

	/**
	 * Build URLs map.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int          $attachment_id      The attachment ID.
	 * @param string       $old_media_url      The old media URL.
	 * @param string       $new_media_url      The new media URL.
	 * @param array<mixed> $old_media_metadata The old media metadata.
	 * @param array<mixed> $new_media_metadata The new media metadata.
	 * @param string       $type               The type of file (protected or unprotected).
	 *
	 * @return array<string,string> The URLs map.
	 */
	private function build_urls_map( int $attachment_id, string $old_media_url, string $new_media_url, array $old_media_metadata, array $new_media_metadata, string $type ): array {
		$urls_map = array();

		if ( 'protected' === $type ) {
			if ( ! empty( $old_media_metadata['sizes'] ) ) {
				foreach ( $old_media_metadata['sizes'] as $size_info ) {
					$old_size_url              = str_replace( basename( $old_media_url ), $size_info['file'], $old_media_url );
					$new_size_url              = $new_media_url . '-' . $size_info['width'] . 'x' . $size_info['height'];
					$urls_map[ $old_size_url ] = $new_size_url;
				}
			}

			// Replace the full size URL
			$urls_map[ $old_media_url ] = $new_media_url;
			return $urls_map;
		}

		// Normalize the URLs in case of query args.
		$old_path = (string) wp_parse_url( $old_media_url, PHP_URL_PATH );
		$new_path = (string) wp_parse_url( $new_media_url, PHP_URL_PATH );

		// Use the meta hash as the source of truth.
		$old_hash = rmfa_get_media_protected_file_hash( $attachment_id );
		if ( null === $old_hash ) {
			$old_hash = basename( $old_path );
		}

			// Build protected size URLs from the constant path + meta hash.
		if ( ! empty( $new_media_metadata['sizes'] ) ) {
			foreach ( $new_media_metadata['sizes'] as $size_info ) {
				$old_size_url              = home_url(
					'/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' . $old_hash
					. '-' . $size_info['width'] . 'x' . $size_info['height']
				);
				$new_size_url              = home_url( str_replace( basename( $new_path ), $size_info['file'], $new_path ) );
				$urls_map[ $old_size_url ] = $new_size_url;
			}
		}

		// Full-size mapping.
		$urls_map[ home_url( $old_path ) ] = home_url( $new_path );
		return $urls_map;
	}

	/**
	 * Update URLs in posts.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int                  $attachment_id The attachment ID.
	 * @param array<string,string> $urls_map The URLs map.
	 * @param bool                 $update_post Whether to update the post.
	 */
	private function update_urls_in_posts( int $attachment_id, array $urls_map, bool $update_post ): void {
		$affected_posts = rmfa_get_posts_with_media_url( $attachment_id );
		if ( ! empty( $affected_posts ) ) {
			$this->update_tracked_posts_content( $affected_posts, $urls_map, $update_post );
		}
	}

	/**
	 * Update content in tracked posts.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<int>           $post_ids Array of post IDs to update.
	 * @param array<string,string> $urls_map Array of URL mappings.
	 * @param bool                 $update_post Whether to trigger post update actions.
	 *
	 * @return void
	 */
	private function update_tracked_posts_content( array $post_ids, array $urls_map, bool $update_post ): void {
		global $wpdb;

		foreach ( $post_ids as $post_id ) {
			// Get the post content
			$post_content = get_post_field( 'post_content', $post_id );

			if ( empty( $post_content ) ) {
				continue;
			}

			$updated_content = $post_content;
			$content_changed = false;

			// Replace all URLs in the content
			foreach ( $urls_map as $search_url => $replace_url ) {
				if ( strpos( $updated_content, $search_url ) !== false ) {
					$updated_content = str_replace( $search_url, $replace_url, $updated_content );
					$content_changed = true;
				}
			}

			// Only update if content actually changed
			if ( $content_changed ) {
				// Update the post content directly
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $updated_content ),
					array( 'ID' => $post_id ),
					array( '%s' ),
					array( '%d' )
				);

				// Clear post cache
				clean_post_cache( $post_id );

				// Trigger post update if requested
				if ( $update_post ) {
					wp_update_post(
						array(
							'ID'    => $post_id,
							'title' => get_the_title( $post_id ),
						)
					);
				}
			}
		}
	}

	/**
	 * Get salt for file hashing.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return string
	 */
	private function get_hash_salt(): string {
		// Try to use WordPress auth salt first
		if ( defined( 'AUTH_SALT' ) ) {
			return AUTH_SALT;
		}

		// Fallback to plugin-specific salt
		$salt = get_option( 'rmfa_hash_salt' );
		if ( empty( $salt ) ) {
			$salt = wp_generate_password( 64, true, true );
			update_option( 'rmfa_hash_salt', $salt );
		}

		return $salt;
	}

	/**
	 * Ensure file has a hash.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	private function ensure_file_hash( int $attachment_id ): void {
		$file_hash = rmfa_get_media_protected_file_hash( $attachment_id );
		if ( empty( $file_hash ) ) {
			$salt      = $this->get_hash_salt();
			$file_path = get_attached_file( $attachment_id );

			// If the file path is not found, log an error and return.
			if ( false === $file_path ) {
				rmfa_log_error( 'rmfa_ensure_file_hash_file_path_not_found:' . $attachment_id );
				return;
			}

			$file_title = basename( $file_path );
			$random_str = wp_generate_password( 8, false );
			$file_hash  = hash( 'sha256', $salt . $file_title . $random_str );
			update_post_meta( $attachment_id, '_protected_file_hash', $file_hash );
		}
	}

	/**
	 * Store original file paths.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int          $attachment_id The attachment ID.
	 * @param string       $file_path     The file path.
	 * @param array<mixed> $metadata      The attachment metadata.
	 */
	private function store_original_paths( int $attachment_id, string $file_path, array $metadata ): void {
		update_post_meta( $attachment_id, '_original_file_path', $file_path );
		if ( ! empty( $metadata['sizes'] ) ) {
			$original_sizes_paths = array();
			$file_dir             = dirname( $file_path );
			foreach ( $metadata['sizes'] as $size => $size_info ) {
				$original_sizes_paths[ $size ] = $file_dir . '/' . $size_info['file'];
			}
			update_post_meta( $attachment_id, '_original_sizes_paths', $original_sizes_paths );
		}
	}

	/**
	 * Move files to protected directory.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int          $attachment_id    The attachment ID.
	 * @param string       $file_path        The file path.
	 * @param string       $new_protected_dir The new protected directory.
	 * @param array<mixed> $metadata         The attachment metadata.
	 */
	private function move_files_to_protected_directory( int $attachment_id, string $file_path, string $new_protected_dir, array $metadata ): void {
		$filename             = basename( $file_path );
		$extension            = pathinfo( $filename, PATHINFO_EXTENSION );
		$filename_without_ext = pathinfo( $filename, PATHINFO_FILENAME );

		// Move the main file preserving directory structure
		$new_path = $new_protected_dir . '/' . basename( $file_path );
		if ( $file_path !== $new_path && Filesystem::exists( $file_path ) ) {
			Filesystem::move( $file_path, $new_path );
			update_attached_file( $attachment_id, $new_path );
		}

		// Check if the current file is a scaled version and move the original image as well
		if ( str_ends_with( $filename_without_ext, '-scaled' ) ) {
			$original_filename   = str_replace( '-scaled', '', $filename_without_ext ) . '.' . $extension;
			$original_image_path = dirname( $file_path ) . '/' . $original_filename;

			if ( Filesystem::exists( $original_image_path ) ) {
				$new_original_path = $new_protected_dir . '/' . $original_filename;
				Filesystem::move( $original_image_path, $new_original_path );
			}
		}

		// Move all size variations preserving directory structure
		if ( ! empty( $metadata['sizes'] ) ) {
			$file_dir = dirname( $file_path );
			foreach ( $metadata['sizes'] as $size_info ) {
				$size_file = $file_dir . '/' . $size_info['file'];
				if ( Filesystem::exists( $size_file ) ) {
					$new_size_path = $new_protected_dir . '/' . $size_info['file'];
					Filesystem::move( $size_file, $new_size_path );
				}
			}
		}
	}

	/**
	 * Update metadata for protected file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int          $attachment_id The attachment ID.
	 * @param array<mixed> $metadata      The attachment metadata.
	 * @param string       $relative_path The relative path.
	 */
	private function update_metadata_for_protected_file( int $attachment_id, array $metadata, string $relative_path ): void {
		if ( ! empty( $metadata ) ) {
			if ( ! empty( $metadata['file'] ) ) {
				$metadata['file'] = RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/' . $relative_path;
			}
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}

	/**
	 * Move files back to original location.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int                       $attachment_id        The attachment ID.
	 * @param string                    $file_path            The current file path.
	 * @param string                    $original_file_path   The original file path.
	 * @param array<mixed>              $metadata             The attachment metadata.
	 * @param array<string,string>|null $original_sizes_paths The original sizes paths.
	 */
	private function move_files_back_to_original_location( int $attachment_id, string $file_path, string $original_file_path, array $metadata, ?array $original_sizes_paths ): void {
		// Move the main file back to its original location
		if ( Filesystem::exists( $file_path ) ) {
			wp_mkdir_p( dirname( $original_file_path ) );
			Filesystem::move( $file_path, $original_file_path );
			update_attached_file( $attachment_id, $original_file_path );
		}

		$filename             = basename( $file_path );
		$extension            = pathinfo( $filename, PATHINFO_EXTENSION );
		$filename_without_ext = pathinfo( $filename, PATHINFO_FILENAME );

		$upload_dir    = wp_upload_dir();
		$protected_dir = $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR;

		$relative_path = str_replace(
			$upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/',
			'',
			$file_path
		);
		$relative_dir  = dirname( $relative_path );

		// Handle flat structure
		$protected_base_path = '.' === $relative_dir ?
			$upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR :
			$upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/' . $relative_dir;

		// Check if the current file is a scaled version and move the original image back as well
		if ( str_ends_with( $filename_without_ext, '-scaled' ) ) {
			$original_filename             = str_replace( '-scaled', '', $filename_without_ext ) . '.' . $extension;
			$original_image_path           = dirname( $original_file_path ) . '/' . $original_filename;
			$protected_original_image_path = $protected_base_path . '/' . $original_filename;

			if ( Filesystem::exists( $protected_original_image_path ) ) {
				wp_mkdir_p( dirname( $original_image_path ) );
				Filesystem::move( $protected_original_image_path, $original_image_path );
			}
		}

		// Move all size variations back to their original locations
		if ( ! empty( $metadata['sizes'] ) && is_array( $original_sizes_paths ) ) {
			foreach ( $metadata['sizes'] as $size => $size_info ) {
				$this->move_size_variation_back( $size_info, $protected_base_path, $protected_dir, $relative_dir, $original_sizes_paths, $size );
			}
		}
	}

	/**
	 * Move size variation back to original location.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<mixed>         $size_info           The size information.
	 * @param string               $protected_base_path The protected base path.
	 * @param string               $protected_dir       The protected directory.
	 * @param string               $relative_dir        The relative directory.
	 * @param array<string,string> $original_sizes_paths The original sizes paths.
	 * @param string               $size                The size.
	 */
	private function move_size_variation_back( array $size_info, string $protected_base_path, string $protected_dir, string $relative_dir, array $original_sizes_paths, string $size ): void {
		// Try all possible locations
		$possible_locations = array(
			// New structured path
			$protected_base_path . '/' . $size_info['file'],
			// Old flat structure
			$protected_dir . '/' . $size_info['file'],
			// Just year structure
			$protected_dir . '/' . substr( $relative_dir, 0, 4 ) . '/' . $size_info['file'],
		);

		$protected_size_file = null;
		foreach ( $possible_locations as $location ) {
			if ( Filesystem::exists( $location ) ) {
				$protected_size_file = $location;
				break;
			}
		}

		if ( null !== $protected_size_file && isset( $original_sizes_paths[ $size ] ) ) {
			wp_mkdir_p( dirname( $original_sizes_paths[ $size ] ) );
			Filesystem::move( $protected_size_file, $original_sizes_paths[ $size ] );
		}
	}

	/**
	 * Update metadata for unprotected file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int          $attachment_id      The attachment ID.
	 * @param array<mixed> $metadata           The attachment metadata.
	 * @param string       $original_file_path The original file path.
	 */
	private function update_metadata_for_unprotected_file( int $attachment_id, array $metadata, string $original_file_path ): void {
		if ( ! empty( $metadata ) ) {
			$upload_dir       = wp_upload_dir();
			$metadata['file'] = str_replace( $upload_dir['basedir'] . '/', '', $original_file_path );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
	}

	/**
	 * Cleanup original paths.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	private function cleanup_original_paths( int $attachment_id ): void {
		delete_post_meta( $attachment_id, '_original_file_path' );
		delete_post_meta( $attachment_id, '_original_sizes_paths' );
	}
}
