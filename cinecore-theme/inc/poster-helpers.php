<?php
/**
 * CineCore Poster Helpers
 *
 * @package CineCore_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get poster URL with fallback chain.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Image size for featured image.
 * @return string
 */
function cinecore_get_poster_url( $post_id, $size = 'large' ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	// 1. Featured image.
	if ( has_post_thumbnail( $post_id ) ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		if ( $url ) {
			return esc_url( $url );
		}
	}
	// 2. CineCore poster URL via meta API (handles legacy).
	if ( class_exists( 'CineCore_Meta' ) ) {
		$url = CineCore_Meta::get_poster( $post_id, false );
		if ( $url ) {
			return esc_url( $url );
		}
	} else {
		$url = get_post_meta( $post_id, '_cinecore_poster_url', true );
		if ( $url ) {
			return esc_url( $url );
		}
	}
	// 3. Fallback image.
	$fallback = get_template_directory_uri() . '/assets/images/fallback.jpg';
	// If fallback file doesn't exist, use placeholder via theme.
	if ( ! file_exists( get_template_directory() . '/assets/images/fallback.jpg' ) ) {
		// Use a data URI or generic placeholder.
		return esc_url( $fallback );
	}
	return esc_url( $fallback );
}

/**
 * Check if post has any poster.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function cinecore_has_poster( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( has_post_thumbnail( $post_id ) ) {
		return true;
	}
	if ( class_exists( 'CineCore_Meta' ) ) {
		return '' !== CineCore_Meta::get_poster( $post_id, false );
	}
	return '' !== (string) get_post_meta( $post_id, '_cinecore_poster_url', true );
}

/**
 * Get poster attachment ID.
 *
 * @param int $post_id Post ID.
 * @return int
 */
function cinecore_get_poster_attachment_id( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}
	if ( has_post_thumbnail( $post_id ) ) {
		return (int) get_post_thumbnail_id( $post_id );
	}
	return 0;
}
