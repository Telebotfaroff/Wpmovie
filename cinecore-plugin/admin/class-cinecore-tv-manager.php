<?php
/**
 * TV / Season / Episode Manager
 *
 * @package CineCore
 */

defined( 'ABSPATH' ) || exit;

class CineCore_TV_Manager {

	public function __construct() {
		add_action( 'wp_ajax_cinecore_create_tv', array( $this, 'create_tv' ) );
		add_action( 'wp_ajax_cinecore_create_season', array( $this, 'create_season' ) );
		add_action( 'wp_ajax_cinecore_import_seasons', array( $this, 'import_seasons' ) );
		add_action( 'wp_ajax_cinecore_import_episodes', array( $this, 'import_episodes' ) );
		add_action( 'wp_ajax_cinecore_save_episode', array( $this, 'save_episode' ) );
		add_action( 'wp_ajax_cinecore_bulk_save', array( $this, 'bulk_save' ) );
		add_action( 'wp_ajax_cinecore_admin_search', array( $this, 'admin_search' ) );
		add_action( 'wp_ajax_cinecore_link_search', array( $this, 'link_search' ) );
		add_action( 'wp_ajax_cinecore_link_save', array( $this, 'link_save' ) );
	}

	public function create_season() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ) ) );
		}
		$tv_id = isset( $_POST['tv_id'] ) ? absint( $_POST['tv_id'] ) : 0;
		$num   = isset( $_POST['season_number'] ) ? absint( $_POST['season_number'] ) : 0;
		if ( ! $tv_id || ! $num ) {
			wp_send_json_error( array( 'message' => __( 'Missing TV or season number.', 'cinecore' ) ) );
		}
		if ( 'tv_shows' !== get_post_type( $tv_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid TV show.', 'cinecore' ) ) );
		}
		// Duplicate check.
		$existing = get_posts( array( 'post_type' => 'seasons', 'meta_query' => array( array( 'key' => '_cinecore_season_number', 'value' => $num ), array( 'key' => '_cinecore_tv_show_id', 'value' => $tv_id ) ), 'posts_per_page' => 1, 'fields' => 'ids' ) );
		if ( ! empty( $existing ) ) {
			wp_send_json_success( array( 'season_id' => $existing[0], 'message' => __( 'Season already exists.', 'cinecore' ) ) );
		}
		$sid = wp_insert_post( array( 'post_title' => sprintf( __( 'Season %d', 'cinecore' ), $num ), 'post_type' => 'seasons', 'post_status' => 'publish', 'post_parent' => $tv_id ), true );
		if ( is_wp_error( $sid ) ) {
			wp_send_json_error( array( 'message' => $sid->get_error_message() ) );
		}
		update_post_meta( $sid, '_cinecore_season_number', $num );
		update_post_meta( $sid, '_cinecore_tv_show_id', $tv_id );
		CineCore_Meta::clear_caches( $sid );
		wp_send_json_success( array( 'season_id' => $sid, 'message' => __( 'Season created.', 'cinecore' ) ) );
	}

	public function create_tv() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$tmdb_id = isset( $_POST['tmdb_id'] ) ? preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['tmdb_id'] ) ) ) : '';
		$poster  = isset( $_POST['poster_url'] ) ? esc_url_raw( wp_unslash( $_POST['poster_url'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';
		$status  = in_array( $status, array( 'draft', 'publish' ), true ) ? $status : 'draft';
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Title required.', 'cinecore' ) ) );
		}
		if ( '' !== $tmdb_id ) {
			$existing = get_posts( array( 'post_type' => 'tv_shows', 'meta_key' => '_cinecore_tmdb_id', 'meta_value' => $tmdb_id, 'posts_per_page' => 1, 'fields' => 'ids' ) );
			if ( ! empty( $existing ) && (int) $existing[0] !== $post_id ) {
				$post_id = (int) $existing[0];
			}
		}
		$args = array( 'post_title' => $title, 'post_content' => $content, 'post_type' => 'tv_shows', 'post_status' => $status );
		if ( $post_id ) {
			$args['ID'] = $post_id;
			$new_id = wp_update_post( $args, true );
		} else {
			$new_id = wp_insert_post( $args, true );
		}
		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ) );
		}
		$post_id = (int) $new_id;
		if ( '' !== $tmdb_id ) {
			CineCore_Meta::set_tmdb_id( $post_id, $tmdb_id );
		}
		if ( '' !== $poster ) {
			CineCore_Meta::set_poster( $post_id, $poster );
			if ( 'publish' === $status ) {
				CineCore_Media::import_poster( $post_id, $poster, false );
			}
		}
		wp_send_json_success( array( 'post_id' => $post_id, 'view_url' => get_permalink( $post_id ), 'edit_url' => admin_url( 'admin.php?page=cinecore-add-tv&post_id=' . $post_id ) ) );
	}

	public function import_seasons() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ) ) );
		}
		$tv_id   = isset( $_POST['tv_id'] ) ? absint( $_POST['tv_id'] ) : 0;
		$tmdb_id = isset( $_POST['tmdb_id'] ) ? preg_replace( '/[^0-9]/', '', sanitize_text_field( wp_unslash( $_POST['tmdb_id'] ) ) ) : '';
		if ( ! $tmdb_id ) {
			$tmdb_id = CineCore_Meta::get_tmdb_id( $tv_id );
		}
		if ( '' === $tmdb_id ) {
			wp_send_json_error( array( 'message' => __( 'TMDB ID required.', 'cinecore' ) ) );
		}
		$api_key = get_option( 'cinecore_tmdb_api_key', '' );
		if ( '' === trim( (string) $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'TMDB key not configured.', 'cinecore' ) ) );
		}
		$url = add_query_arg( array( 'api_key' => $api_key, 'language' => 'en-US' ), "https://api.themoviedb.org/3/tv/{$tmdb_id}" );
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['seasons'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No seasons found.', 'cinecore' ) ) );
		}
		$created = 0;
		foreach ( $data['seasons'] as $season ) {
			$num = isset( $season['season_number'] ) ? absint( $season['season_number'] ) : 0;
			if ( 0 === $num ) {
				continue;
			}
			// Duplicate check.
			$existing = get_posts(
				array(
					'post_type'  => 'seasons',
					'meta_query' => array(
						array( 'key' => '_cinecore_season_number', 'value' => $num ),
						array( 'key' => '_cinecore_tv_show_id', 'value' => $tv_id ),
					),
					'posts_per_page' => 1,
					'fields' => 'ids',
				)
			);
			if ( ! empty( $existing ) ) {
				continue;
			}
			$title = $season['name'] ?? sprintf( __( 'Season %d', 'cinecore' ), $num );
			$sid = wp_insert_post(
				array(
					'post_title'  => sanitize_text_field( $title ),
					'post_content'=> isset( $season['overview'] ) ? wp_kses_post( $season['overview'] ) : '',
					'post_type'   => 'seasons',
					'post_status' => 'publish',
					'post_parent' => $tv_id,
				),
				true
			);
			if ( ! is_wp_error( $sid ) ) {
				update_post_meta( $sid, '_cinecore_season_number', $num );
				update_post_meta( $sid, '_cinecore_tv_show_id', $tv_id );
				if ( ! empty( $season['poster_path'] ) ) {
					$poster = 'https://image.tmdb.org/t/p/w500' . $season['poster_path'];
					CineCore_Meta::set_poster( $sid, $poster );
					CineCore_Media::import_poster( $sid, $poster, false );
				}
				if ( ! empty( $season['air_date'] ) ) {
					update_post_meta( $sid, '_cinecore_air_date', sanitize_text_field( $season['air_date'] ) );
				}
				$created++;
			}
		}
		// Clear cache.
		CineCore_Meta::clear_caches( $tv_id );
		wp_send_json_success( array( 'created' => $created, 'message' => sprintf( __( '%d seasons imported.', 'cinecore' ), $created ) ) );
	}

	public function import_episodes() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ) ) );
		}
		$season_id = isset( $_POST['season_id'] ) ? absint( $_POST['season_id'] ) : 0;
		$season_num = CineCore_Meta::get_season_number( $season_id );
		$tv_id = CineCore_Meta::get_season_tv_show_id( $season_id );
		$tmdb_id = $tv_id ? CineCore_Meta::get_tmdb_id( $tv_id ) : '';
		if ( ! $season_id || ! $tmdb_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing season or TV TMDB ID.', 'cinecore' ) ) );
		}
		$api_key = get_option( 'cinecore_tmdb_api_key', '' );
		$url = add_query_arg( array( 'api_key' => $api_key, 'language' => 'en-US' ), "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$season_num}" );
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || empty( $data['episodes'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No episodes found.', 'cinecore' ) ) );
		}
		$created = 0;
		foreach ( $data['episodes'] as $ep ) {
			$ep_num = isset( $ep['episode_number'] ) ? absint( $ep['episode_number'] ) : 0;
			// Duplicate check.
			$existing = get_posts(
				array(
					'post_type'  => 'episodes',
					'meta_query' => array(
						array( 'key' => '_cinecore_episode_number', 'value' => $ep_num ),
						array( 'key' => '_cinecore_season_id', 'value' => $season_id ),
					),
					'posts_per_page' => 1,
					'fields' => 'ids',
				)
			);
			if ( ! empty( $existing ) ) {
				continue;
			}
			$title = $ep['name'] ?? sprintf( __( 'Episode %d', 'cinecore' ), $ep_num );
			$eid = wp_insert_post(
				array(
					'post_title'  => sanitize_text_field( $title ),
					'post_content'=> isset( $ep['overview'] ) ? wp_kses_post( $ep['overview'] ) : '',
					'post_type'   => 'episodes',
					'post_status' => 'publish',
					'post_parent' => $season_id,
				),
				true
			);
			if ( ! is_wp_error( $eid ) ) {
				update_post_meta( $eid, '_cinecore_episode_number', $ep_num );
				update_post_meta( $eid, '_cinecore_season_id', $season_id );
				if ( ! empty( $ep['air_date'] ) ) {
					update_post_meta( $eid, '_cinecore_air_date', sanitize_text_field( $ep['air_date'] ) );
				}
				if ( ! empty( $ep['runtime'] ) ) {
					CineCore_Meta::set_runtime( $eid, absint( $ep['runtime'] ) );
				}
				if ( ! empty( $ep['still_path'] ) ) {
					$still = 'https://image.tmdb.org/t/p/w500' . $ep['still_path'];
					CineCore_Meta::set_poster( $eid, $still );
					CineCore_Media::import_poster( $eid, $still, false );
				}
				$created++;
			}
		}
		CineCore_Meta::clear_caches( $season_id );
		wp_send_json_success( array( 'created' => $created, 'message' => sprintf( __( '%d episodes imported.', 'cinecore' ), $created ) ) );
	}

	public function save_episode() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ) ) );
		}
		$ep_id   = isset( $_POST['episode_id'] ) ? absint( $_POST['episode_id'] ) : 0;
		$season_id = isset( $_POST['season_id'] ) ? absint( $_POST['season_id'] ) : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$num     = isset( $_POST['episode_number'] ) ? absint( $_POST['episode_number'] ) : 0;
		$air     = isset( $_POST['air_date'] ) ? sanitize_text_field( wp_unslash( $_POST['air_date'] ) ) : '';
		$dl_1080 = isset( $_POST['dl_1080'] ) ? esc_url_raw( wp_unslash( $_POST['dl_1080'] ) ) : '';
		$dl_720  = isset( $_POST['dl_720'] ) ? esc_url_raw( wp_unslash( $_POST['dl_720'] ) ) : '';
		$dl_480  = isset( $_POST['dl_480'] ) ? esc_url_raw( wp_unslash( $_POST['dl_480'] ) ) : '';
		$srv1    = isset( $_POST['server_1'] ) ? esc_url_raw( wp_unslash( $_POST['server_1'] ) ) : '';
		$srv2    = isset( $_POST['server_2'] ) ? esc_url_raw( wp_unslash( $_POST['server_2'] ) ) : '';
		$sub_en  = isset( $_POST['sub_en'] ) ? esc_url_raw( wp_unslash( $_POST['sub_en'] ) ) : '';
		$sub_si  = isset( $_POST['sub_si'] ) ? esc_url_raw( wp_unslash( $_POST['sub_si'] ) ) : '';
		// Create new episode if no ID but season provided.
		if ( ! $ep_id && $season_id ) {
			if ( 'seasons' !== get_post_type( $season_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid season.', 'cinecore' ) ) );
			}
			if ( '' === $title ) {
				$title = $num ? sprintf( __( 'Episode %d', 'cinecore' ), $num ) : __( 'New Episode', 'cinecore' );
			}
			$ep_id = wp_insert_post( array( 'post_title' => $title, 'post_type' => 'episodes', 'post_status' => 'publish', 'post_parent' => $season_id ), true );
			if ( is_wp_error( $ep_id ) ) {
				wp_send_json_error( array( 'message' => $ep_id->get_error_message() ) );
			}
			if ( $num ) {
				update_post_meta( $ep_id, '_cinecore_episode_number', $num );
			}
			update_post_meta( $ep_id, '_cinecore_season_id', $season_id );
			CineCore_Meta::clear_caches( $ep_id );
			wp_send_json_success( array( 'episode_id' => $ep_id, 'next_id' => 0, 'message' => __( 'Episode created.', 'cinecore' ) ) );
		}
		if ( ! $ep_id || 'episodes' !== get_post_type( $ep_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid episode.', 'cinecore' ) ) );
		}
		if ( '' !== $title ) {
			wp_update_post( array( 'ID' => $ep_id, 'post_title' => $title ) );
		}
		if ( $num ) {
			update_post_meta( $ep_id, '_cinecore_episode_number', $num );
		}
		if ( '' !== $air ) {
			update_post_meta( $ep_id, '_cinecore_air_date', $air );
		}
		// Save links via centralized API (preserves compatibility).
		$links = array_filter( array( $dl_1080, $dl_720, $dl_480 ) );
		if ( ! empty( $links ) ) {
			CineCore_Meta::set_download_links( $ep_id, $links );
		}
		if ( '' !== $srv1 ) {
			update_post_meta( $ep_id, '_cinecore_server_1', $srv1 );
		}
		if ( '' !== $srv2 ) {
			update_post_meta( $ep_id, '_cinecore_server_2', $srv2 );
		}
		if ( '' !== $sub_en ) {
			update_post_meta( $ep_id, '_cinecore_subtitle_en', $sub_en );
		}
		// Find next episode.
		$next = CineCore_Meta::get_adjacent_episode( $ep_id, false );
		wp_send_json_success( array( 'next_id' => $next ? $next->ID : 0, 'message' => __( 'Saved.', 'cinecore' ) ) );
	}

	public function bulk_save() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ) ) );
		}
		$rows = isset( $_POST['rows'] ) ? (array) $_POST['rows'] : array();
		$results = array();
		foreach ( $rows as $row ) {
			$ep_id = isset( $row['episode_id'] ) ? absint( $row['episode_id'] ) : 0;
			if ( ! $ep_id ) {
				continue;
			}
			$dl_1080 = isset( $row['dl_1080'] ) ? esc_url_raw( $row['dl_1080'] ) : '';
			$dl_720  = isset( $row['dl_720'] ) ? esc_url_raw( $row['dl_720'] ) : '';
			$valid = true;
			$msg = '';
			if ( $dl_1080 && ! filter_var( $dl_1080, FILTER_VALIDATE_URL ) ) {
				$valid = false;
				$msg = __( 'invalid 1080p URL', 'cinecore' );
			}
			if ( $valid ) {
				$links = array_filter( array( $dl_1080, $dl_720, isset( $row['dl_480'] ) ? esc_url_raw( $row['dl_480'] ) : '' ) );
				CineCore_Meta::set_download_links( $ep_id, $links );
				if ( isset( $row['server_1'] ) ) {
					update_post_meta( $ep_id, '_cinecore_server_1', esc_url_raw( $row['server_1'] ) );
				}
				if ( isset( $row['server_2'] ) ) {
					update_post_meta( $ep_id, '_cinecore_server_2', esc_url_raw( $row['server_2'] ) );
				}
				$results[] = array( 'episode_id' => $ep_id, 'status' => 'ok', 'message' => 'saved' );
			} else {
				$results[] = array( 'episode_id' => $ep_id, 'status' => 'error', 'message' => $msg );
			}
		}
		wp_send_json_success( array( 'results' => $results ) );
	}

	public function admin_search() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		if ( mb_strlen( $q ) < 2 ) {
			wp_send_json_success( array( 'results' => array() ) );
		}
		$query = new WP_Query( array( 'post_type' => array( 'movies', 'tv_shows', 'seasons', 'episodes' ), 's' => $q, 'posts_per_page' => 10, 'post_status' => 'any' ) );
		$results = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$results[] = array( 'id' => get_the_ID(), 'title' => get_the_title(), 'type' => get_post_type(), 'edit_url' => get_edit_post_link( get_the_ID(), '' ) );
		}
		wp_reset_postdata();
		wp_send_json_success( array( 'results' => $results ) );
	}

	public function link_search() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
		if ( '' === $q ) {
			wp_send_json_success( array( 'results' => array() ) );
		}
		$query = new WP_Query( array( 'post_type' => array( 'movies', 'episodes', 'tv_shows' ), 's' => $q, 'posts_per_page' => 8, 'post_status' => 'any' ) );
		$results = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$pid = get_the_ID();
			$results[] = array( 'id' => $pid, 'title' => get_the_title(), 'type' => get_post_type(), 'year' => get_the_date( 'Y' ) );
		}
		wp_reset_postdata();
		wp_send_json_success( array( 'results' => $results ) );
	}

	public function link_save() {
		check_ajax_referer( 'cinecore_content', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No permission.', 'cinecore' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid post.', 'cinecore' ) ) );
		}
		$links = isset( $_POST['links'] ) ? (array) $_POST['links'] : array();
		$clean = array();
		foreach ( $links as $l ) {
			$url = isset( $l['url'] ) ? esc_url_raw( $l['url'] ) : '';
			if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$clean[] = $url;
			}
		}
		CineCore_Meta::set_download_links( $post_id, $clean );
		wp_send_json_success( array( 'message' => __( 'Links saved.', 'cinecore' ) ) );
	}
}

new CineCore_TV_Manager();
