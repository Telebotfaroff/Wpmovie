<?php
defined( 'ABSPATH' ) || exit;
$ep_id = isset( $_GET['episode_id'] ) ? absint( $_GET['episode_id'] ) : 0;
if ( ! $ep_id || 'episodes' !== get_post_type( $ep_id ) ) {
	echo '<div class="wrap"><h1>' . esc_html__( 'Episode Manager', 'cinecore' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Invalid episode.', 'cinecore' ) . '</p></div></div>';
	return;
}
$ep = get_post( $ep_id );
$season_id = CineCore_Meta::get_episode_season_id( $ep_id );
$season = $season_id ? get_post( $season_id ) : null;
$tv_id = $season_id ? CineCore_Meta::get_season_tv_show_id( $season_id ) : 0;
$tv_title = $tv_id ? get_the_title( $tv_id ) : '';
$ep_num = CineCore_Meta::get_episode_number( $ep_id );
$air_date = get_post_meta( $ep_id, '_cinecore_air_date', true );
$links = CineCore_Meta::get_download_links( $ep_id );
$servers = CineCore_Meta::get_servers( $ep_id );
$subs = CineCore_Meta::get_subtitles( $ep_id );
$dl_1080 = $links[0] ?? '';
$dl_720 = $links[1] ?? '';
$dl_480 = $links[2] ?? '';
$next_ep = CineCore_Meta::get_adjacent_episode( $ep_id, false );
$prev_ep = CineCore_Meta::get_adjacent_episode( $ep_id, true );
?>
<div class="wrap cinecore-wrap" style="max-width:700px;">
	<h1><?php echo esc_html( $tv_title ? $tv_title . ' — ' : '' ); ?><?php echo esc_html( $season ? $season->post_title : '' ); ?> — <?php echo esc_html( $ep->post_title ); ?></h1>
	<p>
		<?php if ( $season_id ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-season-manager&season_id=' . $season_id ) ); ?>" class="button"><?php esc_html_e( '← Back to Season', 'cinecore' ); ?></a>
		<?php endif; ?>
		<a href="<?php echo esc_url( get_edit_post_link( $ep_id ) ); ?>" class="button"><?php esc_html_e( 'Edit (WP)', 'cinecore' ); ?></a>
	</p>
	<div id="cinecore-notice" class="notice hidden" style="display:none;"></div>
	<form id="cinecore-episode-form">
		<input type="hidden" name="episode_id" value="<?php echo esc_attr( $ep_id ); ?>" />
		<div class="postbox" style="padding:16px;">
			<h2><?php echo esc_html( sprintf( __( 'Episode %02d', 'cinecore' ), $ep_num ) ); ?></h2>
			<table class="form-table">
				<tr><th><label for="cinecore-ep-title"><?php esc_html_e( 'Title', 'cinecore' ); ?></label></th><td><input type="text" id="cinecore-ep-title" name="title" value="<?php echo esc_attr( $ep->post_title ); ?>" style="width:100%;" /></td></tr>
				<tr><th><label for="cinecore-ep-number"><?php esc_html_e( 'Episode Number', 'cinecore' ); ?></label></th><td><input type="number" id="cinecore-ep-number" name="episode_number" value="<?php echo esc_attr( $ep_num ); ?>" style="width:100px;" /></td></tr>
				<tr><th><label for="cinecore-ep-air"><?php esc_html_e( 'Air Date', 'cinecore' ); ?></label></th><td><input type="date" id="cinecore-ep-air" name="air_date" value="<?php echo esc_attr( $air_date ); ?>" /></td></tr>
				<tr><th><label for="cinecore-ep-1080"><?php esc_html_e( '1080p', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-ep-1080" name="dl_1080" value="<?php echo esc_attr( $dl_1080 ); ?>" style="width:100%;" placeholder="https://" /></td></tr>
				<tr><th><label for="cinecore-ep-720"><?php esc_html_e( '720p', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-ep-720" name="dl_720" value="<?php echo esc_attr( $dl_720 ); ?>" style="width:100%;" /></td></tr>
				<tr><th><label for="cinecore-ep-480"><?php esc_html_e( '480p', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-ep-480" name="dl_480" value="<?php echo esc_attr( $dl_480 ); ?>" style="width:100%;" /></td></tr>
				<tr><th><label for="cinecore-ep-srv1"><?php esc_html_e( 'Server 1', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-ep-srv1" name="server_1" value="<?php echo esc_attr( $servers['server_1'] ); ?>" style="width:100%;" /></td></tr>
				<tr><th><label for="cinecore-ep-srv2"><?php esc_html_e( 'Server 2', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-ep-srv2" name="server_2" value="<?php echo esc_attr( $servers['server_2'] ); ?>" style="width:100%;" /></td></tr>
				<tr><th><label for="cinecore-ep-en"><?php esc_html_e( 'English Subtitle', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-ep-en" name="sub_en" value="<?php echo esc_attr( $subs['en'] ); ?>" style="width:100%;" /></td></tr>
				<tr><th><label for="cinecore-ep-si"><?php esc_html_e( 'Sinhala Subtitle', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-ep-si" name="sub_si" value="<?php echo esc_attr( $subs['si'] ); ?>" style="width:100%;" /></td></tr>
			</table>
		</div>
		<p class="submit" style="display:flex; gap:12px; align-items:center;">
			<button type="button" id="cinecore-ep-save" class="button button-primary button-large"><?php esc_html_e( 'Save Episode', 'cinecore' ); ?></button>
			<button type="button" id="cinecore-ep-save-next" class="button button-large"><?php esc_html_e( 'Save & Next', 'cinecore' ); ?></button>
			<span id="cinecore-ep-status" style="margin-left:8px;"></span>
		</p>
		<?php if ( $prev_ep || $next_ep ) : ?>
			<p style="display:flex; gap:8px; margin-top:16px;">
				<?php if ( $prev_ep ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-episode-manager&episode_id=' . $prev_ep->ID ) ); ?>" class="button">← <?php echo esc_html( get_the_title( $prev_ep->ID ) ); ?></a>
				<?php endif; ?>
				<?php if ( $next_ep ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cinecore-episode-manager&episode_id=' . $next_ep->ID ) ); ?>" id="cinecore-next-link" class="button" style="margin-left:auto;"><?php echo esc_html( get_the_title( $next_ep->ID ) ); ?> →</a>
				<?php else : ?>
					<span style="margin-left:auto; color:#666; padding:8px;"><?php esc_html_e( 'You have reached the last episode of this season.', 'cinecore' ); ?> <a href="<?php echo $season_id ? esc_url( admin_url( 'admin.php?page=cinecore-season-manager&season_id=' . $season_id ) ) : '#'; ?>" class="button"><?php esc_html_e( 'Return to Season', 'cinecore' ); ?></a></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</form>
</div>
