<?php
defined('ABSPATH') || exit;
function pirate69_register_content_types(){
$base=array('public'=>true,'show_in_rest'=>true,'has_archive'=>true,'supports'=>array('title','editor','thumbnail','excerpt','custom-fields'),'menu_icon'=>'dashicons-format-video');
register_post_type('p69_movie',array_merge($base,array('labels'=>array('name'=>__('Movies','pirate69'),'singular_name'=>__('Movie','pirate69'),'add_new_item'=>__('Add Movie','pirate69')),'has_archive'=>'movies','rewrite'=>array('slug'=>'movie','with_front'=>false))));
register_post_type('p69_tv_show',array_merge($base,array('labels'=>array('name'=>__('TV Series','pirate69'),'singular_name'=>__('TV Series','pirate69'),'add_new_item'=>__('Add TV Series','pirate69')),'has_archive'=>'series','rewrite'=>array('slug'=>'series','with_front'=>false))));
$tax=array('p69_genre'=>__('Genres','pirate69'),'p69_language'=>__('Languages','pirate69'),'p69_industry'=>__('Industries','pirate69'),'p69_quality'=>__('Qualities','pirate69'));
foreach($tax as $slug=>$label){register_taxonomy($slug,array('p69_movie','p69_tv_show'),array('labels'=>array('name'=>$label,'singular_name'=>$label),'public'=>true,'hierarchical'=>true,'show_in_rest'=>true,'rewrite'=>array('slug'=>str_replace('p69_','',$slug))));}
}
add_action('init','pirate69_register_content_types');