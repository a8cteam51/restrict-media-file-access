<?php

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin's metadata.
 *
 * @template PluginMetaKey of key-of<PluginMetaData>
 *
 * @param   PluginMetaKey|null $property Optional. The property to return. Default all.
 *
 * @return  ($property is null ? PluginMetaData : ($property is PluginMetaKey ? PluginMetaData[PluginMetaKey] : null))
 */
function restrict_media_file_access_get_plugin_metadata( $property = null ) {
	static $plugin_data = null;

	$can_translate = 0 < did_action( 'init' );
	$translate_key = 0 < did_action( 'plugins_loaded' ) ? 'full' : ( $can_translate ? 'translated' : 'raw' );

	if ( ! isset( $plugin_data[ $translate_key ] ) ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file                   = trailingslashit( WP_PLUGIN_DIR ) . constant( 'RESTRICT_MEDIA_FILE_ACCESS_BASENAME' );
		$plugin_data[ $translate_key ] = get_plugin_data( $plugin_file, false, $can_translate );
	}

	$metadata = $plugin_data[ $translate_key ];
	if ( null === $property ) {
		return $metadata;
	}

	if ( is_string( $property ) && isset( $metadata[ $property ] ) ) {
		return $metadata[ $property ];
	}

	return null;
}

/**
 * Returns the plugin's slug.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function restrict_media_file_access_get_plugin_slug(): string {
	$text_domain = restrict_media_file_access_get_plugin_metadata( 'TextDomain' );
	return sanitize_key( $text_domain );
}

/**
 * Returns the plugin's name.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function restrict_media_file_access_get_plugin_name(): string {
	return restrict_media_file_access_get_plugin_metadata( 'Name' );
}

/**
 * Returns the plugin's version.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @return  string
 */
function restrict_media_file_access_get_plugin_version(): string {
	return restrict_media_file_access_get_plugin_metadata( 'Version' );
}

/**
 * Checks compatibility with the current WordPress version.
 *
 * @param   string $min_wp_version The minimum WP version required to run.
 *
 * @return  bool
 */
function restrict_media_file_access_is_wp_version_compatible( $min_wp_version ) {
	if ( ! function_exists( 'is_wp_version_compatible' ) ) {
		return false;
	}

	return is_wp_version_compatible( $min_wp_version );
}

/**
 * Checks compatibility with the current PHP version.
 *
 * @param   string $min_php_version The minimum PHP version required to run.
 *
 * @return  bool
 */
function restrict_media_file_access_is_php_version_compatible( $min_php_version ) {
	if ( ! function_exists( 'is_php_version_compatible' ) ) {
		return false;
	}

	return is_php_version_compatible( $min_php_version );
}

/**
 * Validates the plugin requirements.
 *
 * @return  true|\WP_Error
 */
function restrict_media_file_access_validate_requirements() {
	$plugin_metadata = restrict_media_file_access_get_plugin_metadata();
	if ( ! isset( $plugin_metadata['RequiresPHP'] ) || '' === $plugin_metadata['RequiresPHP'] ) {
		$plugin_metadata['RequiresPHP'] = '8.3';
	}
	if ( ! isset( $plugin_metadata['RequiresWP'] ) || '' === $plugin_metadata['RequiresWP'] ) {
		$plugin_metadata['RequiresWP'] = '6.7';
	}

	$is_php_compatible = restrict_media_file_access_is_php_version_compatible( $plugin_metadata['RequiresPHP'] );
	$is_wp_compatible  = restrict_media_file_access_is_wp_version_compatible( $plugin_metadata['RequiresWP'] );

	$wp_error = new \WP_Error();
	if ( ! $is_wp_compatible ) {
		$wp_error->add( 'plugin_wp_incompatible', '', array( 'requires_wp' => $plugin_metadata['RequiresWP'] ) );
	}
	if ( ! $is_php_compatible ) {
		$wp_error->add( 'plugin_php_incompatible', '', array( 'requires_php' => $plugin_metadata['RequiresPHP'] ) );
	}

	return $wp_error->has_errors() ? $wp_error : true;
}

/**
 * Outputs an error that the system requirements weren't met.
 *
 * @param   \WP_Error $error          The error message to display.
 *
 * @return  void
 */
function restrict_media_file_access_output_requirements_error( $error ) {
	add_action(
		'admin_notices',
		static function () use ( $error ) {
			$requirements_error = \wp_sprintf(
				/* translators: 1: Plugin name, 2: Plugin version */
				__( '<strong>%1$s (version %2$s)</strong> could not be initialized.', 'restrict-media-file-access' ),
				restrict_media_file_access_get_plugin_metadata( 'Name' ),
				restrict_media_file_access_get_plugin_metadata( 'Version' )
			);

			if ( $error->has_errors() ) {
				$requirements_error .= ' ' . \__( 'Your environment does not meet all the system requirements listed below:', 'restrict-media-file-access' );
				$requirements_error .= '<ul class="ul-disc">';

				foreach ( $error->get_error_codes() as $error_code ) {
					$error_data = $error->get_error_data( $error_code );
					if ( ! is_array( $error_data ) ) {
						$error_data = array();
					}

					switch ( $error_code ) {
						case 'plugin_wp_incompatible':
							$error_message = wp_sprintf(
								/* translators: 1: Current WP version, 2: Minimum WP version */
								__( 'Current <em>WordPress version (%1$s)</em> does not meet minimum required version of %2$s.', 'restrict-media-file-access' ),
								get_bloginfo( 'version' ),
								$error_data['requires_wp']
							);
							break;
						case 'plugin_php_incompatible':
							$error_message = wp_sprintf(
								/* translators: 1: Current PHP version, 2: Minimum PHP version */
								__( 'Current <em>PHP version (%1$s)</em> does not meet minimum required version of %2$s.', 'restrict-media-file-access' ),
								PHP_VERSION,
								$error_data['requires_php']
							);
							break;
						case 'missing_autoloader':
							$error_message = __( 'The autoloader file is missing. Please run <code>composer install</code> to generate it.', 'restrict-media-file-access' );
							break;
						default:
							$error_message = $error->get_error_message( $error_code );
					}

					$requirements_error .= "<li>$error_message</li>";
				}

				$requirements_error .= '</ul>';
			}

			wp_admin_notice( $requirements_error, array( 'type' => 'error' ) );
		}
	);
}
