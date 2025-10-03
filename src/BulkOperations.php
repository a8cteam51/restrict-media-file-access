<?php declare( strict_types=1 );

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Bulk operations for the plugin.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class BulkOperations {

	/**
	 * Update all attachments and posts to use the new tracking system.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string, mixed> $args Optional arguments.
	 *
	 * @return array<string, mixed> Results of the operation.
	 */
	public function update_all_attachments( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'batch_size' => 50,
			'post_types' => $this->get_default_post_types(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate batch size
		if ( $args['batch_size'] < 1 || $args['batch_size'] > 1000 ) {
			return array(
				'success' => false,
				'message' => 'Batch size must be between 1 and 1000.',
			);
		}

		$post_types_escaped = $this->get_escaped_post_types( $args['post_types'] );
		$post_types_list    = "'" . implode( "','", $post_types_escaped ) . "'";

		// Get total count
		$total_posts = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type IN ({$post_types_list})
				AND post_content != ''
				",
			)
		);

		if ( ! $total_posts ) {
			return array(
				'success'             => true,
				'message'             => 'No posts found to process.',
				'processed'           => 0,
				'attachments_found'   => 0,
				'attachments_updated' => 0,
			);
		}

		// Process in batches
		$offset                    = 0;
		$processed                 = 0;
		$total_attachments_found   = 0;
		$total_attachments_updated = 0;
		$attachments_tracking      = new AttachmentsTracking();

		do {
			$posts = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT ID, post_content
					FROM {$wpdb->posts}
					WHERE post_type IN ({$post_types_list})
					AND post_content != ''
					ORDER BY ID
					LIMIT %d OFFSET %d
					",
					$args['batch_size'],
					$offset
				)
			);

			if ( empty( $posts ) ) {
				break;
			}

			$posts_count = count( $posts );

			foreach ( $posts as $post ) {
				$found                      = $this->process_single_post( $post, $attachments_tracking );
				$total_attachments_found   += $found;
				$total_attachments_updated += $found;
				++$processed;
			}

			$offset += $args['batch_size'];

			// Tiny pause to avoid hammering DB
			if ( $posts_count === $args['batch_size'] ) {
				usleep( 100_000 ); // 100 ms
			}
		} while ( $posts_count === $args['batch_size'] );

		return array(
			'success'             => true,
			'message'             => sprintf(
				'Processed %d posts. Found %d attachment relationships. Updated %d attachment tracking relationships.',
				$processed,
				$total_attachments_found,
				$total_attachments_updated
			),
			'processed'           => $processed,
			'attachments_found'   => $total_attachments_found,
			'attachments_updated' => $total_attachments_updated,
		);
	}

	/**
	 * Process a single post to update attachment tracking.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param \stdClass           $post The post object.
	 * @param AttachmentsTracking $attachments_tracking The tracking instance.
	 *
	 * @return int Number of attachments found.
	 */
	private function process_single_post( \stdClass $post, AttachmentsTracking $attachments_tracking ): int {
		$media_ids_with_urls = $attachments_tracking->get_media_ids_with_urls_from_post_content( $post->post_content );
		$attachment_ids      = array_keys( $media_ids_with_urls );

		if ( empty( $attachment_ids ) ) {
			return 0;
		}

		update_post_meta( $post->ID, '_rmfa_attachments', $attachment_ids );

		foreach ( $attachment_ids as $attachment_id ) {
			$this->add_post_to_attachment( (int) $attachment_id, (int) $post->ID );
		}

		return count( $attachment_ids );
	}

	/**
	 * Add a post to an attachment's tracking list.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	private function add_post_to_attachment( int $attachment_id, int $post_id ): void {
		$post_ids = get_post_meta( $attachment_id, '_rmfa_used_in_posts', true );
		$post_ids = is_array( $post_ids ) ? $post_ids : array();

		if ( ! in_array( $post_id, $post_ids, true ) ) {
			$post_ids[] = $post_id;
			update_post_meta( $attachment_id, '_rmfa_used_in_posts', $post_ids );
		}
	}

	/**
	 * Get the default post types.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return array<string>
	 */
	private function get_default_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		return array_diff( $post_types, array( 'attachment', 'nav_menu_item', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'guest-author' ) );
	}

	/**
	 * Get the escaped post types.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string> $post_types The post types.
	 *
	 * @return array<string>
	 */
	private function get_escaped_post_types( array $post_types ): array {
		$escaped_post_types = array();
		foreach ( $post_types as $post_type ) {
			$escaped_post_types[] = esc_sql( $post_type );
		}
		return $escaped_post_types;
	}
}
