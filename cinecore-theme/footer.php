<?php
/**
 * Footer template for CineCore Theme
 *
 * @package CineCore_Theme
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;
?>
</main><!-- #primary -->

<footer class="bg-black border-t border-white/10 mt-12">
	<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
		<div class="flex flex-col md:flex-row items-center justify-between gap-4">
			<div class="flex items-center gap-2">
				<span class="flex items-center justify-center w-8 h-8 bg-cinecore-accent rounded-md font-black text-white text-sm">N</span>
				<span class="font-bold text-white tracking-tight"><?php esc_html_e( 'NEXCINE', 'cinecore' ); ?></span>
				<span class="text-sm text-gray-500 ml-2">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php esc_html_e( 'All rights reserved.', 'cinecore' ); ?></span>
			</div>

			<nav class="flex items-center gap-6" aria-label="<?php esc_attr_e( 'Footer Menu', 'cinecore' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => false,
						'menu_class'     => 'flex items-center gap-6 list-none m-0 p-0 text-sm text-gray-400',
					)
				);
				?>
			</nav>
		</div>
		<p class="text-center text-xs text-gray-600 mt-6">
			<?php
			printf(
				/* translators: %s: WordPress link */
				esc_html__( 'Powered by %s & CineCore', 'cinecore' ),
				'<a href="' . esc_url( __( 'https://wordpress.org/', 'cinecore' ) ) . '" class="hover:text-gray-400 underline">WordPress</a>'
			);
			echo ' &mdash; ';
			esc_html_e( 'Built with Tailwind CSS for Nexcine.', 'cinecore' );
			?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
