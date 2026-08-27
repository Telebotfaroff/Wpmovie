<?php
/**
 * Post Types - Modular placeholder
 *
 * @package CineCore
 */
defined( 'ABSPATH' ) || exit;
class CineCore_Post_Types {
	public function __construct(){add_action('init',array($this,'register_all'),0);}
	public function register_all(){}
}
