<?php
/**
 * Subtitle Ecosystem - Hardened
 *
 * @package CineCore
 */

defined( 'ABSPATH' ) || exit;

class CineCore_Subtitle_Ecosystem {

	const CPT = 'subtitle_requests';
	const ROLE = 'translator';
	const META_COUNT = '_cinecore_upvote_count';
	const META_USERS = '_cinecore_upvoted_users';
	const META_IPS   = '_cinecore_upvoted_ips';

	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ), 0 );
		add_action( 'init', array( $this, 'ensure_role' ) );
		add_action( 'wp_ajax_cinecore_upvote_subtitle', array( $this, 'handle_upvote' ) );
		add_action( 'wp_ajax_nopriv_cinecore_upvote_subtitle', array( $this, 'handle_upvote' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function ensure_role() {
		$role = get_role( self::ROLE );
		if ( null !== $role ) {
			// Ensure correct caps (fix overly permissive legacy).
			$role->remove_cap( 'edit_posts' );
			$role->remove_cap( 'publish_posts' );
			$role->remove_cap( 'edit_published_posts' );
			// Add specific caps for subtitle_requests.
			$caps = array( 'read', 'upload_files', 'edit_subtitle_requests', 'publish_subtitle_requests', 'edit_published_subtitle_requests', 'read_private_subtitle_requests' );
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
			return;
		}
		add_role(
			self::ROLE,
			__( 'Translator', 'cinecore' ),
			array(
				'read'                             => true,
				'upload_files'                     => true,
				'edit_subtitle_requests'           => true,
				'publish_subtitle_requests'        => true,
				'edit_published_subtitle_requests' => true,
				'read_private_subtitle_requests'   => true,
			)
		);
	}

	public function register_cpt() {
		$labels = array(
			'name'               => _x( 'Subtitle Requests', 'Post type general name', 'cinecore' ),
			'singular_name'      => _x( 'Subtitle Request', 'Post type singular name', 'cinecore' ),
			'menu_name'          => _x( 'Subtitle Requests', 'Admin Menu text', 'cinecore' ),
			'add_new'            => __( 'Add New', 'cinecore' ),
			'add_new_item'       => __( 'Add New Request', 'cinecore' ),
			'new_item'           => __( 'New Request', 'cinecore' ),
			'edit_item'          => __( 'Edit Request', 'cinecore' ),
			'view_item'          => __( 'View Request', 'cinecore' ),
			'all_items'          => __( 'All Requests', 'cinecore' ),
			'search_items'       => __( 'Search Requests', 'cinecore' ),
			'not_found'          => __( 'No requests found.', 'cinecore' ),
			'not_found_in_trash' => __( 'No requests found in Trash.', 'cinecore' ),
		);
		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'subtitle-requests' ),
			'capability_type'    => array( 'subtitle_request', 'subtitle_requests' ),
			'map_meta_cap'       => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-translation',
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
		);
		register_post_type( self::CPT, $args );
	}

	public function enqueue_assets() {
		if ( ! is_singular( self::CPT ) && ! is_post_type_archive( self::CPT ) && ! is_author() ) {
			return;
		}
		wp_enqueue_script( 'cinecore-upvote', CINECORE_PLUGIN_URL . 'assets/js/cinecore-upvote.js', array(), CINECORE_VERSION, true );
		wp_localize_script(
			'cinecore-upvote',
			'cinecoreUpvote',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cinecore_upvote' ),
				'i18n'    => array(
					'voted' => __( 'You already voted.', 'cinecore' ),
					'error' => __( 'Vote failed. Try again.', 'cinecore' ),
				),
			)
		);
	}

	public function handle_upvote() {
		if ( ! check_ajax_referer( 'cinecore_upvote', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'cinecore' ), 'code' => 'bad_nonce' ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || self::CPT !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'cinecore' ), 'code' => 'invalid_post' ) );
		}
		// Rate limit: 5 upvotes/min per IP.
		$ip_raw = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = filter_var( $ip_raw, FILTER_VALIDATE_IP ) ? $ip_raw : '';
		$ip_hash = $ip ? CineCore_Upvotes::hash_ip( $ip ) : '';
		$rate_key = 'cinecore_upvote_rate_' . ( $ip_hash ? $ip_hash : 'unknown' );
		$rate = (int) get_transient( $rate_key );
		if ( $rate > 5 ) {
			wp_send_json_error( array( 'message' => __( 'Too many votes. Slow down.', 'cinecore' ), 'code' => 'rate_limited' ) );
		}
		set_transient( $rate_key, $rate + 1, MINUTE_IN_SECONDS );

		$user_id = get_current_user_id();
		$has_voted = false;
		if ( $user_id ) {
			$has_voted = CineCore_Upvotes::has_voted( $post_id, $user_id, '' );
		} elseif ( $ip_hash ) {
			$has_voted = CineCore_Upvotes::has_voted( $post_id, 0, $ip_hash );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not verify voter.', 'cinecore' ), 'code' => 'no_identity' ) );
		}
		if ( $has_voted ) {
			wp_send_json_error( array( 'message' => __( 'You have already upvoted this request.', 'cinecore' ), 'code' => 'already_voted' ) );
		}
		// Race-safe insert via UNIQUE index.
		$added = CineCore_Upvotes::add_vote( $post_id, $user_id ? $user_id : null, $ip_hash ? $ip_hash : null );
		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'You have already upvoted this request.', 'cinecore' ), 'code' => 'already_voted' ) );
		}
		$count = CineCore_Upvotes::get_count( $post_id );
		wp_send_json_success( array( 'count' => $count, 'message' => __( 'Upvoted!', 'cinecore' ) ) );
	}

	public static function get_count( $post_id ) {
		// Use scalable table with fallback to legacy.
		if ( class_exists( 'CineCore_Upvotes' ) ) {
			return CineCore_Upvotes::get_count( $post_id );
		}
		return (int) get_post_meta( $post_id, self::META_COUNT, true );
	}
}
