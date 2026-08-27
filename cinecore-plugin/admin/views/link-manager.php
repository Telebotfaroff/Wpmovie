<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap cinecore-wrap">
	<h1><?php esc_html_e( 'Link Manager', 'cinecore' ); ?></h1>
	<div class="postbox" style="padding:16px; margin-top:16px;">
		<h2><?php esc_html_e( 'Search', 'cinecore' ); ?></h2>
		<div style="display:flex; gap:8px;">
			<input type="text" id="cinecore-link-search" placeholder="<?php esc_attr_e( 'Movie, TV Show, Season, Episode', 'cinecore' ); ?>" style="flex:1;" />
			<button type="button" id="cinecore-link-search-btn" class="button button-primary"><?php esc_html_e( 'Search', 'cinecore' ); ?></button>
		</div>
		<div id="cinecore-link-results" style="margin-top:16px;"></div>
	</div>
	<div id="cinecore-link-editor" style="display:none;" class="postbox">
		<h2 id="cinecore-link-title" style="padding:12px 16px; margin:0; border-bottom:1px solid #ccd0d4;"></h2>
		<div style="padding:16px;">
			<h3><?php esc_html_e( 'Download Links', 'cinecore' ); ?></h3>
			<table class="widefat" id="cinecore-link-dl-table"><thead><tr><th>Quality</th><th>URL</th></tr></thead><tbody></tbody></table>
			<h3 style="margin-top:16px;"><?php esc_html_e( 'Streaming Servers', 'cinecore' ); ?></h3>
			<table class="widefat" id="cinecore-link-srv-table"><thead><tr><th>Server</th><th>URL</th></tr></thead><tbody></tbody></table>
			<h3 style="margin-top:16px;"><?php esc_html_e( 'Subtitles', 'cinecore' ); ?></h3>
			<table class="widefat" id="cinecore-link-sub-table"><thead><tr><th>Language</th><th>URL</th></tr></thead><tbody></tbody></table>
			<p style="margin-top:16px;"><button type="button" id="cinecore-link-save" class="button button-primary"><?php esc_html_e( 'Save Links', 'cinecore' ); ?></button> <span id="cinecore-link-status" style="margin-left:8px;"></span></p>
		</div>
	</div>
</div>
