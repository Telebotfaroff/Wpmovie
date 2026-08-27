<?php
/**
 * Centralized Meta API - CineCore
 *
 * Single source of truth for all post meta keys.
 * Handles legacy keys, validation, sanitization.
 *
 * @package CineCore
 */

defined( 'ABSPATH' ) || exit;

class CineCore_Meta {

	// === Core TMDB ===
	const TMDB_ID      = '_cinecore_tmdb_id';
	const POSTER_URL   = '_cinecore_poster_url';
	const BACKDROP_URL = '_cinecore_backdrop_url';
	const VOTE_AVG     = '_cinecore_vote_average';
	const RATING_LEGACY = '_cinecore_rating';
	const RUNTIME      = '_cinecore_runtime';
	const VOTE_COUNT   = '_cinecore_vote_count';
	const DIRECTOR     = '_cinecore_director';
	const SEASON_COUNT = '_cinecore_season_count';

	// === Relationships (with legacy fallbacks) ===
	const TV_SHOW_ID_LEGACY_1 = '_cinecore_tv_show_id';
	const TV_SHOW_ID_LEGACY_2 = '_cinecore_parent_tv_show';
	const SEASON_ID_LEGACY_1  = '_cinecore_season_id';
	const SEASON_ID_LEGACY_2  = '_cinecore_parent_season';
	const SEASON_NUMBER       = '_cinecore_season_number';
	const EPISODE_NUMBER      = '_cinecore_episode_number';
	const MOVIE_ID            = '_cinecore_movie_id';

	// === Player / Downloads / Subs ===
	const SERVER_1 = '_cinecore_server_1';
	const SERVER_2 = '_cinecore_server_2';
	const SERVER_VIP = '_cinecore_server_vip';
	const DL_1080 = '_cinecore_download_1080p';
	const DL_720  = '_cinecore_download_720p';
	const DL_480  = '_cinecore_download_480p';
	const DL_LINKS_ARRAY = '_cinecore_download_links';
	const SUB_EN = '_cinecore_subtitle_en';
	const SUB_SI = '_cinecore_subtitle_si';

	// === Telegram & SEO ===
	const TELEGRAM_POSTED = '_cinecore_telegram_posted';
	const SEO_DESC        = '_cinecore_seo_description';

	// === Upvote (legacy postmeta) ===
	const UPVOTE_COUNT = '_cinecore_upvote_count';
	const UPVOTED_USERS = '_cinecore_upvoted_users';
	const UPVOTED_IPS   = '_cinecore_upvoted_ips';

	public static function get_tmdb_id( $post_id ) {
		$val = get_post_meta( $post_id, self::TMDB_ID, true );
		return $val ? preg_replace( '/[^0-9]/', '', (string) $val ) : '';
	}
	public static function set_tmdb_id( $post_id, $value ) {
		$value = preg_replace( '/[^0-9]/', '', sanitize_text_field( $value ) );
		update_post_meta( $post_id, self::TMDB_ID, $value );
	}
	public static function get_poster( $post_id, $fallback_thumb = true ) {
		$url = get_post_meta( $post_id, self::POSTER_URL, true );
		$url = $url ? esc_url_raw( trim( $url ) ) : '';
		if ( '' === $url && $fallback_thumb && has_post_thumbnail( $post_id ) ) {
			$url = get_the_post_thumbnail_url( $post_id, 'large' );
		}
		return $url ? esc_url_raw( $url ) : '';
	}
	public static function set_poster( $post_id, $url ) {
		update_post_meta( $post_id, self::POSTER_URL, esc_url_raw( trim( $url ) ) );
	}
	public static function get_backdrop( $post_id ) {
		$url = get_post_meta( $post_id, self::BACKDROP_URL, true );
		return $url ? esc_url_raw( trim( $url ) ) : '';
	}
	public static function set_backdrop( $post_id, $url ) {
		update_post_meta( $post_id, self::BACKDROP_URL, esc_url_raw( trim( $url ) ) );
	}
	public static function get_rating( $post_id ) {
		$val = get_post_meta( $post_id, self::VOTE_AVG, true );
		if ( '' === $val ) {
			$val = get_post_meta( $post_id, self::RATING_LEGACY, true );
		}
		return '' !== $val ? (float) $val : 0;
	}
	public static function set_rating( $post_id, $rating ) {
		update_post_meta( $post_id, self::VOTE_AVG, (float) $rating );
	}
	public static function get_runtime( $post_id ) {
		$val = get_post_meta( $post_id, self::RUNTIME, true );
		return '' !== $val ? absint( $val ) : 0;
	}
	public static function set_runtime( $post_id, $minutes ) {
		update_post_meta( $post_id, self::RUNTIME, absint( $minutes ) );
	}
	public static function get_season_tv_show_id( $season_id ) {
		$val = get_post_meta( $season_id, self::TV_SHOW_ID_LEGACY_1, true );
		if ( '' === $val ) {
			$val = get_post_meta( $season_id, self::TV_SHOW_ID_LEGACY_2, true );
		}
		if ( '' === $val ) {
			$parent = wp_get_post_parent_id( $season_id );
			if ( $parent && 'tv_shows' === get_post_type( $parent ) ) {
				$val = $parent;
			}
		}
		$val = $val ? absint( $val ) : 0;
		if ( $val && 'tv_shows' !== get_post_type( $val ) ) {
			return 0;
		}
		return $val;
	}
	public static function set_season_tv_show_id( $season_id, $tv_id ) {
		$tv_id = absint( $tv_id );
		if ( $tv_id && 'tv_shows' !== get_post_type( $tv_id ) ) {
			return false;
		}
		if ( $tv_id === (int) $season_id ) {
			return false;
		}
		update_post_meta( $season_id, self::TV_SHOW_ID_LEGACY_1, $tv_id );
		delete_post_meta( $season_id, self::TV_SHOW_ID_LEGACY_2 );
		return true;
	}
	public static function get_episode_season_id( $ep_id ) {
		$val = get_post_meta( $ep_id, self::SEASON_ID_LEGACY_1, true );
		if ( '' === $val ) {
			$val = get_post_meta( $ep_id, self::SEASON_ID_LEGACY_2, true );
		}
		if ( '' === $val ) {
			$parent = wp_get_post_parent_id( $ep_id );
			if ( $parent && 'seasons' === get_post_type( $parent ) ) {
				$val = $parent;
			}
		}
		$val = $val ? absint( $val ) : 0;
		if ( $val && 'seasons' !== get_post_type( $val ) ) {
			return 0;
		}
		return $val;
	}
	public static function set_episode_season_id( $ep_id, $season_id ) {
		$season_id = absint( $season_id );
		if ( $season_id && 'seasons' !== get_post_type( $season_id ) ) {
			return false;
		}
		if ( $season_id === (int) $ep_id ) {
			return false;
		}
		update_post_meta( $ep_id, self::SEASON_ID_LEGACY_1, $season_id );
		delete_post_meta( $ep_id, self::SEASON_ID_LEGACY_2 );
		return true;
	}
	public static function get_season_number( $season_id ) {
		$val = get_post_meta( $season_id, self::SEASON_NUMBER, true );
		return '' !== $val ? absint( $val ) : 0;
	}
	public static function get_episode_number( $ep_id ) {
		$val = get_post_meta( $ep_id, self::EPISODE_NUMBER, true );
		return '' !== $val ? absint( $val ) : 0;
	}
	public static function get_episode_tv_show_id( $ep_id ) {
		$season_id = self::get_episode_season_id( $ep_id );
		if ( ! $season_id ) {
			return 0;
		}
		return self::get_season_tv_show_id( $season_id );
	}
	public static function get_download_links( $post_id ) {
		$links = get_post_meta( $post_id, self::DL_LINKS_ARRAY, true );
		if ( ! empty( $links ) ) {
			if ( is_string( $links ) ) {
				$decoded = maybe_unserialize( $links );
				$links = is_array( $decoded ) ? $decoded : array( $links );
			}
			if ( is_array( $links ) ) {
				return array_values( array_filter( array_map( 'esc_url_raw', array_map( 'trim', $links ) ) ) );
			}
		}
		$legacy = array();
		foreach ( array( self::DL_1080, self::DL_720, self::DL_480 ) as $k ) {
			$url = get_post_meta( $post_id, $k, true );
			if ( ! empty( $url ) ) {
				$legacy[] = esc_url_raw( trim( $url ) );
			}
		}
		return array_values( array_filter( $legacy ) );
	}
	public static function set_download_links( $post_id, array $urls ) {
		$clean = array_values( array_filter( array_map( 'esc_url_raw', array_map( 'trim', $urls ) ) ) );
		update_post_meta( $post_id, self::DL_LINKS_ARRAY, $clean );
		$keys = array( self::DL_1080, self::DL_720, self::DL_480 );
		foreach ( $keys as $i => $k ) {
			if ( isset( $clean[ $i ] ) ) {
				update_post_meta( $post_id, $k, $clean[ $i ] );
			}
		}
	}
	public static function get_servers( $post_id ) {
		return array(
			'server_1'   => esc_url_raw( trim( (string) get_post_meta( $post_id, self::SERVER_1, true ) ) ),
			'server_2'   => esc_url_raw( trim( (string) get_post_meta( $post_id, self::SERVER_2, true ) ) ),
			'server_vip' => esc_url_raw( trim( (string) get_post_meta( $post_id, self::SERVER_VIP, true ) ) ),
		);
	}
	public static function get_subtitles( $post_id ) {
		return array(
			'en' => esc_url_raw( trim( (string) get_post_meta( $post_id, self::SUB_EN, true ) ) ),
			'si' => esc_url_raw( trim( (string) get_post_meta( $post_id, self::SUB_SI, true ) ) ),
		);
	}
	public static function is_telegram_sent( $post_id ) {
		$val = get_post_meta( $post_id, self::TELEGRAM_POSTED, true );
		return '1' === (string) $val || 1 === $val;
	}
	public static function mark_telegram_sent( $post_id ) {
		update_post_meta( $post_id, self::TELEGRAM_POSTED, '1' );
	}
	public static function get_seasons_for_show( $tv_id, $limit = 50 ) {
		$tv_id = absint( $tv_id );
		if ( ! $tv_id ) {
			return array();
		}
		$cache_key = 'cinecore_seasons_' . $tv_id . '_' . $limit;
		$cached = wp_cache_get( $cache_key, 'cinecore' );
		if ( false !== $cached ) {
			return $cached;
		}
		$q = new WP_Query(
			array(
				'post_type'      => 'seasons',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'orderby'        => 'meta_value_num title',
				'meta_key'       => self::SEASON_NUMBER,
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => self::TV_SHOW_ID_LEGACY_1, 'value' => $tv_id ),
					array( 'key' => self::TV_SHOW_ID_LEGACY_2, 'value' => $tv_id ),
				),
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);
		$ids = $q->posts;
		if ( empty( $ids ) ) {
			$q2 = new WP_Query(
				array(
					'post_type'      => 'seasons',
					'posts_per_page' => $limit,
					'post_parent'    => $tv_id,
					'post_status'    => 'publish',
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'fields'         => 'ids',
				)
			);
			$ids = $q2->posts;
		}
		wp_cache_set( $cache_key, $ids, 'cinecore', HOUR_IN_SECONDS );
		return $ids;
	}
	public static function get_episodes_for_season( $season_id, $limit = 100 ) {
		$season_id = absint( $season_id );
		if ( ! $season_id ) {
			return array();
		}
		$cache_key = 'cinecore_eps_' . $season_id . '_' . $limit;
		$cached = wp_cache_get( $cache_key, 'cinecore' );
		if ( false !== $cached ) {
			return $cached;
		}
		$q = new WP_Query(
			array(
				'post_type'      => 'episodes',
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
				'orderby'        => 'meta_value_num',
				'meta_key'       => self::EPISODE_NUMBER,
				'order'          => 'ASC',
				'meta_query'     => array(
					'relation' => 'OR',
					array( 'key' => self::SEASON_ID_LEGACY_1, 'value' => $season_id ),
					array( 'key' => self::SEASON_ID_LEGACY_2, 'value' => $season_id ),
				),
				'no_found_rows'  => true,
				'fields'         => 'ids',
			)
		);
		$ids = $q->posts;
		if ( empty( $ids ) ) {
			$q2 = new WP_Query(
				array(
					'post_type'      => 'episodes',
					'posts_per_page' => $limit,
					'post_parent'    => $season_id,
					'post_status'    => 'publish',
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'fields'         => 'ids',
				)
			);
			$ids = $q2->posts;
		}
		wp_cache_set( $cache_key, $ids, 'cinecore', HOUR_IN_SECONDS );
		return $ids;
	}
	public static function get_adjacent_episode( $ep_id, $previous = true ) {
		$season_id = self::get_episode_season_id( $ep_id );
		if ( ! $season_id ) {
			return null;
		}
		$ids = self::get_episodes_for_season( $season_id, 100 );
		$idx = array_search( (int) $ep_id, array_map( 'intval', $ids ), true );
		if ( false === $idx ) {
			return null;
		}
		$target = $previous ? $idx - 1 : $idx + 1;
		if ( ! isset( $ids[ $target ] ) ) {
			return null;
		}
		return get_post( $ids[ $target ] );
	}
	public static function clear_caches( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'seasons' === $type ) {
			$tv_id = self::get_season_tv_show_id( $post_id );
			if ( $tv_id ) {
				wp_cache_delete( 'cinecore_seasons_' . $tv_id . '_50', 'cinecore' );
				wp_cache_delete( 'cinecore_seasons_' . $tv_id . '_100', 'cinecore' );
			}
		}
		if ( 'episodes' === $type ) {
			$season_id = self::get_episode_season_id( $post_id );
			if ( $season_id ) {
				wp_cache_delete( 'cinecore_eps_' . $season_id . '_100', 'cinecore' );
			}
		}
	}
}
