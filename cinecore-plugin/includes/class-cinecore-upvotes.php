<?php
/**
 * Scalable Upvotes - wp_cinecore_upvotes table
 *
 * @package CineCore
 */
defined( 'ABSPATH' ) || exit;
class CineCore_Upvotes {
	const TABLE_VERSION='1.0';
	public static function create_table(){
		global $wpdb;
		$table=$wpdb->prefix.'cinecore_upvotes';
		$charset=$wpdb->get_charset_collate();
		$sql="CREATE TABLE $table (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,post_id bigint(20) unsigned NOT NULL,user_id bigint(20) unsigned DEFAULT NULL,ip_hash varchar(64) DEFAULT NULL,created_at datetime NOT NULL,PRIMARY KEY  (id),UNIQUE KEY user_post (post_id, user_id),UNIQUE KEY ip_post (post_id, ip_hash),KEY post_id (post_id),KEY user_id (user_id)) $charset;";
		require_once ABSPATH.'wp-admin/includes/upgrade.php';
		dbDelta($sql);
		update_option('cinecore_upvotes_db_version',self::TABLE_VERSION,false);
	}
	public static function has_voted($post_id,$user_id=0,$ip_hash=''){
		global $wpdb;
		$table=$wpdb->prefix.'cinecore_upvotes';
		if($user_id){$count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d AND user_id=%d",$post_id,$user_id));return $count>0;}
		if(''!==$ip_hash){$count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d AND ip_hash=%s",$post_id,$ip_hash));return $count>0;}
		return false;
	}
	public static function add_vote($post_id,$user_id=null,$ip_hash=null){
		global $wpdb;
		$table=$wpdb->prefix.'cinecore_upvotes';
		$result=$wpdb->insert($table,array('post_id'=>absint($post_id),'user_id'=>$user_id?absint($user_id):null,'ip_hash'=>$ip_hash?(string)$ip_hash:null,'created_at'=>current_time('mysql')),array('%d','%d','%s','%s'));
		return false!==$result;
	}
	public static function get_count($post_id){
		global $wpdb;
		$table=$wpdb->prefix.'cinecore_upvotes';
		$count=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE post_id=%d",$post_id));
		if(0==$count){$legacy=(int)get_post_meta($post_id,'_cinecore_upvote_count',true);if($legacy>0)return $legacy;}
		return (int)$count;
	}
	public static function hash_ip($ip){
		$ip=filter_var($ip,FILTER_VALIDATE_IP)?$ip:'';
		if(''===$ip)return '';
		return hash('sha256',$ip.wp_salt('auth'));
	}
}
