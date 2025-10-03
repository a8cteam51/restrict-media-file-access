<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Returns an array with meta information for a given asset path. First, it checks for an .asset.php file in the same directory
 * as the given asset file whose contents are returns if it exists. If not, it returns an array with the file's last modified
 * time as the version and the main stylesheet + any extra dependencies passed in as the dependencies.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string        $asset_path         The path to the asset file.
 * @param   string[]|null $extra_dependencies Any extra dependencies to include in the returned meta.
 *
 * @return  array{ version: string, dependencies: array<string> }|null
 */
function restrict_media_file_access_get_asset_meta( string $asset_path, ?array $extra_dependencies = null ): ?array {
	$asset_path = str_starts_with( $asset_path, constant( 'RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH' ) ) ? $asset_path : constant( 'RESTRICT_MEDIA_FILE_ACCESS_DIR_PATH' ) . $asset_path;
	if ( ! file_exists( $asset_path ) ) {
		return null;
	}

	$asset_meta = array(
		'dependencies' => array(),
		'version'      => (string) filemtime( $asset_path ),
	);
	if ( '' === $asset_meta['version'] ) {
		$asset_meta['version'] = restrict_media_file_access_get_plugin_version();
	}

	$asset_pathinfo              = pathinfo( $asset_path );
	$asset_pathinfo['dirname'] ??= '';

	$asset_meta_file = "{$asset_pathinfo['dirname']}/{$asset_pathinfo['filename']}.asset.php";
	if ( file_exists( $asset_meta_file ) ) {
		$asset_meta_generated = require $asset_meta_file;

		if ( isset( $asset_meta_generated['version'] ) ) {
			$asset_meta['version'] = $asset_meta_generated['version'];
		}
		if ( isset( $asset_meta_generated['dependencies'] ) ) {
			$asset_meta['dependencies'] = $asset_meta_generated['dependencies'];
		}
	}

	if ( is_array( $extra_dependencies ) ) {
		$asset_meta['dependencies'] = array_merge( $asset_meta['dependencies'], $extra_dependencies );
		$asset_meta['dependencies'] = array_unique( $asset_meta['dependencies'] );
	}

	return $asset_meta;
}
