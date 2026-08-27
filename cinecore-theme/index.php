<?php
/**
 * Homepage - Redesigned with separate Movies/TV sections and poster helper
 *
 * @package CineCore_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<!-- Hero / Featured -->
<section class="relative bg-black border-b border-white/5">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
		<div class="text-center">
			<h1 class="text-4xl md:text-5xl font-black text-white tracking-tight"><?php esc_html_e( 'Welcome to NexCine', 'cinecore' ); ?></h1>
			<p class="text-gray-400 mt-3 max-w-2xl mx-auto"><?php esc_html_e( 'Stream the latest movies and TV series in 1080p with Sinhala subtitles.', 'cinecore' ); ?></p>
			<div class="mt-6 flex justify-center gap-3">
				<a href="<?php echo esc_url( get_post_type_archive_link( 'movies' ) ); ?>" class="px-6 py-3 bg-cinecore-accent hover:bg-red-700 text-white font-semibold rounded-lg transition-colors"><?php esc_html_e( 'Browse Movies', 'cinecore' ); ?></a>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'tv_shows' ) ); ?>" class="px-6 py-3 bg-white/10 hover:bg-white/15 border border-white/10 text-white font-semibold rounded-lg transition-colors"><?php esc_html_e( 'Browse TV Shows', 'cinecore' ); ?></a>
			</div>
		</div>
	</div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-12">

	<!-- LATEST MOVIES -->
	<section>
		<div class="flex items-center justify-between mb-6">
			<h2 class="text-2xl font-black text-white flex items-center gap-3"><span class="w-1 h-7 bg-cinecore-accent rounded-full"></span><?php esc_html_e( 'Latest Movies', 'cinecore' ); ?></h2>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'movies' ) ); ?>" class="text-sm font-medium text-cinecore-accent hover:text-white transition-colors"><?php esc_html_e( 'View All Movies', 'cinecore' ); ?> →</a>
		</div>
		<?php
		$movies_q = new WP_Query(
			array(
				'post_type'      => 'movies',
				'posts_per_page' => 8,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
		if ( $movies_q->have_posts() ) :
			?>
			<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 md:gap-6">
				<?php
				while ( $movies_q->have_posts() ) :
					$movies_q->the_post();
					$pid = get_the_ID();
					$year = '';
					$terms = get_the_terms( $pid, 'release_year' );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$year = $terms[0]->name;
					} else {
						$year = get_the_date( 'Y' );
					}
					$rating = function_exists( 'CineCore_Meta' ) ? CineCore_Meta::get_rating( $pid ) : (float) get_post_meta( $pid, '_cinecore_vote_average', true );
					$quality_terms = get_the_terms( $pid, 'quality' );
					$quality = ( ! is_wp_error( $quality_terms ) && ! empty( $quality_terms ) ) ? $quality_terms[0]->name : '';
					?>
					<article class="group bg-cinecore-card rounded-xl overflow-hidden border border-white/5 hover:border-white/10 transition-all hover:scale-[1.02] flex flex-col">
						<a href="<?php echo esc_url( get_permalink() ); ?>" class="block aspect-[2/3] overflow-hidden bg-cinecore-surface relative">
							<?php
							$poster_url = function_exists( 'cinecore_get_poster_url' ) ? cinecore_get_poster_url( $pid, 'medium' ) : get_the_post_thumbnail_url( $pid, 'medium' );
							if ( $poster_url ) {
								echo '<img src="' . esc_url( $poster_url ) . '" alt="' . esc_attr( get_the_title() ) . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />';
							} else {
								echo '<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-800 to-black"><span class="text-gray-500 text-xs">' . esc_html__( 'No Poster', 'cinecore' ) . '</span></div>';
							}
							if ( $quality ) {
								echo '<span class="absolute top-2 right-2 bg-black/70 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">' . esc_html( $quality ) . '</span>';
							}
							?>
						</a>
						<div class="p-3 flex flex-col flex-1">
							<h3 class="font-semibold text-white text-sm leading-tight line-clamp-2"><a href="<?php echo esc_url( get_permalink() ); ?>" class="hover:text-cinecore-accent"><?php echo esc_html( get_the_title() ); ?></a></h3>
							<div class="mt-1 flex items-center gap-2 text-xs text-gray-400">
								<span><?php echo esc_html( $year ); ?></span>
								<?php if ( $rating ) : ?>
									<span class="inline-flex items-center gap-1 text-yellow-400">★ <?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="text-center py-12 bg-cinecore-card rounded-xl border border-white/5"><p class="text-gray-400"><?php esc_html_e( 'No movies yet. Add via CineCore → Movies → Add Movie.', 'cinecore' ); ?></p></div>
		<?php endif; ?>
	</section>

	<!-- LATEST TV SHOWS -->
	<section>
		<div class="flex items-center justify-between mb-6">
			<h2 class="text-2xl font-black text-white flex items-center gap-3"><span class="w-1 h-7 bg-blue-600 rounded-full"></span><?php esc_html_e( 'Latest TV Shows', 'cinecore' ); ?></h2>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'tv_shows' ) ); ?>" class="text-sm font-medium text-blue-400 hover:text-white transition-colors"><?php esc_html_e( 'View All TV Shows', 'cinecore' ); ?> →</a>
		</div>
		<?php
		$tv_q = new WP_Query(
			array(
				'post_type'      => 'tv_shows',
				'posts_per_page' => 8,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
		if ( $tv_q->have_posts() ) :
			?>
			<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 md:gap-6">
				<?php
				while ( $tv_q->have_posts() ) :
					$tv_q->the_post();
					$pid = get_the_ID();
					$year = '';
					$terms = get_the_terms( $pid, 'release_year' );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$year = $terms[0]->name;
					} else {
						$year = get_the_date( 'Y' );
					}
					$rating = function_exists( 'CineCore_Meta' ) ? CineCore_Meta::get_rating( $pid ) : 0;
					// Season count cached via CineCore_Meta.
					$season_count = 0;
					if ( function_exists( 'CineCore_Meta' ) ) {
						$seasons = CineCore_Meta::get_seasons_for_show( $pid, 100 );
						$season_count = count( $seasons );
					} else {
						$season_count = (int) get_post_meta( $pid, '_cinecore_season_count', true );
					}
					?>
					<article class="group bg-cinecore-card rounded-xl overflow-hidden border border-white/5 hover:border-white/10 transition-all hover:scale-[1.02] flex flex-col">
						<a href="<?php echo esc_url( get_permalink() ); ?>" class="block aspect-[2/3] overflow-hidden bg-cinecore-surface relative">
							<?php
							$poster_url = function_exists( 'cinecore_get_poster_url' ) ? cinecore_get_poster_url( $pid, 'medium' ) : get_the_post_thumbnail_url( $pid, 'medium' );
							if ( $poster_url ) {
								echo '<img src="' . esc_url( $poster_url ) . '" alt="' . esc_attr( get_the_title() ) . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />';
							} else {
								echo '<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-800 to-black"><span class="text-gray-500 text-xs">' . esc_html__( 'No Poster', 'cinecore' ) . '</span></div>';
							}
							if ( $season_count ) {
								echo '<span class="absolute top-2 right-2 bg-blue-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">' . esc_html( sprintf( _n( '%d Season', '%d Seasons', $season_count, 'cinecore' ), $season_count ) ) . '</span>';
							}
							?>
						</a>
						<div class="p-3 flex flex-col flex-1">
							<h3 class="font-semibold text-white text-sm leading-tight line-clamp-2"><a href="<?php echo esc_url( get_permalink() ); ?>" class="hover:text-blue-400"><?php echo esc_html( get_the_title() ); ?></a></h3>
							<div class="mt-1 flex items-center gap-2 text-xs text-gray-400">
								<span><?php echo esc_html( $year ); ?></span>
								<?php if ( $rating ) : ?>
									<span class="inline-flex items-center gap-1 text-yellow-400">★ <?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="text-center py-12 bg-cinecore-card rounded-xl border border-white/5"><p class="text-gray-400"><?php esc_html_e( 'No TV shows yet. Add via CineCore → TV Shows → Add TV Show.', 'cinecore' ); ?></p></div>
		<?php endif; ?>
	</section>

	<!-- LATEST EPISODES (optional) -->
	<section>
		<div class="flex items-center justify-between mb-6">
			<h2 class="text-2xl font-black text-white flex items-center gap-3"><span class="w-1 h-7 bg-green-600 rounded-full"></span><?php esc_html_e( 'Latest Episodes', 'cinecore' ); ?></h2>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'episodes' ) ); ?>" class="text-sm font-medium text-green-400 hover:text-white"><?php esc_html_e( 'View All Episodes', 'cinecore' ); ?> →</a>
		</div>
		<?php
		$ep_q = new WP_Query(
			array(
				'post_type'      => 'episodes',
				'posts_per_page' => 8,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		if ( $ep_q->have_posts() ) :
			?>
			<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 md:gap-6">
				<?php
				while ( $ep_q->have_posts() ) :
					$ep_q->the_post();
					$pid = get_the_ID();
					?>
					<article class="group bg-cinecore-card rounded-xl overflow-hidden border border-white/5 hover:border-white/10">
						<a href="<?php echo esc_url( get_permalink() ); ?>" class="block aspect-video overflow-hidden bg-cinecore-surface">
							<?php
							$poster_url = function_exists( 'cinecore_get_poster_url' ) ? cinecore_get_poster_url( $pid, 'medium' ) : get_the_post_thumbnail_url( $pid, 'medium' );
							if ( $poster_url ) {
								echo '<img src="' . esc_url( $poster_url ) . '" alt="' . esc_attr( get_the_title() ) . '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />';
							} else {
								echo '<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-800 to-black"><span class="text-gray-500 text-xs">' . esc_html__( 'No Still', 'cinecore' ) . '</span></div>';
							}
							?>
						</a>
						<div class="p-3"><h3 class="font-medium text-white text-sm line-clamp-2"><a href="<?php echo esc_url( get_permalink() ); ?>" class="hover:text-green-400"><?php echo esc_html( get_the_title() ); ?></a></h3><p class="text-xs text-gray-500 mt-1"><?php echo esc_html( get_the_date() ); ?></p></div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="text-center py-8 bg-cinecore-card rounded-xl border border-white/5"><p class="text-gray-500"><?php esc_html_e( 'No episodes yet.', 'cinecore' ); ?></p></div>
		<?php endif; ?>
	</section>

</div>

<?php
get_footer();
