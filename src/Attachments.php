<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main attachments class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Attachments {
	// region FIELDS AND CONSTANTS

	// endregion

	// region MAGIC METHODS

	/**
	 * Initialize the attachments class.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Modify attachment URLs
		add_filter( 'wp_get_attachment_url', array( $this, 'modify_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'modify_attachment_metadata' ), 10, 2 );

		// Add filter for image srcset and sizes
		add_filter( 'wp_calculate_image_srcset', array( $this, 'modify_image_srcset' ), 10, 5 );

		// Add filter for attachment URL to post ID conversion
		add_filter( 'attachment_url_to_postid', array( $this, 'modify_attachment_url_to_postid' ), 10, 2 );

		// Add filter for attachment image attributes
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'modify_attachment_image_attributes' ), 10, 2 );
	}

	/**
	 * Modify attachment URL for protected files
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $url The attachment URL.
	 * @param int    $post_id The attachment post ID.
	 *
	 * @return string Modified URL if protected, original URL otherwise
	 */
	public function modify_attachment_url( string $url, int $post_id ): string {
		$upload_dir = wp_upload_dir();
		$file_path  = get_attached_file( $post_id );

		if ( false === $file_path ) {
			return $url;
		}

		// Check if the file is in the "protected" folder
		if ( strpos( $file_path, $upload_dir['basedir'] . '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/' ) === 0 ) {
			// Get the file hash
			$file_hash = rmfa_get_media_protected_file_hash( $post_id );
			if ( empty( $file_hash ) ) {
				return $url;
			}

			// Check for size suffix in the URL
			if ( 1 === preg_match( '/-(\d+x\d+)\.(jpg|jpeg|png|gif)$/i', $url, $matches ) ) {
				return home_url( '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' . $file_hash . '-' . $matches[1] );
			}

			// Return URL for original file
			return home_url( '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' . $file_hash );
		}

		return $url;
	}

	/**
	 * Modify attachment metadata to use hash-based filenames.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<mixed>|false $data Array of metadata, or false if not found.
	 * @param int                $attachment_id Attachment post ID.
	 *
	 * @return array<mixed>|false Modified metadata if protected, original metadata otherwise
	 */
	public function modify_attachment_metadata( $data, int $attachment_id ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( ! rmfa_is_media_restricted( $attachment_id ) ) {
			return $data;
		}

		$file_hash = rmfa_get_media_protected_file_hash( $attachment_id );
		if ( empty( $file_hash ) ) {
			return $data;
		}

		return $this->update_metadata_with_hash( $data, $file_hash, $attachment_id );
	}

	/**
	 * Modify image srcset to use protected URLs. It does not return srcset for proteced files because in media.php 1509 $src_matched is false.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<mixed> $sources Array of image sources.
	 * @param array<int>   $size_array Array of width and height values.
	 * @param string       $_image_src The 'src' of the image.
	 * @param array<mixed> $_image_meta The image meta data.
	 * @param int          $attachment_id The image attachment ID.
	 *
	 * @return array<mixed> Modified sources array.
	 *
	 * @SuppressWarnings("PHPMD.UnusedFormalParameter")
	 */
	public function modify_image_srcset( array $sources, array $size_array, string $_image_src, array $_image_meta, int $attachment_id ): array {
		if ( ! rmfa_is_media_restricted( $attachment_id ) ) {
			return $sources;
		}

		$file_hash = rmfa_get_media_protected_file_hash( $attachment_id );
		if ( empty( $file_hash ) ) {
			return $sources;
		}

		return $this->update_srcset_sources( $sources, $size_array, $file_hash );
	}

	/**
	 * Update srcset sources with protected URLs.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<mixed> $sources Array of image sources.
	 * @param array<int>   $size_array Array of width and height values.
	 * @param string       $file_hash The file hash.
	 *
	 * @return array<mixed>
	 */
	private function update_srcset_sources( array $sources, array $size_array, string $file_hash ): array {
		$modified_sources = array();
		foreach ( $sources as $width => $source ) {
			$dimensions                 = $this->get_dimensions_from_source( $source['url'], $width, $size_array );
			$modified_sources[ $width ] = array(
				'url'        => home_url( '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' . $file_hash . '-' . $dimensions ),
				'descriptor' => $source['descriptor'],
				'value'      => $source['value'],
			);
		}

		return $modified_sources;
	}

	/**
	 * Get dimensions from source URL or calculate from width.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string     $url The source URL.
	 * @param int        $width The source width.
	 * @param array<int> $size_array Array of width and height values.
	 *
	 * @return string
	 */
	private function get_dimensions_from_source( string $url, int $width, array $size_array ): string {
		if ( 1 === preg_match( '/-(\d+x\d+)\.(jpg|jpeg|png|gif)$/i', $url, $matches ) ) {
			return $matches[1];
		}

		return $width . 'x' . round( $width * ( $size_array[1] / $size_array[0] ) );
	}

	/**
	 * Modify attachment URL to post ID conversion to handle protected files
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int|null $post_id The post ID or null if not found.
	 * @param string   $url     The URL to check.
	 *
	 * @return int Modified post ID or 0 if not found
	 */
	public function modify_attachment_url_to_postid( ?int $post_id, string $url ): int {
		// If post ID was found, return it
		if ( null !== $post_id && 0 !== $post_id ) {
			return $post_id;
		}

		// Check if URL matches protected file pattern
		if ( str_contains( $url, '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' ) ) {
			return $this->find_protected_attachment_id( $url );
		}

		return $this->find_unprotected_attachment_id( $url );
	}

	/**
	 * Find attachment ID for protected file URLs.
	 *
	 * @param string $url The protected file URL.
	 *
	 * @return int Attachment ID or 0 if not found.
	 */
	private function find_protected_attachment_id( string $url ): int {
		if ( 1 !== preg_match( '/\/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '\/([a-zA-Z0-9]+)(?:-\d+x\d+)?/', $url, $matches ) ) {
			return 0;
		}

		$file_hash     = $matches[1];
		$found_post_id = rmfa_find_attachment_id_by_hash( $file_hash );

		return null !== $found_post_id ? $found_post_id : 0;
	}

	/**
	 * Find attachment ID for unprotected file URLs.
	 *
	 * @param string $url The unprotected file URL.
	 *
	 * @return int Attachment ID or 0 if not found.
	 */
	private function find_unprotected_attachment_id( string $url ): int {
		$upload_dir = wp_upload_dir();
		$url_path   = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );

		// Try direct path match first
		$attachment_id = $this->find_by_original_file_path( $url_path );
		if ( 0 !== $attachment_id ) {
			return $attachment_id;
		}

		// Try metadata-based search
		$attachment_id = $this->find_by_metadata_basename( $url_path );
		if ( 0 !== $attachment_id ) {
			return $attachment_id;
		}

		// Try restricted file URLs map
		$attachment_id = $this->find_by_restricted_urls_map( $url, $url_path, $upload_dir );
		if ( 0 !== $attachment_id ) {
			return $attachment_id;
		}

		return 0;
	}

	/**
	 * Find attachment by original file path.
	 *
	 * @param string $url_path The local file path.
	 *
	 * @return int Attachment ID or 0 if not found.
	 */
	private function find_by_original_file_path( string $url_path ): int {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_original_file_path'
			   AND meta_value = %s
			 LIMIT 1",
				$url_path
			)
		);

		return $attachment_id ? (int) $attachment_id : 0;
	}

	/**
	 * Find attachment by matching basename in metadata.
	 *
	 * @param string $url_path The local file path.
	 *
	 * @return int Attachment ID or 0 if not found.
	 */
	private function find_by_metadata_basename( string $url_path ): int {
		global $wpdb;

		$basename   = wp_basename( $url_path );
		$target_dir = wp_normalize_path( dirname( $url_path ) );

		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wp_attachment_metadata'
			   AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $basename ) . '%'
			)
		);

		// Check if the attachment is in the target directory, because the basename might be the same for multiple attachments.
		foreach ( $attachment_ids as $attachment_id ) {
			$attached_file = get_attached_file( $attachment_id );
			if ( false === $attached_file ) {
				continue;
			}

			if ( wp_normalize_path( dirname( $attached_file ) ) === $target_dir ) {
				return (int) $attachment_id;
			}
		}

		return 0;
	}

	/**
	 * Find attachment by restricted URLs map.
	 *
	 * @param string                     $url The original URL.
	 * @param string                     $url_path The local file path.
	 * @param array<string,string|false> $upload_dir Upload directory info.
	 *
	 * @return int Attachment ID or 0 if not found.
	 */
	private function find_by_restricted_urls_map( string $url, string $url_path, array $upload_dir ): int {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_restricted_file_urls_map'
			   AND meta_value LIKE %s
			 LIMIT 1",
				'%' . $wpdb->esc_like( $url ) . '%'
			)
		);

		if ( ! $attachment_id ) {
			return 0;
		}

		// Check original sizes paths
		$original_file_paths = get_post_meta( $attachment_id, '_original_sizes_paths', true );
		if ( ! empty( $original_file_paths ) && in_array( $url_path, $original_file_paths, true ) ) {
			return (int) $attachment_id;
		}

		// Check metadata sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_data ) {
				// Build the full URL path for this size
				$size_url = $upload_dir['baseurl'] . '/' . $metadata['file'];
				$size_url = str_replace( basename( $size_url ), $size_data['file'], $size_url );

				if ( $url === $size_url ) {
					return (int) $attachment_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Modify attachment image attributes to add a protected flag.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string,mixed> $attr       Array of attribute values for the image markup, keyed by attribute name.
	 * @param \WP_Post            $attachment Image attachment post.
	 *
	 * @return array<string,mixed> Modified attribute array
	 */
	public function modify_attachment_image_attributes( array $attr, \WP_Post $attachment ): array {
		if ( false === rmfa_is_media_restricted( $attachment->ID ) ) {
			return $attr;
		}

		$attr['rmfa-protected'] = 'true';

		return $attr;
	}

	/**
	 * Update metadata with hash-based filenames.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<mixed> $data Array of metadata.
	 * @param string       $file_hash The file hash.
	 * @param int          $attachment_id Attachment post ID.
	 *
	 * @return array<mixed>
	 */
	private function update_metadata_with_hash( array $data, string $file_hash, int $attachment_id ): array {
		if ( ! empty( $data['file'] ) ) {
			$data['file'] = RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_DIR . '/' . $file_hash;
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! empty( $data['sizes'] ) && is_array( $data['sizes'] ) ) {
			$data['sizes'] = $this->update_size_metadata( $data['sizes'], $file_hash, $mime_type );
		}

		return $data;
	}

	/**
	 * Update size metadata with hash-based filenames.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<mixed> $sizes Array of size metadata.
	 * @param string       $file_hash The file hash.
	 * @param string|false $mime_type The file mime type.
	 *
	 * @return array<mixed>
	 */
	private function update_size_metadata( array $sizes, string $file_hash, string|false $mime_type ): array {
		foreach ( $sizes as $size => $size_data ) {
			if ( empty( $size_data['file'] ) ) {
				continue;
			}

			if ( 1 === preg_match( '/-(\d+x\d+)\./', $size_data['file'], $matches ) ) {
				$sizes[ $size ]['file'] = $file_hash . '-' . $matches[1];
				continue;
			}

			if ( false !== $mime_type && strpos( $mime_type, 'image/' ) === 0 ) {
				$sizes[ $size ]['file'] = $file_hash;
				continue;
			}

			$sizes[ $size ]['file'] = $file_hash . '?type=jpg';
		}

		return $sizes;
	}

	// endregion
}
