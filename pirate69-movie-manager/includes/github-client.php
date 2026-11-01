<?php
defined('ABSPATH') || exit;
function pirate69_source_config(){
 $s=wp_parse_args(get_option('pirate69_import_settings',array()),array('owner'=>'7xmovies','repository'=>'database','branch'=>'main','timeout'=>20,'batch_size'=>20,'poster_import'=>1,'poster_max_mb'=>8,'poster_retry_limit'=>3));
 $owner=sanitize_user($s['owner'],true);$repo=preg_replace('/[^A-Za-z0-9_.-]/','',$s['repository']);$branch=rawurlencode($s['branch']);
 return array('settings'=>$s,'base'=>"https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/");
}
function pirate69_fetch_json($path){
 $c=pirate69_source_config();$url=$c['base'].ltrim($path,'/');$r=wp_safe_remote_get($url,array('timeout'=>max(5,min(60,absint($c['settings']['timeout']))),'redirection'=>3,'headers'=>array('Accept'=>'application/json','User-Agent'=>'Pirate69-WordPress-Importer')));
 if(is_wp_error($r))return $r;$code=wp_remote_retrieve_response_code($r);if(200!==$code)return new WP_Error('p69_http_error',sprintf('Source returned HTTP %d for %s',$code,esc_url_raw($url)));
 $data=json_decode(wp_remote_retrieve_body($r),true);if(JSON_ERROR_NONE!==json_last_error()||!is_array($data))return new WP_Error('p69_invalid_json','Invalid JSON received from source: '.$path);return $data;
}
function pirate69_source_categories(){return array('hollywood'=>array('label'=>'Hollywood','index'=>'hollywood-index.json','feed'=>'vegamovieshomepage.json'),'bollywood'=>array('label'=>'Bollywood','index'=>'bollywood-index.json','feed'=>'rogmovieshomepage.json'),'xprimehub'=>array('label'=>'X-Hub','index'=>'xprimehub-index.json','feed'=>'xprimehubhomepage.json'));}
function pirate69_merge_index_metadata($record,$row){
 if(!is_array($record)||!is_array($row))return $record;
 foreach(array('poster'=>'poster','year'=>'year','quality'=>'quality','isSeries'=>'isSeries','season'=>'season') as $src=>$dst){if((!isset($record[$dst])||''===$record[$dst])&&isset($row[$src]))$record[$dst]=$row[$src];}
 if(empty($record['cleanTitle'])&&!empty($row['title']))$record['cleanTitle']=$row['title'];if(empty($record['fullTitle'])&&!empty($row['title']))$record['fullTitle']=$row['title'];if(!empty($row['isSeries']))$record['isSeries']=true;return $record;
}


/**
 * Search the lightweight master index. Detailed chunk JSON is only fetched
 * after an administrator chooses a matching movie or chunk to import.
 */
function pirate69_search_catalog($category, $query, $limit = 50) {
	$categories = pirate69_source_categories();
	if (!isset($categories[$category])) return new WP_Error('p69_bad_category', 'Unknown catalog category.');
	$query = trim(sanitize_text_field($query));
	if ('' === $query) return array();
	$index = pirate69_fetch_json($categories[$category]['index']);
	if (is_wp_error($index)) return $index;
	$needle = function_exists('mb_strtolower') ? mb_strtolower($query) : strtolower($query);
	$matches = array();
	foreach ($index as $row) {
		if (!is_array($row)) continue;
		$title = (string)($row['title'] ?? $row['id'] ?? '');
		$id = (string)($row['id'] ?? '');
		$haystack = function_exists('mb_strtolower') ? mb_strtolower($title . ' ' . $id) : strtolower($title . ' ' . $id);
		if (false === strpos($haystack, $needle)) continue;
		$matches[] = array(
			'id' => sanitize_text_field($id),
			'title' => sanitize_text_field($title),
			'chunk' => absint($row['chunk'] ?? 0),
			'year' => sanitize_text_field($row['year'] ?? ''),
			'quality' => sanitize_text_field($row['quality'] ?? ''),
			'isSeries' => !empty($row['isSeries']),
		);
		if (count($matches) >= max(1, min(100, absint($limit)))) break;
	}
	return $matches;
}
