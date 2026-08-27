<?php
/**
 * CineCore Migrations - Idempotent
 *
 * @package CineCore
 */
defined( 'ABSPATH' ) || exit;
class CineCore_Migrations {
	const OPTION_DB_VERSION = 'cinecore_db_version';
	const CURRENT_VERSION   = '2.0';
	public static function init() {
		$version = get_option( self::OPTION_DB_VERSION, '1.0' );
		if ( version_compare( $version, self::CURRENT_VERSION, '<' ) ) {
			self::run( $version );
		}
	}
	private static function run( $from_version ) {
		if ( version_compare( $from_version, '1.1', '<' ) ) {
			self::migrate_legacy_relationships();
			error_log( '[CineCore Migration] 1.0 -> 1.1 legacy relationships normalized' );
		}
		if ( version_compare( $from_version, '2.0', '<' ) ) {
			CineCore_Upvotes::create_table();
			self::migrate_upvotes_to_table();
			error_log( '[CineCore Migration] 1.1 -> 2.0 upvotes table created' );
		}
		update_option( self::OPTION_DB_VERSION, self::CURRENT_VERSION, false );
	}
	private static function migrate_legacy_relationships() {
		$q = new WP_Query(array('post_type'=>'seasons','posts_per_page'=>-1,'meta_query'=>array(array('key'=>'_cinecore_parent_tv_show','compare'=>'EXISTS'),array('key'=>'_cinecore_tv_show_id','compare'=>'NOT EXISTS')),'no_found_rows'=>true,'fields'=>'ids'));
		foreach($q->posts as $sid){$legacy=get_post_meta($sid,'_cinecore_parent_tv_show',true);if(''!==$legacy){update_post_meta($sid,'_cinecore_tv_show_id',$legacy);}}
		$q2 = new WP_Query(array('post_type'=>'episodes','posts_per_page'=>-1,'meta_query'=>array(array('key'=>'_cinecore_parent_season','compare'=>'EXISTS'),array('key'=>'_cinecore_season_id','compare'=>'NOT EXISTS')),'no_found_rows'=>true,'fields'=>'ids'));
		foreach($q2->posts as $eid){$legacy=get_post_meta($eid,'_cinecore_parent_season',true);if(''!==$legacy){update_post_meta($eid,'_cinecore_season_id',$legacy);}}
	}
	private static function migrate_upvotes_to_table() {
		global $wpdb;
		$table=$wpdb->prefix.'cinecore_upvotes';
		$exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));
		if(!$exists)return;
		$q=new WP_Query(array('post_type'=>'subtitle_requests','posts_per_page'=>-1,'meta_query'=>array(array('key'=>'_cinecore_upvote_count','compare'=>'EXISTS')),'no_found_rows'=>true,'fields'=>'ids'));
		foreach($q->posts as $pid){
			$count=(int)get_post_meta($pid,'_cinecore_upvote_count',true);
			if($count<=0)continue;
			$has=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d",$pid));
			if($has>0)continue;
			$users=get_post_meta($pid,'_cinecore_upvoted_users',true);
			if(is_array($users)){foreach($users as $uid){$wpdb->insert($table,array('post_id'=>$pid,'user_id'=>absint($uid),'ip_hash'=>null,'created_at'=>current_time('mysql')),array('%d','%d','%s','%s'));}}
			$ips=get_post_meta($pid,'_cinecore_upvoted_ips',true);
			if(is_array($ips)){foreach($ips as $ip){$hash=hash('sha256',(string)$ip.wp_salt('auth'));$exists_ip=$wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE post_id=%d AND ip_hash=%s",$pid,$hash));if($exists_ip)continue;$wpdb->insert($table,array('post_id'=>$pid,'user_id'=>null,'ip_hash'=>$hash,'created_at'=>current_time('mysql')),array('%d','%d','%s','%s'));} }
		}
	}
}
