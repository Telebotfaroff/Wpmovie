<?php
defined('ABSPATH') || exit;

/**
 * Queue a poster without making the movie import wait for a remote image host.
 * A unique post_id makes this operation idempotent.
 */
function pirate69_queue_poster($post_id, $url) {
	global $wpdb;
	$post_id = absint($post_id);
	$url = esc_url_raw($url);
	if (!$post_id || !$url || !wp_http_validate_url($url) || has_post_thumbnail($post_id)) return false;
	$table = $wpdb->prefix . 'p69_poster_queue';
	$now = current_time('mysql', true);
	$wpdb->replace($table, array(
		'post_id' => $post_id,
		'poster_url' => $url,
		'status' => 'queued',
		'attempts' => 0,
		'last_error' => '',
		'created_at' => $now,
		'updated_at' => $now,
	), array('%d','%s','%s','%d','%s','%s','%s'));
	update_post_meta($post_id, '_p69_poster_status', 'queued');
	if (!wp_next_scheduled('pirate69_process_poster_queue')) {
		wp_schedule_single_event(time() + 5, 'pirate69_process_poster_queue');
	}
	return true;
}

/** Process a small number of posters independently of movie metadata imports. */
function pirate69_process_poster_queue($limit = 3) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_poster_queue';
	$lock = 'p69_poster_worker_lock';
	$token = time() . ':' . wp_generate_password(16, false, false);
	if (!add_option($lock, $token, '', false)) return 0;
	$done = 0;
	try {
		$now = current_time('mysql', true);
		$stale = gmdate('Y-m-d H:i:s', time() - 600);
		$wpdb->query($wpdb->prepare("UPDATE {$table} SET status='queued', updated_at=%s WHERE status='processing' AND updated_at < %s", $now, $stale));
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'queued' AND updated_at <= %s ORDER BY id ASC LIMIT %d",
			$now, max(1, min(5, absint($limit)))
		), ARRAY_A);
		foreach ((array) $rows as $row) {
			$post_id = absint($row['post_id']);
			$wpdb->update($table, array('status'=>'processing','updated_at'=>$now), array('id'=>absint($row['id'])));
			if (!$post_id || !get_post($post_id) || has_post_thumbnail($post_id)) {
				$wpdb->update($table, array('status'=>'completed','updated_at'=>$now), array('id'=>absint($row['id'])));
				if ($post_id) update_post_meta($post_id, '_p69_poster_status', 'downloaded');
				continue;
			}
			$result = pirate69_import_poster($post_id, $row['poster_url']);
			if (!is_wp_error($result)) {
				$wpdb->update($table, array('status'=>'completed','last_error'=>'','updated_at'=>current_time('mysql',true)), array('id'=>absint($row['id'])));
				update_post_meta($post_id, '_p69_poster_status', 'downloaded');
				delete_post_meta($post_id, '_p69_poster_last_error');
				$done++;
				continue;
			}
			$attempts = absint($row['attempts']) + 1;
			$status = $attempts >= max(1, min(10, absint(pirate69_source_config()['settings']['poster_retry_limit'] ?? 3))) ? 'failed' : 'queued';
			$error = sanitize_text_field($result->get_error_message());
			$wpdb->update($table, array(
				'status'=>$status, 'attempts'=>$attempts, 'last_error'=>$error,
				'updated_at'=>gmdate('Y-m-d H:i:s', time() + (15 * $attempts)),
			), array('id'=>absint($row['id'])));
			update_post_meta($post_id, '_p69_poster_status', 'failed' === $status ? 'failed' : 'queued');
			update_post_meta($post_id, '_p69_poster_last_error', $error);
			if ('failed' === $status) pirate69_log(0, 'warning', 'Poster queue item failed after retries.', array('post_id'=>$post_id,'error'=>$error));
		}
		$remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'queued'");
		if ($remaining && !wp_next_scheduled('pirate69_process_poster_queue')) {
			wp_schedule_single_event(time() + 15, 'pirate69_process_poster_queue');
		}
	} finally {
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$lock, maybe_serialize($token)
		));
	}
	return $done;
}
add_action('pirate69_process_poster_queue', 'pirate69_process_poster_queue');

/** Retry failed posters by queueing them; never download inside the admin request. */
function pirate69_retry_failed_posters($limit = 20) {
	global $wpdb;
	$table = $wpdb->prefix . 'p69_poster_queue';
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT post_id, poster_url FROM {$table} WHERE status = 'failed' ORDER BY updated_at ASC LIMIT %d",
		max(1, min(100, absint($limit)))
	), ARRAY_A);
	$queued = 0; $seen = array();
	foreach ((array) $rows as $row) {
		$post_id = absint($row['post_id']);
		if (!$post_id || has_post_thumbnail($post_id)) continue;
		$wpdb->update($table, array('status'=>'queued','attempts'=>0,'last_error'=>'','updated_at'=>current_time('mysql',true)), array('post_id'=>$post_id));
		update_post_meta($post_id, '_p69_poster_status', 'queued');
		$seen[$post_id] = true; $queued++;
	}
	// Include legacy failures created before the asynchronous queue existed.
	$legacy = new WP_Query(array('post_type'=>array('p69_movie','p69_tv_show'),'post_status'=>'any','posts_per_page'=>max(1,min(100,absint($limit))),'fields'=>'ids','meta_query'=>array(array('key'=>'_p69_poster_status','value'=>'failed')),'no_found_rows'=>true));
	foreach ((array) $legacy->posts as $post_id) {
		$post_id = absint($post_id);
		if (isset($seen[$post_id]) || has_post_thumbnail($post_id)) continue;
		$url = get_post_meta($post_id, '_p69_original_poster_url', true);
		if ($url && pirate69_queue_poster($post_id, $url)) { $seen[$post_id] = true; $queued++; }
	}
	if ($queued && !wp_next_scheduled('pirate69_process_poster_queue')) {
		wp_schedule_single_event(time() + 5, 'pirate69_process_poster_queue');
	}
	return $queued;
}

function pirate69_poster_admin_action() {
	if (!current_user_can('manage_options')) wp_die('Not allowed.');
	check_admin_referer('pirate69_poster_retry');
	$count = pirate69_retry_failed_posters(30);
	wp_safe_redirect(add_query_arg(array('page'=>'pirate69-importer','p69_poster_retried'=>$count),admin_url('admin.php')));
	exit;
}
add_action('admin_post_pirate69_poster_retry','pirate69_poster_admin_action');
