<?php defined( 'ABSPATH' ) || exit;
$needs = new WP_Query(array('post_type'=>array('movies','tv_shows','seasons','episodes'),'posts_per_page'=>-1,'meta_query'=>array(array('key'=>'_cinecore_poster_url','compare'=>'EXISTS')),'no_found_rows'=>true,'fields'=>'ids'));
$count_needs = 0;
foreach($needs->posts as $pid){ if(!has_post_thumbnail($pid)) $count_needs++; }
?>
<div class="wrap cinecore-wrap">
	<h1><?php esc_html_e( 'CineCore Tools', 'cinecore' ); ?></h1>
	<div class="postbox" style="padding:16px; margin-top:16px;">
		<h2><?php esc_html_e( 'Repair Posters', 'cinecore' ); ?></h2>
		<p><?php esc_html_e( 'Finds posts with _cinecore_poster_url but without featured image and imports them.', 'cinecore' ); ?></p>
		<p><strong><?php echo esc_html( sprintf( __( 'Posts needing poster repair: %d', 'cinecore' ), $count_needs ) ); ?></strong></p>
		<p><button type="button" id="cinecore-repair-posters" class="button button-primary" data-offset="0" data-batch="5"><?php esc_html_e( 'Repair All Posters', 'cinecore' ); ?></button></p>
		<div id="cinecore-repair-progress" style="margin-top:12px; display:none;"><div style="background:#f0f0f0; border-radius:4px; overflow:hidden; height:20px;"><div id="cinecore-repair-bar" style="width:0%; height:100%; background:#2271b1; transition:width 0.3s;"></div></div><p id="cinecore-repair-log" style="margin-top:8px; font-family:monospace; font-size:12px; max-height:200px; overflow:auto; background:#f9f9f9; padding:8px;"></p></div>
	</div>
</div>
