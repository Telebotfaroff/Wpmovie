<?php
/**
 * GitHub Releases updater for Pirate69 Movie Manager.
 */
defined('ABSPATH') || exit;

final class P69MM_GitHub_Updater {
    const REPOSITORY = 'Telebotfaroff/Wpmovie';
    const ASSET = 'pirate69-movie-manager.zip';
    const CACHE_KEY = 'p69mm_github_release_v1';

    public static function boot() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'inject_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_information'), 10, 3);
    }

    private static function release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if (false !== $cached) return $cached;

        $response = wp_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', array(
            'timeout' => 12,
            'headers' => array('Accept' => 'application/vnd.github+json', 'User-Agent' => 'Pirate69-WordPress-Updater'),
        ));
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            set_site_transient(self::CACHE_KEY, array(), 15 * MINUTE_IN_SECONDS);
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || !empty($data['draft']) || !empty($data['prerelease'])) $data = array();
        set_site_transient(self::CACHE_KEY, $data, 6 * HOUR_IN_SECONDS);
        return $data;
    }

    private static function package_url($release) {
        if (empty($release['assets']) || !is_array($release['assets'])) return '';
        foreach ($release['assets'] as $asset) {
            if (isset($asset['name'], $asset['browser_download_url']) && self::ASSET === $asset['name']) {
                $url = esc_url_raw($asset['browser_download_url']);
                return (0 === strpos($url, 'https://github.com/')) ? $url : '';
            }
        }
        return '';
    }

    public static function inject_update($transient) {
        if (!is_object($transient)) $transient = new stdClass();
        if (!isset($transient->checked) || !is_array($transient->checked)) return $transient;

        $plugin_file = plugin_basename(P69MM_FILE);
        if (!isset($transient->checked[$plugin_file])) return $transient;

        $release = self::release();
        $package = self::package_url($release);
        $version = isset($release['tag_name']) ? trim($release['tag_name']) : '';
        $version = preg_replace('/^plugin-v/i', '', $version);

        if (!$package || !$version || !preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/', $version)) return $transient;

        if (version_compare($version, $transient->checked[$plugin_file], '>')) {
            $transient->response[$plugin_file] = (object) array(
                'slug' => dirname($plugin_file),
                'plugin' => $plugin_file,
                'new_version' => $version,
                'url' => 'https://github.com/' . self::REPOSITORY . '/releases',
                'package' => $package,
                'tested' => get_bloginfo('version'),
                'requires' => '6.2',
                'requires_php' => '7.4',
            );
        } else {
            unset($transient->response[$plugin_file]);
        }
        return $transient;
    }

    public static function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || 'pirate69-movie-manager' !== $args->slug) return $result;
        $release = self::release();
        $version = isset($release['tag_name']) ? preg_replace('/^plugin-v/i', '', trim($release['tag_name'])) : '';
        if (!$version) return $result;
        $info = new stdClass();
        $info->name = 'Pirate69 Movie Manager';
        $info->slug = 'pirate69-movie-manager';
        $info->version = $version;
        $info->author = 'Pirate69';
        $info->homepage = 'https://github.com/' . self::REPOSITORY;
        $info->download_link = self::package_url($release);
        $info->sections = array('description' => 'Movie catalog content types, importer and download-link manager. Release notes are available on GitHub.');
        return $info->download_link ? $info : $result;
    }
}
P69MM_GitHub_Updater::boot();
