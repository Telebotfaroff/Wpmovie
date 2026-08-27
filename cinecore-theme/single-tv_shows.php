<?php
/**
 * Single template for TV Shows CPT - Phase 6.1
 *
 * Hero with backdrop/poster + Season/Episode Accordion.
 *
 * @package CineCore_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$tv_id        = get_the_ID();
	$backdrop_url = get_post_meta( $tv_id, '_cinecore_backdrop_url', true );
	$poster_url   = get_post_meta( $tv_id, '_cinecore_poster_url', true );
	$rating       = get_post_meta( $tv_id, '_cinecore_vote_average', true );
	if ( '' === $rating ) {
		$rating = get_post_meta( $tv_id, '_cinecore_rating', true );
	}
	$runtime = get_post_meta( $tv_id, '_cinecore_runtime', true );

	$release_year = '';
	$terms        = get_the_terms( $tv_id, 'release_year' );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) && is_array( $terms ) ) {
		$release_year = $terms[0]->name;
	}
	if ( '' === $release_year ) {
		$release_year = get_the_date( 'Y' );
	}

	$genre_list = '';
	$genres     = get_the_terms( $tv_id, 'genre' );
	if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) {
		$genre_list = implode( ', ', wp_list_pluck( $genres, 'name' ) );
	}

	$has_backdrop = ! empty( $backdrop_url );
	$has_poster   = ! empty( $poster_url );

	// Query seasons linked to this TV show.
	// Supports: _cinecore_tv_show_id, _cinecore_parent_tv_show, or post_parent.
	$seasons_query = new WP_Query(
		array(
			'post_type'      => 'seasons',
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'orderby'        => 'meta_value_num title',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'   => '_cinecore_tv_show_id',
					'value' => $tv_id,
				),
				array(
					'key'   => '_cinecore_parent_tv_show',
					'value' => $tv_id,
				),
			),
			'no_found_rows'  => true,
		)
	);

	// Fallback: also include seasons where post_parent = tv_id (if meta not set).
	$seasons_by_parent = array();
	if ( ! $seasons_query->have_posts() ) {
		$fallback = new WP_Query(
			array(
				'post_type'      => 'seasons',
				'posts_per_page' => 50,
				'post_parent'    => $tv_id,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		if ( $fallback->have_posts() ) {
			$seasons_query = $fallback;
		}
	}
	?>
	<!-- Hero Section (mirrors single-movies) -->
	<section class="relative w-full overflow-hidden bg-black">
		<?php if ( $has_backdrop ) : ?>
			<div class="absolute inset-0">
				<img src="<?php echo esc_url( $backdrop_url ); ?>" alt="<?php echo esc_attr( sprintf( __( '%s backdrop', 'cinecore' ), get_the_title() ) ); ?>" class="w-full h-full object-cover object-top" loading="eager" />
				<div class="absolute inset-0 bg-gradient-to-t from-cinecore-dark via-cinecore-dark/80 to-black/40"></div>
				<div class="absolute inset-0 bg-gradient-to-r from-black/70 via-transparent to-black/30"></div>
			</div>
		<?php else : ?>
			<div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-black to-cinecore-dark"></div>
		<?php endif; ?>

		<div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-16">
			<div class="flex flex-col md:flex-row gap-8 md:gap-10 items-start">
				<div class="w-full md:w-[300px] flex-shrink-0 mx-auto md:mx-0 max-w-[300px]">
					<div class="aspect-[2/3] rounded-xl overflow-hidden bg-cinecore-surface border border-white/10 shadow-2xl">
						<?php if ( $has_poster ) : ?>
							<img src="<?php echo esc_url( $poster_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" class="w-full h-full object-cover" loading="eager" />
						<?php elseif ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium_large', array( 'class' => 'w-full h-full object-cover', 'alt' => the_title_attribute( array( 'echo' => false ) ) ) ); ?>
						<?php else : ?>
							<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-800 to-black">
								<span class="text-gray-500 text-sm"><?php esc_html_e( 'No Poster', 'cinecore' ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="flex-1 min-w-0 text-center md:text-left">
					<h1 class="text-3xl md:text-5xl font-black text-white leading-tight tracking-tight"><?php echo esc_html( get_the_title() ); ?></h1>
					<div class="mt-4 flex flex-wrap items-center justify-center md:justify-start gap-3 text-sm">
						<?php if ( '' !== $release_year ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 text-white px-3 py-1 rounded-full"><?php echo esc_html( $release_year ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $rating && '0' !== (string) $rating ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-yellow-500/15 border border-yellow-500/30 text-yellow-400 px-3 py-1 rounded-full font-semibold">
								<svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
								<?php echo esc_html( number_format_i18n( (float) $rating, 1 ) ); ?> / 10
							</span>
						<?php endif; ?>
						<?php if ( '' !== $runtime && '0' !== (string) $runtime ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 text-white px-3 py-1 rounded-full"><?php echo esc_html( sprintf( __( '%s min', 'cinecore' ), number_format_i18n( (int) $runtime ) ) ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $genre_list ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 text-gray-200 px-3 py-1 rounded-full"><?php echo esc_html( $genre_list ); ?></span>
						<?php endif; ?>
					</div>
					<div class="mt-6 prose prose-invert max-w-none text-gray-200 leading-relaxed text-[15px]">
						<?php
						$content = get_the_content();
						if ( '' !== trim( $content ) ) {
							echo wp_kses_post( $content );
						} else {
							echo '<p class="text-gray-400 italic">' . esc_html__( 'No overview available for this series.', 'cinecore' ) . '</p>';
						}
						?>
					</div>
					<div class="mt-8 flex flex-wrap gap-3 justify-center md:justify-start">
						<a href="#cinecore-seasons" class="inline-flex items-center gap-2 bg-cinecore-accent hover:bg-red-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors shadow-lg">
							<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
							<?php esc_html_e( 'Browse Episodes', 'cinecore' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Seasons Accordion -->
	<section id="cinecore-seasons" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
		<div class="flex items-center gap-3 mb-6">
			<span class="w-1 h-6 bg-cinecore-accent rounded-full"></span>
			<h2 class="text-xl font-bold text-white"><?php esc_html_e( 'Seasons & Episodes', 'cinecore' ); ?></h2>
			<?php if ( $seasons_query->have_posts() ) : ?>
				<span class="ml-2 bg-white/10 border border-white/10 text-gray-300 text-xs font-semibold px-2.5 py-1 rounded-full">
					<?php echo esc_html( sprintf( _n( '%s Season', '%s Seasons', $seasons_query->post_count, 'cinecore' ), number_format_i18n( $seasons_query->post_count ) ) ); ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $seasons_query->have_posts() ) : ?>
			<div class="space-y-3" id="cinecore-accordion" role="tablist">
				<?php
				$season_index = 0;
				while ( $seasons_query->have_posts() ) :
					$seasons_query->the_post();
					$season_id    = get_the_ID();
					$season_title = get_the_title();
					$season_num   = get_post_meta( $season_id, '_cinecore_season_number', true );
					if ( '' === $season_num ) {
						// Fallback: extract number from title or use index.
						$season_num = $season_index + 1;
					}

					// Episodes for this season.
					$episodes_query = new WP_Query(
						array(
							'post_type'      => 'episodes',
							'posts_per_page' => 100,
							'post_status'    => 'publish',
							'orderby'        => 'meta_value_num title',
							'meta_key'       => '_cinecore_episode_number',
							'order'          => 'ASC',
							'meta_query'     => array(
								'relation' => 'OR',
								array(
									'key'   => '_cinecore_season_id',
									'value' => $season_id,
								),
								array(
									'key'   => '_cinecore_parent_season',
									'value' => $season_id,
								),
							),
							'no_found_rows'  => true,
						)
					);
					// Fallback: parent = season_id.
					if ( ! $episodes_query->have_posts() ) {
						$fallback_ep = new WP_Query(
							array(
								'post_type'      => 'episodes',
								'posts_per_page' => 100,
								'post_parent'    => $season_id,
								'post_status'    => 'publish',
								'orderby'        => 'menu_order title',
								'order'          => 'ASC',
								'no_found_rows'  => true,
							)
						);
						if ( $fallback_ep->have_posts() ) {
							$episodes_query = $fallback_ep;
						}
					}

					$episode_count = $episodes_query->post_count;
					$is_first      = 0 === $season_index;
					?>
					<div class="bg-cinecore-card rounded-xl border border-white/5 overflow-hidden">
						<button type="button" class="cinecore-accordion-btn w-full flex items-center justify-between p-4 md:p-5 text-left hover:bg-white/[0.03] transition-colors" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="cinecore-panel-<?php echo esc_attr( $season_id ); ?>" data-target="cinecore-panel-<?php echo esc_attr( $season_id ); ?>">
							<span class="flex items-center gap-3 min-w-0">
								<span class="flex items-center justify-center w-10 h-10 rounded-lg bg-cinecore-accent/15 border border-cinecore-accent/30 text-white font-black text-sm flex-shrink-0">S<?php echo esc_html( $season_num ); ?></span>
								<span class="min-w-0">
									<span class="block font-semibold text-white truncate"><?php echo esc_html( $season_title ? $season_title : sprintf( __( 'Season %s', 'cinecore' ), $season_num ) ); ?></span>
									<span class="block text-xs text-gray-400">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %s: episode count */
												_n( '%s Episode', '%s Episodes', $episode_count, 'cinecore' ),
												number_format_i18n( $episode_count )
											)
										);
										?>
									</span>
								</span>
							</span>
							<span class="flex items-center gap-3 flex-shrink-0 ml-4">
								<span class="hidden sm:inline text-xs text-gray-500"><?php echo $is_first ? esc_html__( 'Hide', 'cinecore' ) : esc_html__( 'Show', 'cinecore' ); ?></span>
								<svg class="cinecore-chevron w-5 h-5 text-gray-400 transition-transform <?php echo $is_first ? 'rotate-180' : ''; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
							</span>
						</button>

						<div id="cinecore-panel-<?php echo esc_attr( $season_id ); ?>" class="<?php echo $is_first ? '' : 'hidden'; ?> border-t border-white/5">
							<?php if ( $episodes_query->have_posts() ) : ?>
								<ul class="divide-y divide-white/5">
									<?php
									while ( $episodes_query->have_posts() ) :
										$episodes_query->the_post();
										$ep_id      = get_the_ID();
										$ep_num     = get_post_meta( $ep_id, '_cinecore_episode_number', true );
										$ep_runtime = get_post_meta( $ep_id, '_cinecore_runtime', true );
										$ep_thumb   = has_post_thumbnail( $ep_id ) ? get_the_post_thumbnail( $ep_id, 'thumbnail', array( 'class' => 'w-20 h-12 object-cover rounded-md bg-cinecore-surface' ) ) : '';
										?>
										<li class="flex items-center gap-4 p-3 md:p-4 hover:bg-white/[0.02] transition-colors">
											<span class="hidden sm:flex items-center justify-center w-8 h-8 rounded-md bg-white/10 text-white text-xs font-bold flex-shrink-0"><?php echo esc_html( $ep_num ? $ep_num : get_the_title() ); ?></span>
											<?php if ( $ep_thumb ) : ?>
												<a href="<?php echo esc_url( get_permalink() ); ?>" class="flex-shrink-0"><?php echo wp_kses_post( $ep_thumb ); ?></a>
											<?php endif; ?>
											<div class="flex-1 min-w-0">
												<a href="<?php echo esc_url( get_permalink() ); ?>" class="block font-medium text-white hover:text-cinecore-accent truncate transition-colors"><?php echo esc_html( get_the_title() ); ?></a>
												<?php if ( '' !== $ep_runtime && '0' !== (string) $ep_runtime ) : ?>
													<span class="text-xs text-gray-500"><?php echo esc_html( sprintf( __( '%s min', 'cinecore' ), $ep_runtime ) ); ?></span>
												<?php endif; ?>
											</div>
											<a href="<?php echo esc_url( get_permalink() ); ?>" class="inline-flex items-center gap-1.5 bg-white/10 hover:bg-cinecore-accent hover:text-white border border-white/10 text-white text-xs font-semibold px-3 py-2 rounded-lg transition-colors flex-shrink-0">
												<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
												<?php esc_html_e( 'Watch', 'cinecore' ); ?>
											</a>
										</li>
										<?php
									endwhile;
									wp_reset_postdata();
									?>
								</ul>
							<?php else : ?>
								<p class="p-6 text-sm text-gray-500 text-center"><?php esc_html_e( 'No episodes found for this season.', 'cinecore' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
					<?php
					$season_index++;
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<div class="text-center py-12 bg-cinecore-card rounded-xl border border-white/5">
				<p class="text-gray-400"><?php esc_html_e( 'No seasons found for this series. Add Season posts and link them via _cinecore_tv_show_id meta or set parent to this TV Show.', 'cinecore' ); ?></p>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=seasons' ) ); ?>" class="inline-flex mt-4 px-4 py-2 bg-cinecore-accent text-white rounded-lg text-sm font-medium"><?php esc_html_e( 'Add Season', 'cinecore' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>

	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var btns = document.querySelectorAll('.cinecore-accordion-btn');
		btns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var targetId = btn.getAttribute('data-target');
				var panel = document.getElementById(targetId);
				var chevron = btn.querySelector('.cinecore-chevron');
				if (!panel) return;
				var isHidden = panel.classList.contains('hidden');
				panel.classList.toggle('hidden', !isHidden);
				btn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
				if (chevron) chevron.classList.toggle('rotate-180', isHidden);
			});
		});
	});
	</script>

	<?php
endwhile;

get_footer();
