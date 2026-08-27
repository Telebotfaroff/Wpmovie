<?php
/**
 * Filter Bar - Phase 6.4 (Hardened, A11y, non-JS fallback)
 *
 * @package CineCore_Theme
 */

defined( 'ABSPATH' ) || exit;

$current_post_type = get_post_type();
if ( ! in_array( $current_post_type, array( 'movies', 'tv_shows' ), true ) ) {
	$current_post_type = 'movies';
}
if ( is_post_type_archive( array( 'movies', 'tv_shows' ) ) ) {
	$obj = get_queried_object();
	$current_post_type = $obj && isset( $obj->name ) ? $obj->name : $current_post_type;
}

// Preserve current filter values from URL for non-JS and initial state.
$current_genre = isset( $_GET['genre'] ) ? sanitize_text_field( wp_unslash( $_GET['genre'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_year  = isset( $_GET['release_year'] ) ? sanitize_text_field( wp_unslash( $_GET['release_year'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_quality = isset( $_GET['quality'] ) ? sanitize_text_field( wp_unslash( $_GET['quality'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$genres = get_terms( array( 'taxonomy' => 'genre', 'hide_empty' => true, 'number' => 50 ) );
$years  = get_terms( array( 'taxonomy' => 'release_year', 'hide_empty' => true, 'number' => 50 ) );
$qualities = get_terms( array( 'taxonomy' => 'quality', 'hide_empty' => true, 'number' => 20 ) );

$archive_url = is_post_type_archive() ? get_post_type_archive_link( $current_post_type ) : home_url( '/' );
if ( ! $archive_url ) {
	$archive_url = home_url( '/' );
}
?>
<form id="cinecore-filter-form" data-post-type="<?php echo esc_attr( $current_post_type ); ?>" action="<?php echo esc_url( $archive_url ); ?>" method="get" class="bg-cinecore-card border border-white/5 rounded-xl p-4 md:p-5 flex flex-col md:flex-row gap-4 items-stretch md:items-end">
	<div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-4 w-full">
			<label for="cinecore-filter-genre" class="flex flex-col gap-1.5">
			<span class="text-xs font-semibold tracking-wider uppercase text-gray-400"><?php esc_html_e( 'Genre', 'cinecore' ); ?></span>
			<select id="cinecore-filter-genre" name="genre" class="cinecore-filter-select w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-cinecore-accent focus:ring-2 focus:ring-cinecore-accent/20">
				<option value=""><?php esc_html_e( 'All Genres', 'cinecore' ); ?></option>
				<?php if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) : ?>
					<?php foreach ( $genres as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_genre, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
		</label>

		<label for="cinecore-filter-year" class="flex flex-col gap-1.5">
			<span class="text-xs font-semibold tracking-wider uppercase text-gray-400"><?php esc_html_e( 'Release Year', 'cinecore' ); ?></span>
			<select id="cinecore-filter-year" name="release_year" class="cinecore-filter-select w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-cinecore-accent focus:ring-2 focus:ring-cinecore-accent/20">
				<option value=""><?php esc_html_e( 'All Years', 'cinecore' ); ?></option>
				<?php if ( ! is_wp_error( $years ) && ! empty( $years ) ) : ?>
					<?php foreach ( $years as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_year, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
		</label>

		<label for="cinecore-filter-quality" class="flex flex-col gap-1.5">
			<span class="text-xs font-semibold tracking-wider uppercase text-gray-400"><?php esc_html_e( 'Quality', 'cinecore' ); ?></span>
			<select id="cinecore-filter-quality" name="quality" class="cinecore-filter-select w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-cinecore-accent focus:ring-2 focus:ring-cinecore-accent/20">
				<option value=""><?php esc_html_e( 'All Qualities', 'cinecore' ); ?></option>
				<?php if ( ! is_wp_error( $qualities ) && ! empty( $qualities ) ) : ?>
					<?php foreach ( $qualities as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_quality, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
		</label>
	</div>

	<button type="button" id="cinecore-filter-reset" class="hidden md:inline-flex items-center justify-center px-4 py-2.5 bg-white/10 hover:bg-white/15 border border-white/10 text-white text-sm font-medium rounded-lg transition-colors" onclick="document.getElementById('cinecore-filter-genre').value='';document.getElementById('cinecore-filter-year').value='';document.getElementById('cinecore-filter-quality').value='';document.getElementById('cinecore-filter-genre').dispatchEvent(new Event('change'));">
		<?php esc_html_e( 'Reset', 'cinecore' ); ?>
	</button>
</form>
