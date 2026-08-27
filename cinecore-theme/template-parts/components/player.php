<?php
/**
 * Player Component
 *
 * @package CineCore_Theme
 *
 * @param array $args { post_id, servers[] }
 */

defined( 'ABSPATH' ) || exit;

$args    = wp_parse_args( $args ?? array(), array( 'post_id' => get_the_ID() ) );
$post_id = absint( $args['post_id'] );
$servers = CineCore_Meta::get_servers( $post_id );
// Fallback demo if empty.
if ( empty( $servers['server_1'] ) ) {
	$servers['server_1'] = 'https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0';
}
if ( empty( $servers['server_2'] ) ) {
	$servers['server_2'] = 'https://www.youtube.com/embed/9bZkp7q19f0?rel=0';
}
if ( empty( $servers['server_vip'] ) ) {
	$servers['server_vip'] = 'https://www.youtube.com/embed/jNQXAC9IVRw?rel=0';
}
?>
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
	<h2 class="text-lg font-bold text-white flex items-center gap-2"><span class="w-1 h-6 bg-cinecore-accent rounded-full"></span><?php esc_html_e( 'Watch', 'cinecore' ); ?></h2>
	<div class="flex items-center gap-2 p-1 bg-cinecore-card rounded-lg border border-white/5" role="tablist" aria-label="<?php esc_attr_e( 'Server Switcher', 'cinecore' ); ?>">
		<button type="button" class="cinecore-server-btn active bg-cinecore-accent text-white px-4 py-2 rounded-md text-sm font-semibold" data-src="<?php echo esc_url( $servers['server_1'] ); ?>" aria-selected="true" role="tab"><?php esc_html_e( 'Server 1', 'cinecore' ); ?></button>
		<button type="button" class="cinecore-server-btn bg-transparent hover:bg-white/10 text-gray-300 px-4 py-2 rounded-md text-sm" data-src="<?php echo esc_url( $servers['server_2'] ); ?>" aria-selected="false" role="tab"><?php esc_html_e( 'Server 2', 'cinecore' ); ?></button>
		<button type="button" class="cinecore-server-btn bg-transparent hover:bg-white/10 text-gray-300 px-4 py-2 rounded-md text-sm border border-yellow-500/20" data-src="<?php echo esc_url( $servers['server_vip'] ); ?>" aria-selected="false" role="tab"><?php esc_html_e( 'VIP Server', 'cinecore' ); ?></button>
	</div>
</div>
<div id="cinecore-player-wrap" class="relative w-full aspect-video bg-black rounded-xl overflow-hidden border border-white/10 shadow-2xl">
	<iframe id="cinecore-player" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" src="<?php echo esc_url( $servers['server_1'] ); ?>" title="<?php echo esc_attr( sprintf( __( '%s player', 'cinecore' ), get_the_title( $post_id ) ) ); ?>" class="absolute inset-0 w-full h-full" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>
	<div id="cinecore-player-loader" class="absolute inset-0 flex items-center justify-center bg-black pointer-events-none opacity-0 transition-opacity"><span class="inline-flex items-center gap-2 text-gray-400 text-sm"><svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><?php esc_html_e( 'Switching server...', 'cinecore' ); ?></span></div>
</div>
