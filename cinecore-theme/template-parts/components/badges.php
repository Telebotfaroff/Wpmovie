<?php
/**
 * Meta Badges Component
 *
 * @package CineCore_Theme
 *
 * @param array $args { post_id }
 */

defined( 'ABSPATH' ) || exit;

$args    = wp_parse_args( $args ?? array(), array( 'post_id' => get_the_ID() ) );
$post_id = absint( $args['post_id'] );

$year = '';
$terms = get_the_terms( $post_id, 'release_year' );
if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
	$year = $terms[0]->name;
}
if ( '' === $year ) {
	$year = get_the_date( 'Y', $post_id );
}
$rating = CineCore_Meta::get_rating( $post_id );
$runtime = CineCore_Meta::get_runtime( $post_id );
$genres = get_the_terms( $post_id, 'genre' );
$genre_list = '';
if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) {
	$genre_list = implode( ', ', wp_list_pluck( $genres, 'name' ) );
}
?>
<div class="flex flex-wrap items-center gap-3 text-sm">
	<?php if ( $year ) : ?>
		<span class="inline-flex items-center gap-1.5 bg-white/10 border border-white/10 text-white px-3 py-1 rounded-full"><?php echo esc_html( $year ); ?></span>
	<?php endif; ?>
	<?php if ( $rating > 0 ) : ?>
		<span class="inline-flex items-center gap-1.5 bg-yellow-500/15 border border-yellow-500/30 text-yellow-400 px-3 py-1 rounded-full font-semibold">
			<svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
			<?php echo esc_html( number_format_i18n( $rating, 1 ) ); ?> / 10
		</span>
	<?php endif; ?>
	<?php if ( $runtime ) : ?>
		<span class="inline-flex items-center gap-1.5 bg-white/10 border border-white/10 text-white px-3 py-1 rounded-full"><?php echo esc_html( sprintf( __( '%s min', 'cinecore' ), number_format_i18n( $runtime ) ) ); ?></span>
	<?php endif; ?>
	<?php if ( $genre_list ) : ?>
		<span class="inline-flex items-center gap-1.5 bg-white/10 border border-white/10 text-gray-200 px-3 py-1 rounded-full"><?php echo esc_html( $genre_list ); ?></span>
	<?php endif; ?>
</div>
