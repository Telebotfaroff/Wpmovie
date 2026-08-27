<?php
defined('ABSPATH') || exit;

/**
 * Dead Link Checker System - Phase 6.2
 *
 * Schedules daily WP-Cron, checks _cinecore_download_links via wp_remote_head,
 * logs 404/failed URLs and shows Dashboard Widget.
 *
 * @since 1.0.0
 */
class CineCore_Dead_Link_Checker {

	/**
	 * Cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'cinecore_daily_link_check';

	/**
	 * Option key for broken links log.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'cinecore_broken_links';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_check' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_widget' ) );
		add_action( 'admin_post_cinecore_run_link_check', array( $this, 'handle_manual_run' ) );
		// Ensure scheduled for existing installs (activation hook may have been missed).
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Don't auto-schedule on every load via cron check - schedule once.
			add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		}
	}

	/**
	 * Schedule daily event (call on activation + init fallback).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule on deactivation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Manual run handler (admin-post).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_manual_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run link checks.', 'cinecore' ) );
		}
		check_admin_referer( 'cinecore_run_link_check' );
		$this->run_check();
		wp_safe_redirect( add_query_arg( 'cinecore_link_check', 'done', admin_url() ) );
		exit;
	}

	/**
	 * Batch size (links per cron run) - filterable.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	private function get_batch_size() {
		return (int) apply_filters( 'cinecore_link_checker_batch_size', 20 );
	}

	/**
	 * Check single URL with caching and detailed status.
	 *
	 * @since 2.0.0
	 * @param string $url URL.
	 * @return array [is_broken(bool), code(mixed), cached(bool)]
	 */
	private function check_url( $url ) {
		$url = esc_url_raw( trim( $url ) );
		if ( '' === $url ) {
			return array( false, 0, false );
		}
		$cache_key = 'cinecore_link_' . md5( $url );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			// Return cached result if checked within 24h and not 429.
			if ( isset( $cached['checked'] ) && time() - $cached['checked'] < DAY_IN_SECONDS && 429 !== $cached['code'] ) {
				return array( $cached['is_broken'], $cached['code'], true );
			}
		}

		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'CineCore Dead Link Checker/1.0',
			)
		);

		$is_broken = false;
		$code      = 0;
		$retryable = false;

		if ( is_wp_error( $response ) ) {
			$code = $response->get_error_code();
			// Timeout/DNS are retryable, not immediately permanent.
			$retryable = in_array( $code, array( 'http_request_failed', 'timeout' ), true );
			$is_broken = true;
		} else {
			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 404 === $code ) {
				$is_broken = true;
			} elseif ( 403 === $code ) {
				// 403 may be bot protection, not necessarily broken - retryable.
				$is_broken = false;
				$retryable = true;
			} elseif ( 429 === $code ) {
				$is_broken = true; // Rate limited, retryable.
				$retryable = true;
			} elseif ( 500 <= $code && $code < 600 ) {
				$is_broken = true;
				$retryable = true; // Server error, retry next time.
			} elseif ( $code >= 400 ) {
				$is_broken = true;
			}
		}

		// Cache result: 24h for permanent, 1h for retryable.
		$ttl = $retryable ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
		set_transient( $cache_key, array( 'is_broken' => $is_broken, 'code' => $code, 'checked' => time(), 'retryable' => $retryable ), $ttl );

		return array( $is_broken && ! $retryable ? true : ( 404 === $code ? true : false ), $code, false );
		// For retryable 403/429/500 we do NOT mark as broken immediately; will recheck next cron.
		// But for dashboard, we show only confirmed broken (404 or non-retryable >=400).
	}

	/**
	 * Run daily check: batched, cached, never blocks frontend.
	 *
	 * @since 2.0.0
	 * @param int $batch_size Override batch size.
	 * @return void
	 */
	public function run_check( $batch_size = 0 ) {
		$batch_size = $batch_size ? absint( $batch_size ) : $this->get_batch_size();
		$offset_key = 'cinecore_link_checker_offset';
		$offset = (int) get_option( $offset_key, 0 );

		// Collect all URLs with post context (flattened).
		$all_links = array();

		// Primary: _cinecore_download_links array.
		$q = new WP_Query(
			array(
				'post_type'      => array( 'movies', 'tv_shows', 'episodes', 'seasons' ),
				'post_status'    => 'publish',
				'posts_per_page' => 100, // Limit to avoid huge query, paginate via offset.
				'offset'         => $offset,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array( 'key' => '_cinecore_download_links', 'compare' => 'EXISTS' ),
				),
				'no_found_rows'  => false,
				'fields'         => 'ids',
			)
		);
		foreach ( $q->posts as $post_id ) {
			$links = CineCore_Meta::get_download_links( $post_id );
			foreach ( $links as $url ) {
				$all_links[] = array( 'post_id' => $post_id, 'url' => $url );
			}
		}
		// Legacy keys if no array links for this batch.
		if ( count( $all_links ) < $batch_size ) {
			$legacy_keys = array( '_cinecore_download_1080p', '_cinecore_download_720p', '_cinecore_download_480p' );
			$lq = new WP_Query(
				array(
					'post_type'      => array( 'movies', 'episodes' ),
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'offset'         => $offset,
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'OR',
						array( 'key' => '_cinecore_download_1080p', 'compare' => 'EXISTS' ),
						array( 'key' => '_cinecore_download_720p', 'compare' => 'EXISTS' ),
					),
					'no_found_rows'  => false,
					'fields'         => 'ids',
				)
			);
			foreach ( $lq->posts as $post_id ) {
				foreach ( $legacy_keys as $k ) {
					$url = get_post_meta( $post_id, $k, true );
					if ( empty( $url ) ) {
						continue;
					}
					$url = esc_url_raw( trim( $url ) );
					$all_links[] = array( 'post_id' => $post_id, 'url' => $url );
				}
			}
		}

		// Deduplicate same URL+post.
		$seen = array();
		$unique = array();
		foreach ( $all_links as $item ) {
			$key = $item['post_id'] . '|' . $item['url'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$unique[] = $item;
		}
		$all_links = $unique;

		// Load existing broken log.
		$existing = get_option( self::OPTION_KEY, array() );
		$existing = is_array( $existing ) ? $existing : array();
		// Remove old entries that were checked >7 days ago to keep log fresh.
		$broken_map = array();
		foreach ( $existing as $e ) {
			if ( isset( $e['checked'] ) && time() - $e['checked'] > 7 * DAY_IN_SECONDS ) {
				continue;
			}
			$broken_map[ $e['post_id'] . '|' . $e['url'] ] = $e;
		}

		// Process only batch_size links this run.
		$to_check = array_slice( $all_links, 0, $batch_size );
		foreach ( $to_check as $item ) {
			$post_id = $item['post_id'];
			$url     = $item['url'];
			list( $is_broken, $code, $cached ) = $this->check_url( $url );
			$key = $post_id . '|' . $url;
			if ( $is_broken ) {
				$broken_map[ $key ] = array(
					'post_id'    => (int) $post_id,
					'post_title' => get_the_title( $post_id ),
					'post_type'  => get_post_type( $post_id ),
					'url'        => $url,
					'code'       => $code,
					'checked'    => time(),
				);
			} else {
				// If previously marked broken but now ok, remove.
				unset( $broken_map[ $key ] );
			}
		}

		$broken = array_values( $broken_map );
		update_option( self::OPTION_KEY, $broken, false );

		// Handle pagination offset.
		$total_found = (int) $q->found_posts;
		$new_offset = $offset + 100;
		if ( $new_offset >= $total_found && $total_found > 0 ) {
			$new_offset = 0; // Completed cycle, restart.
		}
		update_option( $offset_key, $new_offset, false );

		// If we processed a full batch and there are more links, reschedule next batch in 5 minutes via single event.
		$remaining = count( $all_links ) - count( $to_check );
		if ( $remaining > 0 || $new_offset > 0 ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK . '_batch' ) ) {
				wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON_HOOK );
			}
		}
	}

	/**
	 * Add dashboard widget.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_widget() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'cinecore_broken_links_widget',
			__( 'Broken Download Links', 'cinecore' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render widget.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_widget() {
		$broken = get_option( self::OPTION_KEY, array() );
		$run_url = wp_nonce_url( admin_url( 'admin-post.php?action=cinecore_run_link_check' ), 'cinecore_run_link_check' );

		echo '<p class="description">' . esc_html__( 'Daily WP-Cron checks _cinecore_download_links via wp_remote_head. 404/>=400 or WP_Error = broken.', 'cinecore' ) . '</p>';
		echo '<p><a href="' . esc_url( $run_url ) . '" class="button button-secondary">' . esc_html__( 'Run Check Now', 'cinecore' ) . '</a>';
		if ( ! empty( $broken ) ) {
			echo ' <span class="description">' . esc_html( sprintf( __( 'Last check: %s', 'cinecore' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $broken[0]['checked'] ) ) ) . '</span>';
		}
		echo '</p>';

		if ( isset( $_GET['cinecore_link_check'] ) && 'done' === $_GET['cinecore_link_check'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Link check completed.', 'cinecore' ) . '</p></div>';
		}

		if ( empty( $broken ) ) {
			echo '<p style="color:#46b450;">&#10003; ' . esc_html__( 'No broken download links found.', 'cinecore' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="margin-top:10px;"><thead><tr><th>' . esc_html__( 'Post', 'cinecore' ) . '</th><th>' . esc_html__( 'URL', 'cinecore' ) . '</th><th>' . esc_html__( 'Code', 'cinecore' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $broken as $item ) {
			$post_id    = (int) $item['post_id'];
			$post_title = isset( $item['post_title'] ) ? $item['post_title'] : get_the_title( $post_id );
			$url        = isset( $item['url'] ) ? $item['url'] : '';
			$code       = isset( $item['code'] ) ? $item['code'] : '';
			$edit_url   = get_edit_post_link( $post_id );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $post_title ) . '</strong><br><small>' . esc_html( $item['post_type'] ) . ' #' . esc_html( (string) $post_id ) . '</small></td>';
			echo '<td style="word-break:break-all;"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></td>';
			echo '<td><span style="background:#d63638;color:#fff;padding:2px 6px;border-radius:3px;font-size:11px;">' . esc_html( (string) $code ) . '</span></td>';
			echo '<td><a href="' . esc_url( $edit_url ) . '" class="button button-primary button-small">' . esc_html__( 'Edit Post', 'cinecore' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html( sprintf( _n( '%s broken link found.', '%s broken links found.', count( $broken ), 'cinecore' ), number_format_i18n( count( $broken ) ) ) ) . '</p>';
	}
}

