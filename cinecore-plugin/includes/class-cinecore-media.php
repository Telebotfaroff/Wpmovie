<?php
/**
 * CineCore Media - Poster import to Media Library
 *
 * @package CineCore
 */

defined( 'ABSPATH' ) || exit;

class CineCore_Media {

	/**
	 * Import poster from URL and set as featured image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $url     Remote poster URL.
	 * @param bool   $force   Force re-download even if thumbnail exists.
	 * @return int|WP_Error Attachment ID or error.
	 */
	public static function import_poster( $post_id, $url, $force = false ) {
		$post_id = absint( $post_id );
		$url     = esc_url_raw( trim( $url ) );
		if ( ! $post_id || '' === $url ) {
			return new WP_Error( 'invalid', __( 'Invalid post or URL.', 'cinecore' ) );
		}
		// Check if already has featured image and not forced.
		if ( ! $force && has_post_thumbnail( $post_id ) ) {
			return (int) get_post_thumbnail_id( $post_id );
		}
		// Check if same URL already imported (hash).
		$hash = md5( $url );
		$existing = get_post_meta( $post_id, '_cinecore_poster_hash', true );
		if ( ! $force && $existing === $hash && has_post_thumbnail( $post_id ) ) {
			return (int) get_post_thumbnail_id( $post_id );
		}
		// Check if attachment with same source already exists (by hash).
		$existing_attach = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array( 'key' => '_cinecore_source_url_hash', 'value' => $hash ),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $existing_attach ) && ! $force ) {
			$attach_id = (int) $existing_attach[0];
			set_post_thumbnail( $post_id, $attach_id );
			update_post_meta( $post_id, '_cinecore_poster_hash', $hash );
			return $attach_id;
		}

		// Transient to avoid repeated download attempts for same URL (cache 1 hour on failure).
		$transient_key = 'cinecore_poster_dl_' . $hash;
		if ( ! $force && false !== get_transient( $transient_key ) ) {
			$cached = get_transient( $transient_key );
			if ( is_array( $cached ) && isset( $cached['attach_id'] ) ) {
				set_post_thumbnail( $post_id, (int) $cached['attach_id'] );
				return (int) $cached['attach_id'];
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'image/*' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			set_transient( $transient_key, array( 'error' => $response->get_error_message() ), HOUR_IN_SECONDS );
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$err = new WP_Error( 'http_error', sprintf( __( 'HTTP %d fetching poster.', 'cinecore' ), $code ) );
			set_transient( $transient_key, array( 'error' => $err->get_error_message() ), HOUR_IN_SECONDS );
			return $err;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty', __( 'Empty image body.', 'cinecore' ) );
		}
		$headers = wp_remote_retrieve_headers( $response );
		$content_type = '';
		if ( isset( $headers['content-type'] ) ) {
			$content_type = is_array( $headers['content-type'] ) ? $headers['content-type'][0] : $headers['content-type'];
		}
		if ( $content_type && 0 !== strpos( $content_type, 'image/' ) ) {
			return new WP_Error( 'mime', sprintf( __( 'Invalid MIME: %s', 'cinecore' ), $content_type ) );
		}
		// Determine extension.
		$ext = 'jpg';
		if ( $content_type && preg_match( '#image/(jpe?g|png|webp|gif)#i', $content_type, $m ) ) {
			$ext = strtolower( $m[1] );
			if ( 'jpeg' === $ext ) {
				$ext = 'jpg';
			}
		} else {
			$path = parse_url( $url, PHP_URL_PATH );
			if ( $path && preg_match( '/\.(jpe?g|png|webp|gif)$/i', $path, $m ) ) {
				$ext = strtolower( $m[1] );
			}
		}
		$filename = sanitize_file_name( 'cinecore-' . $post_id . '-' . $hash . '.' . $ext );
		$upload = wp_upload_bits( $filename, null, $body );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'upload', $upload['error'] );
		}
		$type = wp_check_filetype( $upload['file'], null );
		$attachment = array(
			'post_mime_type' => $type['type'] ? $type['type'] : 'image/jpeg',
			'post_title'     => get_the_title( $post_id ) . ' Poster',
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		wp_update_attachment_metadata( $attach_id, $metadata );
		update_post_meta( $attach_id, '_cinecore_source_url', esc_url_raw( $url ) );
		update_post_meta( $attach_id, '_cinecore_source_url_hash', $hash );
		update_post_meta( $post_id, '_cinecore_poster_hash', $hash );
		set_post_thumbnail( $post_id, $attach_id );
		set_transient( $transient_key, array( 'attach_id' => $attach_id ), DAY_IN_SECONDS );
		return $attach_id;
	}
}
