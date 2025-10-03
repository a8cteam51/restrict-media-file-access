<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main attachments tracking class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class AttachmentsTracking {

	/**
	 * Initialize the attachments admin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Fix URLs and track relationships
		add_filter( 'wp_insert_post_data', array( $this, 'fix_wrong_image_urls' ), 99, 1 );
		add_action( 'wp_insert_post', array( $this, 'track_post_attachments' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'remove_post_from_attachments' ), 10, 1 );
	}

	/**
	 * Fix wrong image URLs before saving the post.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string, mixed> $data The post data.
	 *
	 * @return array<string, mixed> Modified post data.
	 */
	public function fix_wrong_image_urls( array $data ): array {
		// Skip if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}

		if ( ! isset( $data['post_content'] ) ) {
			return $data;
		}

		$media_ids_with_urls = $this->get_media_ids_with_urls_from_post_content( $data['post_content'] );

		if ( empty( $media_ids_with_urls ) ) {
			return $data;
		}

		foreach ( $media_ids_with_urls as $media_id => $urls ) {
			$data['post_content'] = $this->process_media_urls( $media_id, $urls, $data['post_content'] );
		}

		return $data;
	}

	/**
	 * Process URLs for a specific media item.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int           $media_id The media ID.
	 * @param array<string> $urls The URLs to process.
	 * @param string        $post_content The post content.
	 *
	 * @return string Modified post content.
	 */
	private function process_media_urls( int $media_id, array $urls, string $post_content ): string {
		$is_restricted = rmfa_is_media_restricted( $media_id );

		foreach ( $urls as $url ) {
			$should_fix_url = $is_restricted
				? ! str_contains( $url, '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' )
				: str_contains( $url, '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' );

			if ( $should_fix_url ) {
				$post_content = $this->fix_url_in_content( $media_id, $url, $post_content );
			}
		}

		return $post_content;
	}

	/**
	 * Fix a specific URL in the post content.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int    $media_id The media ID.
	 * @param string $url The URL to fix.
	 * @param string $post_content The post content.
	 *
	 * @return string Modified post content.
	 */
	private function fix_url_in_content( int $media_id, string $url, string $post_content ): string {
		$restricted_file_urls_map = get_post_meta( $media_id, '_restricted_file_urls_map', true );

		if ( empty( $restricted_file_urls_map ) ) {
			return $post_content;
		}

		foreach ( $restricted_file_urls_map as $old_url => $new_url ) {
			if ( str_contains( $url, $old_url ) ) {
				$post_content = str_replace( $url, $new_url, $post_content );
			}
		}

		return $post_content;
	}

	/**
	 * Extract all media IDs with URLs from post content.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $post_content The post content.
	 *
	 * @return array<int, array<string>> The media IDs.
	 */
	public function get_media_ids_with_urls_from_post_content( string $post_content ): array {
		$media_ids_with_urls    = array();
		$unslashed_post_content = wp_unslash( $post_content );
		$url_patterns           = $this->get_url_patterns();

		// Process different types of URLs
		$media_ids_with_urls = $this->extract_href_urls( $unslashed_post_content, $url_patterns, $media_ids_with_urls );
		$media_ids_with_urls = $this->extract_src_urls( $unslashed_post_content, $url_patterns, $media_ids_with_urls );
		$media_ids_with_urls = $this->extract_srcset_urls( $unslashed_post_content, $media_ids_with_urls );

		// Merge manually to preserve keys
		foreach ( $media_ids_with_urls as $media_id => $urls ) {
			if ( ! isset( $media_ids_with_urls[ $media_id ] ) ) {
				$media_ids_with_urls[ $media_id ] = array();
			}
			$media_ids_with_urls[ $media_id ] = array_merge( $media_ids_with_urls[ $media_id ], $urls );
		}

		return $this->deduplicate_urls( $media_ids_with_urls );
	}

	/**
	 * Get URL patterns for matching.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return array<string, mixed> URL patterns.
	 */
	private function get_url_patterns(): array {
		$upload_dir      = wp_upload_dir();
		$upload_base_url = preg_quote( $upload_dir['baseurl'], '/' );
		$site_url        = preg_quote( site_url(), '/' );

		return array(
			'upload_base_url' => $upload_base_url,
			'site_url'        => $site_url,
			'upload_dir'      => $upload_dir,
		);
	}

	/**
	 * Extract URLs from href attributes.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string                    $content The post content.
	 * @param array<string, string>     $patterns URL patterns.
	 * @param array<int, array<string>> $media_ids_with_urls Media IDs with URLs.
	 *
	 * @return array<int, array<string>> Media IDs with URLs.
	 */
	private function extract_href_urls( string $content, array $patterns, array $media_ids_with_urls ): array {
		$pattern = '/<a[^>]+href=[\'"]((?:' . $patterns['upload_base_url'] . '|' . $patterns['site_url'] . '\/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '\/)[^\'"]+)[\'"][^>]*>/i';

		$result = preg_match_all( $pattern, $content, $matches );
		if ( false === $result || 0 === $result ) {
			return $media_ids_with_urls;
		}

		foreach ( $matches[1] as $file_url ) {
			$media_id = $this->get_media_id_from_url( $file_url );
			if ( null !== $media_id ) {
				$media_ids_with_urls[ $media_id ][] = $file_url;
			}
		}

		return $media_ids_with_urls;
	}

	/**
	 * Extract URLs from src and poster attributes.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string                    $content The post content.
	 * @param array<string, string>     $patterns URL patterns.
	 * @param array<int, array<string>> $media_ids_with_urls Media IDs with URLs.
	 *
	 * @return array<int, array<string>> Media IDs with URLs.
	 */
	private function extract_src_urls( string $content, array $patterns, array $media_ids_with_urls ): array {
		$pattern = '/(?:src|poster)=[\'"]((?:' . $patterns['upload_base_url'] . '|' . $patterns['site_url'] . '\/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '\/)[^\'"]+)[\'"]/';

		$result = preg_match_all( $pattern, $content, $matches );
		if ( false === $result || 0 === $result ) {
			return $media_ids_with_urls;
		}

		foreach ( $matches[1] as $src_url ) {
			$media_id = $this->get_media_id_from_url( $src_url );
			if ( null !== $media_id ) {
				$media_ids_with_urls[ $media_id ][] = $src_url;
			}
		}

		return $media_ids_with_urls;
	}

	/**
	 * Extract URLs from srcset attributes.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string                    $content The post content.
	 * @param array<int, array<string>> $media_ids_with_urls Media IDs with URLs.
	 *
	 * @return array<int, mixed> Media IDs with URLs.
	 */
	private function extract_srcset_urls( string $content, array $media_ids_with_urls ): array {
		$result = preg_match_all( '/srcset=[\'"](.[^\'"]*)[\'"]/i', $content, $matches );
		if ( false === $result || 0 === $result ) {
			return $media_ids_with_urls;
		}

		foreach ( $matches[1] as $srcset ) {
			$srcset_urls = explode( ',', $srcset );
			foreach ( $srcset_urls as $srcset_url ) {
				$url = preg_replace( '/\s+\d+[wx]$/', '', trim( $srcset_url ) );
				if ( null === $url ) {
					continue;
				}

				$media_id = $this->get_media_id_from_url( $url );
				if ( null !== $media_id ) {
					$media_ids_with_urls[ $media_id ][] = $url;
				}
			}
		}

		return $media_ids_with_urls;
	}

	/**
	 * Get media ID from URL.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $url The URL.
	 *
	 * @return int|null Media ID or null if not found.
	 */
	private function get_media_id_from_url( string $url ): ?int {
		if ( strpos( $url, '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' ) !== false ) {
			$parsed_url = wp_parse_url( $url, PHP_URL_PATH );
			if ( is_string( $parsed_url ) ) {
				$hash = basename( $parsed_url );
				$hash = explode( '-', $hash )[0];
				return rmfa_find_attachment_id_by_hash( $hash );
			}

			return null;
		}

		$media_id = attachment_url_to_postid( $url );
		if ( 0 !== $media_id ) {
			return $media_id;
		}

		return null;
	}

	/**
	 * Deduplicate URLs for each media ID.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<int, array<string>> $media_ids_with_urls Media IDs with URLs.
	 *
	 * @return array<int, array<string>> Deduplicated media IDs with URLs.
	 */
	private function deduplicate_urls( array $media_ids_with_urls ): array {
		if ( empty( $media_ids_with_urls ) ) {
			return $media_ids_with_urls;
		}

		foreach ( $media_ids_with_urls as $media_id => $urls ) {
			$media_ids_with_urls[ $media_id ] = array_unique( $urls );
		}

		return $media_ids_with_urls;
	}

	/**
	 * Track which posts contain which attachments.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post   The post object.
	 * @param bool     $update  Whether this is an update.
	 *
	 * @return void
	 */
	public function track_post_attachments( int $post_id, \WP_Post $post, bool $update ): void {
		// Skip revisions, autosaves, and non-published posts
		if ( false !== wp_is_post_revision( $post_id ) || false !== wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Skip if no content
		if ( empty( $post->post_content ) ) {
			// If this is an update and content is empty, remove all attachments
			if ( $update ) {
				$this->remove_post_from_all_attachments( $post_id );
			}
			return;
		}

		// Get current attachments for this post
		$current_attachments    = $this->get_media_ids_with_urls_from_post_content( $post->post_content );
		$current_attachment_ids = array_keys( $current_attachments );

		// Get previously tracked attachments for this post
		$previous_attachment_ids = get_post_meta( $post_id, '_rmfa_attachments', true );
		if ( ! is_array( $previous_attachment_ids ) ) {
			$previous_attachment_ids = array();
		}

		// Find attachments to add
		$attachments_to_add = array_diff( $current_attachment_ids, $previous_attachment_ids );

		// Find attachments to remove
		$attachments_to_remove = array_diff( $previous_attachment_ids, $current_attachment_ids );

		// Add this post to new attachments
		foreach ( $attachments_to_add as $attachment_id ) {
			$this->add_post_to_attachment( $attachment_id, $post_id );
		}

		// Remove this post from old attachments
		foreach ( $attachments_to_remove as $attachment_id ) {
			$this->remove_post_from_attachment( $attachment_id, $post_id );
		}

		// Update the post's attachment tracking
		update_post_meta( $post_id, '_rmfa_attachments', $current_attachment_ids );
	}

	/**
	 * Add a post to an attachment's tracking list.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $post_id       The post ID.
	 *
	 * @return void
	 */
	private function add_post_to_attachment( int $attachment_id, int $post_id ): void {
		$post_ids = get_post_meta( $attachment_id, '_rmfa_used_in_posts', true );

		if ( ! is_array( $post_ids ) ) {
			$post_ids = array();
		}

		if ( ! in_array( $post_id, $post_ids, true ) ) {
			$post_ids[] = $post_id;
			update_post_meta( $attachment_id, '_rmfa_used_in_posts', $post_ids );
		}
	}

	/**
	 * Remove a post from an attachment's tracking list.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $post_id       The post ID.
	 *
	 * @return void
	 */
	private function remove_post_from_attachment( int $attachment_id, int $post_id ): void {
		$post_ids = get_post_meta( $attachment_id, '_rmfa_used_in_posts', true );

		if ( is_array( $post_ids ) && in_array( $post_id, $post_ids, true ) ) {
			$post_ids = array_diff( $post_ids, array( $post_id ) );
			update_post_meta( $attachment_id, '_rmfa_used_in_posts', $post_ids );
		}
	}

	/**
	 * Remove an attachment from a post's tracking list.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $post_id       The post ID.
	 *
	 * @return void
	 */
	public function remove_attachment_from_post( int $attachment_id, int $post_id ): void {
		$attachment_ids = get_post_meta( $post_id, '_rmfa_attachments', true );

		if ( is_array( $attachment_ids ) && in_array( $attachment_id, $attachment_ids, true ) ) {
			$attachment_ids = array_diff( $attachment_ids, array( $attachment_id ) );
			update_post_meta( $post_id, '_rmfa_attachments', $attachment_ids );
		}
	}

	/**
	 * Remove post from all attachments when post is deleted.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $post_id The post ID being deleted.
	 *
	 * @return void
	 */
	public function remove_post_from_attachments( int $post_id ): void {
		$attachment_ids = get_post_meta( $post_id, '_rmfa_attachments', true );

		if ( is_array( $attachment_ids ) ) {
			foreach ( $attachment_ids as $attachment_id ) {
				$this->remove_post_from_attachment( $attachment_id, $post_id );
			}
		}

		delete_post_meta( $post_id, '_rmfa_attachments' );
	}

	/**
	 * Remove a post from all attachments.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	private function remove_post_from_all_attachments( int $post_id ): void {
		global $wpdb;

		// Find all attachments that contain this post ID
		$attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = '_rmfa_used_in_posts'
			AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $post_id ) . '%'
			)
		);

		foreach ( $attachment_ids as $attachment_id ) {
			$this->remove_post_from_attachment( (int) $attachment_id, $post_id );
		}
	}
}
