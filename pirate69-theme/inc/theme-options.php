<?php
defined('ABSPATH') || exit;

function pirate69_sanitize_settings($input) {
	$input = is_array($input) ? $input : array();
	$accent = sanitize_hex_color($input['accent'] ?? '#8b5cf6');
	return array(
		'accent' => $accent ?: '#8b5cf6',
		'posts_per_page' => max(6, min(48, absint($input['posts_per_page'] ?? 24))),
		'show_hero' => empty($input['show_hero']) ? 0 : 1,
	);
}
function pirate69_register_settings() {
	register_setting('pirate69_settings', 'pirate69_settings', array(
		'type' => 'array',
		'sanitize_callback' => 'pirate69_sanitize_settings',
		'default' => array('accent' => '#8b5cf6', 'posts_per_page' => 24, 'show_hero' => 1),
	));
}
function pirate69_add_options_page() {
	add_theme_page(__('Pirate69 Settings', 'pirate69'), __('Pirate69 Settings', 'pirate69'), 'manage_options', 'pirate69-settings', 'pirate69_render_options');
}
function pirate69_render_options() {
	if (!current_user_can('manage_options')) return;
	$s = wp_parse_args(get_option('pirate69_settings', array()), array('accent' => '#8b5cf6', 'posts_per_page' => 24, 'show_hero' => 1));
	?>
	<div class="wrap">
		<h1>Pirate69 Settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields('pirate69_settings'); ?>
			<table class="form-table">
				<tr><th><label for="p69-accent">Accent color</label></th><td><input id="p69-accent" type="color" name="pirate69_settings[accent]" value="<?php echo esc_attr($s['accent']); ?>"></td></tr>
				<tr><th><label for="p69-count">Catalog items per page</label></th><td><input id="p69-count" type="number" min="6" max="48" name="pirate69_settings[posts_per_page]" value="<?php echo esc_attr($s['posts_per_page']); ?>"></td></tr>
				<tr><th>Homepage hero</th><td><label><input type="checkbox" name="pirate69_settings[show_hero]" value="1" <?php checked($s['show_hero'], 1); ?>> Show cinematic hero section</label></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
add_action('admin_init', 'pirate69_register_settings');
add_action('admin_menu', 'pirate69_add_options_page');
