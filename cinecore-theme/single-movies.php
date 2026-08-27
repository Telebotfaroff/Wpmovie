<?php
/**
 * Single template for Movies CPT - Phase 4
 *
 * Hero with backdrop/poster, smart video player with server switcher,
 * and download/subtitles grid.
 *
 * @package CineCore_Theme
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$post_id = get_the_ID();

	// --- Fetch Phase 2 meta (with fallbacks for rating key) ---
	$backdrop_url = get_post_meta( $post_id, '_cinecore_backdrop_url', true );
	$poster_url   = get_post_meta( $post_id, '_cinecore_poster_url', true );

	// Rating: Phase 2 saved as _cinecore_vote_average, spec asks _cinecore_rating.
	$rating = get_post_meta( $post_id, '_cinecore_vote_average', true );
	if ( '' === $rating ) {
		$rating = get_post_meta( $post_id, '_cinecore_rating', true );
	}

	$runtime = get_post_meta( $post_id, '_cinecore_runtime', true );

	// Servers (placeholder embed URLs).
	$server_1   = get_post_meta( $post_id, '_cinecore_server_1', true );
	$server_2   = get_post_meta( $post_id, '_cinecore_server_2', true );
	$server_vip = get_post_meta( $post_id, '_cinecore_server_vip', true );

	// Fallbacks for demo if empty (so UI is testable).
	if ( '' === $server_1 ) {
		$server_1 = 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0';
	}
	if ( '' === $server_2 ) {
		$server_2 = 'https://www.youtube.com/embed/9bZkp7q19f0?rel=0';
	}
	if ( '' === $server_vip ) {
		$server_vip = 'https://www.youtube.com/embed/jNQXAC9IVRw?rel=0';
	}

	// Download links.
	$dl_1080 = get_post_meta( $post_id, '_cinecore_download_1080p', true );
	$dl_720  = get_post_meta( $post_id, '_cinecore_download_720p', true );
	$dl_480  = get_post_meta( $post_id, '_cinecore_download_480p', true );

	// Subtitles.
	$sub_en = get_post_meta( $post_id, '_cinecore_subtitle_en', true );
	$sub_si = get_post_meta( $post_id, '_cinecore_subtitle_si', true );

	// Release year taxonomy.
	$release_year = '';
	$terms        = get_the_terms( $post_id, 'release_year' );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) && is_array( $terms ) ) {
		$release_year = $terms[0]->name;
	}
	if ( '' === $release_year ) {
		$release_year = get_the_date( 'Y' );
	}

	// Genre list.
	$genre_list = '';
	$genres     = get_the_terms( $post_id, 'genre' );
	if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) {
		$genre_names = wp_list_pluck( $genres, 'name' );
		$genre_list  = implode( ', ', $genre_names );
	}

	// Sanitized defaults for display.
	$has_backdrop = ! empty( $backdrop_url );
	$has_poster   = ! empty( $poster_url );
	?>
	<!-- Hero Section -->
	<section class="relative w-full overflow-hidden bg-black">
		<?php if ( $has_backdrop ) : ?>
			<div class="absolute inset-0">
				<img src="<?php echo esc_url( $backdrop_url ); ?>" alt="<?php echo esc_attr( sprintf( __( '%s backdrop', 'cinecore' ), get_the_title() ) ); ?>" class="w-full h-full object-cover object-top" loading="eager" />
				<!-- Dark gradient overlay -->
				<div class="absolute inset-0 bg-gradient-to-t from-cinecore-dark via-cinecore-dark/80 to-black/40"></div>
				<div class="absolute inset-0 bg-gradient-to-r from-black/70 via-transparent to-black/30"></div>
			</div>
		<?php else : ?>
			<div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-black to-cinecore-dark"></div>
		<?php endif; ?>

		<div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-16">
			<div class="flex flex-col md:flex-row gap-8 md:gap-10 items-start">
				<!-- Poster -->
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

				<!-- Details -->
				<div class="flex-1 min-w-0 text-center md:text-left">
					<h1 class="text-3xl md:text-5xl font-black text-white leading-tight tracking-tight">
						<?php echo esc_html( get_the_title() ); ?>
					</h1>

					<!-- Meta row: year • rating • runtime • genres -->
					<div class="mt-4 flex flex-wrap items-center justify-center md:justify-start gap-3 text-sm">
						<?php if ( '' !== $release_year ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 text-white px-3 py-1 rounded-full">
								<svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
								<?php echo esc_html( $release_year ); ?>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $rating && '0' !== (string) $rating ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-yellow-500/15 border border-yellow-500/30 text-yellow-400 px-3 py-1 rounded-full font-semibold">
								<svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
								<?php echo esc_html( number_format_i18n( (float) $rating, 1 ) ); ?> / 10
							</span>
						<?php endif; ?>

						<?php if ( '' !== $runtime && '0' !== (string) $runtime ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 text-white px-3 py-1 rounded-full">
								<svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
								<?php
								/* translators: %s: runtime in minutes */
								echo esc_html( sprintf( __( '%s min', 'cinecore' ), number_format_i18n( (int) $runtime ) ) );
								?>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $genre_list ) : ?>
							<span class="inline-flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 text-gray-200 px-3 py-1 rounded-full">
								<?php echo esc_html( $genre_list ); ?>
							</span>
						<?php endif; ?>
					</div>

					<!-- Overview -->
					<div class="mt-6 prose prose-invert max-w-none text-gray-200 leading-relaxed text-[15px]">
						<?php
						$content = get_the_content();
						if ( '' !== trim( $content ) ) {
							echo wp_kses_post( $content );
						} else {
							echo '<p class="text-gray-400 italic">' . esc_html__( 'No overview available for this title.', 'cinecore' ) . '</p>';
						}
						?>
					</div>

					<!-- Quick actions -->
					<div class="mt-8 flex flex-wrap gap-3 justify-center md:justify-start">
						<a href="#cinecore-player-wrap" class="inline-flex items-center gap-2 bg-cinecore-accent hover:bg-red-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors shadow-lg">
							<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
							<?php esc_html_e( 'Watch Now', 'cinecore' ); ?>
						</a>
						<?php if ( '' !== $dl_1080 ) : ?>
							<a href="<?php echo esc_url( $dl_1080 ); ?>" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur border border-white/10 text-white font-medium px-6 py-3 rounded-lg transition-colors">
								<?php esc_html_e( 'Download 1080p', 'cinecore' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Video Player + Server Switcher -->
	<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
		<!-- Server Switcher UI -->
		<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
			<h2 class="text-lg font-bold text-white flex items-center gap-2">
				<span class="w-1 h-6 bg-cinecore-accent rounded-full"></span>
				<?php esc_html_e( 'Watch', 'cinecore' ); ?>
			</h2>
			<div class="flex items-center gap-2 p-1 bg-cinecore-card rounded-lg border border-white/5" role="tablist" aria-label="<?php esc_attr_e( 'Server Switcher', 'cinecore' ); ?>">
				<button type="button" class="cinecore-server-btn active bg-cinecore-accent text-white px-4 py-2 rounded-md text-sm font-semibold transition-colors" data-src="<?php echo esc_url( $server_1 ); ?>" aria-selected="true" role="tab">
					<?php esc_html_e( 'Server 1', 'cinecore' ); ?>
				</button>
				<button type="button" class="cinecore-server-btn bg-transparent hover:bg-white/10 text-gray-300 hover:text-white px-4 py-2 rounded-md text-sm font-medium transition-colors" data-src="<?php echo esc_url( $server_2 ); ?>" aria-selected="false" role="tab">
					<?php esc_html_e( 'Server 2', 'cinecore' ); ?>
				</button>
				<button type="button" class="cinecore-server-btn bg-transparent hover:bg-white/10 text-gray-300 hover:text-white px-4 py-2 rounded-md text-sm font-medium transition-colors border border-yellow-500/20" data-src="<?php echo esc_url( $server_vip ); ?>" aria-selected="false" role="tab">
					<span class="inline-flex items-center gap-1">
						<svg class="w-3.5 h-3.5 text-yellow-400" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<?php esc_html_e( 'VIP Server', 'cinecore' ); ?>
					</span>
				</button>
			</div>
		</div>

		<!-- 16:9 Player Container -->
		<div id="cinecore-player-wrap" class="relative w-full aspect-video bg-black rounded-xl overflow-hidden border border-white/10 shadow-2xl">
			<iframe
				id="cinecore-player"
				data-post-id="<?php echo esc_attr( (string) get_the_ID() ); ?>"
				src="<?php echo esc_url( $server_1 ); ?>"
				title="<?php echo esc_attr( sprintf( __( '%s player', 'cinecore' ), get_the_title() ) ); ?>"
				class="absolute inset-0 w-full h-full"
				frameborder="0"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				allowfullscreen
				loading="lazy"
				referrerpolicy="strict-origin-when-cross-origin"
			></iframe>
			<!-- Loading indicator -->
			<div id="cinecore-player-loader" class="absolute inset-0 flex items-center justify-center bg-black pointer-events-none opacity-0 transition-opacity">
				<span class="inline-flex items-center gap-2 text-gray-400 text-sm">
					<svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
					<?php esc_html_e( 'Switching server...', 'cinecore' ); ?>
				</span>
			</div>
		</div>
		<p class="text-xs text-gray-500 mt-3 text-center">
			<?php esc_html_e( 'If current server does not work, please try another server.', 'cinecore' ); ?>
		</p>
	</section>

	<!-- Download & Subtitles Grid -->
	<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 cinecore-downloads-wrap">
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
			<!-- Left: Download Links -->
			<div class="bg-cinecore-card rounded-xl border border-white/5 p-6">
				<h3 class="text-lg font-bold text-white flex items-center gap-2 mb-5">
					<span class="w-8 h-8 rounded-lg bg-cinecore-accent/20 border border-cinecore-accent/30 flex items-center justify-center">
						<svg class="w-4 h-4 text-cinecore-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
					</span>
					<?php esc_html_e( 'Download Links', 'cinecore' ); ?>
				</h3>

				<div class="space-y-3">
					<?php if ( '' !== $dl_1080 ) : ?>
						<a href="<?php echo esc_url( $dl_1080 ); ?>" class="flex items-center justify-between w-full bg-gradient-to-r from-cinecore-accent to-red-700 hover:from-red-700 hover:to-red-800 text-white font-semibold px-5 py-3.5 rounded-lg transition-all shadow-md group" download>
							<span class="flex items-center gap-3">
								<span class="bg-white/20 rounded-md px-2 py-1 text-xs font-black">1080p</span>
								<?php esc_html_e( 'Full HD', 'cinecore' ); ?>
							</span>
							<svg class="w-5 h-5 opacity-70 group-hover:translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
						</a>
					<?php else : ?>
						<div class="flex items-center justify-between w-full bg-white/5 border border-dashed border-white/10 text-gray-400 font-medium px-5 py-3.5 rounded-lg">
							<span class="flex items-center gap-3">
								<span class="bg-white/10 rounded-md px-2 py-1 text-xs font-black text-gray-500">1080p</span>
								<?php esc_html_e( 'Not available', 'cinecore' ); ?>
							</span>
							<span class="text-xs"><?php esc_html_e( 'Coming soon', 'cinecore' ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $dl_720 ) : ?>
						<a href="<?php echo esc_url( $dl_720 ); ?>" class="flex items-center justify-between w-full bg-white/10 hover:bg-white/15 backdrop-blur border border-white/10 text-white font-medium px-5 py-3.5 rounded-lg transition-colors" download>
							<span class="flex items-center gap-3">
								<span class="bg-white/15 rounded-md px-2 py-1 text-xs font-bold">720p</span>
								<?php esc_html_e( 'HD', 'cinecore' ); ?>
							</span>
							<svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
						</a>
					<?php else : ?>
						<div class="flex items-center justify-between w-full bg-white/5 border border-dashed border-white/10 text-gray-400 font-medium px-5 py-3.5 rounded-lg">
							<span class="flex items-center gap-3">
								<span class="bg-white/10 rounded-md px-2 py-1 text-xs font-bold text-gray-500">720p</span>
								<?php esc_html_e( 'Not available', 'cinecore' ); ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $dl_480 ) : ?>
						<a href="<?php echo esc_url( $dl_480 ); ?>" class="flex items-center justify-between w-full bg-white/5 hover:bg-white/10 border border-white/5 text-gray-300 hover:text-white font-medium px-5 py-3 rounded-lg transition-colors" download>
							<span class="flex items-center gap-3">
								<span class="bg-white/10 rounded-md px-2 py-1 text-xs">480p</span>
								<?php esc_html_e( 'SD', 'cinecore' ); ?>
							</span>
							<svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Right: Subtitles -->
			<div class="bg-cinecore-card rounded-xl border border-white/5 p-6">
				<h3 class="text-lg font-bold text-white flex items-center gap-2 mb-5">
					<span class="w-8 h-8 rounded-lg bg-yellow-500/15 border border-yellow-500/30 flex items-center justify-center">
						<svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
					</span>
					<?php esc_html_e( 'Subtitles', 'cinecore' ); ?>
				</h3>

				<div class="space-y-3">
					<?php if ( '' !== $sub_en ) : ?>
						<a href="<?php echo esc_url( $sub_en ); ?>" class="flex items-center justify-between w-full bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-3.5 rounded-lg transition-colors shadow-md" download>
							<span class="flex items-center gap-3">
								<span class="bg-white/20 rounded-md px-2 py-1 text-xs font-black tracking-wider">EN</span>
								<?php esc_html_e( 'English', 'cinecore' ); ?>
							</span>
							<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
						</a>
					<?php else : ?>
						<div class="flex items-center justify-between w-full bg-green-600/20 border border-green-600/30 text-green-300/70 font-medium px-5 py-3.5 rounded-lg">
							<span class="flex items-center gap-3">
								<span class="bg-green-600/30 rounded-md px-2 py-1 text-xs font-black">EN</span>
								<?php esc_html_e( 'English — Not available', 'cinecore' ); ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $sub_si ) : ?>
						<a href="<?php echo esc_url( $sub_si ); ?>" class="flex items-center justify-between w-full bg-yellow-500 hover:bg-yellow-600 text-black font-semibold px-5 py-3.5 rounded-lg transition-colors shadow-md" download>
							<span class="flex items-center gap-3">
								<span class="bg-black/15 rounded-md px-2 py-1 text-xs font-black tracking-wider">SI</span>
								<?php esc_html_e( 'Sinhala', 'cinecore' ); ?>
							</span>
							<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
						</a>
					<?php else : ?>
						<div class="flex items-center justify-between w-full bg-yellow-500/15 border border-yellow-500/20 text-yellow-300/60 font-medium px-5 py-3.5 rounded-lg">
							<span class="flex items-center gap-3">
								<span class="bg-yellow-500/20 rounded-md px-2 py-1 text-xs font-black">SI</span>
								<?php esc_html_e( 'Sinhala — Not available', 'cinecore' ); ?>
							</span>
						</div>
					<?php endif; ?>

					<p class="text-xs text-gray-500 pt-2">
						<?php esc_html_e( 'Subtitles are provided as .srt files. Load via player CC or download.', 'cinecore' ); ?>
					</p>
				</div>
			</div>
		</div>
	</section>

	<!-- Comments fallback -->
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
	</div>

	<!-- Vanilla JS: Server Switcher -->
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var buttons = document.querySelectorAll('.cinecore-server-btn');
		var player  = document.getElementById('cinecore-player');
		var loader  = document.getElementById('cinecore-player-loader');
		if (!buttons.length || !player) return;

		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var src = btn.getAttribute('data-src');
				if (!src) return;

				// Update active state
				buttons.forEach(function (b) {
					b.classList.remove('active', 'bg-cinecore-accent', 'text-white');
					b.classList.add('bg-transparent', 'text-gray-300');
					b.setAttribute('aria-selected', 'false');
				});
				btn.classList.add('active', 'bg-cinecore-accent', 'text-white');
				btn.classList.remove('bg-transparent', 'text-gray-300');
				btn.setAttribute('aria-selected', 'true');

				// Show loader, switch src
				if (loader) {
					loader.classList.remove('opacity-0');
					loader.classList.add('opacity-100');
				}
				// Avoid reload if same
				if (player.getAttribute('src') !== src) {
					player.setAttribute('src', src);
				}
				player.addEventListener('load', function handler() {
					if (loader) {
						loader.classList.add('opacity-0');
						loader.classList.remove('opacity-100');
					}
					player.removeEventListener('load', handler);
				});
			});
		});
	});
	</script>

	<?php
endwhile;

get_footer();
