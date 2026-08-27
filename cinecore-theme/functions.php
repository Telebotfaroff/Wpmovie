<?php
/**
 * CineCore Theme functions and definitions
 *
 * @package CineCore_Theme
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Load poster helpers (centralized fallback).
require_once get_template_directory() . '/inc/poster-helpers.php';

/**
 * Define theme constants.
 */
define( 'CINECORE_THEME_VERSION', '1.0.0' );
define( 'CINECORE_THEME_DIR', get_template_directory() );
define( 'CINECORE_THEME_URI', get_template_directory_uri() );

/**
 * Theme setup.
 *
 * @since 1.0.0
 * @return void
 */
function cinecore_theme_setup() {
	// Make theme available for translation.
	load_theme_textdomain( 'cinecore', CINECORE_THEME_DIR . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	// Let WordPress manage the document title.
	add_theme_support( 'title-tag' );

	// Enable support for Post Thumbnails.
	add_theme_support( 'post-thumbnails' );

	// Switch default core markup to output valid HTML5.
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

	// Add support for responsive embeds.
	add_theme_support( 'responsive-embeds' );

	// Register navigation menus.
	register_nav_menus(
		array(
			'primary' => esc_html__( 'Primary Menu', 'cinecore' ),
			'footer'  => esc_html__( 'Footer Menu', 'cinecore' ),
		)
	);

	// Add support for logo (optional, fallback to text logo).
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 40,
			'width'       => 160,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
}
add_action( 'after_setup_theme', 'cinecore_theme_setup' );

/**
 * Enqueue scripts and styles.
 *
 * @since 1.0.0
 * @return void
 */
function cinecore_theme_scripts() {
	// Compiled Tailwind CSS.
	wp_enqueue_style(
		'cinecore-tailwind',
		CINECORE_THEME_URI . '/assets/css/tailwind.css',
		array(),
		CINECORE_THEME_VERSION
	);

	// Theme stylesheet (contains header only, but required by WP).
	wp_enqueue_style(
		'cinecore-style',
		get_stylesheet_uri(),
		array( 'cinecore-tailwind' ),
		CINECORE_THEME_VERSION
	);

	// Live Search JS - Phase 5.
	wp_enqueue_script(
		'cinecore-search',
		CINECORE_THEME_URI . '/assets/js/cinecore-search.js',
		array(),
		CINECORE_THEME_VERSION,
		true
	);

	wp_localize_script(
		'cinecore-search',
		'cinecoreSearch',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'cinecore_live_search' ),
			'i18n'    => array(
				'noResults' => __( 'No results found.', 'cinecore' ),
				'loading'   => __( 'Searching...', 'cinecore' ),
			),
		)
	);

	// Archive Live Filter - Phase 6.4.
	if ( is_post_type_archive( array( 'movies', 'tv_shows' ) ) || is_tax( array( 'genre', 'release_year', 'quality' ) ) || is_home() ) {
		wp_enqueue_script(
			'cinecore-filter',
			CINECORE_THEME_URI . '/assets/js/cinecore-filter.js',
			array(),
			CINECORE_THEME_VERSION,
			true
		);
		wp_localize_script(
			'cinecore-filter',
			'cinecoreFilter',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cinecore_filter' ),
			)
		);
	}

	// Smart Player Enhancements - Phase 6.5.
	if ( is_singular( array( 'movies', 'episodes' ) ) ) {
		wp_enqueue_script(
			'cinecore-player',
			CINECORE_THEME_URI . '/assets/js/cinecore-player.js',
			array(),
			CINECORE_THEME_VERSION,
			true
		);
		wp_localize_script(
			'cinecore-player',
			'cinecorePlayer',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'adsTestUrl' => CINECORE_THEME_URI . '/assets/js/ads.js',
			)
		);
	}

	// Comment reply.
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'cinecore_theme_scripts' );

/**
 * Register widget area (sidebar) - optional but standard.
 *
 * @since 1.0.0
 * @return void
 */
function cinecore_theme_widgets_init() {
	register_sidebar(
		array(
			'name'          => esc_html__( 'Sidebar', 'cinecore' ),
			'id'            => 'sidebar-1',
			'description'   => esc_html__( 'Add widgets here.', 'cinecore' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s bg-cinecore-surface rounded-lg p-6 mb-6">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="widget-title text-lg font-bold mb-4">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'cinecore_theme_widgets_init' );

/**
 * Fallback for primary menu if no menu assigned.
 *
 * @since 1.0.0
 * @return void
 */
function cinecore_primary_menu_fallback() {
	echo '<ul class="flex items-center gap-8 list-none m-0 p-0">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '" class="text-sm font-medium text-white hover:text-cinecore-accent transition-colors">' . esc_html__( 'Home', 'cinecore' ) . '</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/movies/' ) ) . '" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">' . esc_html__( 'Movies', 'cinecore' ) . '</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/tv-shows/' ) ) . '" class="text-sm font-medium text-gray-300 hover:text-white transition-colors">' . esc_html__( 'TV Shows', 'cinecore' ) . '</a></li>';
	echo '</ul>';
}
