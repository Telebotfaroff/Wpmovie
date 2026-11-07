<?php
defined('ABSPATH') || exit;

/**
 * Lightweight download-link reports stored in wp_options to avoid a schema migration.
 * Raw IP addresses are never stored.
 */
function p69_handle_download_link_report() {
	$link_id = absint($_POST['link_id'] ?? 0);
	$post_id = absint($_POST['post_id'] ?? 0);
	if (!$link_id || !$post_id) wp_die(esc_html__('Invalid download link report.', 'pirate69'), '', array('response' => 400));
	check_admin_referer('p69_report_link_' . $link_id);

	global $wpdb;
	$table = $wpdb->prefix . 'p69_download_links';
	$link = $wpdb->get_row($wpdb->prepare("SELECT id, post_id, is_enabled FROM {$table} WHERE id=%d", $link_id), ARRAY_A);
	if (!$link || (int) $link['post_id'] !== $post_id || !(int) $link['is_enabled'] || 'publish' !== get_post_status($post_id)) {
		wp_die(esc_html__('This download link is no longer available.', 'pirate69'), '', array('response' => 404));
	}

	$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
	$ip_hash = hash_hmac('sha256', $ip, wp_salt('auth'));
	$reports = get_option('p69_download_link_reports', array());
	$reports = is_array($reports) ? $reports : array();
	$cutoff = time() - DAY_IN_SECONDS;
	foreach ($reports as $key => $report) {
		if (!is_array($report) || empty($report['created_ts']) || (int) $report['created_ts'] < $cutoff) unset($reports[$key]);
		if (is_array($report) && (int) ($report['link_id'] ?? 0) === $link_id && hash_equals((string) ($report['ip_hash'] ?? ''), $ip_hash)) {
			$redirect = wp_get_referer() ?: get_permalink($post_id);
			wp_safe_redirect(add_query_arg('p69_report', 'duplicate', $redirect) . '#p69-downloads');
			exit;
		}
	}

	$key = wp_generate_uuid4();
	$reports[$key] = array(
		'key' => $key,
		'link_id' => $link_id,
		'post_id' => $post_id,
		'created' => current_time('mysql'),
		'created_ts' => time(),
		'ip_hash' => $ip_hash,
		'user_id' => get_current_user_id(),
		'status' => 'open',
	);
	if (count($reports) > 500) $reports = array_slice($reports, -500, null, true);
	update_option('p69_download_link_reports', $reports, false);

	$redirect = wp_get_referer() ?: get_permalink($post_id);
	wp_safe_redirect(add_query_arg('p69_report', 'received', $redirect) . '#p69-downloads');
	exit;
}
add_action('admin_post_p69_report_download_link', 'p69_handle_download_link_report');
add_action('admin_post_nopriv_p69_report_download_link', 'p69_handle_download_link_report');

function p69_download_reports_menu() {
	add_submenu_page('pirate69-importer', 'Download Link Reports', 'Link Reports', 'manage_options', 'p69-download-link-reports', 'p69_render_download_link_reports');
}
add_action('admin_menu', 'p69_download_reports_menu', 30);

function p69_render_download_link_reports() {
	if (!current_user_can('manage_options')) return;
	$reports = get_option('p69_download_link_reports', array());
	$reports = is_array($reports) ? $reports : array();
	$reports = array_reverse($reports, true);
	?>
	<div class="wrap">
		<h1>Download Link Reports</h1>
		<p>Reports submitted by visitors for enabled download links. Duplicate reports from the same network for the same link are limited to one per 24 hours.</p>
		<table class="widefat striped">
			<thead><tr><th>Reported</th><th>Title</th><th>Link</th><th>Report status</th><th>Actions</th></tr></thead>
			<tbody>
			<?php if ($reports) : foreach ($reports as $key => $report) :
				$post_id = absint($report['post_id'] ?? 0);
				$link_id = absint($report['link_id'] ?? 0);
				$status = sanitize_key($report['status'] ?? 'open');
				?>
				<tr>
					<td><?php echo esc_html($report['created'] ?? ''); ?></td>
					<td><?php if ($post_id && get_post($post_id)) : ?><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a><?php else : ?>Deleted post #<?php echo $post_id; ?><?php endif; ?></td>
					<td>#<?php echo $link_id; ?></td>
					<td><strong><?php echo esc_html(ucfirst($status)); ?></strong></td>
					<td>
						<?php if ($status !== 'resolved') : ?>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
								<input type="hidden" name="action" value="p69_update_download_report"><input type="hidden" name="report_key" value="<?php echo esc_attr($key); ?>"><input type="hidden" name="report_status" value="resolved">
								<?php wp_nonce_field('p69_update_download_report_' . $key); ?><button class="button button-small">Mark resolved</button>
							</form>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
								<input type="hidden" name="action" value="p69_update_download_report"><input type="hidden" name="report_key" value="<?php echo esc_attr($key); ?>"><input type="hidden" name="report_status" value="open">
								<?php wp_nonce_field('p69_update_download_report_' . $key); ?><button class="button button-small">Reopen</button>
							</form>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; else : ?><tr><td colspan="5">No broken-link reports yet.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function p69_update_download_report() {
	if (!current_user_can('manage_options')) wp_die('Not allowed.');
	$key = sanitize_text_field(wp_unslash($_POST['report_key'] ?? ''));
	check_admin_referer('p69_update_download_report_' . $key);
	$status = sanitize_key($_POST['report_status'] ?? 'open');
	if (!in_array($status, array('open', 'resolved'), true)) $status = 'open';
	$reports = get_option('p69_download_link_reports', array());
	if (is_array($reports) && isset($reports[$key])) {
		$reports[$key]['status'] = $status;
		update_option('p69_download_link_reports', $reports, false);
	}
	wp_safe_redirect(admin_url('admin.php?page=p69-download-link-reports'));
	exit;
}
add_action('admin_post_p69_update_download_report', 'p69_update_download_report');
