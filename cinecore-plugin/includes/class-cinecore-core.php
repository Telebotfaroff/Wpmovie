<?php
defined('ABSPATH') || exit;

/**
 * Main CineCore Class - Phase 1.
 *
 * Handles registration of Custom Post Types and Custom Taxonomies.
 *
 * @since 1.0.0
 */
final class CineCore {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Single instance.
	 *
	 * @var CineCore|null
	 */
	private static $instance = null;

	/**
	 * Returns single instance.
	 *
	 * @since 1.0.0
	 * @return CineCore
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_hooks();

		// Load admin modules (Phase 2 + Phase 5 Telegram + Phase 6 Dead Link).
		if ( is_admin() ) {
			new CineCore_Settings();
			new CineCore_TMDB();
			new CineCore_Telegram();
			new CineCore_Dead_Link_Checker();
		}

		// Live search needs to run on frontend + ajax (both logged-in and nopriv).
		new CineCore_Live_Search();

		// Subtitle ecosystem runs everywhere (CPT + upvote).
		new CineCore_Subtitle_Ecosystem();

		// Archive live filtering.
		new CineCore_Filter();

		// SEO module (OG, Twitter, JSON-LD, meta description, title).
		new CineCore_SEO();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'register_post_types' ), 0 );
		add_action( 'init', array( $this, 'register_taxonomies' ), 0 );
	}

	/**
	 * Register Custom Post Types.
	 *
	 * Registers 4 CPTs: movies, tv_shows, seasons, episodes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types() {

		// Movies CPT.
		$movies_labels = array(
			'name'                  => _x( 'Movies', 'Post type general name', 'cinecore' ),
			'singular_name'         => _x( 'Movie', 'Post type singular name', 'cinecore' ),
			'menu_name'             => _x( 'Movies', 'Admin Menu text', 'cinecore' ),
			'name_admin_bar'        => _x( 'Movie', 'Add New on Toolbar', 'cinecore' ),
			'add_new'               => __( 'Add New', 'cinecore' ),
			'add_new_item'          => __( 'Add New Movie', 'cinecore' ),
			'new_item'              => __( 'New Movie', 'cinecore' ),
			'edit_item'             => __( 'Edit Movie', 'cinecore' ),
			'view_item'             => __( 'View Movie', 'cinecore' ),
			'all_items'             => __( 'All Movies', 'cinecore' ),
			'search_items'          => __( 'Search Movies', 'cinecore' ),
			'parent_item_colon'     => __( 'Parent Movies:', 'cinecore' ),
			'not_found'             => __( 'No movies found.', 'cinecore' ),
			'not_found_in_trash'    => __( 'No movies found in Trash.', 'cinecore' ),
			'featured_image'        => _x( 'Movie Cover Image', 'Overrides the â€œFeatured Imageâ€ phrase', 'cinecore' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the â€œSet featured imageâ€ phrase', 'cinecore' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the â€œRemove featured imageâ€ phrase', 'cinecore' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the â€œUse as featured imageâ€ phrase', 'cinecore' ),
			'archives'              => _x( 'Movie archives', 'The post type archive label used in nav menus', 'cinecore' ),
			'insert_into_item'      => _x( 'Insert into movie', 'Overrides the â€œInsert into postâ€ phrase', 'cinecore' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this movie', 'Overrides the â€œUploaded to this postâ€ phrase', 'cinecore' ),
			'filter_items_list'     => _x( 'Filter movies list', 'Screen reader text for the filter links', 'cinecore' ),
			'items_list_navigation' => _x( 'Movies list navigation', 'Screen reader text for the pagination', 'cinecore' ),
			'items_list'            => _x( 'Movies list', 'Screen reader text for the items list', 'cinecore' ),
		);

		$movies_args = array(
			'labels'             => $movies_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'movies' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-video-alt2',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
		);

		register_post_type( 'movies', $movies_args );

		// TV Shows CPT.
		$tv_shows_labels = array(
			'name'                  => _x( 'TV Shows', 'Post type general name', 'cinecore' ),
			'singular_name'         => _x( 'TV Show', 'Post type singular name', 'cinecore' ),
			'menu_name'             => _x( 'TV Shows', 'Admin Menu text', 'cinecore' ),
			'name_admin_bar'        => _x( 'TV Show', 'Add New on Toolbar', 'cinecore' ),
			'add_new'               => __( 'Add New', 'cinecore' ),
			'add_new_item'          => __( 'Add New TV Show', 'cinecore' ),
			'new_item'              => __( 'New TV Show', 'cinecore' ),
			'edit_item'             => __( 'Edit TV Show', 'cinecore' ),
			'view_item'             => __( 'View TV Show', 'cinecore' ),
			'all_items'             => __( 'All TV Shows', 'cinecore' ),
			'search_items'          => __( 'Search TV Shows', 'cinecore' ),
			'parent_item_colon'     => __( 'Parent TV Shows:', 'cinecore' ),
			'not_found'             => __( 'No TV shows found.', 'cinecore' ),
			'not_found_in_trash'    => __( 'No TV shows found in Trash.', 'cinecore' ),
			'featured_image'        => _x( 'TV Show Cover Image', 'Overrides the â€œFeatured Imageâ€ phrase', 'cinecore' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the â€œSet featured imageâ€ phrase', 'cinecore' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the â€œRemove featured imageâ€ phrase', 'cinecore' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the â€œUse as featured imageâ€ phrase', 'cinecore' ),
			'archives'              => _x( 'TV Show archives', 'The post type archive label used in nav menus', 'cinecore' ),
			'insert_into_item'      => _x( 'Insert into TV show', 'Overrides the â€œInsert into postâ€ phrase', 'cinecore' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this TV show', 'Overrides the â€œUploaded to this postâ€ phrase', 'cinecore' ),
			'filter_items_list'     => _x( 'Filter TV shows list', 'Screen reader text for the filter links', 'cinecore' ),
			'items_list_navigation' => _x( 'TV Shows list navigation', 'Screen reader text for the pagination', 'cinecore' ),
			'items_list'            => _x( 'TV Shows list', 'Screen reader text for the items list', 'cinecore' ),
		);

		$tv_shows_args = array(
			'labels'             => $tv_shows_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'tv-shows' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 6,
			'menu_icon'          => 'dashicons-video-alt3',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
		);

		register_post_type( 'tv_shows', $tv_shows_args );

		// Seasons CPT.
		$seasons_labels = array(
			'name'                  => _x( 'Seasons', 'Post type general name', 'cinecore' ),
			'singular_name'         => _x( 'Season', 'Post type singular name', 'cinecore' ),
			'menu_name'             => _x( 'Seasons', 'Admin Menu text', 'cinecore' ),
			'name_admin_bar'        => _x( 'Season', 'Add New on Toolbar', 'cinecore' ),
			'add_new'               => __( 'Add New', 'cinecore' ),
			'add_new_item'          => __( 'Add New Season', 'cinecore' ),
			'new_item'              => __( 'New Season', 'cinecore' ),
			'edit_item'             => __( 'Edit Season', 'cinecore' ),
			'view_item'             => __( 'View Season', 'cinecore' ),
			'all_items'             => __( 'All Seasons', 'cinecore' ),
			'search_items'          => __( 'Search Seasons', 'cinecore' ),
			'parent_item_colon'     => __( 'Parent Seasons:', 'cinecore' ),
			'not_found'             => __( 'No seasons found.', 'cinecore' ),
			'not_found_in_trash'    => __( 'No seasons found in Trash.', 'cinecore' ),
			'featured_image'        => _x( 'Season Cover Image', 'Overrides the â€œFeatured Imageâ€ phrase', 'cinecore' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the â€œSet featured imageâ€ phrase', 'cinecore' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the â€œRemove featured imageâ€ phrase', 'cinecore' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the â€œUse as featured imageâ€ phrase', 'cinecore' ),
			'archives'              => _x( 'Season archives', 'The post type archive label used in nav menus', 'cinecore' ),
			'insert_into_item'      => _x( 'Insert into season', 'Overrides the â€œInsert into postâ€ phrase', 'cinecore' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this season', 'Overrides the â€œUploaded to this postâ€ phrase', 'cinecore' ),
			'filter_items_list'     => _x( 'Filter seasons list', 'Screen reader text for the filter links', 'cinecore' ),
			'items_list_navigation' => _x( 'Seasons list navigation', 'Screen reader text for the pagination', 'cinecore' ),
			'items_list'            => _x( 'Seasons list', 'Screen reader text for the items list', 'cinecore' ),
		);

		$seasons_args = array(
			'labels'             => $seasons_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'seasons' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 7,
			'menu_icon'          => 'dashicons-playlist-video',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
		);

		register_post_type( 'seasons', $seasons_args );

		// Episodes CPT.
		$episodes_labels = array(
			'name'                  => _x( 'Episodes', 'Post type general name', 'cinecore' ),
			'singular_name'         => _x( 'Episode', 'Post type singular name', 'cinecore' ),
			'menu_name'             => _x( 'Episodes', 'Admin Menu text', 'cinecore' ),
			'name_admin_bar'        => _x( 'Episode', 'Add New on Toolbar', 'cinecore' ),
			'add_new'               => __( 'Add New', 'cinecore' ),
			'add_new_item'          => __( 'Add New Episode', 'cinecore' ),
			'new_item'              => __( 'New Episode', 'cinecore' ),
			'edit_item'             => __( 'Edit Episode', 'cinecore' ),
			'view_item'             => __( 'View Episode', 'cinecore' ),
			'all_items'             => __( 'All Episodes', 'cinecore' ),
			'search_items'          => __( 'Search Episodes', 'cinecore' ),
			'parent_item_colon'     => __( 'Parent Episodes:', 'cinecore' ),
			'not_found'             => __( 'No episodes found.', 'cinecore' ),
			'not_found_in_trash'    => __( 'No episodes found in Trash.', 'cinecore' ),
			'featured_image'        => _x( 'Episode Cover Image', 'Overrides the â€œFeatured Imageâ€ phrase', 'cinecore' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the â€œSet featured imageâ€ phrase', 'cinecore' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the â€œRemove featured imageâ€ phrase', 'cinecore' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the â€œUse as featured imageâ€ phrase', 'cinecore' ),
			'archives'              => _x( 'Episode archives', 'The post type archive label used in nav menus', 'cinecore' ),
			'insert_into_item'      => _x( 'Insert into episode', 'Overrides the â€œInsert into postâ€ phrase', 'cinecore' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this episode', 'Overrides the â€œUploaded to this postâ€ phrase', 'cinecore' ),
			'filter_items_list'     => _x( 'Filter episodes list', 'Screen reader text for the filter links', 'cinecore' ),
			'items_list_navigation' => _x( 'Episodes list navigation', 'Screen reader text for the pagination', 'cinecore' ),
			'items_list'            => _x( 'Episodes list', 'Screen reader text for the items list', 'cinecore' ),
		);

		$episodes_args = array(
			'labels'             => $episodes_labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'episodes' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 8,
			'menu_icon'          => 'dashicons-controls-play',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
		);

		register_post_type( 'episodes', $episodes_args );
	}

	/**
	 * Register Custom Taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomies() {

		// Genre - Hierarchical.
		$genre_labels = array(
			'name'              => _x( 'Genres', 'taxonomy general name', 'cinecore' ),
			'singular_name'     => _x( 'Genre', 'taxonomy singular name', 'cinecore' ),
			'search_items'      => __( 'Search Genres', 'cinecore' ),
			'all_items'         => __( 'All Genres', 'cinecore' ),
			'parent_item'       => __( 'Parent Genre', 'cinecore' ),
			'parent_item_colon' => __( 'Parent Genre:', 'cinecore' ),
			'edit_item'         => __( 'Edit Genre', 'cinecore' ),
			'update_item'       => __( 'Update Genre', 'cinecore' ),
			'add_new_item'      => __( 'Add New Genre', 'cinecore' ),
			'new_item_name'     => __( 'New Genre Name', 'cinecore' ),
			'menu_name'         => __( 'Genre', 'cinecore' ),
			'view_item'         => __( 'View Genre', 'cinecore' ),
			'popular_items'     => __( 'Popular Genres', 'cinecore' ),
			'separate_items_with_commas' => __( 'Separate genres with commas', 'cinecore' ),
			'add_or_remove_items'        => __( 'Add or remove genres', 'cinecore' ),
			'choose_from_most_used'      => __( 'Choose from the most used genres', 'cinecore' ),
			'not_found'                  => __( 'No genres found.', 'cinecore' ),
			'no_terms'                   => __( 'No genres', 'cinecore' ),
			'items_list_navigation'      => __( 'Genres list navigation', 'cinecore' ),
			'items_list'                 => __( 'Genres list', 'cinecore' ),
			'back_to_items'              => __( '&larr; Go to Genres', 'cinecore' ),
		);

		$genre_args = array(
			'labels'            => $genre_labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'genre' ),
		);

		register_taxonomy( 'genre', array( 'movies', 'tv_shows' ), $genre_args );

		// Release Year - Non-hierarchical.
		$release_year_labels = array(
			'name'              => _x( 'Release Years', 'taxonomy general name', 'cinecore' ),
			'singular_name'     => _x( 'Release Year', 'taxonomy singular name', 'cinecore' ),
			'search_items'      => __( 'Search Release Years', 'cinecore' ),
			'all_items'         => __( 'All Release Years', 'cinecore' ),
			'parent_item'       => null,
			'parent_item_colon' => null,
			'edit_item'         => __( 'Edit Release Year', 'cinecore' ),
			'update_item'       => __( 'Update Release Year', 'cinecore' ),
			'add_new_item'      => __( 'Add New Release Year', 'cinecore' ),
			'new_item_name'     => __( 'New Release Year Name', 'cinecore' ),
			'menu_name'         => __( 'Release Year', 'cinecore' ),
			'view_item'         => __( 'View Release Year', 'cinecore' ),
			'popular_items'     => __( 'Popular Release Years', 'cinecore' ),
			'separate_items_with_commas' => __( 'Separate release years with commas', 'cinecore' ),
			'add_or_remove_items'        => __( 'Add or remove release years', 'cinecore' ),
			'choose_from_most_used'      => __( 'Choose from the most used release years', 'cinecore' ),
			'not_found'                  => __( 'No release years found.', 'cinecore' ),
			'no_terms'                   => __( 'No release years', 'cinecore' ),
			'items_list_navigation'      => __( 'Release Years list navigation', 'cinecore' ),
			'items_list'                 => __( 'Release Years list', 'cinecore' ),
			'back_to_items'              => __( '&larr; Go to Release Years', 'cinecore' ),
		);

		$release_year_args = array(
			'labels'            => $release_year_labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'release-year' ),
			'update_count_callback' => '_update_post_term_count',
		);

		register_taxonomy( 'release_year', array( 'movies', 'tv_shows' ), $release_year_args );

		// Quality - Non-hierarchical.
		$quality_labels = array(
			'name'              => _x( 'Qualities', 'taxonomy general name', 'cinecore' ),
			'singular_name'     => _x( 'Quality', 'taxonomy singular name', 'cinecore' ),
			'search_items'      => __( 'Search Qualities', 'cinecore' ),
			'all_items'         => __( 'All Qualities', 'cinecore' ),
			'parent_item'       => null,
			'parent_item_colon' => null,
			'edit_item'         => __( 'Edit Quality', 'cinecore' ),
			'update_item'       => __( 'Update Quality', 'cinecore' ),
			'add_new_item'      => __( 'Add New Quality', 'cinecore' ),
			'new_item_name'     => __( 'New Quality Name', 'cinecore' ),
			'menu_name'         => __( 'Quality', 'cinecore' ),
			'view_item'         => __( 'View Quality', 'cinecore' ),
			'popular_items'     => __( 'Popular Qualities', 'cinecore' ),
			'separate_items_with_commas' => __( 'Separate qualities with commas', 'cinecore' ),
			'add_or_remove_items'        => __( 'Add or remove qualities', 'cinecore' ),
			'choose_from_most_used'      => __( 'Choose from the most used qualities', 'cinecore' ),
			'not_found'                  => __( 'No qualities found.', 'cinecore' ),
			'no_terms'                   => __( 'No qualities', 'cinecore' ),
			'items_list_navigation'      => __( 'Qualities list navigation', 'cinecore' ),
			'items_list'                 => __( 'Qualities list', 'cinecore' ),
			'back_to_items'              => __( '&larr; Go to Qualities', 'cinecore' ),
		);

		$quality_args = array(
			'labels'            => $quality_labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
			'show_tagcloud'     => true,
			'show_in_rest'      => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'quality' ),
			'update_count_callback' => '_update_post_term_count',
		);

		register_taxonomy( 'quality', array( 'movies', 'episodes' ), $quality_args );
	}

	/**
	 * Prevent cloning.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'cinecore' ), '1.0.0' );
	}
}

