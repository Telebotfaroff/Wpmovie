<?php
/**
 * CineCore Admin - Content Manager Menu
 *
 * @package CineCore
 */

defined( 'ABSPATH' ) || exit;

class CineCore_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		// Top level CineCore.
		add_menu_page(
			__( 'CineCore', 'cinecore' ),
			__( 'CineCore', 'cinecore' ),
			'edit_posts',
			'cinecore',
			array( $this, 'render_dashboard' ),
			'dashicons-video-alt2',
			5
		);

		// Dashboard (same as top level).
		add_submenu_page( 'cinecore', __( 'Dashboard', 'cinecore' ), __( 'Dashboard', 'cinecore' ), 'edit_posts', 'cinecore', array( $this, 'render_dashboard' ) );

		// Movies.
		add_submenu_page( 'cinecore', __( 'Movies', 'cinecore' ), __( 'Movies', 'cinecore' ), 'edit_posts', 'cinecore-movies', array( $this, 'render_movies' ) );
		add_submenu_page( 'cinecore', __( 'Add Movie', 'cinecore' ), __( 'Add Movie', 'cinecore' ), 'edit_posts', 'cinecore-add-movie', array( $this, 'render_add_movie' ) );

		// TV Shows.
		add_submenu_page( 'cinecore', __( 'TV Shows', 'cinecore' ), __( 'TV Shows', 'cinecore' ), 'edit_posts', 'cinecore-tv-shows', array( $this, 'render_tv_shows' ) );
		add_submenu_page( 'cinecore', __( 'Add TV Show', 'cinecore' ), __( 'Add TV Show', 'cinecore' ), 'edit_posts', 'cinecore-add-tv', array( $this, 'render_add_tv' ) );

		// Seasons / Episodes link to existing CPT list (keeps compatibility).
		add_submenu_page( 'cinecore', __( 'Seasons', 'cinecore' ), __( 'Seasons', 'cinecore' ), 'edit_posts', 'edit.php?post_type=seasons' );
		add_submenu_page( 'cinecore', __( 'Episodes', 'cinecore' ), __( 'Episodes', 'cinecore' ), 'edit_posts', 'edit.php?post_type=episodes' );

		// Link Manager.
		add_submenu_page( 'cinecore', __( 'Link Manager', 'cinecore' ), __( 'Link Manager', 'cinecore' ), 'edit_posts', 'cinecore-links', array( $this, 'render_link_manager' ) );

		// Tools / Repair.
		add_submenu_page( 'cinecore', __( 'Tools', 'cinecore' ), __( 'Tools', 'cinecore' ), 'manage_options', 'cinecore-tools', array( $this, 'render_tools' ) );

		// Hidden managers (no menu, accessible via direct link).
		add_submenu_page( null, __( 'Season Manager', 'cinecore' ), __( 'Season Manager', 'cinecore' ), 'edit_posts', 'cinecore-season-manager', array( $this, 'render_season_manager' ) );
		add_submenu_page( null, __( 'Episode Manager', 'cinecore' ), __( 'Episode Manager', 'cinecore' ), 'edit_posts', 'cinecore-episode-manager', array( $this, 'render_episode_manager' ) );

		// Settings (also under Settings menu, but duplicate for convenience).
		add_submenu_page( 'cinecore', __( 'Settings', 'cinecore' ), __( 'Settings', 'cinecore' ), 'manage_options', 'cinecore-settings', array( $this, 'render_settings_proxy' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'cinecore' ) === false ) {
			return;
		}
		wp_enqueue_style( 'cinecore-admin', CINECORE_PLUGIN_URL . 'admin/assets/cinecore-admin.css', array(), CINECORE_VERSION );
		wp_enqueue_script( 'cinecore-content-manager', CINECORE_PLUGIN_URL . 'admin/assets/cinecore-content-manager.js', array( 'jquery' ), CINECORE_VERSION, true );
		wp_localize_script(
			'cinecore-content-manager',
			'cinecoreAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cinecore_content' ),
				'i18n'    => array(
					'searching' => __( 'Searching TMDB...', 'cinecore' ),
					'noResults' => __( 'No matching titles found.', 'cinecore' ),
					'error'     => __( 'Request failed. Try again.', 'cinecore' ),
				),
			)
		);
	}

	public function render_dashboard() {
		include CINECORE_PLUGIN_DIR . 'admin/views/dashboard.php';
	}
	public function render_movies() {
		// Reuse WP_List_Table for movies but with CineCore styling.
		echo '<div class="wrap cinecore-wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Movies', 'cinecore' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=cinecore-add-movie' ) ) . '" class="page-title-action">' . esc_html__( 'Add Movie', 'cinecore' ) . '</a>';
		echo '<hr class="wp-header-end">';
		// Embed existing movies list via iframe or redirect? For now, show WP_Query table.
		$this->render_post_list( 'movies' );
		echo '</div>';
	}
	public function render_tv_shows() {
		echo '<div class="wrap cinecore-wrap"><h1 class="wp-heading-inline">' . esc_html__( 'TV Shows', 'cinecore' ) . '</h1>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=cinecore-add-tv' ) ) . '" class="page-title-action">' . esc_html__( 'Add TV Show', 'cinecore' ) . '</a><hr class="wp-header-end">';
		$this->render_post_list( 'tv_shows' );
		echo '</div>';
	}
	public function render_add_movie() {
		include CINECORE_PLUGIN_DIR . 'admin/views/movie-form.php';
	}
	public function render_add_tv() {
		include CINECORE_PLUGIN_DIR . 'admin/views/tv-form.php';
	}
	public function render_link_manager() {
		include CINECORE_PLUGIN_DIR . 'admin/views/link-manager.php';
	}
	public function render_season_manager() {
		include CINECORE_PLUGIN_DIR . 'admin/views/season-manager.php';
	}
	public function render_episode_manager() {
		include CINECORE_PLUGIN_DIR . 'admin/views/episode-manager.php';
	}
	public function render_tools() {
		include CINECORE_PLUGIN_DIR . 'admin/views/tools.php';
	}
	public function render_settings_proxy() {
		echo '<div class="wrap"><h1>' . esc_html__( 'CineCore Settings', 'cinecore' ) . '</h1>';
		echo '<p><a href="' . esc_url( admin_url( 'options-general.php?page=cinecore-settings' ) ) . '" class="button button-primary">' . esc_html__( 'Go to Settings', 'cinecore' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Settings are managed under Settings → CineCore Settings for compatibility.', 'cinecore' ) . '</p></div>';
	}

	private function render_post_list( $post_type ) {
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 20,
			'paged'          => $paged,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$q = new WP_Query( $args );
		echo '<form method="get" style="margin:12px 0;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( 'cinecore-' . ( 'movies' === $post_type ? 'movies' : 'tv-shows' ) ) . '" />';
		echo '<p class="search-box"><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search by title / TMDB ID', 'cinecore' ) . '" /><input type="submit" class="button" value="' . esc_attr__( 'Search', 'cinecore' ) . '" /></p>';
		echo '</form>';
		if ( $q->have_posts() ) {
			echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Poster', 'cinecore' ) . '</th><th>' . esc_html__( 'Title', 'cinecore' ) . '</th><th>' . esc_html__( 'Year', 'cinecore' ) . '</th><th>' . esc_html__( 'Status', 'cinecore' ) . '</th><th></th></tr></thead><tbody>';
			while ( $q->have_posts() ) {
				$q->the_post();
				$pid = get_the_ID();
				echo '<tr>';
				echo '<td style="width:60px;">';
				if ( has_post_thumbnail( $pid ) ) {
					echo get_the_post_thumbnail( $pid, 'thumbnail', array( 'style' => 'width:50px;height:70px;object-fit:cover;' ) );
				} else {
					$poster = CineCore_Meta::get_poster( $pid, false );
					if ( $poster ) {
						echo '<img src="' . esc_url( $poster ) . '" style="width:50px;height:70px;object-fit:cover;" alt="" />';
					} else {
						echo '<span class="description">' . esc_html__( 'No Poster', 'cinecore' ) . '</span>';
					}
				}
				echo '</td>';
				echo '<td><strong>' . esc_html( get_the_title() ) . '</strong><br><small>ID ' . esc_html( (string) $pid ) . ' • TMDB ' . esc_html( CineCore_Meta::get_tmdb_id( $pid ) ) . '</small></td>';
				$year = '';
				$terms = get_the_terms( $pid, 'release_year' );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$year = $terms[0]->name;
				}
				echo '<td>' . esc_html( $year ? $year : get_the_date( 'Y' ) ) . '</td>';
				echo '<td>' . esc_html( get_post_status() ) . '</td>';
				echo '<td><a href="' . esc_url( get_edit_post_link( $pid ) ) . '" class="button">' . esc_html__( 'Edit', 'cinecore' ) . '</a> <a href="' . esc_url( get_permalink( $pid ) ) . '" class="button" target="_blank">' . esc_html__( 'View', 'cinecore' ) . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			$big = 999999999;
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ), 'format' => '?paged=%#%', 'current' => $paged, 'total' => $q->max_num_pages ) ) ) . '</div></div>';
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'No titles found.', 'cinecore' ) . '</p>';
		}
	}
}

new CineCore_Admin();
