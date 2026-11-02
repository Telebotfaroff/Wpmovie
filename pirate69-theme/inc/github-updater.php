<?php
/**
 * GitHub Releases updater for the Pirate69 theme.
 */
defined('ABSPATH') || exit;

final class Pirate69_Theme_GitHub_Updater {
    const REPOSITORY = 'Telebotfaroff/Wpmovie';
    const ASSET = 'pirate69-theme.zip';
    const CACHE_KEY = 'p69_theme_github_release_v1';

    public static function boot() {
        add_filter('pre_set_site_transient_update_themes', array(__CLASS__, 'inject_update'));
        add_filter('themes_api', array(__CLASS__, 'theme_information'), 10, 3);
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

        $stylesheet = get_template();
        if (!isset($transient->checked[$stylesheet])) return $transient;

        $release = self::release();
        $package = self::package_url($release);
        $version = '';
        if (!empty($release['tag_name']) && preg_match('/theme-v(\\d+\\.\\d+\\.\\d+)/i', $release['tag_name'], $match)) $version = $match[1];

        if (!$package || !$version || !preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/', $version)) return $transient;

        if (version_compare($version, $transient->checked[$stylesheet], '>')) {
            $transient->response[$stylesheet] = array(
                'theme' => $stylesheet,
                'new_version' => $version,
                'url' => 'https://github.com/' . self::REPOSITORY . '/releases',
                'package' => $package,
                'requires' => '6.2',
                'requires_php' => '7.4',
            );
        } else {
            unset($transient->response[$stylesheet]);
        }
        return $transient;
    }

    public static function theme_information($result, $action, $args) {
        if ('theme_information' !== $action || empty($args->slug) || get_template() !== $args->slug) return $result;
        $release = self::release();
        $version = '';
        if (!empty($release['tag_name']) && preg_match('/theme-v(\\d+\\.\\d+\\.\\d+)/i', $release['tag_name'], $match)) $version = $match[1];
        if (!$version) return $result;
        $info = new stdClass();
        $info->name = 'Pirate69';
        $info->slug = get_template();
        $info->version = $version;
        $info->author = 'Pirate69';
        $info->homepage = 'https://github.com/' . self::REPOSITORY;
        $info->download_link = self::package_url($release);
        $info->sections = array('description' => 'Pirate69 cinematic movie and TV catalog theme. Release notes are available on GitHub.');
        return $info->download_link ? $info : $result;
    }
}
Pirate69_Theme_GitHub_Updater::boot();
