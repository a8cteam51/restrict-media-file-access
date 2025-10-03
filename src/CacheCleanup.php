<?php
/**
 * Cache cleanup functionality for the Restrict Media File Access plugin.
 *
 * @package A8C\SpecialProjects\RestrictMediaFileAccess
 * @since 1.0.0
 */

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Class CacheCleanup
 *
 * Handles cache cleanup when attachments or posts are deleted.
 *
 * @since 1.0.0
 */
class CacheCleanup {

	/**
	 * Initialize the cache cleanup functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize(): void {
		// Clear cache when an attachment is deleted
		add_action( 'delete_attachment', array( $this, 'clear_attachment_cache' ), 10, 1 );

		// Clear cache when a post is deleted
		add_action( 'delete_post', array( $this, 'clear_post_cache' ), 10, 1 );
	}

	/**
	 * Clear cache when an attachment is deleted.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The ID of the attachment being deleted.
	 * @return void
	 */
	public function clear_attachment_cache( int $attachment_id ): void {
		// Clear the cache for this attachment
		rmfa_delete_cache( 'rmfa_media_post_ids_' . $attachment_id );
	}

	/**
	 * Clear cache when a post is deleted or moved to trash.
	 *
	 * @since 1.0.0
	 * @param int $post_id The ID of the post being deleted or moved to trash.
	 * @return void
	 */
	public function clear_post_cache( int $post_id ): void {
		// Get the post type
		$post_type = get_post_type( $post_id );

		// If this is an attachment, clear its cache
		if ( 'attachment' === $post_type ) {
			$this->clear_attachment_cache( $post_id );
			return;
		}

		// For other post types, we need to check if they contain any media
		// and clear the cache for those media items
		$post = get_post( $post_id );
		if ( null === $post ) {
			return;
		}

		// Extract media URLs from post content
		$media_urls = $this->extract_media_urls_from_content( $post->post_content );

		if ( empty( $media_urls ) ) {
			return;
		}

		// For each media URL, find the attachment ID and clear its cache
		foreach ( $media_urls as $url ) {
			$attachment_id = attachment_url_to_postid( $url );
			if ( 0 !== $attachment_id ) {
				$this->clear_attachment_cache( $attachment_id );
			}
		}
	}

	/**
	 * Extract media URLs from post content.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param string $content The post content.
	 *
	 * @return array<string> Array of media URLs.
	 */
	private function extract_media_urls_from_content( string $content ): array {
		$media_urls = array();

		// Extract URLs from img tags
		if ( 0 < preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches ) ) {
			$media_urls = array_merge( $media_urls, $matches[1] );
		}

		// Extract URLs from a tags (for linked media)
		if ( 0 < preg_match_all( '/<a[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches ) ) {
			$media_urls = array_merge( $media_urls, $matches[1] );
		}

		return array_unique( $media_urls );
	}
}
