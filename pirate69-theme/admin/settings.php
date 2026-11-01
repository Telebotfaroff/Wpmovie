<?php
defined('ABSPATH') || exit;
function pirate69_register_import_settings() {
	register_setting('pirate69_import_settings_group','pirate69_import_settings',array('type'=>'array','sanitize_callback'=>'pirate69_sanitize_import_settings'));
}
function pirate69_sanitize_import_settings($input) {
	$input=is_array($input)?$input:array();
	return array(
		'owner'=>sanitize_user($input['owner']??'7xmovies',true),
		'repository'=>preg_replace('/[^A-Za-z0-9_.-]/','',$input['repository']??'database'),
		'branch'=>sanitize_text_field($input['branch']??'main'),
		'timeout'=>max(5,min(60,absint($input['timeout']??20))),
		'batch_size'=>max(1,min(30,absint($input['batch_size']??10))),
		'poster_import'=>empty($input['poster_import'])?0:1,
	);
}
function pirate69_import_settings_menu(){add_submenu_page('pirate69-importer','Importer Settings','Settings','manage_options','pirate69-importer-settings','pirate69_render_import_settings');}
function pirate69_render_import_settings(){
	if(!current_user_can('manage_options'))return;
	$s=wp_parse_args(get_option('pirate69_import_settings',array()),array('owner'=>'7xmovies','repository'=>'database','branch'=>'main','timeout'=>20,'batch_size'=>10,'poster_import'=>1));
	?><div class="wrap"><h1>Importer Settings</h1><form method="post" action="options.php"><?php settings_fields('pirate69_import_settings_group'); ?><table class="form-table">
	<tr><th>GitHub owner</th><td><input class="regular-text" name="pirate69_import_settings[owner]" value="<?php echo esc_attr($s['owner']); ?>"></td></tr>
	<tr><th>Repository</th><td><input class="regular-text" name="pirate69_import_settings[repository]" value="<?php echo esc_attr($s['repository']); ?>"></td></tr>
	<tr><th>Branch</th><td><input class="regular-text" name="pirate69_import_settings[branch]" value="<?php echo esc_attr($s['branch']); ?>"></td></tr>
	<tr><th>HTTP timeout</th><td><input type="number" min="5" max="60" name="pirate69_import_settings[timeout]" value="<?php echo absint($s['timeout']); ?>"> seconds</td></tr>
	<tr><th>Records per batch</th><td><input type="number" min="1" max="30" name="pirate69_import_settings[batch_size]" value="<?php echo absint($s['batch_size']); ?>"></td></tr>
	<tr><th>Poster storage</th><td><label><input type="checkbox" name="pirate69_import_settings[poster_import]" value="1" <?php checked($s['poster_import'],1); ?>> Download posters into Media Library</label></td></tr>
	</table><?php submit_button(); ?></form></div><?php
}
add_action('admin_init','pirate69_register_import_settings');
add_action('admin_menu','pirate69_import_settings_menu',20);
