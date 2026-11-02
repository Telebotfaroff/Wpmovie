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
		$job_type = sanitize_key($_POST['job_type'] ?? 'sync');
		$target = sanitize_text_field(wp_unslash($_POST['target'] ?? ''));
		$result = pirate69_create_import_job($category, $job_type, $target);
		$notice = is_wp_error($result) ? $result->get_error_message() : 'Import job #' . absint($result) . ' queued.';
	} else {
		$job_id = absint($_POST['job_id'] ?? 0);
		global $wpdb; $table = $wpdb->prefix . 'p69_import_jobs';
		$job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$job_id),ARRAY_A);
		if ($job && in_array($action,array('pause','resume','retry','cancel'),true)) {
			if ($action === 'pause') $wpdb->update($table,array('status'=>'paused'),array('id'=>$job_id));
			if ($action === 'resume') { $wpdb->update($table,array('status'=>'pending'),array('id'=>$job_id)); pirate69_schedule_job($job_id,5); }
			if ($action === 'retry') { $wpdb->update($table,array('status'=>'pending'),array('id'=>$job_id)); pirate69_schedule_job($job_id,5); }
			if ($action === 'cancel') { $wpdb->update($table,array('status'=>'cancelled'),array('id'=>$job_id)); }
			$notice = 'Job updated.';
		} else $notice = 'Job not found.';
	}
	set_transient('pirate69_importer_notice', $notice, 30);
	wp_safe_redirect(admin_url('admin.php?page=pirate69-importer')); exit;
}
add_action('admin_post_pirate69_importer_action','pirate69_importer_action');

function pirate69_ajax_import_worker() {
	if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Not allowed.'), 403);
	check_ajax_referer('pirate69_import_worker', 'nonce');
	$job_id = absint($_POST['job_id'] ?? 0);
	global $wpdb;
	$table = $wpdb->prefix . 'p69_import_jobs';
	$before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $job_id), ARRAY_A);
	if (!$before) wp_send_json_error(array('message' => 'Job not found.'), 404);
	if (in_array($before['status'], array('pending', 'running'), true)) pirate69_process_import_job($job_id);
	$job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $job_id), ARRAY_A);
	wp_send_json_success(array('id' => $job_id, 'status' => $job['status'], 'total' => (int) $job['total_items'], 'processed' => (int) $job['processed_items'], 'created' => (int) $job['created_count'], 'updated' => (int) $job['updated_count'], 'skipped' => (int) $job['skipped_count'], 'errors' => (int) $job['error_count']));
}
add_action('wp_ajax_pirate69_import_worker', 'pirate69_ajax_import_worker');



function pirate69_render_job_action($category, $job_type, $target, $label) {
	?>
	<form class="p69-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
		<?php wp_nonce_field('pirate69_importer_action'); ?>
		<input type="hidden" name="action" value="pirate69_importer_action">
		<input type="hidden" name="p69_action" value="start">
		<input type="hidden" name="category" value="<?php echo esc_attr($category); ?>">
		<input type="hidden" name="job_type" value="<?php echo esc_attr($job_type); ?>">
		<input type="hidden" name="target" value="<?php echo esc_attr($target); ?>">
		<button class="button button-small"><?php echo esc_html($label); ?></button>
	</form>
	<?php
}

function pirate69_render_importer() {
	if (!current_user_can('manage_options')) return;
	global $wpdb;
	$jobs_table = $wpdb->prefix . 'p69_import_jobs';
	$logs_table = $wpdb->prefix . 'p69_import_logs';
	$jobs = $wpdb->get_results("SELECT * FROM {$jobs_table} ORDER BY id DESC LIMIT 12",ARRAY_A);
	$logs = $wpdb->get_results("SELECT * FROM {$logs_table} ORDER BY id DESC LIMIT 20",ARRAY_A);
	$notice = get_transient('pirate69_importer_notice');
	if ($notice) delete_transient('pirate69_importer_notice');
	$categories = pirate69_source_categories();
	$search_category = sanitize_key($_GET['p69_category'] ?? 'hollywood');
	if (!isset($categories[$search_category])) $search_category = 'hollywood';
	$search_query = sanitize_text_field(wp_unslash($_GET['p69_search'] ?? ''));
	$search_results = null;
	$search_error = '';
	if ('' !== $search_query) {
		$search_results = pirate69_search_catalog($search_category, $search_query, 50);
		if (is_wp_error($search_results)) { $search_error = $search_results->get_error_message(); $search_results = array(); }
	}
	$has_active = false; $active_count = 0; $completed_count = 0; $failed_count = 0;
	foreach ((array)$jobs as $j) {
		if (in_array($j['status'],array('pending','running','paused'),true)) { $has_active = true; $active_count++; }
		if ($j['status'] === 'completed') $completed_count++;
		if ($j['status'] === 'failed') $failed_count++;
	}
	$total_imported = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key='_p69_source_id' WHERE p.post_type IN ('p69_movie','p69_tv_show') AND p.post_status NOT IN ('trash','auto-draft')");
	?>
	<div class="wrap p69-admin">
	<div class="p69-hero">
		<div class="p69-hero-copy"><span class="p69-eyebrow">PIRATE69 · CATALOG CONTROL CENTER</span><h1>Pirate69 <span>Movie Manager</span></h1>
		<p>Search, select and import exactly what you need. Your catalog stays in control, one movie or chunk at a time.</p></div>
		<div class="p69-hero-mark" aria-hidden="true">P69</div>
	</div>
	<div class="p69-stat-grid">
		<div class="p69-stat-card"><span class="p69-stat-label">Imported titles</span><strong><?php echo number_format_i18n($total_imported); ?></strong><small>Movies &amp; TV series</small></div>
		<div class="p69-stat-card"><span class="p69-stat-label">Active jobs</span><strong><?php echo number_format_i18n($active_count); ?></strong><small>Pending, running or paused</small></div>
		<div class="p69-stat-card"><span class="p69-stat-label">Completed jobs</span><strong><?php echo number_format_i18n($completed_count); ?></strong><small>Recent job history</small></div>
		<div class="p69-stat-card"><span class="p69-stat-label">Failed jobs</span><strong><?php echo number_format_i18n($failed_count); ?></strong><small>Need attention</small></div>
	</div>
	<?php if($notice): ?><div class="notice notice-info is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>

	<div class="p69-section-heading"><div><span class="p69-section-kicker">DISCOVER</span><h2>Find something to import</h2><p>Search the master index first. Detailed chunk data is fetched only after you choose an import.</p></div><span class="p69-step">01</span></div>
	<div class="p69-admin-panel p69-search-panel">
		<h3><span class="dashicons dashicons-search"></span> Source catalog search</h3>
		<form class="p69-search-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
			<input type="hidden" name="page" value="pirate69-importer">
			<label for="p69-search-category">Catalog</label>
			<select id="p69-search-category" name="p69_category"><?php foreach($categories as $key=>$cat): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($search_category,$key); ?>><?php echo esc_html($cat['label']); ?></option><?php endforeach; ?></select>
			<input type="search" name="p69_search" value="<?php echo esc_attr($search_query); ?>" placeholder="Movie title or source ID" class="regular-text" required>
			<button class="button button-primary">Search database</button>
		</form>
		<?php if ('' !== $search_query): ?>
			<?php if ($search_error): ?><div class="notice notice-error inline"><p><?php echo esc_html($search_error); ?></p></div>
			<?php elseif ($search_results): ?>
				<p><?php echo esc_html(count($search_results)); ?> matching results (up to 50 shown).</p>
				<table class="widefat striped"><thead><tr><th>Title</th><th>Year</th><th>Type</th><th>Chunk</th><th>Actions</th></tr></thead><tbody>
				<?php foreach($search_results as $item): ?><tr>
					<td><strong><?php echo esc_html($item['title']); ?></strong><br><code><?php echo esc_html($item['id']); ?></code></td>
					<td><?php echo esc_html($item['year']); ?></td>
					<td><?php echo !empty($item['isSeries']) ? 'Series' : 'Movie'; ?></td>
					<td>#<?php echo absint($item['chunk']); ?></td>
					<td><?php pirate69_render_job_action($search_category,'movie',$item['id'],'Import this movie'); ?><?php pirate69_render_job_action($search_category,'chunk',(string)$item['chunk'],'Import chunk #'.$item['chunk']); ?></td>
				</tr><?php endforeach; ?>
				</tbody></table>
			<?php else: ?><p>No matches found in this catalog index.</p><?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="p69-section-heading"><div><span class="p69-section-kicker">TARGETED IMPORT</span><h2>Choose your import method</h2><p>Bring in a single movie or a complete source chunk. No need to sync the entire database.</p></div><span class="p69-step">02</span></div>
	<div class="p69-tool-grid"><div class="p69-admin-panel p69-tool-card"><div class="p69-tool-icon"><span class="dashicons dashicons-screenoptions"></span></div><h3>Import by chunk</h3><p>Import every indexed record belonging to one chunk number.</p>
		<form class="p69-chunk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('pirate69_importer_action'); ?>
			<input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="start"><input type="hidden" name="job_type" value="chunk">
			<label for="p69-chunk-category">Catalog</label>
			<select id="p69-chunk-category" name="category"><?php foreach($categories as $key=>$cat): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($cat['label']); ?></option><?php endforeach; ?></select>
			<label for="p69-chunk-number">Chunk number</label>
			<input id="p69-chunk-number" type="number" name="target" min="1" step="1" required placeholder="e.g. 12">
			<button class="button button-primary">Import this chunk only</button>
		</form>
		<p class="description">Only the selected chunk is processed. All other chunks remain untouched.</p>
	</div>

	<div class="p69-admin-panel p69-tool-card p69-bulk-card"><div class="p69-tool-icon"><span class="dashicons dashicons-database"></span></div><h3>Quick feeds &amp; full sync</h3><p>Use homepage feeds for latest additions, or preview the full catalog before a large sync.</p>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<?php wp_nonce_field('pirate69_importer_action'); ?><input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="start">
			<label for="p69-bulk-category">Catalog</label> <select id="p69-bulk-category" name="category"><?php foreach($categories as $key=>$cat): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($cat['label']); ?></option><?php endforeach; ?></select>
			<button class="button" name="job_type" value="homepage">Import homepage feed</button>
			<button class="button" name="job_type" value="preview">Preview full catalog</button>
			<button class="button" name="job_type" value="sync" onclick="return confirm('This queues every chunk in the selected catalog. Continue?')">Queue full catalog sync</button>
		</form>
	</div></div>

	<div class="p69-section-heading"><div><span class="p69-section-kicker">MONITOR</span><h2>Import activity</h2><p>Track progress, inspect results and control running jobs.</p></div><span class="p69-live-indicator">● LIVE</span></div>
	<div class="p69-admin-panel p69-jobs-panel"><div class="p69-panel-heading"><h3>Recent jobs</h3><span>Last 12 jobs</span></div><div class="p69-table-wrap"><table class="widefat striped"><thead><tr><th>ID</th><th>Catalog / target</th><th>Status</th><th>Progress</th><th>Results</th><th>Errors</th><th>Actions</th></tr></thead><tbody>
	<?php if($jobs): foreach($jobs as $job):
		$total=(int)$job['total_items'];$done=(int)$job['processed_items'];$pct=$total?min(100,(int)floor(($done/$total)*100)):0;
		$state=json_decode($job['payload']??'',true);$state=is_array($state)?$state:array();$chunks=$state['chunks']??array();$chunk_pos=absint($state['chunk_pos']??0);$current_chunk=isset($chunks[$chunk_pos])?absint($chunks[$chunk_pos]):0;
		$target_label=in_array($job['job_type'],array('chunk','movie'),true)?' · '.$job['job_type'].' '.($state['target']??''):'';
		?>
		<tr><td>#<?php echo absint($job['id']); ?></td><td><?php echo esc_html($job['category'].$target_label); ?></td><td><strong><?php echo esc_html($job['status']); ?></strong><br><small><?php echo esc_html($state['phase']??'queued'); ?></small></td>
		<td><progress max="100" value="<?php echo esc_attr($pct); ?>" style="width:120px"></progress> <?php echo esc_html($done.' / '.($total?:'…').' ('.$pct.'%)'); ?><?php if($current_chunk): ?><br><small>Current chunk #<?php echo $current_chunk; ?> · chunk <?php echo ($chunk_pos+1); ?> of <?php echo count($chunks); ?></small><?php endif; ?></td>
		<td>+<?php echo absint($job['created_count']??0); ?> / ~<?php echo absint($job['updated_count']??0); ?> / =<?php echo absint($job['skipped_count']??0); ?></td><td><?php echo absint($job['error_count']); ?></td><td>
		<?php foreach(array('pause'=>'Pause','resume'=>'Resume','retry'=>'Retry','cancel'=>'Cancel') as $key=>$label): if(($key==='pause' && !in_array($job['status'],array('running','pending'),true)) || ($key==='resume' && $job['status']!=='paused') || ($key==='retry' && $job['status']!=='failed') || ($key==='cancel' && !in_array($job['status'],array('pending','running','paused'),true))) continue; ?>
		<form class="p69-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('pirate69_importer_action'); ?><input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="<?php echo esc_attr($key); ?>"><input type="hidden" name="job_id" value="<?php echo absint($job['id']); ?>"><button class="button button-small"><?php echo esc_html($label); ?></button></form>
		<?php endforeach; ?></td></tr>
	<?php endforeach; else: ?><tr><td colspan="7">No import jobs yet.</td></tr><?php endif; ?></tbody></table></div></div>

	<div class="p69-tool-grid p69-bottom-grid"><div class="p69-admin-panel"><h3><span class="dashicons dashicons-format-image"></span> Poster manager</h3><p>Retry failed poster downloads without replacing existing featured images.</p><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('pirate69_poster_retry'); ?><input type="hidden" name="action" value="pirate69_poster_retry"><button class="button">Retry failed posters (up to 30)</button></form></div>
	<div class="p69-admin-panel p69-activity-panel"><h3><span class="dashicons dashicons-list-view"></span> Recent activity</h3><ul><?php if($logs): foreach($logs as $log): ?><li><code><?php echo esc_html($log['created_at']); ?></code> <strong><?php echo esc_html(strtoupper($log['level'])); ?></strong> <?php echo esc_html($log['message']); ?></li><?php endforeach; else: ?><li>No logs yet.</li><?php endif; ?></ul></div></div>
	</div>
	<?php if($has_active): ?><script>setTimeout(function(){window.location.reload();},8000);</script><?php endif; ?>
	<?php
}
