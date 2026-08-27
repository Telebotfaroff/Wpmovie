<?php
defined('ABSPATH') || exit;

/**
 * TMDB Integration Class - Phase 2.
 *
 * Handles Meta Box, JS enqueue and AJAX fetching from TMDB.
 *
 * @since 1.0.0
 */
class CineCore_TMDB {

	/**
	 * Allowed post types for TMDB.
	 *
	 * @var string[]
	 */
	const ALLOWED_POST_TYPES = array( 'movies', 'tv_shows' );

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_tmdb_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_tmdb_id' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_cinecore_fetch_tmdb', array( $this, 'ajax_fetch_tmdb' ) );
	}

	/**
	 * Add meta box to movies and tv_shows.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_tmdb_meta_box() {
		foreach ( self::ALLOWED_POST_TYPES as $post_type ) {
			add_meta_box(
				'cinecore_tmdb_box',
				__( 'TMDB Data', 'cinecore' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render meta box.
	 *
	 * @since 1.0.0
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'cinecore_tmdb_nonce_action', 'cinecore_tmdb_nonce' );

		$tmdb_id = get_post_meta( $post->ID, '_cinecore_tmdb_id', true );
		?>
		<p>
			<label for="cinecore_tmdb_id"><strong><?php esc_html_e( 'TMDB ID', 'cinecore' ); ?></strong></label><br />
			<input
				type="text"
				id="cinecore_tmdb_id"
				name="cinecore_tmdb_id"
				value="<?php echo esc_attr( $tmdb_id ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. 550', 'cinecore' ); ?>"
				class="widefat"
				inputmode="numeric"
				autocomplete="off"
			/>
			<span class="description"><?php esc_html_e( 'Enter TMDB Movie/TV ID.', 'cinecore' ); ?></span>
		</p>
		<p>
			<button type="button" id="cinecore-fetch-tmdb" class="button button-primary widefat">
				<?php esc_html_e( 'Fetch TMDB Data', 'cinecore' ); ?>
			</button>
		</p>
		<div id="cinecore-tmdb-status" style="margin-top:8px;min-height:20px;"></div>
		<?php
		// Show saved meta preview if exists.
		$poster_url = get_post_meta( $post->ID, '_cinecore_poster_url', true );
		if ( ! empty( $poster_url ) ) {
			echo '<p style="margin-top:10px;"><em>' . esc_html__( 'Saved Poster:', 'cinecore' ) . '</em><br/><a href="' . esc_url( $poster_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $poster_url ) . '</a></p>';
		}
	}

	/**
	 * Save TMDB ID on post save.
	 *
	 * @since 1.0.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_tmdb_id( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['cinecore_tmdb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cinecore_tmdb_nonce'] ) ), 'cinecore_tmdb_nonce_action' ) ) {
			return;
		}

		// Autosave / revision check.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check post type allowed.
		if ( ! in_array( $post->post_type, self::ALLOWED_POST_TYPES, true ) ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save sanitized TMDB ID.
		if ( isset( $_POST['cinecore_tmdb_id'] ) ) {
			$tmdb_id = sanitize_text_field( wp_unslash( $_POST['cinecore_tmdb_id'] ) );
			// TMDB IDs are numeric; allow only digits.
			$tmdb_id = preg_replace( '/[^0-9]/', '', $tmdb_id );
			update_post_meta( $post_id, '_cinecore_tmdb_id', $tmdb_id );
		}
	}

	/**
	 * Enqueue admin JS on post edit screens only.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, self::ALLOWED_POST_TYPES, true ) ) {
			return;
		}

		global $post;
		$post_id   = $post ? (int) $post->ID : 0;
		$post_type = $screen->post_type;

		wp_enqueue_script(
			'cinecore-admin',
			CINECORE_PLUGIN_URL . 'assets/js/cinecore-admin.js',
			array( 'jquery' ),
			CINECORE_VERSION,
			true
		);

		wp_localize_script(
			'cinecore-admin',
			'cinecoreTmdb',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'cinecore_tmdb_fetch' ),
				'postId'     => $post_id,
				'postType'   => $post_type,
				'i18n'       => array(
					'fetchButton' => __( 'Fetch TMDB Data', 'cinecore' ),
					'fetching'    => __( 'Fetching...', 'cinecore' ),
					'loading'     => __( 'Loading from TMDB...', 'cinecore' ),
					'success'     => __( 'Data fetched! Title & content populated. Click Update to save.', 'cinecore' ),
					'error'       => __( 'Failed to fetch data. Check ID & API Key.', 'cinecore' ),
					'enterId'     => __( 'Please enter a TMDB ID.', 'cinecore' ),
				),
			)
		);
	}

	/**
	 * AJAX handler: Fetch TMDB data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_fetch_tmdb() {
		// Verify nonce - WPCS: use check_ajax_referer.
		if ( ! check_ajax_referer( 'cinecore_tmdb_fetch', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed. Please refresh and try again.', 'cinecore' ) );
		}

		// Capability check.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'cinecore' ) );
		}

		$tmdb_id   = isset( $_POST['tmdb_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tmdb_id'] ) ) : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		// Validate TMDB ID (numeric).
		$tmdb_id = preg_replace( '/[^0-9]/', '', $tmdb_id );
		if ( '' === $tmdb_id ) {
			wp_send_json_error( __( 'Invalid TMDB ID.', 'cinecore' ) );
		}

		// Validate post type.
		if ( ! in_array( $post_type, self::ALLOWED_POST_TYPES, true ) ) {
			wp_send_json_error( __( 'Invalid post type.', 'cinecore' ) );
		}

		// If post_id provided, verify edit capability for that post.
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'You cannot edit this post.', 'cinecore' ) );
		}

		// Retrieve API key.
		$api_key = get_option( CineCore_Settings::OPTION_KEY, '' );
		$api_key = trim( (string) $api_key );

		if ( '' === $api_key ) {
			wp_send_json_error( __( 'TMDB API Key is not configured. Please set it under Settings > CineCore Settings.', 'cinecore' ) );
		}

		// Build TMDB endpoint with caching.
		$tmdb_entity = ( 'movies' === $post_type ) ? 'movie' : 'tv';
		$cache_key   = CineCore_Cache::tmdb_key( $tmdb_entity, $tmdb_id );
		$cached      = CineCore_Cache::get( $cache_key );
		$data        = null;
		$code        = 0;

		if ( is_array( $cached ) && isset( $cached['data'] ) && isset( $cached['code'] ) ) {
			$data = $cached['data'];
			$code = (int) $cached['code'];
		} else {
			$endpoint = sprintf( 'https://api.themoviedb.org/3/%s/%s', $tmdb_entity, $tmdb_id );
			$url      = add_query_arg(
				array(
					'api_key'  => $api_key,
					'language' => 'en-US',
				),
				$endpoint
			);

			$response = wp_remote_get(
				$url,
				array(
					'timeout' => 15,
					'headers' => array(
						'Accept' => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				// Network/timeout - don't cache, return friendly message without exposing key.
				wp_send_json_error( sprintf( __( 'Network error contacting TMDB: %s', 'cinecore' ), $response->get_error_message() ) );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! is_array( $data ) ) {
				wp_send_json_error( __( 'Malformed response from TMDB. Try again.', 'cinecore' ) );
			}

			// Handle specific HTTP errors without exposing key.
			if ( 401 === $code ) {
				wp_send_json_error( __( 'Invalid TMDB API key. Check Settings > CineCore Settings.', 'cinecore' ) );
			}
			if ( 404 === $code ) {
				$message = isset( $data['status_message'] ) ? $data['status_message'] : __( 'TMDB ID not found. Check the ID.', 'cinecore' );
				wp_send_json_error( $message );
			}
			if ( 429 === $code ) {
				wp_send_json_error( __( 'TMDB rate limit hit. Please wait 10 seconds and retry.', 'cinecore' ) );
			}
			if ( 200 !== $code ) {
				$message = isset( $data['status_message'] ) ? $data['status_message'] : sprintf( __( 'TMDB error (HTTP %d).', 'cinecore' ), $code );
				wp_send_json_error( $message );
			}

			// TMDB sometimes returns success false even with 200.
			if ( isset( $data['success'] ) && false === $data['success'] ) {
				wp_send_json_error( isset( $data['status_message'] ) ? $data['status_message'] : __( 'TMDB returned an error.', 'cinecore' ) );
			}

			// Cache successful 200 for 6 hours to avoid repeated hits.
			CineCore_Cache::set( $cache_key, array( 'data' => $data, 'code' => $code ), 6 * HOUR_IN_SECONDS );
		}

		// Extract fields.
		$title   = '';
		if ( 'movie' === $tmdb_entity ) {
			$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
		} else {
			$title = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		}
		$overview = isset( $data['overview'] ) ? sanitize_textarea_field( $data['overview'] ) : '';

		$poster_path   = isset( $data['poster_path'] ) ? $data['poster_path'] : '';
		$backdrop_path = isset( $data['backdrop_path'] ) ? $data['backdrop_path'] : '';

		$poster_url   = $poster_path ? 'https://image.tmdb.org/t/p/w500' . $poster_path : '';
		$backdrop_url = $backdrop_path ? 'https://image.tmdb.org/t/p/w1280' . $backdrop_path : '';

		$vote_average = isset( $data['vote_average'] ) ? (float) $data['vote_average'] : 0;

		$runtime = 0;
		if ( 'movie' === $tmdb_entity ) {
			$runtime = isset( $data['runtime'] ) ? absint( $data['runtime'] ) : 0;
		} else {
			// TV: use episode_run_time array first element.
			if ( isset( $data['episode_run_time'] ) && is_array( $data['episode_run_time'] ) && ! empty( $data['episode_run_time'] ) ) {
				$runtime = absint( $data['episode_run_time'][0] );
			}
		}

		// Save meta if we have a post ID via centralized API (preserves legacy keys).
		if ( $post_id ) {
			CineCore_Meta::set_tmdb_id( $post_id, $tmdb_id );
			CineCore_Meta::set_poster( $post_id, $poster_url );
			CineCore_Meta::set_backdrop( $post_id, $backdrop_url );
			CineCore_Meta::set_rating( $post_id, $vote_average );
			CineCore_Meta::set_runtime( $post_id, $runtime );
			// Optional extra data if available.
			if ( isset( $data['vote_count'] ) ) {
				update_post_meta( $post_id, '_cinecore_vote_count', absint( $data['vote_count'] ) );
			}
			if ( isset( $data['release_date'] ) && '' !== $data['release_date'] ) {
				update_post_meta( $post_id, '_cinecore_release_date', sanitize_text_field( $data['release_date'] ) );
			} elseif ( isset( $data['first_air_date'] ) && '' !== $data['first_air_date'] ) {
				update_post_meta( $post_id, '_cinecore_release_date', sanitize_text_field( $data['first_air_date'] ) );
			}
		}

		wp_send_json_success(
			array(
				'title'        => $title,
				'overview'     => $overview,
				'poster_url'   => esc_url_raw( $poster_url ),
				'backdrop_url' => esc_url_raw( $backdrop_url ),
				'vote_average' => $vote_average,
				'runtime'      => $runtime,
				'tmdb_id'      => $tmdb_id,
			)
		);
	}
}

