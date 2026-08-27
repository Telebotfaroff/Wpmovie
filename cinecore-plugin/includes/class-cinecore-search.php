<?php
defined('ABSPATH') || exit;

/**
 * Live AJAX Search Class - Phase 5.
 *
 * Handles frontend live search for movies and tv_shows.
 *
 * @since 1.0.0
 */
class CineCore_Live_Search {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_cinecore_live_search', array( $this, 'handle_search' ) );
		add_action( 'wp_ajax_nopriv_cinecore_live_search', array( $this, 'handle_search' ) );
	}

	/**
	 * AJAX handler for live search.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_search() {
		// Nonce check - required for both logged-in and nopriv (localized via wp_localize_script).
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'cinecore_live_search' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'cinecore' ), 'code' => 'bad_nonce' ) );
		}

		// Rate limiting: 30 req/min per IP (transient).
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
		$rate_key = 'cinecore_search_rate_' . hash( 'sha256', $ip . wp_salt( 'auth' ) );
		$rate = (int) get_transient( $rate_key );
		if ( $rate > 30 ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Slow down.', 'cinecore' ), 'code' => 'rate_limited' ) );
		}
		set_transient( $rate_key, $rate + 1, MINUTE_IN_SECONDS );

		// Sanitize query - accept 'q' or 'query' or 's'.
		$search = '';
		if ( isset( $_REQUEST['q'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['q'] ) );
		} elseif ( isset( $_REQUEST['query'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['query'] ) );
		} elseif ( isset( $_REQUEST['s'] ) ) {
			$search = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		$search = trim( $search );

		if ( '' === $search ) {
			wp_send_json_success( array() );
		}

		// Limit length to prevent abuse.
		if ( mb_strlen( $search ) > 100 ) {
			$search = mb_substr( $search, 0, 100 );
		}

		// Require at least 2 chars to search.
		if ( mb_strlen( $search ) < 2 ) {
			wp_send_json_success( array() );
		}

		// Cache repeated searches for 5 minutes (helps rapid typing with same term).
		$cache_key = 'cinecore_search_' . md5( strtolower( $search ) );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( $cached );
		}

		$query = new WP_Query(
			array(
				'post_type'      => array( 'movies', 'tv_shows' ),
				'post_status'    => 'publish',
				's'              => $search,
				'posts_per_page' => 8,
				'no_found_rows'  => true,
				'orderby'        => 'relevance',
			)
		);

		$results = array();

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();
				$poster = CineCore_Meta::get_poster( $post_id, true );
				if ( empty( $poster ) ) {
					$thumb = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
					$poster = $thumb ? esc_url_raw( $thumb ) : '';
				}

				$results[] = array(
					'id'        => $post_id,
					'title'     => get_the_title(),
					'permalink' => get_permalink(),
					'poster'    => $poster,
					'type'      => get_post_type(),
				);
			}
			wp_reset_postdata();
		}

		// Cache for 5 minutes.
		set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

		wp_send_json_success( $results );
	}
}

