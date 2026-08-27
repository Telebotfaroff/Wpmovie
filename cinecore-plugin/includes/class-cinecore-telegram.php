<?php
/**
 * Telegram Auto-Post - Hardened
 *
 * @package CineCore
 */

defined( 'ABSPATH' ) || exit;

class CineCore_Telegram {

	const META_FLAG   = '_cinecore_telegram_posted';
	const META_STATUS = '_cinecore_telegram_status'; // sent|failed|pending
	const META_ERROR  = '_cinecore_telegram_last_error';
	const META_SENT_AT = '_cinecore_telegram_sent_at';
	const ALLOWED_TYPES = array( 'movies', 'tv_shows' );

	public function __construct() {
		add_action( 'transition_post_status', array( $this, 'handle_transition' ), 10, 3 );
		add_action( 'add_meta_boxes', array( $this, 'add_status_box' ) );
		add_action( 'admin_post_cinecore_telegram_retry', array( $this, 'handle_retry' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
	}

	public function handle_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status ) {
			return;
		}
		if ( 'publish' === $old_status ) {
			return;
		}
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::ALLOWED_TYPES, true ) ) {
			return;
		}
		$already = get_post_meta( $post->ID, self::META_FLAG, true );
		if ( '1' === (string) $already ) {
			return;
		}
		// Check status: if previously failed, allow retry on republish; if sent, block.
		$status = get_post_meta( $post->ID, self::META_STATUS, true );
		if ( 'sent' === $status ) {
			return;
		}
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}
		$this->maybe_send_telegram( $post->ID );
	}

	public function add_status_box() {
		foreach ( self::ALLOWED_TYPES as $pt ) {
			add_meta_box( 'cinecore-telegram-status', __( 'Telegram Status', 'cinecore' ), array( $this, 'render_status_box' ), $pt, 'side', 'low' );
		}
	}

	public function render_status_box( $post ) {
		$status = get_post_meta( $post->ID, self::META_STATUS, true );
		$error  = get_post_meta( $post->ID, self::META_ERROR, true );
		$sent_at = get_post_meta( $post->ID, self::META_SENT_AT, true );
		if ( ! $status ) {
			$status = get_post_meta( $post->ID, self::META_FLAG, true ) ? 'sent' : 'pending';
		}
		echo '<p><strong>' . esc_html__( 'Status:', 'cinecore' ) . '</strong> ';
		if ( 'sent' === $status ) {
			echo '<span style="color:#46b450;">' . esc_html__( 'Sent', 'cinecore' ) . '</span>';
			if ( $sent_at ) {
				echo ' <small>(' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $sent_at ) ) . ')</small>';
			}
		} elseif ( 'failed' === $status ) {
			echo '<span style="color:#d63638;">' . esc_html__( 'Failed', 'cinecore' ) . '</span>';
			if ( $error ) {
				echo '<br><small style="color:#d63638;word-break:break-all;">' . esc_html( $error ) . '</small>';
			}
			$retry_url = wp_nonce_url( admin_url( 'admin-post.php?action=cinecore_telegram_retry&post_id=' . $post->ID ), 'cinecore_telegram_retry_' . $post->ID );
			echo '<br><a href="' . esc_url( $retry_url ) . '" class="button button-secondary" style="margin-top:8px;">' . esc_html__( 'Retry Send', 'cinecore' ) . '</a>';
		} else {
			echo '<span style="color:#dba617;">' . esc_html__( 'Pending (will send on publish)', 'cinecore' ) . '</span>';
		}
		echo '</p>';
	}

	public function handle_retry() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'No permission.', 'cinecore' ) );
		}
		check_admin_referer( 'cinecore_telegram_retry_' . $post_id );
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::ALLOWED_TYPES, true ) ) {
			wp_die( esc_html__( 'Invalid post.', 'cinecore' ) );
		}
		// Clear flag to allow resend, then send.
		delete_post_meta( $post_id, self::META_FLAG );
		update_post_meta( $post_id, self::META_STATUS, 'pending' );
		delete_post_meta( $post_id, self::META_ERROR );
		$this->maybe_send_telegram( $post_id );
		$status = get_post_meta( $post_id, self::META_STATUS, true );
		$redirect = add_query_arg( 'cinecore_telegram', ( 'sent' === $status ? 'sent' : 'failed' ), get_edit_post_link( $post_id, '' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	public function admin_notice() {
		if ( ! isset( $_GET['cinecore_telegram'] ) ) {
			return;
		}
		$val = sanitize_text_field( wp_unslash( $_GET['cinecore_telegram'] ) );
		if ( 'sent' === $val ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Telegram message sent successfully.', 'cinecore' ) . '</p></div>';
		} elseif ( 'failed' === $val ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Telegram send failed. Check status box for error.', 'cinecore' ) . '</p></div>';
		}
	}

	private function maybe_send_telegram( $post_id ) {
		$bot_token = get_option( CineCore_Settings::OPTION_BOT_TOKEN, '' );
		$chat_id   = get_option( CineCore_Settings::OPTION_CHAT_ID, '' );
		$bot_token = trim( (string) $bot_token );
		$chat_id   = trim( (string) $chat_id );
		if ( '' === $bot_token || '' === $chat_id ) {
			update_post_meta( $post_id, self::META_STATUS, 'failed' );
			update_post_meta( $post_id, self::META_ERROR, __( 'Bot token or chat ID not configured.', 'cinecore' ) );
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$title     = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );
		$poster    = CineCore_Meta::get_poster( $post_id, true );
		// Sanitize caption - Telegram HTML: only <b><i><a> allowed, so escape and allow <b>.
		$caption  = '<b>' . esc_html( $title ) . '</b>' . "\n";
		$caption .= esc_url( $permalink ) . "\n\n";
		$excerpt  = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 20 );
		if ( '' !== $excerpt ) {
			$caption .= esc_html( wp_trim_words( $excerpt, 25 ) );
		}
		$caption = mb_substr( $caption, 0, 1024 );
		$api_url = sprintf( 'https://api.telegram.org/bot%s/sendPhoto', $bot_token );
		$body = array( 'chat_id' => $chat_id, 'caption' => $caption, 'parse_mode' => 'HTML' );
		if ( ! empty( $poster ) ) {
			$body['photo'] = esc_url_raw( $poster );
		} else {
			$api_url = sprintf( 'https://api.telegram.org/bot%s/sendMessage', $bot_token );
			$body['text'] = $caption;
			unset( $body['photo'], $body['caption'] );
		}
		$response = wp_remote_post(
			$api_url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			update_post_meta( $post_id, self::META_STATUS, 'failed' );
			update_post_meta( $post_id, self::META_ERROR, $response->get_error_message() );
			// Never expose token in log.
			error_log( sprintf( '[CineCore Telegram] post %d failed: %s', $post_id, $response->get_error_message() ) );
			return;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( 200 !== $code || ! is_array( $data ) || empty( $data['ok'] ) ) {
			$msg = isset( $data['description'] ) ? $data['description'] : ( isset( $data['status_message'] ) ? $data['status_message'] : sprintf( __( 'HTTP %d', 'cinecore' ), $code ) );
			$msg = sanitize_text_field( $msg );
			// Truncate error to avoid leaking token.
			$msg = mb_substr( $msg, 0, 200 );
			update_post_meta( $post_id, self::META_STATUS, 'failed' );
			update_post_meta( $post_id, self::META_ERROR, $msg );
			error_log( sprintf( '[CineCore Telegram] API failed post %d code %d: %s', $post_id, $code, $msg ) );
			return;
		}
		// Success.
		update_post_meta( $post_id, self::META_FLAG, '1' );
		update_post_meta( $post_id, self::META_STATUS, 'sent' );
		update_post_meta( $post_id, self::META_SENT_AT, time() );
		delete_post_meta( $post_id, self::META_ERROR );
	}
}
