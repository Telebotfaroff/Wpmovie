<?php
defined( 'ABSPATH' ) || exit;
$edit_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
$editing = $edit_id && get_post_type( $edit_id ) === 'movies';
$movie = $editing ? get_post( $edit_id ) : null;
$title = $editing ? $movie->post_title : '';
$content = $editing ? $movie->post_content : '';
$tmdb_id = $editing ? CineCore_Meta::get_tmdb_id( $edit_id ) : '';
$year_terms = $editing ? get_the_terms( $edit_id, 'release_year' ) : false;
$year = ( ! is_wp_error( $year_terms ) && ! empty( $year_terms ) ) ? $year_terms[0]->name : '';
$poster = $editing ? CineCore_Meta::get_poster( $edit_id, false ) : '';
$backdrop = $editing ? CineCore_Meta::get_backdrop( $edit_id ) : '';
$rating = $editing ? CineCore_Meta::get_rating( $edit_id ) : '';
$links = $editing ? CineCore_Meta::get_download_links( $edit_id ) : array();
$servers = $editing ? CineCore_Meta::get_servers( $edit_id ) : array( 'server_1' => '', 'server_2' => '', 'server_vip' => '' );
$subs = $editing ? CineCore_Meta::get_subtitles( $edit_id ) : array( 'en' => '', 'si' => '' );
// Genres.
$all_genres = get_terms( array( 'taxonomy' => 'genre', 'hide_empty' => false ) );
$selected_genres = $editing ? wp_get_object_terms( $edit_id, 'genre', array( 'fields' => 'ids' ) ) : array();
if ( is_wp_error( $selected_genres ) ) {
	$selected_genres = array();
}
?>
<div class="wrap cinecore-wrap" style="max-width:900px;">
	<h1><?php echo $editing ? esc_html__( 'Edit Movie', 'cinecore' ) : esc_html__( 'Add Movie', 'cinecore' ); ?></h1>

	<div id="cinecore-notice" class="notice hidden" style="display:none;"></div>

	<!-- TMDB Search -->
	<div class="postbox" style="padding:16px; margin-top:16px;">
		<h2 style="margin:0 0 12px;"><?php esc_html_e( 'Search TMDB', 'cinecore' ); ?></h2>
		<div style="display:flex; gap:8px;">
			<input type="text" id="cinecore-tmdb-query" placeholder="<?php esc_attr_e( 'Movie title / TMDB ID', 'cinecore' ); ?>" style="flex:1;" />
			<button type="button" id="cinecore-tmdb-search" class="button button-primary"><?php esc_html_e( 'Search', 'cinecore' ); ?></button>
		</div>
		<div id="cinecore-tmdb-results" style="margin-top:12px; display:none;">
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Poster', 'cinecore' ); ?></th><th><?php esc_html_e( 'Title', 'cinecore' ); ?></th><th><?php esc_html_e( 'Year', 'cinecore' ); ?></th><th><?php esc_html_e( 'Rating', 'cinecore' ); ?></th><th><?php esc_html_e( 'TMDB ID', 'cinecore' ); ?></th><th></th></tr></thead><tbody id="cinecore-tmdb-tbody"></tbody></table>
		</div>
		<div id="cinecore-tmdb-preview" style="margin-top:16px; display:none; border:1px solid #ccd0d4; padding:16px; display:flex; gap:16px;">
			<img id="cinecore-preview-poster" src="" style="width:120px; height:180px; object-fit:cover; background:#f0f0f0;" alt="" />
			<div>
				<h3 id="cinecore-preview-title" style="margin:0;"></h3>
				<p id="cinecore-preview-year" style="margin:4px 0; color:#666;"></p>
				<p id="cinecore-preview-rating" style="margin:4px 0;"></p>
				<p id="cinecore-preview-desc" style="margin:8px 0; color:#444; max-width:500px;"></p>
			</div>
		</div>
	</div>

	<!-- Movie Form -->
	<form id="cinecore-movie-form">
		<input type="hidden" name="post_id" value="<?php echo esc_attr( $edit_id ); ?>" />
		<input type="hidden" id="cinecore-tmdb-id" name="tmdb_id" value="<?php echo esc_attr( $tmdb_id ); ?>" />
		<input type="hidden" id="cinecore-poster-url" name="poster_url" value="<?php echo esc_attr( $poster ); ?>" />
		<input type="hidden" id="cinecore-backdrop-url" name="backdrop_url" value="<?php echo esc_attr( $backdrop ); ?>" />

		<div class="postbox" style="padding:16px;">
			<h2><?php esc_html_e( 'Movie Details', 'cinecore' ); ?></h2>
			<table class="form-table">
				<tr><th><label for="cinecore-title"><?php esc_html_e( 'Title', 'cinecore' ); ?></label></th><td><input type="text" id="cinecore-title" name="title" value="<?php echo esc_attr( $title ); ?>" class="regular-text" style="width:100%;" required /></td></tr>
				<tr><th><label for="cinecore-content"><?php esc_html_e( 'Description', 'cinecore' ); ?></label></th><td><textarea id="cinecore-content" name="content" rows="5" style="width:100%;"><?php echo esc_textarea( $content ); ?></textarea></td></tr>
				<tr><th><label><?php esc_html_e( 'Genres', 'cinecore' ); ?></label></th><td>
					<?php if ( ! is_wp_error( $all_genres ) && ! empty( $all_genres ) ) : ?>
						<?php foreach ( $all_genres as $term ) : ?>
							<label style="display:inline-block; margin-right:12px;"><input type="checkbox" name="genres[]" value="<?php echo esc_attr( $term->term_id ); ?>" <?php checked( in_array( (int) $term->term_id, array_map( 'intval', $selected_genres ), true ) ); ?> /> <?php echo esc_html( $term->name ); ?></label>
						<?php endforeach; ?>
					<?php else : ?>
						<span class="description"><?php esc_html_e( 'No genres. Create in Posts → Genres.', 'cinecore' ); ?></span>
					<?php endif; ?>
				</td></tr>
				<tr><th><label for="cinecore-year"><?php esc_html_e( 'Release Year', 'cinecore' ); ?></label></th><td><input type="text" id="cinecore-year" name="year" value="<?php echo esc_attr( $year ); ?>" placeholder="2024" style="width:120px;" /></td></tr>
				<tr><th><label for="cinecore-rating"><?php esc_html_e( 'Rating', 'cinecore' ); ?></label></th><td><input type="text" id="cinecore-rating" name="rating" value="<?php echo esc_attr( $rating ); ?>" placeholder="8.4" style="width:80px;" /></td></tr>
				<tr><th><label><?php esc_html_e( 'Poster', 'cinecore' ); ?></label></th><td>
					<div id="cinecore-poster-preview" style="margin-bottom:8px;">
						<?php if ( $poster ) : ?>
							<img src="<?php echo esc_url( $poster ); ?>" style="max-width:150px;" alt="" />
						<?php endif; ?>
					</div>
					<input type="url" id="cinecore-poster-input" name="poster_input" value="<?php echo esc_attr( $poster ); ?>" placeholder="https://..." style="width:100%;" />
					<p class="description"><?php esc_html_e( 'Will be downloaded to Media Library on publish.', 'cinecore' ); ?></p>
				</td></tr>
				<tr><th><label><?php esc_html_e( 'Backdrop', 'cinecore' ); ?></label></th><td><input type="url" id="cinecore-backdrop-input" name="backdrop_input" value="<?php echo esc_attr( $backdrop ); ?>" placeholder="https://..." style="width:100%;" /></td></tr>
			</table>
		</div>

		<div class="postbox" style="padding:16px;">
			<h2><?php esc_html_e( 'Download Links', 'cinecore' ); ?></h2>
			<table class="widefat" id="cinecore-download-table"><thead><tr><th><?php esc_html_e( 'Quality', 'cinecore' ); ?></th><th><?php esc_html_e( 'URL', 'cinecore' ); ?></th><th><?php esc_html_e( 'Label', 'cinecore' ); ?></th><th><?php esc_html_e( 'Size', 'cinecore' ); ?></th><th></th></tr></thead><tbody>
				<?php
				// Show existing links or default 1080p/720p/480p.
				$default_quals = array( '1080p' => '', '720p' => '', '480p' => '' );
				// If links is array of URLs without quality, map to defaults.
				if ( ! empty( $links ) && is_array( $links ) ) {
					// Try to preserve quality if stored as associative? For now map sequentially.
					$i = 0;
					foreach ( $links as $url ) {
						$qual = array_keys( $default_quals )[ $i ] ?? '1080p';
						echo '<tr><td><input type="text" name="dl_quality[]" value="' . esc_attr( $qual ) . '" style="width:80px;" /></td><td><input type="url" name="dl_url[]" value="' . esc_attr( $url ) . '" style="width:100%;" /></td><td><input type="text" name="dl_label[]" value="" style="width:100px;" /></td><td><input type="text" name="dl_size[]" value="" style="width:80px;" /></td><td><button type="button" class="button cinecore-remove-row">×</button></td></tr>';
						$i++;
						if ( $i >= 3 ) {
							break;
						}
					}
				}
				if ( empty( $links ) ) {
					foreach ( $default_quals as $q => $v ) {
						echo '<tr><td><input type="text" name="dl_quality[]" value="' . esc_attr( $q ) . '" style="width:80px;" /></td><td><input type="url" name="dl_url[]" value="" style="width:100%;" /></td><td><input type="text" name="dl_label[]" value="" style="width:100px;" /></td><td><input type="text" name="dl_size[]" value="" style="width:80px;" /></td><td><button type="button" class="button cinecore-remove-row">×</button></td></tr>';
					}
				}
				?>
			</tbody></table>
			<p><button type="button" id="cinecore-add-quality" class="button"><?php esc_html_e( '+ Add Quality', 'cinecore' ); ?></button></p>
		</div>

		<div class="postbox" style="padding:16px;">
			<h2><?php esc_html_e( 'Streaming Servers', 'cinecore' ); ?></h2>
			<table class="widefat" id="cinecore-server-table"><thead><tr><th><?php esc_html_e( 'Server Name', 'cinecore' ); ?></th><th><?php esc_html_e( 'URL', 'cinecore' ); ?></th><th></th></tr></thead><tbody>
				<?php
				$servers_list = array( 'Server 1' => $servers['server_1'], 'Server 2' => $servers['server_2'], 'Server 3' => $servers['server_vip'] );
				foreach ( $servers_list as $name => $url ) {
					echo '<tr><td><input type="text" name="srv_name[]" value="' . esc_attr( $name ) . '" style="width:120px;" /></td><td><input type="url" name="srv_url[]" value="' . esc_attr( $url ) . '" style="width:100%;" /></td><td><button type="button" class="button cinecore-remove-row">×</button></td></tr>';
				}
				?>
			</tbody></table>
			<p><button type="button" id="cinecore-add-server" class="button"><?php esc_html_e( '+ Add Server', 'cinecore' ); ?></button></p>
		</div>

		<div class="postbox" style="padding:16px;">
			<h2><?php esc_html_e( 'Subtitles', 'cinecore' ); ?></h2>
			<table class="widefat" id="cinecore-subtitle-table"><thead><tr><th><?php esc_html_e( 'Language', 'cinecore' ); ?></th><th><?php esc_html_e( 'URL', 'cinecore' ); ?></th><th></th></tr></thead><tbody>
				<?php
				$subs_list = array( 'English' => $subs['en'], 'Sinhala' => $subs['si'] );
				foreach ( $subs_list as $lang => $url ) {
					echo '<tr><td><input type="text" name="sub_lang[]" value="' . esc_attr( $lang ) . '" style="width:120px;" /></td><td><input type="url" name="sub_url[]" value="' . esc_attr( $url ) . '" style="width:100%;" /></td><td><button type="button" class="button cinecore-remove-row">×</button></td></tr>';
				}
				?>
			</tbody></table>
			<p><button type="button" id="cinecore-add-subtitle" class="button"><?php esc_html_e( '+ Add Subtitle', 'cinecore' ); ?></button></p>
		</div>

		<p class="submit" style="display:flex; gap:12px;">
			<button type="button" id="cinecore-save-draft" class="button button-large"><?php esc_html_e( 'Save Draft', 'cinecore' ); ?></button>
			<button type="button" id="cinecore-publish" class="button button-primary button-large"><?php esc_html_e( 'Publish Movie', 'cinecore' ); ?></button>
		</p>
	</form>
</div>
