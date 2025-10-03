<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\RestrictMediaFileAccess;

defined( 'ABSPATH' ) || exit;

/**
 * Static filesystem helper class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Filesystem {
	/**
	 * Get the WordPress filesystem instance.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return \WP_Filesystem_Base
	 */
	public static function get_instance(): \WP_Filesystem_Base {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem;
	}

	/**
	 * Check if a file exists.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $file_path Path to the file.
	 * @return bool Whether the file exists.
	 */
	public static function exists( string $file_path ): bool {
		return self::get_instance()->exists( $file_path );
	}

	/**
	 * Move a file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $source_path Source file path.
	 * @param string $destination_path Destination file path.
	 * @return bool Whether the move was successful.
	 */
	public static function move( string $source_path, string $destination_path ): bool {
		return self::get_instance()->move( $source_path, $destination_path );
	}

	/**
	 * Delete a file.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $file_path Path to the file.
	 * @return bool Whether the deletion was successful.
	 */
	public static function delete( string $file_path ): bool {
		return self::get_instance()->delete( $file_path );
	}

	/**
	 * Delete a directory recursively.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $directory_path Path to the directory.
	 *
	 * @return bool Whether the deletion was successful.
	 */
	public static function delete_recursive( string $directory_path ): bool {
		return self::get_instance()->delete( $directory_path, true );
	}

	/**
	 * Read a file's contents.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string $file_path Path to the file.
	 * @return string|false The file contents or false on failure.
	 */
	public static function read( string $file_path ) {
		return self::get_instance()->get_contents( $file_path );
	}

	/**
	 * Get a directory list.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param string              $directory_path Path to the directory.
	 * @param array<string, bool> $options Options include hidden and recursive.
	 *
	 * @return array<string, mixed>|false The directory list or false on failure.
	 */
	public static function dirlist( string $directory_path, array $options = array() ): array|false {
		$include_hidden = $options['include_hidden'] ?? true;
		$recursive      = $options['recursive'] ?? false;

		return self::get_instance()->dirlist( $directory_path, $include_hidden, $recursive );
	}
}
