<?php
/**
 * Author profile - Phase 6.3
 *
 * Translator badge + subtitles count + movie grid.
 *
 * @package CineCore_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$author = get_queried_object();
if ( ! $author instanceof WP_User ) {
	// Fallback for author archive without WP_User (e.g., numeric).
	$author_id = get_query_var( 'author' );
	$author    = get_user_by( 'ID', (int) $author_id );
}
$author_id   = $author ? (int) $author->ID : 0;
$is_translator = $author && in_array( 'translator', (array) $author->roles, true );
$display_name = $author ? $author->display_name : get_the_author();
$avatar_url   = $author ? get_avatar_url( $author_id, array( 'size' => 96 ) ) : '';
$bio          = $author ? get_the_author_meta( 'description', $author_id ) : '';

// Count uploaded subtitles (subtitle_requests CPT).
$subtitle_count = 0;
$movie_ids      = array();
$subtitle_posts = array();
if ( $author_id ) {
	$subtitle_count = count_user_posts( $author_id, 'subtitle_requests', true );
	// Also get via WP_Query for grid (respect maybe).
	$q = new WP_Query(
		array(
			'post_type'      => 'subtitle_requests',
			'author'         => $author_id,
			'posts_per_page' => 12,
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		)
	);
	if ( $q->have_posts() ) {
		$subtitle_posts = $q->posts;
		foreach ( $subtitle_posts as $p ) {
			$mid = get_post_meta( $p->ID, '_cinecore_movie_id', true );
			if ( '' !== $mid ) {
				$movie_ids[] = (int) $mid;
			}
		}
		wp_reset_postdata();
	}
}
$movie_ids = array_unique( array_filter( $movie_ids ) );
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
	<!-- Profile Header -->
	<div class="bg-cinecore-card rounded-2xl border border-white/5 p-8 md:p-10 flex flex-col md:flex-row gap-8 items-start">
		<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>" class="w-24 h-24 rounded-full border-2 border-white/10 flex-shrink-0" loading="lazy" />
		<div class="flex-1 min-w-0">
			<div class="flex flex-wrap items-center gap-3">
				<h1 class="text-3xl font-black text-white"><?php echo esc_html( $display_name ); ?></h1>
				<?php if ( $is_translator ) : ?>
					<span class="inline-flex items-center gap-1.5 bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full border border-blue-500">
						<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<?php esc_html_e( 'Verified Translator', 'cinecore' ); ?>
					</span>
				<?php else : ?>
					<span class="inline-flex items-center gap-1.5 bg-white/10 border border-white/10 text-gray-300 text-xs font-semibold px-3 py-1 rounded-full"><?php echo esc_html( implode( ', ', (array) ( $author ? $author->roles : array() ) ) ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $bio ) : ?>
				<p class="mt-3 text-gray-300 leading-relaxed max-w-2xl"><?php echo esc_html( $bio ); ?></p>
			<?php endif; ?>

			<?php if ( $is_translator ) : ?>
				<div class="mt-6 flex flex-wrap gap-6">
					<div class="bg-black/40 border border-white/5 rounded-xl px-5 py-4 min-w-[140px]">
						<span class="block text-2xl font-black text-white"><?php echo esc_html( number_format_i18n( $subtitle_count ) ); ?></span>
						<span class="text-xs text-gray-400 uppercase tracking-wider font-semibold"><?php esc_html_e( 'Subtitles Uploaded', 'cinecore' ); ?></span>
					</div>
					<div class="bg-black/40 border border-white/5 rounded-xl px-5 py-4 min-w-[140px]">
						<span class="block text-2xl font-black text-white"><?php echo esc_html( number_format_i18n( count( $movie_ids ) ? count( $movie_ids ) : $subtitle_count ) ); ?></span>
						<span class="text-xs text-gray-400 uppercase tracking-wider font-semibold"><?php esc_html_e( 'Movies Translated', 'cinecore' ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $is_translator ) : ?>
		<!-- Translated Movies Grid -->
		<div class="mt-10">
			<h2 class="text-xl font-bold text-white flex items-center gap-2 mb-6">
				<span class="w-1 h-6 bg-cinecore-accent rounded-full"></span>
				<?php esc_html_e( 'Movies Translated', 'cinecore' ); ?>
			</h2>

			<?php if ( ! empty( $movie_ids ) ) : ?>
				<?php
				$movies_q = new WP_Query(
					array(
						'post_type'      => 'movies',
						'post__in'       => $movie_ids,
						'posts_per_page' => 12,
						'orderby'        => 'post__in',
						'no_found_rows'  => true,
					)
				);
				if ( $movies_q->have_posts() ) :
					?>
					<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
						<?php
						while ( $movies_q->have_posts() ) :
							$movies_q->the_post();
							?>
							<article class="bg-cinecore-card rounded-xl overflow-hidden border border-white/5 hover:border-white/10 transition-colors group">
								<a href="<?php echo esc_url( get_permalink() ); ?>" class="block aspect-[2/3] overflow-hidden bg-cinecore-surface">
									<?php if ( has_post_thumbnail() ) : ?>
										<?php the_post_thumbnail( 'medium', array( 'class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-500', 'alt' => the_title_attribute( array( 'echo' => false ) ) ) ); ?>
									<?php else : ?>
										<div class="w-full h-full flex items-center justify-center text-gray-500 text-sm"><?php esc_html_e( 'No Poster', 'cinecore' ); ?></div>
									<?php endif; ?>
								</a>
								<div class="p-4">
									<h3 class="font-semibold text-white truncate"><a href="<?php echo esc_url( get_permalink() ); ?>" class="hover:text-cinecore-accent"><?php echo esc_html( get_the_title() ); ?></a></h3>
									<p class="text-xs text-gray-500"><?php echo esc_html( get_the_date( 'Y' ) ); ?></p>
								</div>
							</article>
							<?php
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				<?php else : ?>
					<p class="text-gray-500"><?php esc_html_e( 'No linked movies found, showing subtitle requests below.', 'cinecore' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( empty( $movie_ids ) && ! empty( $subtitle_posts ) ) : ?>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<?php foreach ( $subtitle_posts as $sp ) : ?>
						<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>" class="block bg-cinecore-card rounded-xl border border-white/5 p-5 hover:border-white/10 transition-colors">
							<h3 class="font-semibold text-white"><?php echo esc_html( get_the_title( $sp->ID ) ); ?></h3>
							<p class="text-sm text-gray-400 line-clamp-2 mt-1"><?php echo esc_html( wp_trim_words( get_post_field( 'post_content', $sp->ID ), 18 ) ); ?></p>
							<span class="inline-flex mt-3 text-xs font-semibold text-cinecore-accent"><?php esc_html_e( 'View Request', 'cinecore' ); ?> →</span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php elseif ( empty( $subtitle_posts ) ) : ?>
				<div class="text-center py-12 bg-cinecore-card rounded-xl border border-white/5">
					<p class="text-gray-400"><?php esc_html_e( 'This translator has not uploaded any subtitles yet.', 'cinecore' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<!-- Default author archive (non-translator): show their posts -->
		<div class="mt-10">
			<h2 class="text-xl font-bold text-white mb-6"><?php esc_html_e( 'Recent Posts', 'cinecore' ); ?></h2>
			<?php
			$recent = new WP_Query(
				array(
					'author'         => $author_id,
					'posts_per_page' => 8,
					'post_status'    => 'publish',
					'no_found_rows'  => true,
				)
			);
			if ( $recent->have_posts() ) :
				?>
				<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
					<?php while ( $recent->have_posts() ) : $recent->the_post(); ?>
						<a href="<?php echo esc_url( get_permalink() ); ?>" class="block bg-cinecore-card rounded-xl border border-white/5 p-5 hover:border-white/10">
							<h3 class="font-semibold text-white"><?php echo esc_html( get_the_title() ); ?></h3>
							<span class="text-xs text-gray-500"><?php echo esc_html( get_post_type() ); ?> • <?php echo esc_html( get_the_date() ); ?></span>
						</a>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php else : ?>
				<p class="text-gray-500"><?php esc_html_e( 'No posts found.', 'cinecore' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
