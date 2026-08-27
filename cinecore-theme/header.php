<?php
/**
 * Header template for CineCore Theme
 *
 * @package CineCore_Theme
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-cinecore-dark text-white antialiased min-h-screen flex flex-col' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'cinecore' ); ?></a>

<header class="sticky top-0 z-50 bg-black/90 backdrop-blur-md border-b border-white/10">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
		<div class="flex items-center justify-between h-16">
			<!-- Logo Area -->
			<div class="flex-shrink-0 flex items-center gap-3">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-2 no-underline" rel="home">
						<span class="flex items-center justify-center w-9 h-9 bg-cinecore-accent rounded-lg font-black text-white text-lg leading-none">N</span>
						<span class="font-black text-xl tracking-tight text-white"><?php esc_html_e( 'NEXCINE', 'cinecore' ); ?></span>
					</a>
				<?php endif; ?>
			</div>

			<!-- Primary Navigation -->
			<nav class="hidden md:flex items-center" aria-label="<?php esc_attr_e( 'Primary Menu', 'cinecore' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_id'        => 'primary-menu',
						'container'      => false,
						'depth'          => 2,
						'fallback_cb'    => 'cinecore_primary_menu_fallback',
						'menu_class'     => 'flex items-center gap-8 list-none m-0 p-0',
						'walker'         => null,
					)
				);
				?>
			</nav>

			<!-- Mobile Menu Button -->
			<div class="md:hidden">
				<button type="button" id="cinecore-mobile-toggle" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" aria-controls="cinecore-mobile-menu" aria-expanded="false">
					<span class="sr-only"><?php esc_html_e( 'Open main menu', 'cinecore' ); ?></span>
					<!-- Icon: hamburger -->
					<svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
					</svg>
				</button>
			</div>
		</div>
	</div>

	<!-- Mobile Menu Panel -->
	<div id="cinecore-mobile-menu" class="md:hidden hidden border-t border-white/10 bg-black">
		<div class="px-2 pt-2 pb-3 space-y-1">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'fallback_cb'    => 'cinecore_primary_menu_fallback',
					'menu_class'     => 'space-y-1 list-none m-0 p-0',
					'depth'          => 1,
				)
			);
			?>
		</div>
	</div>
</header>

<script>
// Accessible mobile toggle with focus trap & Escape.
document.addEventListener('DOMContentLoaded', function () {
	var btn = document.getElementById('cinecore-mobile-toggle');
	var menu = document.getElementById('cinecore-mobile-menu');
	if (!btn || !menu) return;
	var focusable = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';
	var lastFocus = null;
	function openMenu() {
		lastFocus = document.activeElement;
		btn.setAttribute('aria-expanded', 'true');
		menu.classList.remove('hidden');
		// Trap focus.
		var first = menu.querySelector(focusable);
		if (first) first.focus();
		document.addEventListener('keydown', onKey);
	}
	function closeMenu() {
		btn.setAttribute('aria-expanded', 'false');
		menu.classList.add('hidden');
		document.removeEventListener('keydown', onKey);
		if (lastFocus) lastFocus.focus();
	}
	function onKey(e) {
		if (e.key === 'Escape') { closeMenu(); }
		if (e.key === 'Tab' && !menu.classList.contains('hidden')) {
			var els = menu.querySelectorAll(focusable);
			if (!els.length) return;
			var first = els[0], last = els[els.length-1];
			if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
			else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
		}
	}
	btn.addEventListener('click', function () {
		var expanded = btn.getAttribute('aria-expanded') === 'true';
		if (expanded) closeMenu(); else openMenu();
	});
	// Close on click outside.
	document.addEventListener('click', function (e) {
		if (!menu.classList.contains('hidden') && !menu.contains(e.target) && !btn.contains(e.target)) {
			closeMenu();
		}
	});
});
</script>

<main id="primary" class="site-main flex-1">
