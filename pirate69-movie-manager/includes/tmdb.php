<?php
defined('ABSPATH') || exit;

/**
 * TMDB is the metadata authority. GitHub remains the source for download links.
 * Matching and image work run in a separate cron request, never in the import batch.
 */
function pirate69_tmdb_settings() {
	$settings = wp_parse_args(get_option('pirate69_import_settings', array()), array(
		'tmdb_token' => '', 'tmdb_language' => 'en-US', 'tmdb_image_size' => 'w500',
		'tmdb_auto_sync' => 1, 'tmdb_min_score' => 82,
	));
	return $settings;
}

function pirate69_tmdb_request($path, $args = array()) {
	$settings = pirate69_tmdb_settings();
	$token = trim((string) $settings['tmdb_token']);
	if (!$token) return new WP_Error('p69_tmdb_no_token', 'Add your TMDB API Read Access Token in Importer Settings.');
	$url = add_query_arg(array_merge(array('language' => $settings['tmdb_language']), $args), 'https://api.themoviedb.org/3/' . ltrim($path, '/'));
	$response = wp_remote_get($url, array(
		'timeout' => 18,
		'headers' => array('Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'),
		'redirection' => 3,
	));
	if (is_wp_error($response)) return $response;
	$code = (int) wp_remote_retrieve_response_code($response);
	$data = json_decode(wp_remote_retrieve_body($response), true);
	if ($code < 200 || $code >= 300 || !is_array($data)) {
		$message = is_array($data) && !empty($data['status_message']) ? $data['status_message'] : 'TMDB request failed (HTTP ' . $code . ').';
		return new WP_Error('p69_tmdb_http', sanitize_text_field($message));
	}
	return $data;
}

function pirate69_tmdb_normalize_title($title) {
	$title = html_entity_decode(wp_strip_all_tags((string) $title), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
	$title = preg_replace('/\b(download|watch online|full movie|web[- ]?dl|webrip|bluray|brrip|hdrip|hdtv|uncensored|proper|repack|dual audio|multi audio|multi language|hindi dubbed|english dubbed|org)\b/i', ' ', $title);
	$title = preg_replace('/\b(480p|540p|576p|720p|1080p|1440p|2160p|4k|8k|x264|x265|h264|h265|hevc|aac|ddp?5?1|10bit|8bit)\b/i', ' ', $title);
	$title = preg_replace('/\[[^\]]*(mb|gb|kb)\s*\]/i', ' ', $title);
	$title = preg_replace('/\b\d+(?:\.\d+)?\s*(?:mb|gb|kb)\b/i', ' ', $title);
	$title = preg_replace('/\b(primevideo|amazon prime|netflix|hotstar|disney\+|zee5|sonyliv|apple tv\+|mx player|jio cinema)\b/i', ' ', $title);
	$title = preg_replace('/\b(19|20)\d{2}\b/', ' ', $title);
	$title = preg_replace('/\b(hindi|english|tamil|telugu|malayalam|kannada|bengali|punjabi|japanese|korean|chinese)\b/i', ' ', $title);
	$title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title);
	return trim(preg_replace('/\s+/u', ' ', $title));
}

function pirate69_tmdb_title_similarity($a, $b) {
	$a = strtolower(remove_accents(pirate69_tmdb_normalize_title($a)));
	$b = strtolower(remove_accents(pirate69_tmdb_normalize_title($b)));
	if (!$a || !$b) return 0;
	if ($a === $b) return 1;
	similar_text($a, $b, $percent);
	$tokens_a = array_unique(explode(' ', $a));
	$tokens_b = array_unique(explode(' ', $b));
	$intersection = count(array_intersect($tokens_a, $tokens_b));
	$union = max(1, count(array_unique(array_merge($tokens_a, $tokens_b))));
	return max($percent / 100, $intersection / $union);
}

function pirate69_tmdb_year_from_record($record) {
	$year = absint($record['year'] ?? 0);
	if ($year >= 1888 && $year <= ((int) gmdate('Y') + 2)) return $year;
	$title = (string) ($record['fullTitle'] ?? $record['cleanTitle'] ?? '');
	return preg_match('/\b((?:19|20)\d{2})\b/', $title, $m) ? absint($m[1]) : 0;
}

function pirate69_tmdb_match_record($record, $post_type) {
	$title = pirate69_tmdb_normalize_title($record['cleanTitle'] ?? $record['fullTitle'] ?? $record['title'] ?? '');
	if (!$title) return new WP_Error('p69_tmdb_empty_title', 'Could not extract a searchable title.');
	$year = pirate69_tmdb_year_from_record($record);
	$is_tv = ('p69_tv_show' === $post_type);
	$endpoint = $is_tv ? 'search/tv' : 'search/movie';
	$search = pirate69_tmdb_request($endpoint, array('query' => $title, 'include_adult' => 'false', 'year' => $year ?: null, 'first_air_date_year' => $year ?: null));
	if (is_wp_error($search)) return $search;
	$results = array_slice((array) ($search['results'] ?? array()), 0, 8);
	$ranked = array();
	foreach ($results as $item) {
		$candidate_title = $is_tv ? ($item['name'] ?? '') : ($item['title'] ?? '');
		$original_title = $is_tv ? ($item['original_name'] ?? '') : ($item['original_title'] ?? '');
		$score = max(pirate69_tmdb_title_similarity($title, $candidate_title), pirate69_tmdb_title_similarity($title, $original_title));
		$date = $is_tv ? ($item['first_air_date'] ?? '') : ($item['release_date'] ?? '');
		$candidate_year = $date ? absint(substr($date, 0, 4)) : 0;
		if ($year && $candidate_year) $score += ($year === $candidate_year) ? 0.12 : -0.18;
		$ranked[] = array('item' => $item, 'score' => min(1, max(0, $score)), 'year' => $candidate_year);
	}
	usort($ranked, function($a, $b) { return $b['score'] <=> $a['score']; });
	$best = $ranked[0] ?? null;
	$second = $ranked[1]['score'] ?? 0;
	$minimum = max(0.65, min(0.98, ((int) pirate69_tmdb_settings()['tmdb_min_score']) / 100));
	if (!$best || $best['score'] < $minimum || ($best['score'] - $second) < 0.035) {
		return new WP_Error('p69_tmdb_unmatched', 'No confident TMDB match. Review this title manually.', array('score' => $best['score'] ?? 0));
	}
	return array('id' => absint($best['item']['id']), 'type' => $is_tv ? 'tv' : 'movie', 'score' => round($best['score'], 4));
}

function pirate69_tmdb_sync_post($post_id, $force = false) {
	$post_id = absint($post_id);
	$post = get_post($post_id);
	if (!$post || !in_array($post->post_type, array('p69_movie', 'p69_tv_show'), true)) return new WP_Error('p69_tmdb_bad_post', 'Movie post not found.');
	$settings = pirate69_tmdb_settings();
	if (empty($settings['tmdb_auto_sync']) && !$force) return false;
	$token = trim((string) $settings['tmdb_token']);
	if (!$token) return new WP_Error('p69_tmdb_no_token', 'TMDB token is not configured.');
	$tmdb_id = absint(get_post_meta($post_id, '_p69_tmdb_id', true));
	$tmdb_type = get_post_meta($post_id, '_p69_tmdb_type', true);
	$record = array(
		'id' => get_post_meta($post_id, '_p69_source_id', true),
		'cleanTitle' => get_post_meta($post_id, '_p69_full_title', true) ?: $post->post_title,
		'fullTitle' => get_post_meta($post_id, '_p69_full_title', true) ?: $post->post_title,
		'year' => get_post_meta($post_id, '_p69_release_year', true),
	);
	if (!$tmdb_id || !in_array($tmdb_type, array('movie', 'tv'), true)) {
		$match = pirate69_tmdb_match_record($record, $post->post_type);
		if (is_wp_error($match)) {
			update_post_meta($post_id, '_p69_tmdb_status', 'unmatched');
			update_post_meta($post_id, '_p69_tmdb_error', $match->get_error_message());
			return $match;
		}
		$tmdb_id = $match['id'];
		$tmdb_type = $match['type'];
		update_post_meta($post_id, '_p69_tmdb_id', $tmdb_id);
		update_post_meta($post_id, '_p69_tmdb_type', $tmdb_type);
		update_post_meta($post_id, '_p69_tmdb_match_score', $match['score']);
	}
	$details = pirate69_tmdb_request($tmdb_type . '/' . $tmdb_id, array('append_to_response' => 'credits,external_ids'));
	if (is_wp_error($details)) return $details;
	$title = $tmdb_type === 'tv' ? ($details['name'] ?? '') : ($details['title'] ?? '');
	$original = $tmdb_type === 'tv' ? ($details['original_name'] ?? '') : ($details['original_title'] ?? '');
	$date = $tmdb_type === 'tv' ? ($details['first_air_date'] ?? '') : ($details['release_date'] ?? '');
	$year = $date ? substr($date, 0, 4) : '';
	$poster_path = $details['poster_path'] ?? '';
	$backdrop_path = $details['backdrop_path'] ?? '';
	$size = in_array($settings['tmdb_image_size'], array('w342','w500','w780','original'), true) ? $settings['tmdb_image_size'] : 'w500';
	$poster_url = $poster_path ? 'https://image.tmdb.org/t/p/' . $size . $poster_path : '';
	$backdrop_url = $backdrop_path ? 'https://image.tmdb.org/t/p/w1280' . $backdrop_path : '';
	$old_imported_title = get_post_meta($post_id, '_p69_imported_title', true);
	$old_tmdb_title = get_post_meta($post_id, '_p69_tmdb_title', true);
	$update = array('ID' => $post_id);
	if ($title && ($post->post_title === $old_imported_title || $post->post_title === $old_tmdb_title)) $update['post_title'] = sanitize_text_field($title);
	$overview = sanitize_textarea_field($details['overview'] ?? '');
	if ($overview && !has_excerpt($post_id)) $update['post_excerpt'] = $overview;
	if (count($update) > 1) wp_update_post(wp_slash($update));
	$genres = array_map(function($genre) { return sanitize_text_field($genre['name'] ?? ''); }, (array) ($details['genres'] ?? array()));
	$cast = array();
	foreach (array_slice((array) ($details['credits']['cast'] ?? array()), 0, 12) as $person) {
		if (!empty($person['name'])) $cast[] = sanitize_text_field($person['name']);
	}
	$crew = array();
	foreach ((array) ($details['credits']['crew'] ?? array()) as $person) {
		if (($person['job'] ?? '') === 'Director' && !empty($person['name'])) $crew[] = sanitize_text_field($person['name']);
	}
	$meta = array(
		'_p69_tmdb_status' => 'matched',
		'_p69_tmdb_title' => sanitize_text_field($title),
		'_p69_tmdb_original_title' => sanitize_text_field($original),
		'_p69_tmdb_overview' => $overview,
		'_p69_tmdb_tagline' => sanitize_text_field($details['tagline'] ?? ''),
		'_p69_tmdb_release_date' => sanitize_text_field($date),
		'_p69_tmdb_year' => $year,
		'_p69_tmdb_runtime' => absint($details['runtime'] ?? ($details['episode_run_time'][0] ?? 0)),
		'_p69_tmdb_rating' => isset($details['vote_average']) ? (float) $details['vote_average'] : '',
		'_p69_tmdb_vote_count' => absint($details['vote_count'] ?? 0),
		'_p69_tmdb_status_text' => sanitize_text_field($details['status'] ?? ''),
		'_p69_tmdb_genres' => $genres,
		'_p69_tmdb_cast' => $cast,
		'_p69_tmdb_directors' => array_values(array_unique($crew)),
		'_p69_tmdb_poster_url' => esc_url_raw($poster_url),
		'_p69_tmdb_backdrop_url' => esc_url_raw($backdrop_url),
		'_p69_tmdb_last_sync' => current_time('mysql', true),
		'_p69_tmdb_error' => '',
	);
	foreach ($meta as $key => $value) update_post_meta($post_id, $key, $value);
	if ($year) update_post_meta($post_id, '_p69_release_year', $year);
	if ($poster_url && function_exists('pirate69_import_poster')) {
		$current_poster_source = get_post_meta($post_id, '_p69_poster_source', true);
		if ($current_poster_source !== 'tmdb') {
			$attachment = pirate69_import_poster($post_id, $poster_url);
			if (!is_wp_error($attachment)) update_post_meta($post_id, '_p69_poster_source', 'tmdb');
			else update_post_meta($post_id, '_p69_tmdb_poster_error', $attachment->get_error_message());
		}
	}
	return array('status' => 'matched', 'tmdb_id' => $tmdb_id, 'title' => $title);
}

function pirate69_schedule_tmdb_sync($post_id, $delay = 12) {
	$post_id = absint($post_id);
	if (!$post_id || !pirate69_tmdb_settings()['tmdb_auto_sync']) return;
	$args = array($post_id);
	if (!wp_next_scheduled('pirate69_tmdb_sync_post', $args)) wp_schedule_single_event(time() + max(5, absint($delay)), 'pirate69_tmdb_sync_post', $args);
}

function pirate69_tmdb_sync_cron($post_id) {
	$result = pirate69_tmdb_sync_post($post_id);
	if (is_wp_error($result)) {
		pirate69_log(0, 'warning', 'TMDB metadata sync failed.', array('post_id' => absint($post_id), 'error' => $result->get_error_message()));
	}
}
add_action('pirate69_tmdb_sync_post', 'pirate69_tmdb_sync_cron');
