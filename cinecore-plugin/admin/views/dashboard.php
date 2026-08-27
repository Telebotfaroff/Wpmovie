<?php defined( 'ABSPATH' ) || exit;
$movies = wp_count_posts( 'movies' );
$tv = wp_count_posts( 'tv_shows' );
$seasons = wp_count_posts( 'seasons' );
$episodes = wp_count_posts( 'episodes' );
?>
<div class="wrap cinecore-wrap">
	<h1><?php esc_html_e( 'CineCore Dashboard', 'cinecore' ); ?></h1>
	<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-top:16px;">
		<div class="postbox" style="padding:16px; text-align:center;"><h2 style="font-size:32px; margin:0;"><?php echo esc_html( $movies->publish ?? 0 ); ?></h2><p><?php esc_html_e( 'Movies', 'cinecore' ); ?></p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-movies' ) ); ?>" class="button"><?php esc_html_e( 'Manage', 'cinecore' ); ?></a></div>
		<div class="postbox" style="padding:16px; text-align:center;"><h2 style="font-size:32px; margin:0;"><?php echo esc_html( $tv->publish ?? 0 ); ?></h2><p><?php esc_html_e( 'TV Shows', 'cinecore' ); ?></p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-tv-shows' ) ); ?>" class="button"><?php esc_html_e( 'Manage', 'cinecore' ); ?></a></div>
		<div class="postbox" style="padding:16px; text-align:center;"><h2 style="font-size:32px; margin:0;"><?php echo esc_html( $seasons->publish ?? 0 ); ?></h2><p><?php esc_html_e( 'Seasons', 'cinecore' ); ?></p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=seasons' ) ); ?>" class="button"><?php esc_html_e( 'Manage', 'cinecore' ); ?></a></div>
		<div class="postbox" style="padding:16px; text-align:center;"><h2 style="font-size:32px; margin:0;"><?php echo esc_html( $episodes->publish ?? 0 ); ?></h2><p><?php esc_html_e( 'Episodes', 'cinecore' ); ?></p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=episodes' ) ); ?>" class="button"><?php esc_html_e( 'Manage', 'cinecore' ); ?></a></div>
	</div>
	<div style="margin-top:24px; display:flex; gap:12px;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-add-movie' ) ); ?>" class="button button-primary button-large"><?php esc_html_e( 'Add Movie', 'cinecore' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-add-tv' ) ); ?>" class="button button-large"><?php esc_html_e( 'Add TV Show', 'cinecore' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-tools' ) ); ?>" class="button"><?php esc_html_e( 'Repair Posters', 'cinecore' ); ?></a>
	</div>
</div>
