<?php
defined('ABSPATH') || exit;

/**
 * AJAX Archive & Live Filtering - Phase 6.4
 *
 * Filter UI via taxonomy dropdowns, JS change -> AJAX cinecore_filter_posts -> WP_Query HTML.
 *
 * @since 1.0.0
 */
class CineCore_Filter {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_cinecore_filter_posts', array( $this, 'handle_filter' ) );
		add_action( 'wp_ajax_nopriv_cinecore_filter_posts', array( $this, 'handle_filter' ) );
		// Non-JS fallback: handle ?genre=&release_year=&quality= on archive pages.
		add_action( 'pre_get_posts', array( $this, 'handle_archive_query' ) );
	}

	/**
	 * Non-JS fallback for archives - reads URL params.
	 *
	 * @since 2.0.0
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function handle_archive_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! is_post_type_archive( array( 'movies', 'tv_shows' ) ) && ! is_tax( array( 'genre', 'release_year', 'quality' ) ) ) {
			return;
		}
		$tax_query = array();
		if ( isset( $_GET['genre'] ) && '' !== $_GET['genre'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$genre = sanitize_text_field( wp_unslash( $_GET['genre'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tax_query[] = array( 'taxonomy' => 'genre', 'field' => 'slug', 'terms' => $genre );
		}
		if ( isset( $_GET['release_year'] ) && '' !== $_GET['release_year'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$year = sanitize_text_field( wp_unslash( $_GET['release_year'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tax_query[] = array( 'taxonomy' => 'release_year', 'field' => 'slug', 'terms' => $year );
		}
		if ( isset( $_GET['quality'] ) && '' !== $_GET['quality'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$qual = sanitize_text_field( wp_unslash( $_GET['quality'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tax_query[] = array( 'taxonomy' => 'quality', 'field' => 'slug', 'terms' => $qual );
		}
		if ( ! empty( $tax_query ) ) {
			$existing = $query->get( 'tax_query' );
			if ( ! empty( $existing ) && is_array( $existing ) ) {
				$tax_query = array_merge( $existing, $tax_query );
			}
			$query->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
	}

	/**
	 * Handle filter AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_filter() {
		// Nonce optional for public archive but verify if sent.
		if ( isset( $_REQUEST['nonce'] ) && '' !== $_REQUEST['nonce'] ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'cinecore_filter' ) ) {
				wp_send_json_error( __( 'Security check failed.', 'cinecore' ) );
			}
		}

		$genre        = isset( $_REQUEST['genre'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['genre'] ) ) : '';
		$release_year = isset( $_REQUEST['release_year'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['release_year'] ) ) : '';
		$quality      = isset( $_REQUEST['quality'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['quality'] ) ) : '';
		$post_type    = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : 'movies';
		$paged        = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;

		// Validate post_type.
		$allowed_types = array( 'movies', 'tv_shows', 'episodes', 'any' );
		if ( ! in_array( $post_type, $allowed_types, true ) ) {
			$post_type = 'movies';
		}
		if ( 'any' === $post_type ) {
			$post_type = array( 'movies', 'tv_shows' );
		}

		$tax_query = array();
		if ( '' !== $genre ) {
			$tax_query[] = array(
				'taxonomy' => 'genre',
				'field'    => 'slug',
				'terms'    => $genre,
			);
		}
		if ( '' !== $release_year ) {
			$tax_query[] = array(
				'taxonomy' => 'release_year',
				'field'    => 'slug',
				'terms'    => $release_year,
			);
		}
		if ( '' !== $quality ) {
			$tax_query[] = array(
				'taxonomy' => 'quality',
				'field'    => 'slug',
				'terms'    => $quality,
			);
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => 8,
			'paged'          => $paged,
			'no_found_rows'  => false,
		);
		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$query = new WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			echo '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">';
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();
				$year_terms = get_the_terms( $post_id, 'release_year' );
				$year = '';
				if ( ! is_wp_error( $year_terms ) && ! empty( $year_terms ) ) {
					$year = $year_terms[0]->name;
				}
				if ( '' === $year ) {
					$year = get_the_date( 'Y' );
				}
				?>
				<article class="bg-cinecore-card rounded-xl overflow-hidden border border-white/5 hover:border-white/10 transition-colors group flex flex-col">
					<a href="<?php echo esc_url( get_permalink() ); ?>" class="block aspect-[2/3] overflow-hidden bg-cinecore-surface">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium', array( 'class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500', 'alt' => the_title_attribute( array( 'echo' => false ) ) ) ); ?>
						<?php else : ?>
							<div class="w-full h-full flex items-center justify-center text-gray-500 text-sm"><?php esc_html_e( 'No Poster', 'cinecore' ); ?></div>
						<?php endif; ?>
					</a>
					<div class="p-4 flex flex-col flex-1">
						<h3 class="font-bold text-white text-[15px] leading-tight line-clamp-2"><a href="<?php echo esc_url( get_permalink() ); ?>" class="hover:text-cinecore-accent"><?php echo esc_html( get_the_title() ); ?></a></h3>
						<p class="text-xs text-gray-400 mt-2"><?php echo esc_html( $year ); ?></p>
					</div>
				</article>
				<?php
			}
			echo '</div>';
			// Pagination info.
			$max_pages = (int) $query->max_num_pages;
			echo '<div class="cinecore-filter-pagination" data-max-pages="' . esc_attr( (string) $max_pages ) . '" data-current-page="' . esc_attr( (string) $paged ) . '"></div>';
		} else {
			echo '<div class="text-center py-12 bg-cinecore-card rounded-xl border border-white/5"><p class="text-gray-400">' . esc_html__( 'No titles match your filters.', 'cinecore' ) . '</p></div>';
		}
		wp_reset_postdata();

		$html = ob_get_clean();
		wp_send_json_success(
			array(
				'html'      => $html,
				'found'     => $query->found_posts,
				'max_pages' => $query->max_num_pages,
			)
		);
	}
}

