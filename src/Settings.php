<?php

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the settings for the Restrict Media File Access plugin.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Settings {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function initialize(): void {
		// Add settings to the Media Settings page
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		if ( (bool) get_option( 'rmfa_show_border_on_restricted_files', true ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_restricted_files_assets' ) );
		}

		// Enqueue admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {

		$rmfa_media = restrict_media_file_access_get_asset_meta( RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH . 'assets/admin/css/media.css' );

		if ( null !== $rmfa_media ) {
			wp_enqueue_style(
				'rmfa-media',
				RESTRICT_MEDIA_FILE_ACCESS_DIR_URL . 'assets/admin/css/media.css',
				$rmfa_media['dependencies'] ?? array(),
				$rmfa_media['version'] ?? '1.0.0'
			);
		}

		// Only load on media pages or when media modal is used
		if ( ! in_array( $hook, array( 'upload.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$rmfa_media_js = restrict_media_file_access_get_asset_meta( RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH . 'assets/admin/js/media.js', array( 'jquery', 'lodash', 'media-editor' ) );

		if ( null !== $rmfa_media_js ) {
			wp_enqueue_script(
				'rmfa-media',
				RESTRICT_MEDIA_FILE_ACCESS_DIR_URL . 'assets/admin/js/media.js',
				$rmfa_media_js['dependencies'] ?? array(),
				$rmfa_media_js['version'] ?? '1.0.0',
				true
			);
		}
	}

	/**
	 * Enqueue restricted files styling assets.
	 *
	 * @return void
	 */
	public function enqueue_restricted_files_assets(): void {
		$rmfa_media_restricted_files = restrict_media_file_access_get_asset_meta( RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH . 'assets/admin/css/media-restricted-files.css' );

		if ( null !== $rmfa_media_restricted_files ) {
			wp_enqueue_style(
				'rmfa-media-restricted-files',
				RESTRICT_MEDIA_FILE_ACCESS_DIR_URL . 'assets/admin/css/media-restricted-files.css',
				$rmfa_media_restricted_files['dependencies'] ?? array(),
				$rmfa_media_restricted_files['version'] ?? '1.0.0'
			);
		}
	}

	/**
	 * Register settings for the plugin.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// Register a new setting
		register_setting(
			'media',
			'rmfa_show_border_on_restricted_files',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'media',
			'rmfa_should_old_restricted_file_urls_work_if_file_set_as_public',
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		// Add a new section to the Media Settings page
		add_settings_section(
			'rmfa_section',
			__( 'Restrict Media File Access Settings', 'restrict-media-file-access' ),
			array( $this, 'render_section_description' ),
			'media'
		);

		// Add a new field to the section
		add_settings_field(
			'rmfa_show_border_on_restricted_files',
			__( 'Show border on restricted files', 'restrict-media-file-access' ),
			array( $this, 'render_show_border_on_restricted_files_field' ),
			'media',
			'rmfa_section'
		);

		// Add a new field to the section
		add_settings_field(
			'rmfa_should_old_restricted_file_urls_work_if_file_set_as_public',
			__( 'Should old restricted file URLs work if file set as public?', 'restrict-media-file-access' ),
			array( $this, 'render_should_old_restricted_file_urls_work_if_file_set_as_public_field' ),
			'media',
			'rmfa_section'
		);
	}

	/**
	 * Render the section description.
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure settings for the Restrict Media File Access plugin.', 'restrict-media-file-access' ) . '</p>';
	}

	/**
	 * Render the sync post audiences field.
	 *
	 * @return void
	 */
	public function render_show_border_on_restricted_files_field(): void {
		$show_border_enabled = get_option( 'rmfa_show_border_on_restricted_files', true );
		?>
		<label for="rmfa_show_border_on_restricted_files">
			<input type="checkbox" id="rmfa_show_border_on_restricted_files" name="rmfa_show_border_on_restricted_files" value="1" <?php checked( $show_border_enabled ); ?> />
		<?php esc_html_e( 'Show border on restricted files in the admin', 'restrict-media-file-access' ); ?>
		</label>
		<p class="description">
		<?php esc_html_e( 'When enabled, a border will be shown on restricted files in the admin.', 'restrict-media-file-access' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the should old restricted file URLs work if file set as public field.
	 *
	 * @return void
	 */
	public function render_should_old_restricted_file_urls_work_if_file_set_as_public_field(): void {
		$should_old_restricted_file_urls_work_if_file_set_as_public = get_option( 'rmfa_should_old_restricted_file_urls_work_if_file_set_as_public', true );
		?>
		<label for="rmfa_should_old_restricted_file_urls_work_if_file_set_as_public">
			<input type="checkbox" id="rmfa_should_old_restricted_file_urls_work_if_file_set_as_public" name="rmfa_should_old_restricted_file_urls_work_if_file_set_as_public" value="1" <?php checked( $should_old_restricted_file_urls_work_if_file_set_as_public ); ?> />
		<?php esc_html_e( 'Should old restricted file URLs work if file set as public?', 'restrict-media-file-access' ); ?>
		</label>
		<p class="description">
		<?php esc_html_e( 'When enabled, old restricted file URLs will work even if the file is set as public.', 'restrict-media-file-access' ); ?>
		</p>
		<?php
	}
}
