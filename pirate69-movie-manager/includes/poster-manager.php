<?php
defined('ABSPATH') || exit;
/** Retry failed posters without changing existing featured images. */
function pirate69_retry_failed_posters($limit=20){
 $q=new WP_Query(array('post_type'=>array('p69_movie','p69_tv_show'),'post_status'=>'any','posts_per_page'=>max(1,min(100,absint($limit))),'fields'=>'ids','meta_query'=>array(array('key'=>'_p69_poster_status','value'=>'failed')),'no_found_rows'=>true));
 $done=0;foreach($q->posts as $post_id){$url=get_post_meta($post_id,'_p69_original_poster_url',true);if(!$url||has_post_thumbnail($post_id))continue;$result=pirate69_import_poster($post_id,$url);if(!is_wp_error($result))$done++;else update_post_meta($post_id,'_p69_poster_last_error',sanitize_text_field($result->get_error_message()));}return $done;
}
function pirate69_poster_admin_action(){
 if(!current_user_can('manage_options'))wp_die('Not allowed.');check_admin_referer('pirate69_poster_retry');$count=pirate69_retry_failed_posters(30);
 wp_safe_redirect(add_query_arg(array('page'=>'pirate69-importer','p69_poster_retried'=>$count),admin_url('admin.php')));exit;
}
add_action('admin_post_pirate69_poster_retry','pirate69_poster_admin_action');
