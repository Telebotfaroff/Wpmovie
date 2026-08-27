<?php
/**
 * Movie Manager - AJAX handlers for Content Manager
 *
 * @package CineCore
 */

defined( 'ABSPATH' ) || exit;

class CineCore_Movie_Manager {

	public function __construct() {
		add_action( 'wp_ajax_cinecore_tmdb_search', array( $this, 'tmdb_search' ) );
		add_action( 'wp_ajax_cinecore_create_movie', array( $this, 'create_movie' ) );
		add_action( 'wp_ajax_cinecore_repair_posters', array( $this, 'repair_posters' ) );
	}

	public function tmdb_search() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ), 'code' => 'forbidden' ) );
		}
		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'movie';
		if ( '' === $query ) {
			wp_send_json_error( array( 'message' => __( 'Empty query.', 'cinecore' ), 'code' => 'empty' ) );
		}
		$api_key = get_option( 'cinecore_tmdb_api_key', '' );
		$api_key = trim( (string) $api_key );
		if ( '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'TMDB API key not configured.', 'cinecore' ), 'code' => 'no_key' ) );
		}
		// If numeric, treat as ID fetch.
		if ( preg_match( '/^\d+$/', $query ) ) {
			$entity = ( 'tv' === $type ? 'tv' : 'movie' );
			$url = add_query_arg( array( 'api_key' => $api_key, 'language' => 'en-US' ), "https://api.themoviedb.org/3/{$entity}/{$query}" );
			$res = wp_remote_get( $url, array( 'timeout' => 10 ) );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message(), 'code' => 'http' ) );
			}
			$code = (int) wp_remote_retrieve_response_code( $res );
			$data = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( 200 !== $code || ! is_array( $data ) ) {
				wp_send_json_error( array( 'message' => __( 'Not found.', 'cinecore' ), 'code' => 'notfound' ) );
			}
			// Normalize single result.
			$year = '';
			if ( isset( $data['release_date'] ) ) {
				$year = substr( $data['release_date'], 0, 4 );
			} elseif ( isset( $data['first_air_date'] ) ) {
				$year = substr( $data['first_air_date'], 0, 4 );
			}
			$title = $data['title'] ?? $data['name'] ?? '';
			$results = array(
				array(
					'tmdb_id' => $data['id'] ?? $query,
					'title'   => $title,
					'year'    => $year,
					'rating'  => $data['vote_average'] ?? '',
					'poster'  => isset( $data['poster_path'] ) && $data['poster_path'] ? 'https://image.tmdb.org/t/p/w200' . $data['poster_path'] : '',
					'overview'=> $data['overview'] ?? '',
				),
			);
			wp_send_json_success( array( 'results' => $results ) );
		}
		// Title search.
		$entity = ( 'tv' === $type ? 'tv' : 'movie' );
		$endpoint = "https://api.themoviedb.org/3/search/{$entity}";
		$url = add_query_arg( array( 'api_key' => $api_key, 'language' => 'en-US', 'query' => $query, 'page' => 1 ), $endpoint );
		$res = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message(), 'code' => 'http' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );
		if ( 200 !== $code || ! is_array( $data ) || ! isset( $data['results'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Search failed.', 'cinecore' ), 'code' => 'api' ) );
		}
		$results = array();
		foreach ( array_slice( $data['results'], 0, 6 ) as $item ) {
			$year = '';
			if ( isset( $item['release_date'] ) ) {
				$year = substr( $item['release_date'], 0, 4 );
			} elseif ( isset( $item['first_air_date'] ) ) {
				$year = substr( $item['first_air_date'], 0, 4 );
			}
			$results[] = array(
				'tmdb_id' => $item['id'],
				'title'   => $item['title'] ?? $item['name'] ?? '',
				'year'    => $year,
				'rating'  => $item['vote_average'] ?? '',
				'poster'  => isset( $item['poster_path'] ) && $item['poster_path'] ? 'https://image.tmdb.org/t/p/w200' . $item['poster_path'] : '',
				'overview'=> $item['overview'] ?? '',
			);
		}
		wp_send_json_success( array( 'results' => $results ) );
	}

	public function create_movie() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ), 'code' => 'forbidden' ) );
		}
		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content    = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$tmdb_id    = isset( $_POST['tmdb_id'] ) ? preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['tmdb_id'] ) ) ) : '';
		$year       = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : '';
		$rating     = isset( $_POST['rating'] ) ? sanitize_text_field( wp_unslash( $_POST['rating'] ) ) : '';
		$poster_url = isset( $_POST['poster_url'] ) ? esc_url_raw( wp_unslash( $_POST['poster_url'] ) ) : '';
		$backdrop   = isset( $_POST['backdrop_url'] ) ? esc_url_raw( wp_unslash( $_POST['backdrop_url'] ) ) : '';
		$status     = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';
		$status     = in_array( $status, array( 'draft', 'publish' ), true ) ? $status : 'draft';

		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Title required.', 'cinecore' ), 'code' => 'no_title' ) );
		}
		// Duplicate check by TMDB ID.
		if ( '' !== $tmdb_id ) {
			$existing = get_posts( array( 'post_type' => 'movies', 'meta_key' => '_cinecore_tmdb_id', 'meta_value' => $tmdb_id, 'posts_per_page' => 1, 'fields' => 'ids' ) );
			if ( ! empty( $existing ) && (int) $existing[0] !== $post_id ) {
				$post_id = (int) $existing[0];
			}
		}
		$args = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_type'    => 'movies',
			'post_status'  => $status,
		);
		if ( $post_id ) {
			$args['ID'] = $post_id;
			$new_id = wp_update_post( $args, true );
		} else {
			$new_id = wp_insert_post( $args, true );
		}
		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message(), 'code' => 'db' ) );
		}
		$post_id = (int) $new_id;
		// Save meta via centralized API.
		if ( '' !== $tmdb_id ) {
			CineCore_Meta::set_tmdb_id( $post_id, $tmdb_id );
		}
		if ( '' !== $poster_url ) {
			CineCore_Meta::set_poster( $post_id, $poster_url );
		}
		if ( '' !== $backdrop ) {
			CineCore_Meta::set_backdrop( $post_id, $backdrop );
		}
		if ( '' !== $rating ) {
			CineCore_Meta::set_rating( $post_id, (float) $rating );
		}
		if ( '' !== $year ) {
			// Ensure release_year term exists.
			$term = term_exists( $year, 'release_year' );
			if ( ! $term ) {
				$term = wp_insert_term( $year, 'release_year' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? $term['term_id'] : $term;
				wp_set_object_terms( $post_id, array( (int) $term_id ), 'release_year' );
			}
		}
		// Genres.
		if ( isset( $_POST['genres'] ) && is_array( $_POST['genres'] ) ) {
			$genre_ids = array_map( 'absint', $_POST['genres'] );
			$genre_ids = array_filter( $genre_ids );
			wp_set_object_terms( $post_id, $genre_ids, 'genre' );
		}
		// Download links.
		$dl_quals = isset( $_POST['dl_quality'] ) ? (array) wp_unslash( $_POST['dl_quality'] ) : array();
		$dl_urls  = isset( $_POST['dl_url'] ) ? (array) wp_unslash( $_POST['dl_url'] ) : array();
		$links = array();
		foreach ( $dl_urls as $i => $url ) {
			$url = esc_url_raw( trim( $url ) );
			if ( '' !== $url ) {
				$links[] = $url;
			}
		}
		if ( ! empty( $links ) ) {
			CineCore_Meta::set_download_links( $post_id, $links );
		}
		// Servers.
		$srv_names = isset( $_POST['srv_name'] ) ? (array) wp_unslash( $_POST['srv_name'] ) : array();
		$srv_urls  = isset( $_POST['srv_url'] ) ? (array) wp_unslash( $_POST['srv_url'] ) : array();
		$servers = array();
		foreach ( $srv_urls as $i => $url ) {
			$url = esc_url_raw( trim( $url ) );
			if ( '' !== $url ) {
				$servers[] = $url;
			}
		}
		if ( isset( $servers[0] ) ) {
			update_post_meta( $post_id, '_cinecore_server_1', $servers[0] );
		}
		if ( isset( $servers[1] ) ) {
			update_post_meta( $post_id, '_cinecore_server_2', $servers[1] );
		}
		if ( isset( $servers[2] ) ) {
			update_post_meta( $post_id, '_cinecore_server_vip', $servers[2] );
		}
		// Subtitles.
		$sub_langs = isset( $_POST['sub_lang'] ) ? (array) wp_unslash( $_POST['sub_lang'] ) : array();
		$sub_urls  = isset( $_POST['sub_url'] ) ? (array) wp_unslash( $_POST['sub_url'] ) : array();
		foreach ( $sub_langs as $i => $lang ) {
			$lang = sanitize_text_field( $lang );
			$url  = isset( $sub_urls[ $i ] ) ? esc_url_raw( trim( $sub_urls[ $i ] ) ) : '';
			if ( '' === $url ) {
				continue;
			}
			if ( 'English' === $lang ) {
				update_post_meta( $post_id, '_cinecore_subtitle_en', $url );
			} elseif ( 'Sinhala' === $lang ) {
				update_post_meta( $post_id, '_cinecore_subtitle_si', $url );
			} else {
				update_post_meta( $post_id, '_cinecore_subtitle_' . sanitize_key( $lang ), $url );
			}
		}
		// Import poster to media library if publish and has poster.
		if ( 'publish' === $status && '' !== $poster_url ) {
			$import = CineCore_Media::import_poster( $post_id, $poster_url, false );
			if ( is_wp_error( $import ) ) {
				// Don't fail publish, just log.
				error_log( '[CineCore] Poster import failed for ' . $post_id . ': ' . $import->get_error_message() );
			}
		}

		wp_send_json_success(
			array(
				'post_id'  => $post_id,
				'edit_url' => get_edit_post_link( $post_id, '' ),
				'view_url' => get_permalink( $post_id ),
				'message'  => 'publish' === $status ? __( 'Movie published successfully.', 'cinecore' ) : __( 'Draft saved.', 'cinecore' ),
			)
		);
	}

	public function repair_posters() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ), 'code' => 'forbidden' ) );
		}
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch  = isset( $_POST['batch'] ) ? absint( $_POST['batch'] ) : 5;
		$batch  = $batch > 0 && $batch <= 20 ? $batch : 5;

		$q = new WP_Query(
			array(
				'post_type'      => array( 'movies', 'tv_shows', 'seasons', 'episodes' ),
				'posts_per_page' => $batch,
				'offset'         => $offset,
				'post_status'    => 'any',
				'meta_query'     => array(
					array( 'key' => '_cinecore_poster_url', 'compare' => 'EXISTS' ),
				),
				'no_found_rows'  => false,
			)
		);
		$processed = 0;
		$repaired  = 0;
		$log = array();
		foreach ( $q->posts as $post ) {
			$pid = $post->ID;
			if ( has_post_thumbnail( $pid ) ) {
				$log[] = sprintf( __( 'Skipped %d: already has thumbnail', 'cinecore' ), $pid );
				continue;
			}
			$url = CineCore_Meta::get_poster( $pid, false );
			if ( '' === $url ) {
				continue;
			}
			$res = CineCore_Media::import_poster( $pid, $url, false );
			if ( is_wp_error( $res ) ) {
				$log[] = sprintf( __( 'Failed %d: %s', 'cinecore' ), $pid, $res->get_error_message() );
			} else {
				$repaired++;
				$log[] = sprintf( __( 'Repaired %d: %s', 'cinecore' ), $pid, get_the_title( $pid ) );
			}
			$processed++;
		}
		$total = (int) $q->found_posts;
		$next_offset = $offset + $batch;
		$done = $next_offset >= $total;
		wp_send_json_success(
			array(
				'processed'   => $processed,
				'repaired'    => $repaired,
				'total'       => $total,
				'next_offset' => $next_offset,
				'done'        => $done,
				'log'         => $log,
				'message'     => $done ? __( 'Repair complete.', 'cinecore' ) : __( 'Batch complete.', 'cinecore' ),
			)
		);
	}
}

new CineCore_Movie_Manager();
