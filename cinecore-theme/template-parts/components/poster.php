<?php
/**
 * Poster Component - Reusable
 *
 * @package CineCore_Theme
 *
 * @param array $args {
 *     @type int    $post_id Post ID.
 *     @type string $size    Image size.
 *     @type string $class   Extra classes.
 * }
 */

defined( 'ABSPATH' ) || exit;

$args    = wp_parse_args( $args ?? array(), array( 'post_id' => get_the_ID(), 'size' => 'medium_large', 'class' => 'w-full h-full object-cover' ) );
$post_id = absint( $args['post_id'] );
$size    = sanitize_key( $args['size'] );
$class   = sanitize_text_field( $args['class'] );

$poster = CineCore_Meta::get_poster( $post_id, false );
if ( $poster ) {
	echo '<img src="' . esc_url( $poster ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" class="' . esc_attr( $class ) . '" loading="lazy" />';
} elseif ( has_post_thumbnail( $post_id ) ) {
	echo get_the_post_thumbnail( $post_id, $size, array( 'class' => $class, 'alt' => get_the_title( $post_id ) ) );
} else {
	echo '<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-800 to-black"><span class="text-gray-500 text-sm">' . esc_html__( 'No Poster', 'cinecore' ) . '</span></div>';
}
