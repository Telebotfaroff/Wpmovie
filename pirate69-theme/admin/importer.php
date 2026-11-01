<?php
defined('ABSPATH') || exit;

function pirate69_register_importer_menu() {
	add_menu_page('Pirate69 Importer','Pirate69 Importer','manage_options','pirate69-importer','pirate69_render_importer','dashicons-database-import',26);
}
add_action('admin_menu','pirate69_register_importer_menu');

function pirate69_importer_action() {
	if (!current_user_can('manage_options')) wp_die('Not allowed.');
	check_admin_referer('pirate69_importer_action');
	$action = sanitize_key($_POST['p69_action'] ?? '');
	if ($action === 'start') {
		$category = sanitize_key($_POST['category'] ?? '');
		$result = pirate69_create_import_job($category);
		$notice = is_wp_error($result) ? $result->get_error_message() : 'Import job #' . absint($result) . ' queued.';
	} else {
		$job_id = absint($_POST['job_id'] ?? 0);
		global $wpdb; $table = $wpdb->prefix . 'p69_import_jobs';
		$job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$job_id),ARRAY_A);
		if ($job && in_array($action,array('pause','resume','retry'),true)) {
			if ($action === 'pause') $wpdb->update($table,array('status'=>'paused'),array('id'=>$job_id));
			if ($action === 'resume') { $wpdb->update($table,array('status'=>'pending'),array('id'=>$job_id)); pirate69_schedule_job($job_id,5); }
			if ($action === 'retry') { $wpdb->update($table,array('status'=>'pending'),array('id'=>$job_id)); pirate69_schedule_job($job_id,5); }
			$notice = 'Job updated.';
		} else $notice = 'Job not found.';
	}
	set_transient('pirate69_importer_notice', $notice, 30);
	wp_safe_redirect(admin_url('admin.php?page=pirate69-importer')); exit;
}
add_action('admin_post_pirate69_importer_action','pirate69_importer_action');

function pirate69_render_importer() {
	if (!current_user_can('manage_options')) return;
	global $wpdb;
	$jobs_table = $wpdb->prefix . 'p69_import_jobs';
	$logs_table = $wpdb->prefix . 'p69_import_logs';
	$jobs = $wpdb->get_results("SELECT * FROM {$jobs_table} ORDER BY id DESC LIMIT 12",ARRAY_A);
	$logs = $wpdb->get_results("SELECT * FROM {$logs_table} ORDER BY id DESC LIMIT 20",ARRAY_A);
	$notice = get_transient('pirate69_importer_notice');
	if ($notice) delete_transient('pirate69_importer_notice');
	?>
	<div class="wrap p69-admin"><h1>Pirate69 Importer</h1>
	<p>Sync the public JSON catalog. Jobs run in small resumable batches through WP-Cron; frontend requests never fetch source JSON.</p>
	<?php if($notice): ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
	<div class="p69-admin-panel"><h2>Start a sync</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('pirate69_importer_action'); ?><input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="start"><label for="p69-category">Catalog</label> <select id="p69-category" name="category"><?php foreach(pirate69_source_categories() as $key=>$category): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($category['label']); ?></option><?php endforeach; ?></select> <button class="button button-primary">Queue incremental sync</button></form></div>
	<div class="p69-admin-panel"><h2>Recent jobs</h2><table class="widefat striped"><thead><tr><th>ID</th><th>Catalog</th><th>Status</th><th>Progress</th><th>Errors</th><th>Actions</th></tr></thead><tbody>
	<?php if($jobs): foreach($jobs as $job): $total=(int)$job['total_items']; $done=(int)$job['processed_items']; ?><tr><td>#<?php echo absint($job['id']); ?></td><td><?php echo esc_html($job['category']); ?></td><td><strong><?php echo esc_html($job['status']); ?></strong></td><td><?php echo esc_html($done . ' / ' . ($total ?: '…')); ?></td><td><?php echo absint($job['error_count']); ?></td><td><?php foreach(array('pause'=>'Pause','resume'=>'Resume','retry'=>'Retry') as $key=>$label): if(($key==='pause' && $job['status']!=='running' && $job['status']!=='pending') || ($key==='resume' && $job['status']!=='paused') || ($key==='retry' && $job['status']!=='failed')) continue; ?><form class="p69-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('pirate69_importer_action'); ?><input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="<?php echo esc_attr($key); ?>"><input type="hidden" name="job_id" value="<?php echo absint($job['id']); ?>"><button class="button button-small"><?php echo esc_html($label); ?></button></form><?php endforeach; ?></td></tr><?php endforeach; else: ?><tr><td colspan="6">No import jobs yet.</td></tr><?php endif; ?></tbody></table></div>
	<div class="p69-admin-panel"><h2>Recent activity</h2><ul><?php if($logs): foreach($logs as $log): ?><li><code><?php echo esc_html($log['created_at']); ?></code> <strong><?php echo esc_html(strtoupper($log['level'])); ?></strong> <?php echo esc_html($log['message']); ?></li><?php endforeach; else: ?><li>No logs yet.</li><?php endif; ?></ul></div>
	</div>
	<?php
}
