<?php
defined('ABSPATH') || exit;

function pirate69_source_config() {
	$settings = wp_parse_args(get_option('pirate69_import_settings', array()), array(
		'owner' => '7xmovies', 'repository' => 'database', 'branch' => 'main',
		'timeout' => 20, 'batch_size' => 10, 'poster_import' => 1,
	));
	$owner = sanitize_user($settings['owner'], true);
	$repo = preg_replace('/[^A-Za-z0-9_.-]/', '', $settings['repository']);
	$branch = rawurlencode($settings['branch']);
	return array('settings' => $settings, 'base' => "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/");
}

function pirate69_fetch_json($path) {
	$config = pirate69_source_config();
	$url = $config['base'] . ltrim($path, '/');
	$response = wp_safe_remote_get($url, array(
		'timeout' => max(5, min(60, absint($config['settings']['timeout']))),
		'redirection' => 3,
		'headers' => array('Accept' => 'application/json', 'User-Agent' => 'Pirate69-WordPress-Importer'),
	));
	if (is_wp_error($response)) return $response;
	$code = wp_remote_retrieve_response_code($response);
	if (200 !== $code) return new WP_Error('p69_http_error', sprintf('Source returned HTTP %d for %s', $code, esc_url_raw($url)));
	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body, true);
	if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) return new WP_Error('p69_invalid_json', 'Invalid JSON received from source: ' . $path);
	return $data;
}

function pirate69_source_categories() {
	return array(
		'hollywood' => array('label' => 'Hollywood', 'index' => 'hollywood-index.json'),
		'bollywood' => array('label' => 'Bollywood', 'index' => 'bollywood-index.json'),
		'xprimehub' => array('label' => 'X-Hub', 'index' => 'xprimehub-index.json'),
	);
}
