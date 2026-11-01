<?php
defined('ABSPATH') || exit;
function pirate69_enqueue_assets(){wp_enqueue_style('pirate69-style',get_stylesheet_uri(),array(),PIRATE69_VERSION);wp_enqueue_style('pirate69-main',PIRATE69_URI.'/assets/css/theme.css',array('pirate69-style'),PIRATE69_VERSION);wp_enqueue_script('pirate69-main',PIRATE69_URI.'/assets/js/theme.js',array(),PIRATE69_VERSION,true);}
add_action('wp_enqueue_scripts','pirate69_enqueue_assets');