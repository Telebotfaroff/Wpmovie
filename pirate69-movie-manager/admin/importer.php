<?php
defined('ABSPATH') || exit;

/**
 * Pirate69 Import Center
 * Each workflow has its own admin screen: overview, repository, jobs, history and data quality.
 */
function pirate69_register_importer_menu() {
    add_menu_page('Pirate69 Import Center', 'Pirate69 Importer', 'manage_options', 'pirate69-importer', 'pirate69_render_importer_overview', 'dashicons-database-import', 26);
    add_submenu_page('pirate69-importer', 'Overview', 'Overview', 'manage_options', 'pirate69-importer', 'pirate69_render_importer_overview');
    add_submenu_page('pirate69-importer', 'Repository Browser', 'Repository Browser', 'manage_options', 'pirate69-repository-browser', 'pirate69_render_repository_browser');
    add_submenu_page('pirate69-importer', 'Import Jobs', 'Import Jobs', 'manage_options', 'pirate69-import-jobs', 'pirate69_render_import_jobs');
    add_submenu_page('pirate69-importer', 'Import History', 'Import History', 'manage_options', 'pirate69-import-history', 'pirate69_render_import_history');
    add_submenu_page('pirate69-importer', 'Missing Data', 'Missing Data', 'manage_options', 'pirate69-import-missing', 'pirate69_render_missing_data');
}
add_action('admin_menu', 'pirate69_register_importer_menu');

function pirate69_importer_enqueue($hook) {
    if (false === strpos((string) $hook, 'pirate69')) return;
    wp_enqueue_style('pirate69-admin', plugin_dir_url(P69MM_FILE) . 'assets/admin.css', array(), P69MM_VERSION);
    wp_enqueue_script('pirate69-admin', plugin_dir_url(P69MM_FILE) . 'assets/admin.js', array(), P69MM_VERSION, true);
    wp_localize_script('pirate69-admin', 'P69Importer', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'workerNonce' => wp_create_nonce('pirate69_import_worker'),
        'posterNonce' => wp_create_nonce('pirate69_poster_worker'),
        'strings' => array('working' => 'Updating import status…', 'failed' => 'Unable to refresh job status.')
    ));
}
add_action('admin_enqueue_scripts', 'pirate69_importer_enqueue');

function pirate69_importer_url($page, $args = array()) {
    return add_query_arg(array_merge(array('page' => $page), $args), admin_url('admin.php'));
}
function pirate69_importer_header($title, $subtitle, $active = 'overview') {
    $nav = array(
        'overview' => array('Overview', 'pirate69-importer'),
        'repository' => array('Repository Browser', 'pirate69-repository-browser'),
        'jobs' => array('Import Jobs', 'pirate69-import-jobs'),
        'history' => array('Import History', 'pirate69-import-history'),
        'missing' => array('Missing Data', 'pirate69-import-missing'),
    );
    ?>
    <div class="wrap p69-admin">
      <div class="p69-hero">
        <div class="p69-hero-copy"><span class="p69-eyebrow">PIRATE69 · CATALOG CONTROL CENTER</span><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($subtitle); ?></p></div>
        <div class="p69-hero-mark" aria-hidden="true">P69</div>
      </div>
      <nav class="p69-nav" aria-label="Importer sections">
      <?php foreach ($nav as $key => $item): ?>
        <a class="p69-nav-link <?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url(pirate69_importer_url($item[1])); ?>"><?php echo esc_html($item[0]); ?></a>
      <?php endforeach; ?>
      </nav>
      <?php $notice = get_transient('pirate69_importer_notice'); if ($notice) { delete_transient('pirate69_importer_notice'); ?>
        <div class="notice notice-info is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
      <?php } ?>
    <?php
}
function pirate69_importer_footer() { echo '</div>'; }

function pirate69_importer_db_stats() {
    global $wpdb;
    $jobs = $wpdb->prefix . 'p69_import_jobs';
    $map = $wpdb->prefix . 'p69_source_map';
    $stats = array('imported' => 0, 'active' => 0, 'failed' => 0, 'completed' => 0, 'recent' => 0);
    $stats['imported'] = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$map}");
    $stats['active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$jobs} WHERE status IN ('pending','running','paused')");
    $stats['failed'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$jobs} WHERE status='failed'");
    $stats['completed'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$jobs} WHERE status='completed'");
    $stats['recent'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$jobs} WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)");
    return $stats;
}

/** Fetch all lightweight indexes once per request and calculate repository coverage/quality. */
function pirate69_repository_stats($force = false) {
    $cached = $force ? false : get_transient('p69_repository_stats');
    if (is_array($cached)) return $cached;
    global $wpdb;
    $map_table = $wpdb->prefix . 'p69_source_map';
    $stats = array('total' => 0, 'imported' => 0, 'missing' => 0, 'incomplete' => 0, 'categories' => array(), 'errors' => array());
    foreach (pirate69_source_categories() as $key => $category) {
        $index = pirate69_fetch_json($category['index']);
        if (is_wp_error($index)) {
            $stats['errors'][$key] = $index->get_error_message();
            $stats['categories'][$key] = array('label' => $category['label'], 'total' => 0, 'imported' => 0, 'missing' => 0, 'incomplete' => 0);
            continue;
        }
        $rows = is_array($index) ? $index : array();
        $ids = array();
        $incomplete = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) { $incomplete++; continue; }
            $id = sanitize_text_field($row['id'] ?? '');
            if ($id !== '') $ids[$id] = true;
            if ($id === '' || trim((string)($row['title'] ?? '')) === '' || empty($row['poster']) || empty($row['chunk'])) $incomplete++;
        }
        $total = count($ids);
        $imported = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$map_table} WHERE category=%s", $key));
        $imported = min($total, $imported);
        $missing = max(0, $total - $imported);
        $stats['categories'][$key] = array('label' => $category['label'], 'total' => $total, 'imported' => $imported, 'missing' => $missing, 'incomplete' => $incomplete);
        $stats['total'] += $total;
        $stats['imported'] += $imported;
        $stats['missing'] += $missing;
        $stats['incomplete'] += $incomplete;
    }
    set_transient('p69_repository_stats', $stats, 5 * MINUTE_IN_SECONDS);
    return $stats;
}

function pirate69_importer_start_form($category, $type, $target, $label, $class = 'button button-primary') {
    ?>
    <form class="p69-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('pirate69_importer_action'); ?>
      <input type="hidden" name="action" value="pirate69_importer_action">
      <input type="hidden" name="p69_action" value="start">
      <input type="hidden" name="category" value="<?php echo esc_attr($category); ?>">
      <input type="hidden" name="job_type" value="<?php echo esc_attr($type); ?>">
      <input type="hidden" name="target" value="<?php echo esc_attr($target); ?>">
      <button class="<?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></button>
    </form>
    <?php
}
function pirate69_importer_action() {
    if (!current_user_can('manage_options')) wp_die('Not allowed.');
    check_admin_referer('pirate69_importer_action');
    $action = sanitize_key($_POST['p69_action'] ?? '');
    $redirect = sanitize_key($_POST['return_page'] ?? 'pirate69-import-jobs');
    $allowed_pages = array('pirate69-importer','pirate69-repository-browser','pirate69-import-jobs','pirate69-import-history','pirate69-import-missing');
    if (!in_array($redirect, $allowed_pages, true)) $redirect = 'pirate69-import-jobs';
    if ($action === 'start') {
        $category = sanitize_key($_POST['category'] ?? '');
        $type = sanitize_key($_POST['job_type'] ?? 'sync');
        $target = sanitize_text_field(wp_unslash($_POST['target'] ?? ''));
        $result = pirate69_create_import_job($category, $type, $target);
        $notice = is_wp_error($result) ? $result->get_error_message() : 'Import job #' . absint($result) . ' queued.';
        delete_transient('p69_repository_stats');
    } elseif ($action === 'clear_logs') {
        global $wpdb; $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}p69_import_logs"); $notice = 'Import activity cleared.';
    } elseif ($action === 'clear_completed') {
        global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}p69_import_jobs WHERE status IN ('completed','failed','cancelled')"); $notice = 'Finished jobs cleared. Active jobs were kept.';
    } else {
        $job_id = absint($_POST['job_id'] ?? 0);
        global $wpdb; $table = $wpdb->prefix . 'p69_import_jobs';
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $job_id), ARRAY_A);
        if (!$job || !in_array($action, array('pause','resume','retry','cancel'), true)) $notice = 'Job not found.';
        else {
            if ($action === 'pause') $wpdb->update($table, array('status'=>'paused','updated_at'=>current_time('mysql', true)), array('id'=>$job_id));
            if ($action === 'resume' || $action === 'retry') {
                if ($action === 'retry') { $state = json_decode($job['payload'] ?? '', true); if (!is_array($state)) $state = array(); $state['retries'] = 0; $wpdb->update($table, array('payload'=>wp_json_encode($state)), array('id'=>$job_id)); }
                $wpdb->update($table, array('status'=>'pending','updated_at'=>current_time('mysql', true)), array('id'=>$job_id));
                pirate69_schedule_job($job_id, 5);
            }
            if ($action === 'cancel') $wpdb->update($table, array('status'=>'cancelled','updated_at'=>current_time('mysql', true)), array('id'=>$job_id));
            $notice = 'Job #' . $job_id . ' updated.';
        }
    }
    set_transient('pirate69_importer_notice', $notice, 30);
    wp_safe_redirect(pirate69_importer_url($redirect)); exit;
}
add_action('admin_post_pirate69_importer_action', 'pirate69_importer_action');

function pirate69_ajax_import_worker() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'Not allowed.'), 403);
    check_ajax_referer('pirate69_import_worker', 'nonce');
    $job_id = absint($_POST['job_id'] ?? 0);
    global $wpdb; $table = $wpdb->prefix . 'p69_import_jobs';
    $before = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $job_id), ARRAY_A);
    if (!$before) wp_send_json_error(array('message'=>'Job not found.'), 404);
    if (in_array($before['status'], array('pending','running'), true)) pirate69_process_import_job($job_id);
    $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $job_id), ARRAY_A);
    wp_send_json_success(array('id'=>$job_id,'status'=>$job['status'],'total'=>(int)$job['total_items'],'processed'=>(int)$job['processed_items'],'created'=>(int)$job['created_count'],'updated'=>(int)$job['updated_count'],'skipped'=>(int)$job['skipped_count'],'errors'=>(int)$job['error_count']));
}
add_action('wp_ajax_pirate69_import_worker', 'pirate69_ajax_import_worker');

function pirate69_ajax_poster_worker() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message'=>'Not allowed.'), 403);
    check_ajax_referer('pirate69_poster_worker', 'nonce');
    pirate69_process_poster_queue(1);
    global $wpdb; $table = $wpdb->prefix . 'p69_poster_queue';
    $counts = $wpdb->get_results("SELECT status, COUNT(*) AS amount FROM {$table} GROUP BY status", ARRAY_A);
    $stats = array('queued'=>0,'processing'=>0,'completed'=>0,'failed'=>0);
    foreach ((array)$counts as $row) if (isset($stats[$row['status']])) $stats[$row['status']] = (int)$row['amount'];
    wp_send_json_success($stats);
}
add_action('wp_ajax_pirate69_poster_worker', 'pirate69_ajax_poster_worker');

function pirate69_render_importer_overview() {
    if (!current_user_can('manage_options')) return;
    $remote = pirate69_repository_stats();
    $local = pirate69_importer_db_stats();
    pirate69_importer_header('Importer Overview', 'A live view of the GitHub catalog and your website import coverage.', 'overview');
    ?>
    <div class="p69-stat-grid p69-stat-grid-five">
      <div class="p69-stat-card"><span class="p69-stat-label">Repository records</span><strong><?php echo number_format_i18n($remote['total']); ?></strong><small>Across all source catalogs</small></div>
      <div class="p69-stat-card"><span class="p69-stat-label">Imported on website</span><strong><?php echo number_format_i18n($local['imported']); ?></strong><small>Mapped source records</small></div>
      <div class="p69-stat-card"><span class="p69-stat-label">Not imported yet</span><strong><?php echo number_format_i18n($remote['missing']); ?></strong><small>Available in GitHub</small></div>
      <div class="p69-stat-card"><span class="p69-stat-label">Active processes</span><strong><?php echo number_format_i18n($local['active']); ?></strong><small>Pending, running or paused</small></div>
      <div class="p69-stat-card"><span class="p69-stat-label">Failed jobs</span><strong><?php echo number_format_i18n($local['failed']); ?></strong><small>Review and retry</small></div>
    </div>
    <div class="p69-dashboard-grid">
      <section class="p69-admin-panel"><div class="p69-panel-heading"><div><span class="p69-section-kicker">SOURCE BREAKDOWN</span><h2>Repository coverage</h2></div><a class="button" href="<?php echo esc_url(pirate69_importer_url('pirate69-repository-browser')); ?>">Browse repository</a></div>
      <?php foreach ($remote['categories'] as $key=>$cat): $percent=$cat['total']?min(100,round($cat['imported']/$cat['total']*100)):0; ?>
        <div class="p69-coverage-row"><div><strong><?php echo esc_html($cat['label']); ?></strong><span><?php echo number_format_i18n($cat['imported']); ?> / <?php echo number_format_i18n($cat['total']); ?> imported</span></div><div class="p69-coverage-track"><i style="width:<?php echo esc_attr($percent); ?>%"></i></div><b><?php echo esc_html($percent); ?>%</b></div>
        <?php if (isset($remote['errors'][$key])): ?><p class="p69-muted p69-error-text"><?php echo esc_html($remote['errors'][$key]); ?></p><?php endif; ?>
      <?php endforeach; ?>
      <p class="p69-footnote">Repository statistics are cached for five minutes. Use the refresh action to recalculate them.</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('p69_refresh_repo_stats'); ?><input type="hidden" name="action" value="p69_refresh_repo_stats"><button class="button">Refresh repository totals</button></form>
      </section>
      <section class="p69-admin-panel"><span class="p69-section-kicker">QUICK ACCESS</span><h2>Import operations</h2><p>Jump directly to the workflow you need. Each section has its own workspace.</p>
        <a class="p69-action-card" href="<?php echo esc_url(pirate69_importer_url('pirate69-repository-browser')); ?>"><span class="dashicons dashicons-search"></span><span><strong>Search repository</strong><small>Find titles and queue individual imports</small></span></a>
        <a class="p69-action-card" href="<?php echo esc_url(pirate69_importer_url('pirate69-import-jobs')); ?>"><span class="dashicons dashicons-controls-play"></span><span><strong>Monitor active jobs</strong><small>Progress, pause, resume and cancel</small></span></a>
        <a class="p69-action-card" href="<?php echo esc_url(pirate69_importer_url('pirate69-import-history')); ?>"><span class="dashicons dashicons-backup"></span><span><strong>Recent imports</strong><small>Completed, skipped and failed activity</small></span></a>
        <a class="p69-action-card" href="<?php echo esc_url(pirate69_importer_url('pirate69-import-missing')); ?>"><span class="dashicons dashicons-warning"></span><span><strong>Missing data</strong><small>Find missing imports and incomplete source rows</small></span></a>
      </section>
    </div>
    <?php if (!empty($remote['incomplete'])): ?><div class="p69-admin-panel"><h2>Source data quality</h2><p><strong><?php echo number_format_i18n($remote['incomplete']); ?></strong> repository records have one or more missing index fields (ID, title, poster or chunk). Review Missing Data for details.</p></div><?php endif; ?>
    <?php pirate69_importer_footer();
}

function pirate69_render_repository_browser() {
    if (!current_user_can('manage_options')) return;
    $categories=pirate69_source_categories();
    $category=sanitize_key($_GET['catalog']??'hollywood'); if(!isset($categories[$category]))$category='hollywood';
    $query=sanitize_text_field(wp_unslash($_GET['q']??''));
    $results=array();$error='';
    if($query!==''){ $results=pirate69_search_catalog($category,$query,100); if(is_wp_error($results)){$error=$results->get_error_message();$results=array();} }
    pirate69_importer_header('Repository Browser','Search the live GitHub index. Only selected records are fetched and imported.','repository');
    ?>
    <div class="p69-admin-panel p69-search-panel">
      <form class="p69-search-form" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="pirate69-repository-browser">
        <select name="catalog" aria-label="Catalog"><?php foreach($categories as $key=>$cat): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($category,$key); ?>><?php echo esc_html($cat['label']); ?></option><?php endforeach; ?></select>
        <input type="search" name="q" value="<?php echo esc_attr($query); ?>" placeholder="Search movie title or source ID…" required>
        <button class="button button-primary">Search GitHub</button>
      </form>
      <?php if($query!==''): ?>
        <?php if($error): ?><div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div>
        <?php elseif(!$results): ?><p class="p69-empty">No matching records found in <?php echo esc_html($categories[$category]['label']); ?>.</p>
        <?php else: ?><div class="p69-results-heading"><strong><?php echo number_format_i18n(count($results)); ?> results</strong><span>Showing up to 100 matches from the source index</span></div>
          <div class="p69-table-wrap"><table class="widefat striped"><thead><tr><th>Title / source ID</th><th>Year</th><th>Type</th><th>Chunk</th><th>Action</th></tr></thead><tbody>
          <?php foreach($results as $item): ?><tr><td><strong><?php echo esc_html($item['title']); ?></strong><code><?php echo esc_html($item['id']); ?></code></td><td><?php echo esc_html($item['year']?:'—'); ?></td><td><?php echo !empty($item['isSeries'])?'TV series':'Movie'; ?></td><td><?php echo $item['chunk']?'#'.absint($item['chunk']):'—'; ?></td><td><?php pirate69_importer_start_form($category,'movie',$item['id'],'Import record'); ?></td></tr><?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      <?php else: ?><div class="p69-empty-state"><span class="dashicons dashicons-search"></span><h2>Search your source catalog</h2><p>Search by movie title or source ID. Results come directly from the selected GitHub index.</p></div><?php endif; ?>
    </div>
    <?php pirate69_importer_footer();
}

function pirate69_render_import_jobs() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $table=$wpdb->prefix.'p69_import_jobs'; $categories=pirate69_source_categories();
    $status=sanitize_key($_GET['status']??'all');$category=sanitize_key($_GET['catalog']??'all');
    $where=array('1=1');$args=array();
    if(in_array($status,array('pending','running','paused','completed','failed','cancelled'),true)){$where[]='status=%s';$args[]=$status;}
    if(isset($categories[$category])){$where[]='category=%s';$args[]=$category;}
    $sql="SELECT * FROM {$table} WHERE ".implode(' AND ',$where)." ORDER BY (status IN ('pending','running','paused')) DESC, id DESC LIMIT 100";
    $jobs=$args?$wpdb->get_results($wpdb->prepare($sql,$args),ARRAY_A):$wpdb->get_results($sql,ARRAY_A);
    $active_ids=array();
    foreach($jobs as $job)if(in_array($job['status'],array('pending','running'),true))$active_ids[]=(int)$job['id'];
    pirate69_importer_header('Import Jobs','Monitor active processes and control each job independently.','jobs');
    ?>
    <div class="p69-jobs-toolbar"><form method="get"><input type="hidden" name="page" value="pirate69-import-jobs"><select name="status"><option value="all">All statuses</option><?php foreach(array('pending','running','paused','completed','failed','cancelled') as $s): ?><option value="<?php echo esc_attr($s); ?>" <?php selected($status,$s); ?>><?php echo esc_html(ucfirst($s)); ?></option><?php endforeach; ?></select><select name="catalog"><option value="all">All catalogs</option><?php foreach($categories as $key=>$cat): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($category,$key); ?>><?php echo esc_html($cat['label']); ?></option><?php endforeach; ?></select><button class="button">Filter jobs</button></form>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Clear completed, failed and cancelled jobs?');"><?php wp_nonce_field('pirate69_importer_action'); ?><input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="clear_completed"><input type="hidden" name="return_page" value="pirate69-import-jobs"><button class="button">Clear finished jobs</button></form></div>
    <div class="p69-admin-panel"><div class="p69-panel-heading"><h2>Job queue</h2><span><?php echo number_format_i18n(count($jobs)); ?> recent jobs</span></div><div class="p69-table-wrap"><table class="widefat striped"><thead><tr><th>Job</th><th>Catalog / target</th><th>Status</th><th>Progress</th><th>Created / updated / skipped</th><th>Errors</th><th>Controls</th></tr></thead><tbody>
    <?php if($jobs):foreach($jobs as $job):$total=(int)$job['total_items'];$done=(int)$job['processed_items'];$pct=$total?min(100,(int)floor($done/$total*100)):0;$state=json_decode($job['payload']??'',true);$state=is_array($state)?$state:array();$chunks=$state['chunks']??array();$pos=absint($state['chunk_pos']??0);$current=isset($chunks[$pos])?absint($chunks[$pos]):0; ?>
    <tr data-p69-job="<?php echo absint($job['id']); ?>"><td><strong>#<?php echo absint($job['id']); ?></strong><small><?php echo esc_html($job['created_at']); ?></small></td><td><?php echo esc_html(($categories[$job['category']]['label']??$job['category']).' · '.$job['job_type']); ?><?php if(!empty($state['target'])): ?><code><?php echo esc_html($state['target']); ?></code><?php endif; ?><?php if($current): ?><small>Chunk #<?php echo $current; ?> · <?php echo $pos+1; ?>/<?php echo count($chunks); ?></small><?php endif; ?></td><td><span class="p69-status p69-status-<?php echo esc_attr($job['status']); ?>"><?php echo esc_html(ucfirst($job['status'])); ?></span><small><?php echo esc_html($state['phase']??''); ?></small></td><td><progress max="100" value="<?php echo esc_attr($pct); ?>"></progress><span class="p69-progress-label"><?php echo number_format_i18n($done); ?> / <?php echo $total?number_format_i18n($total):'—'; ?> · <?php echo $pct; ?>%</span></td><td><?php echo absint($job['created_count']); ?> / <?php echo absint($job['updated_count']); ?> / <?php echo absint($job['skipped_count']); ?></td><td><?php echo absint($job['error_count']); ?></td><td>
    <?php foreach(array('pause'=>'Pause','resume'=>'Resume','retry'=>'Retry','cancel'=>'Cancel') as $action=>$label):if(($action==='pause'&&!in_array($job['status'],array('pending','running'),true))||($action==='resume'&&$job['status']!=='paused')||($action==='retry'&&$job['status']!=='failed')||($action==='cancel'&&!in_array($job['status'],array('pending','running','paused'),true)))continue; ?>
    <form class="p69-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('pirate69_importer_action'); ?><input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="<?php echo esc_attr($action); ?>"><input type="hidden" name="job_id" value="<?php echo absint($job['id']); ?>"><input type="hidden" name="return_page" value="pirate69-import-jobs"><button class="button button-small"><?php echo esc_html($label); ?></button></form><?php endforeach; ?></td></tr>
    <?php endforeach;else:?><tr><td colspan="7">No import jobs found.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php if($active_ids): ?><div class="p69-worker-status" data-p69-worker data-job-ids="<?php echo esc_attr(wp_json_encode($active_ids)); ?>">Live worker connected · processing queued jobs…</div><?php endif; ?>
    <?php pirate69_importer_footer();
}

function pirate69_render_import_history() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $jobs_table=$wpdb->prefix.'p69_import_jobs';$logs_table=$wpdb->prefix.'p69_import_logs';
    $level=sanitize_key($_GET['level']??'all');$search=sanitize_text_field(wp_unslash($_GET['q']??''));
    $where=array('1=1');$args=array();if(in_array($level,array('info','warning','error','success'),true)){$where[]='level=%s';$args[]=$level;}if($search!==''){$where[]='message LIKE %s';$args[]='%'.$wpdb->esc_like($search).'%';}
    $sql="SELECT * FROM {$logs_table} WHERE ".implode(' AND ',$where)." ORDER BY id DESC LIMIT 100";$logs=$args?$wpdb->get_results($wpdb->prepare($sql,$args),ARRAY_A):$wpdb->get_results($sql,ARRAY_A);
    $recent=$wpdb->get_results("SELECT * FROM {$jobs_table} ORDER BY id DESC LIMIT 20",ARRAY_A);
    pirate69_importer_header('Import History','Recent successful imports, skipped records, errors and job outcomes.','history');
    ?>
    <div class="p69-admin-panel"><div class="p69-panel-heading"><h2>Recent import jobs</h2><a href="<?php echo esc_url(pirate69_importer_url('pirate69-import-jobs')); ?>">View all jobs →</a></div><div class="p69-table-wrap"><table class="widefat striped"><thead><tr><th>Job</th><th>Catalog</th><th>Status</th><th>Created</th><th>Updated</th><th>Skipped</th><th>Failed records</th><th>Finished / updated</th></tr></thead><tbody><?php foreach($recent as $job): ?><tr><td>#<?php echo absint($job['id']); ?> · <?php echo esc_html($job['job_type']); ?></td><td><?php echo esc_html($job['category']); ?></td><td><span class="p69-status p69-status-<?php echo esc_attr($job['status']); ?>"><?php echo esc_html($job['status']); ?></span></td><td><?php echo absint($job['created_count']); ?></td><td><?php echo absint($job['updated_count']); ?></td><td><?php echo absint($job['skipped_count']); ?></td><td><?php echo absint($job['error_count']); ?></td><td><?php echo esc_html($job['updated_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
    <div class="p69-admin-panel"><div class="p69-panel-heading"><h2>Activity log</h2><span>Latest 100 events</span></div><form class="p69-log-filters" method="get"><input type="hidden" name="page" value="pirate69-import-history"><select name="level"><option value="all">All events</option><?php foreach(array('success','info','warning','error') as $v): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($level,$v); ?>><?php echo esc_html(ucfirst($v)); ?></option><?php endforeach; ?></select><input type="search" name="q" value="<?php echo esc_attr($search); ?>" placeholder="Search import activity"><button class="button">Filter</button></form><div class="p69-log-list"><?php if($logs):foreach($logs as $log):$context=json_decode($log['context']??'',true); ?><article class="p69-log-entry"><span class="p69-log-dot p69-log-<?php echo esc_attr($log['level']); ?>"></span><div><strong><?php echo esc_html($log['message']); ?></strong><p><?php echo esc_html($log['created_at']); ?> · Job #<?php echo absint($log['job_id']); ?><?php if(!empty($context['source_id'])): ?> · <?php echo esc_html($context['source_id']); ?><?php endif; ?></p><?php if(!empty($context['error'])): ?><small><?php echo esc_html($context['error']); ?></small><?php endif; ?></div></article><?php endforeach;else:?><p>No activity matches these filters.</p><?php endif; ?></div></div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Clear all importer activity logs?');"><?php wp_nonce_field('pirate69_importer_action'); ?><input type="hidden" name="action" value="pirate69_importer_action"><input type="hidden" name="p69_action" value="clear_logs"><input type="hidden" name="return_page" value="pirate69-import-history"><button class="button">Clear activity logs</button></form>
    <?php pirate69_importer_footer();
}

function pirate69_render_missing_data() {
    if (!current_user_can('manage_options')) return;
    global $wpdb; $categories=pirate69_source_categories();$map=$wpdb->prefix.'p69_source_map';
    $category=sanitize_key($_GET['catalog']??'all');$only=sanitize_key($_GET['view']??'missing');
    $summary=pirate69_repository_stats();$rows=array();$errors=array();
    foreach($categories as $key=>$cat){
        if($category!=='all'&&$category!==$key)continue;
        $index=pirate69_fetch_json($cat['index']);if(is_wp_error($index)){$errors[$key]=$index->get_error_message();continue;}
        $mapped_rows=$wpdb->get_col($wpdb->prepare("SELECT source_id FROM {$map} WHERE category=%s",$key));
        $mapped_ids=array_fill_keys(array_map('strval',(array)$mapped_rows),true);
        foreach((array)$index as $row){if(!is_array($row))continue;$id=sanitize_text_field($row['id']??'');if(!$id)continue;
            $mapped=isset($mapped_ids[$id])?1:0;
            $missing_fields=array();foreach(array('title'=>'Title','poster'=>'Poster URL','chunk'=>'Chunk reference') as $field=>$label)if(empty($row[$field]))$missing_fields[]=$label;
            if($only==='missing'&&$mapped)continue;if($only==='incomplete'&&!$missing_fields)continue;if($only==='all'&&!$mapped&&!$missing_fields){}elseif($only==='all'){} 
            if($only==='incomplete'&&!$missing_fields)continue;
            $rows[]=array('category'=>$key,'id'=>$id,'title'=>sanitize_text_field($row['title']??$id),'chunk'=>absint($row['chunk']??0),'year'=>sanitize_text_field($row['year']??''),'mapped'=>$mapped,'missing_fields'=>$missing_fields);
        }
    }
    pirate69_importer_header('Missing Data','Find source records not yet imported and index entries with incomplete fields.','missing');
    ?>
    <div class="p69-stat-grid"><div class="p69-stat-card"><span class="p69-stat-label">Not imported</span><strong><?php echo number_format_i18n($summary['missing']); ?></strong><small>Across all catalogs</small></div><div class="p69-stat-card"><span class="p69-stat-label">Incomplete source rows</span><strong><?php echo number_format_i18n($summary['incomplete']); ?></strong><small>Missing ID, title, poster or chunk</small></div></div>
    <div class="p69-admin-panel"><form class="p69-search-form" method="get"><input type="hidden" name="page" value="pirate69-import-missing"><select name="catalog"><option value="all">All catalogs</option><?php foreach($categories as $key=>$cat): ?><option value="<?php echo esc_attr($key); ?>" <?php selected($category,$key); ?>><?php echo esc_html($cat['label']); ?></option><?php endforeach; ?></select><select name="view"><option value="missing" <?php selected($only,'missing'); ?>>Not imported</option><option value="incomplete" <?php selected($only,'incomplete'); ?>>Incomplete source data</option><option value="all" <?php selected($only,'all'); ?>>All source records</option></select><button class="button button-primary">Apply</button></form>
    <p class="p69-footnote">Showing up to 300 records. Records with missing chunk references cannot be imported through the current chunk-based worker until the source data is corrected.</p>
    <div class="p69-table-wrap"><table class="widefat striped"><thead><tr><th>Record</th><th>Catalog</th><th>Year</th><th>Chunk</th><th>Website status</th><th>Source quality</th><th>Action</th></tr></thead><tbody><?php $shown=0;foreach($rows as $row):if($shown++>=300)break; ?><tr><td><strong><?php echo esc_html($row['title']); ?></strong><code><?php echo esc_html($row['id']); ?></code></td><td><?php echo esc_html($categories[$row['category']]['label']); ?></td><td><?php echo esc_html($row['year']?:'—'); ?></td><td><?php echo $row['chunk']?'#'.absint($row['chunk']):'Missing'; ?></td><td><?php echo $row['mapped']?'Imported':'<span class="p69-status p69-status-pending">Not imported</span>'; ?></td><td><?php echo $row['missing_fields']?esc_html(implode(', ',$row['missing_fields'])):'Complete'; ?></td><td><?php if(!$row['mapped']&&$row['chunk']):pirate69_importer_start_form($row['category'],'movie',$row['id'],'Import');else:echo '—';endif; ?></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="7">No matching records found.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php pirate69_importer_footer();
}

function pirate69_refresh_repo_stats() {
    if (!current_user_can('manage_options')) wp_die('Not allowed.');
    check_admin_referer('p69_refresh_repo_stats');
    delete_transient('p69_repository_stats');
    pirate69_repository_stats(true);
    set_transient('pirate69_importer_notice','Repository statistics refreshed.',30);
    wp_safe_redirect(pirate69_importer_url('pirate69-importer')); exit;
}
add_action('admin_post_p69_refresh_repo_stats','pirate69_refresh_repo_stats');
