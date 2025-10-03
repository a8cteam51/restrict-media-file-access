<?php

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Self Update class
 * Sets up autoupdates for this GitHub-hosted plugin
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class SelfUpdate {

	/**
	 * Initialize WordPress hooks
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @return void
	 */
	public function initialize() {
		add_filter( 'update_plugins_github.com', array( $this, 'self_update' ), 10, 3 );
	}

	/**
	 * Check for updates to this plugin
	 *
	 * @param array<string, mixed>|false $update      Array of update data.
	 * @param array<string, mixed>       $plugin_data Array of plugin data.
	 * @param string                     $plugin_file Path to plugin file.
	 *
	 * @return array<string, mixed>|boolean Array of update data or false if no update available.
	 */
	public function self_update( $update, array $plugin_data, string $plugin_file ) {
		// only check this plugin
		if ( 'restrict-media-file-access/restrict-media-file-access.php' !== $plugin_file ) {
			return $update;
		}

		// already completed update check elsewhere
		if ( ! empty( $update ) ) {
			return $update;
		}

		// let's go get the latest version number from GitHub
		$response = wp_remote_get(
			'https://api.github.com/repos/a8cteam51/restrict-media-file-access/releases/latest',
			array(
				'user-agent' => 'wpspecialprojects',
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$output              = json_decode( wp_remote_retrieve_body( $response ), true );
		$new_version_number  = $output['tag_name'];
		$is_update_available = version_compare( $plugin_data['Version'], $new_version_number, '<' );

		if ( ! $is_update_available ) {
			return false;
		}

		$new_url     = $output['html_url'];
		$new_package = $output['assets'][0]['browser_download_url'];

		return array(
			'slug'    => $plugin_data['TextDomain'],
			'version' => $new_version_number,
			'url'     => $new_url,
			'package' => $new_package,
		);
	}
}
