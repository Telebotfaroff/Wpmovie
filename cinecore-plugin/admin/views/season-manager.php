<?php
defined( 'ABSPATH' ) || exit;
$season_id = isset( $_GET['season_id'] ) ? absint( $_GET['season_id'] ) : 0;
if ( ! $season_id || 'seasons' !== get_post_type( $season_id ) ) {
	echo '<div class="wrap"><h1>' . esc_html__( 'Season Manager', 'cinecore' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Invalid season.', 'cinecore' ) . '</p></div></div>';
	return;
}
$season = get_post( $season_id );
$tv_id = CineCore_Meta::get_season_tv_show_id( $season_id );
$tv_title = $tv_id ? get_the_title( $tv_id ) : __( 'Unknown Show', 'cinecore' );
$season_num = CineCore_Meta::get_season_number( $season_id );
$episodes = CineCore_Meta::get_episodes_for_season( $season_id, 100 );
?>
<div class="wrap cinecore-wrap">
	<h1><?php echo esc_html( $tv_title ); ?> — <?php echo esc_html( $season->post_title ? $season->post_title : sprintf( __( 'Season %s', 'cinecore' ), $season_num ) ); ?></h1>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-add-tv&post_id=' . $tv_id ) ); ?>" class="button"><?php esc_html_e( '← Back to TV Show', 'cinecore' ); ?></a> <a href="<?php echo esc_url( get_edit_post_link( $season_id ) ); ?>" class="button"><?php esc_html_e( 'Edit Season (WP)', 'cinecore' ); ?></a></p>

	<div id="cinecore-notice" class="notice hidden" style="display:none;"></div>

	<div class="postbox" style="padding:16px; margin-top:16px;">
		<h2><?php esc_html_e( 'Episodes', 'cinecore' ); ?> <span class="count">— <?php echo esc_html( sprintf( _n( '%s Episode', '%s Episodes', count( $episodes ), 'cinecore' ), count( $episodes ) ) ); ?></span></h2>
		<p>
			<button type="button" id="cinecore-import-episodes" class="button button-primary" data-season-id="<?php echo esc_attr( $season_id ); ?>"><?php esc_html_e( 'Import Episodes from TMDB', 'cinecore' ); ?></button>
			<button type="button" id="cinecore-add-episode" class="button" data-season-id="<?php echo esc_attr( $season_id ); ?>"><?php esc_html_e( '+ Add Episode', 'cinecore' ); ?></button>
		</p>
	</div>

	<!-- Bulk Link Manager -->
	<div class="postbox" style="padding:16px;">
		<h2><?php esc_html_e( 'Bulk Episode Link Manager', 'cinecore' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Paste links directly. Save All will update every episode without reloading.', 'cinecore' ); ?></p>
		<?php if ( empty( $episodes ) ) : ?>
			<p><?php esc_html_e( 'No episodes found. Import or add episodes first.', 'cinecore' ); ?></p>
		<?php else : ?>
			<div style="overflow-x:auto;">
				<table class="widefat striped" id="cinecore-bulk-table" style="min-width:800px;">
					<thead><tr><th style="width:80px;"><?php esc_html_e( 'Episode', 'cinecore' ); ?></th><th><?php esc_html_e( '1080p', 'cinecore' ); ?></th><th><?php esc_html_e( '720p', 'cinecore' ); ?></th><th><?php esc_html_e( '480p', 'cinecore' ); ?></th><th><?php esc_html_e( 'Server 1', 'cinecore' ); ?></th><th><?php esc_html_e( 'Server 2', 'cinecore' ); ?></th><th style="width:100px;"><?php esc_html_e( 'Status', 'cinecore' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $episodes as $ep_id ) :
							$ep_num = CineCore_Meta::get_episode_number( $ep_id );
							$links = CineCore_Meta::get_download_links( $ep_id );
							$servers = CineCore_Meta::get_servers( $ep_id );
							$dl_1080 = $links[0] ?? '';
							$dl_720 = $links[1] ?? '';
							$dl_480 = $links[2] ?? '';
							?>
							<tr data-episode-id="<?php echo esc_attr( $ep_id ); ?>">
								<td><strong>E<?php echo esc_html( str_pad( (string) $ep_num, 2, '0', STR_PAD_LEFT ) ); ?></strong><br><small><?php echo esc_html( get_the_title( $ep_id ) ); ?></small><br><a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-episode-manager&episode_id=' . $ep_id ) ); ?>" class="button button-small" style="margin-top:4px;"><?php esc_html_e( 'Edit', 'cinecore' ); ?></a></td>
								<td><input type="url" class="cinecore-bulk-1080" value="<?php echo esc_attr( $dl_1080 ); ?>" placeholder="https://" style="width:100%;" /></td>
								<td><input type="url" class="cinecore-bulk-720" value="<?php echo esc_attr( $dl_720 ); ?>" placeholder="https://" style="width:100%;" /></td>
								<td><input type="url" class="cinecore-bulk-480" value="<?php echo esc_attr( $dl_480 ); ?>" placeholder="https://" style="width:100%;" /></td>
								<td><input type="url" class="cinecore-bulk-srv1" value="<?php echo esc_attr( $servers['server_1'] ); ?>" placeholder="https://" style="width:100%;" /></td>
								<td><input type="url" class="cinecore-bulk-srv2" value="<?php echo esc_attr( $servers['server_2'] ); ?>" placeholder="https://" style="width:100%;" /></td>
								<td class="cinecore-bulk-status" style="font-size:12px; color:#666;"></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p style="margin-top:12px;"><button type="button" id="cinecore-bulk-save" class="button button-primary button-large"><?php esc_html_e( 'Save All Episodes', 'cinecore' ); ?></button> <span id="cinecore-bulk-summary" style="margin-left:8px;"></span></p>
		<?php endif; ?>
	</div>

	<!-- Single Episode Quick List (fallback) -->
	<?php if ( ! empty( $episodes ) ) : ?>
		<div class="postbox" style="padding:16px;">
			<h3><?php esc_html_e( 'Episode Quick Edit', 'cinecore' ); ?></h3>
			<ul>
				<?php foreach ( $episodes as $ep_id ) : ?>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-episode-manager&episode_id=' . $ep_id ) ); ?>">E<?php echo esc_html( str_pad( (string) CineCore_Meta::get_episode_number( $ep_id ), 2, '0', STR_PAD_LEFT ) ); ?> — <?php echo esc_html( get_the_title( $ep_id ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
