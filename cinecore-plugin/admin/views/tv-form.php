<?php defined( 'ABSPATH' ) || exit;
$edit_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
$editing = $edit_id && get_post_type( $edit_id ) === 'tv_shows';
$tv = $editing ? get_post( $edit_id ) : null;
$title = $editing ? $tv->post_title : '';
$content = $editing ? $tv->post_content : '';
$tmdb_id = $editing ? CineCore_Meta::get_tmdb_id( $edit_id ) : '';
$poster = $editing ? CineCore_Meta::get_poster( $edit_id, false ) : '';
?>
<div class="wrap cinecore-wrap" style="max-width:900px;">
	<h1><?php echo $editing ? esc_html__( 'Edit TV Show', 'cinecore' ) : esc_html__( 'Add TV Show', 'cinecore' ); ?></h1>
	<div id="cinecore-notice" class="notice hidden" style="display:none;"></div>
	<div class="postbox" style="padding:16px; margin-top:16px;">
		<h2 style="margin:0 0 12px;"><?php esc_html_e( 'Search TV Show', 'cinecore' ); ?></h2>
		<div style="display:flex; gap:8px;">
			<input type="text" id="cinecore-tv-query" placeholder="<?php esc_attr_e( 'TV Show title / TMDB ID', 'cinecore' ); ?>" style="flex:1;" />
			<button type="button" id="cinecore-tv-search" class="button button-primary"><?php esc_html_e( 'Search', 'cinecore' ); ?></button>
		</div>
		<div id="cinecore-tv-results" style="margin-top:12px; display:none;">
			<table class="widefat striped"><thead><tr><th>Poster</th><th>Title</th><th>Year</th><th>Rating</th><th>TMDB ID</th><th></th></tr></thead><tbody id="cinecore-tv-tbody"></tbody></table>
		</div>
		<div id="cinecore-tv-preview" style="margin-top:16px; display:none; border:1px solid #ccd0d4; padding:16px; display:flex; gap:16px;">
			<img id="cinecore-tv-poster" src="" style="width:120px; height:180px; object-fit:cover;" alt="" />
			<div><h3 id="cinecore-tv-title" style="margin:0;"></h3><p id="cinecore-tv-year"></p><p id="cinecore-tv-rating"></p><p id="cinecore-tv-desc"></p></div>
		</div>
	</div>
	<form id="cinecore-tv-form">
		<input type="hidden" name="post_id" value="<?php echo esc_attr( $edit_id ); ?>" />
		<input type="hidden" id="cinecore-tv-tmdb-id" name="tmdb_id" value="<?php echo esc_attr( $tmdb_id ); ?>" />
		<input type="hidden" id="cinecore-tv-poster-url" name="poster_url" value="<?php echo esc_attr( $poster ); ?>" />
		<div class="postbox" style="padding:16px;">
			<h2><?php esc_html_e( 'TV Show Details', 'cinecore' ); ?></h2>
			<table class="form-table">
				<tr><th><label for="cinecore-tv-title-input"><?php esc_html_e( 'Title', 'cinecore' ); ?></label></th><td><input type="text" id="cinecore-tv-title-input" name="title" value="<?php echo esc_attr( $title ); ?>" style="width:100%;" required /></td></tr>
				<tr><th><label><?php esc_html_e( 'Description', 'cinecore' ); ?></label></th><td><textarea name="content" rows="5" style="width:100%;"><?php echo esc_textarea( $content ); ?></textarea></td></tr>
				<tr><th><label>Poster</label></th><td><input type="url" name="poster_input" value="<?php echo esc_attr( $poster ); ?>" style="width:100%;" /></td></tr>
			</table>
		</div>
		<p class="submit"><button type="button" id="cinecore-tv-draft" class="button"><?php esc_html_e( 'Save Draft', 'cinecore' ); ?></button> <button type="button" id="cinecore-tv-publish" class="button button-primary"><?php esc_html_e( 'Publish TV Show', 'cinecore' ); ?></button></p>
	</form>
	<?php if ( $editing ) : ?>
		<div class="postbox" style="padding:16px; margin-top:24px;">
			<h2><?php echo esc_html( $title ); ?> — <?php esc_html_e( 'Seasons', 'cinecore' ); ?></h2>
			<?php
			$seasons = CineCore_Meta::get_seasons_for_show( $edit_id, 50 );
			if ( $seasons ) {
				echo '<ul>';
				foreach ( $seasons as $sid ) {
					$snum = CineCore_Meta::get_season_number( $sid );
					$eps = CineCore_Meta::get_episodes_for_season( $sid, 100 );
					echo '<li><strong>' . esc_html( get_the_title( $sid ) ? get_the_title( $sid ) : sprintf( __( 'Season %s', 'cinecore' ), $snum ) ) . '</strong> — ' . esc_html( sprintf( _n( '%s Episode', '%s Episodes', count( $eps ), 'cinecore' ), count( $eps ) ) ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=cinecore-season-manager&season_id=' . $sid ) ) . '" class="button button-small">' . esc_html__( 'Manage', 'cinecore' ) . '</a></li>';
				}
				echo '</ul>';
			} else {
				echo '<p>' . esc_html__( 'No seasons yet.', 'cinecore' ) . '</p>';
			}
			?>
			<p><button type="button" class="button" id="cinecore-add-season" data-tv-id="<?php echo esc_attr( $edit_id ); ?>" data-tmdb-id="<?php echo esc_attr( $tmdb_id ); ?>"><?php esc_html_e( '+ Add Season', 'cinecore' ); ?></button> <button type="button" class="button button-primary" id="cinecore-import-seasons" data-tv-id="<?php echo esc_attr( $edit_id ); ?>"><?php esc_html_e( 'Import Seasons from TMDB', 'cinecore' ); ?></button></p>
		</div>
	<?php endif; ?>
</div>
