<?php
/**
 * CineCore Cache Layer
 *
 * @package CineCore
 */
defined( 'ABSPATH' ) || exit;
class CineCore_Cache {
	public static function get( $key, $group = 'cinecore' ) {
		$cached = wp_cache_get( $key, $group );
		if ( false !== $cached ) {
			return $cached;
		}
		return get_transient( 'cinecore_' . $key );
	}
	public static function set( $key, $value, $expiration = HOUR_IN_SECONDS, $group = 'cinecore' ) {
		wp_cache_set( $key, $value, $group, $expiration );
		set_transient( 'cinecore_' . $key, $value, $expiration );
	}
	public static function delete( $key, $group = 'cinecore' ) {
		wp_cache_delete( $key, $group );
		delete_transient( 'cinecore_' . $key );
	}
	public static function tmdb_key( $type, $id ) {
		return 'tmdb_' . sanitize_key( $type ) . '_' . absint( $id );
	}
}
