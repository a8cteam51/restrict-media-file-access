<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Main attachments admin class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class AttachmentsAdmin {

	/**
	 * Initialize the attachments admin.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Add media fields
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_attachment_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_field' ), 10, 2 );

		// Add media library column
		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'display_media_column' ), 10, 2 );

		// Add custom classes to attachment in media library
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 999 );

		// Disable default featured image for attachments in media library
		add_filter( 'post_thumbnail_id', array( $this, 'disable_default_thumbnail_for_attachments' ), 10, 2 );

		// Add a meta box on the attachment edit screen (not shown in modal).
		add_action( 'add_meta_boxes_attachment', array( $this, 'register_restriction_meta_box' ) );
	}

	/**
	 * Prepare attachment for JavaScript.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string,mixed> $attachment Array of attachment data.
	 *
	 * @return array<string,mixed> Modified attachment.
	 */
	public function prepare_attachment_for_js( array $attachment ): array {
		// Check if this attachment is restricted
		$is_restricted = get_post_meta( $attachment['id'], '_restricted_file', true );

		$attachment['rmfaClasses'] = array( 'rmfa-restricted-file' );
		$attachment['isProtected'] = boolval( $is_restricted );

		return $attachment;
	}

	/**
	 * Add custom field to attachment edit form.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string,mixed> $form_fields Array of form fields.
	 * @param \WP_Post            $post Post object.
	 *
	 * @return array<string,mixed> Modified form fields.
	 */
	public function add_attachment_field( array $form_fields, \WP_Post $post ): array {
		$file_path = get_attached_file( $post->ID );

		if ( false === $file_path ) {
			return $form_fields;
		}

		$is_restricted = rmfa_is_media_restricted( $post->ID );

		// Allow filtering of the help text
		$help_text = apply_filters(
			'rmfa_restricted_file_help_text',
			__( 'When enabled, this file will be moved to a protected directory and will only be accessible to users with appropriate permissions.', 'restrict-media-file-access' ),
			$post
		);

		// Allow filtering of the helps text
		$helps_text = apply_filters(
			'rmfa_restricted_file_helps_text',
			__( 'Check this to restrict access to users with access only.', 'restrict-media-file-access' ),
			$post
		);

		$form_fields['restricted_file'] = array(
			'label' => __( 'Is restricted file', 'restrict-media-file-access' ) .
				sprintf(
					'<span class="dashicons dashicons-info" style="cursor:help;" title="%s"></span>',
					esc_attr( $help_text )
				),
			'input' => 'html',
			'html'  => sprintf(
				'<input type="checkbox" id="attachments-%1$d-restricted_file"
					name="attachments[%1$d][restricted_file]"
					value="1"
					data-attachment-id="%1$d"
					data-original-url="%2$s"
					data-protected-url="%3$s"
					class="rmfa-restricted-toggle"
					%4$s />',
				$post->ID,
				wp_get_attachment_url( $post->ID ),
				home_url( '/' . RESTRICT_MEDIA_FILE_ACCESS_PROTECTED_PATH . '/' . basename( $file_path ) ),
				checked( $is_restricted, '1', false )
			),
			'helps' => $helps_text,
		);

		return $form_fields;
	}

	/**
	 * Save custom field from attachment edit form.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string,mixed> $post Post data.
	 * @param array<string,mixed> $attachment Attachment fields.
	 *
	 * @return array<string,mixed> Modified post data.
	 */
	public function save_attachment_field( array $post, array $attachment ): array {
		$file_manager  = new AttachmentsFileManager();
		$is_restricted = isset( $attachment['restricted_file'] ) && '1' === $attachment['restricted_file'];

		$restrictions_set = $is_restricted
			? $file_manager->set_file_as_protected( (int) $post['ID'] )
			: $file_manager->set_file_as_unprotected( (int) $post['ID'] );

		do_action( 'rmfa_attachment_restrictions_updated_on_save', $restrictions_set, $post['ID'] );

		return $post;
	}

	/**
	 * Add custom column to media library.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param array<string,string> $columns Array of columns.
	 *
	 * @return array<string,string> Modified columns.
	 */
	public function add_media_column( array $columns ): array {
		$columns['restricted'] = __( 'Restricted', 'restrict-media-file-access' );
		return $columns;
	}

	/**
	 * Display custom column content in media library.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id Post ID.
	 *
	 * @return void
	 */
	public function display_media_column( string $column_name, int $post_id ): void {
		if ( 'restricted' === $column_name ) {
			$is_restricted = rmfa_is_media_restricted( $post_id );
			if ( true === $is_restricted ) {
				echo '<span class="dashicons dashicons-lock" title="' .
					esc_attr__( 'This file is restricted', 'restrict-media-file-access' ) . '"></span>';
			}
		}
	}

	/**
	 * Disable default thumbnail for attachments in media library.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param int      $thumbnail_id The thumbnail ID.
	 * @param \WP_Post $current_post The current post object.
	 *
	 * @return int|false The thumbnail ID or false if the default thumbnail should be used.
	 */
	public function disable_default_thumbnail_for_attachments( $thumbnail_id, $current_post ) {
		// If we have a post object and it's an attachment
		if ( is_object( $current_post ) && 'attachment' === $current_post->post_type ) {
			// Check if this attachment actually has a _thumbnail_id meta set
			$direct_meta = get_post_meta( $current_post->ID, '_thumbnail_id', true );

			// If no direct meta is set, don't use the default thumbnail
			if ( empty( $direct_meta ) ) {
				return false;
			}
		}

		return $thumbnail_id;
	}

	/**
	 * Register the Restriction Activity meta box on attachment edit screen.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function register_restriction_meta_box(): void {
		add_meta_box(
			'rmfa_api_restriction',
			__( 'Additional Information', 'restrict-media-file-access' ),
			array( $this, 'render_restriction_meta_box' ),
			'attachment',
			'normal',
			'default'
		);
	}

	/**
	 * Render the Restriction Activity meta box.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param \WP_Post $post Attachment post.
	 *
	 * @return void
	 */
	public function render_restriction_meta_box( \WP_Post $post ): void {

		$meta_box_html = '<div class="rmfa-api-restricted-box">';

		$meta_box_html .= $this->get_api_restricted_data_meta_box_html( $post );

		$meta_box_html = apply_filters( 'rmfa_api_restricted_meta_box_html', $meta_box_html, $post );

		$meta_box_html .= '</div>';

		echo wp_kses_post( $meta_box_html );
	}

	/**
	 * Get the API restricted data meta box HTML.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param \WP_Post $post Attachment post.
	 *
	 * @return string
	 */
	private function get_api_restricted_data_meta_box_html( \WP_Post $post ): string {
		$api_data = get_post_meta( $post->ID, '_rmfa_api_restricted', true );

		$meta_box_html = '<h4>' . esc_html__( 'Latest Restriction API Activity', 'restrict-media-file-access' ) . '</h4>';

		if ( is_array( $api_data ) && ! empty( $api_data ) ) {
			return $this->get_api_restricted_data_meta_box_html_with_activity( $meta_box_html, $api_data );
		}

		$meta_box_html .= '<p>' . esc_html__( 'No activity.', 'restrict-media-file-access' ) . '</p>';

		$meta_box_html .= '<hr>';

		return $meta_box_html;
	}

	/**
	 * Get the API restricted data meta box HTML with activity.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string              $meta_box_html The meta box HTML.
	 * @param array<string,mixed> $api_data The API data.
	 *
	 * @return string
	 */
	private function get_api_restricted_data_meta_box_html_with_activity( string $meta_box_html, array $api_data ): string {
		$action = ! empty( $api_data['restrict'] )
				? __( 'Set as Restricted', 'restrict-media-file-access' )
				: __( 'Set as Unrestricted', 'restrict-media-file-access' );

		$user_html = esc_html__( 'Unknown', 'restrict-media-file-access' );
		if ( ! empty( $api_data['user_id'] ) ) {
			$user_id   = (int) $api_data['user_id'];
			$user_name = get_the_author_meta( 'display_name', $user_id );
			$user_url  = get_edit_user_link( $user_id );
			if ( '' !== $user_name ) {
				$user_html = esc_html( $user_name );
			}
			if ( '' !== $user_url ) {
				$user_html = sprintf( '<a href="%s">%s</a>', esc_url( $user_url ), $user_html );
			}
		}

		$timestamp = ! empty( $api_data['date'] ) ? strtotime( $api_data['date'] ) : false;
		$date_str  = $timestamp
			? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) )
			: esc_html__( 'Unknown', 'restrict-media-file-access' );

		$updated_posts = ! empty( $api_data['update_post'] )
			? __( 'Yes', 'restrict-media-file-access' )
			: __( 'No', 'restrict-media-file-access' );

		$meta_box_html .= '<p><strong>' . esc_html__( 'Action', 'restrict-media-file-access' ) . '</strong>: ' . esc_html( $action ) . '</p>';
		$meta_box_html .= '<p><strong>' . esc_html__( 'By user', 'restrict-media-file-access' ) . '</strong>: ' . $user_html . '</p>';
		$meta_box_html .= '<p><strong>' . esc_html__( 'Date', 'restrict-media-file-access' ) . '</strong>: ' . $date_str . '</p>';
		$meta_box_html .= '<p><strong>' . esc_html__( 'Updated posts', 'restrict-media-file-access' ) . '</strong>: ' . esc_html( $updated_posts ) . '</p>';
		$meta_box_html .= '<hr>';

		return $meta_box_html;
	}
}
