<?php
defined('ABSPATH') || exit;

/**
 * Manual TMDb matching. Explicit selections always take precedence over title guessing.
 */
function pirate69_tmdb_manager_menu() {
	add_submenu_page('pirate69-importer', 'TMDb Manager', 'TMDb Manager', 'manage_options', 'pirate69-tmdb-manager', 'pirate69_render_tmdb_manager');
}
add_action('admin_menu', 'pirate69_tmdb_manager_menu', 30);

function pirate69_tmdb_ajax_search() {
	if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Not allowed.'), 403);
	check_ajax_referer('p69_tmdb_manager', 'nonce');
	$query = sanitize_text_field(wp_unslash($_POST['query'] ?? ''));
	$type = sanitize_key($_POST['type'] ?? 'movie');
	$year = absint($_POST['year'] ?? 0);
	if (strlen($query) < 2) wp_send_json_error(array('message' => 'Enter at least two characters.'), 400);
	if (!in_array($type, array('movie', 'tv'), true)) wp_send_json_error(array('message' => 'Invalid media type.'), 400);
	$args = array('query' => $query, 'include_adult' => 'false', 'page' => 1);
	if ($year >= 1888 && $year <= ((int) gmdate('Y') + 2)) {
		$args[$type === 'tv' ? 'first_air_date_year' : 'year'] = $year;
	}
	$data = pirate69_tmdb_request('search/' . $type, $args);
	if (is_wp_error($data)) wp_send_json_error(array('message' => $data->get_error_message()), 502);
	$items = array();
	foreach (array_slice((array) ($data['results'] ?? array()), 0, 10) as $item) {
		$title = $type === 'tv' ? ($item['name'] ?? '') : ($item['title'] ?? '');
		$date = $type === 'tv' ? ($item['first_air_date'] ?? '') : ($item['release_date'] ?? '');
		if (empty($item['id']) || !$title) continue;
		$items[] = array(
			'id' => absint($item['id']),
			'title' => sanitize_text_field($title),
			'original_title' => sanitize_text_field($type === 'tv' ? ($item['original_name'] ?? '') : ($item['original_title'] ?? '')),
			'year' => $date ? substr($date, 0, 4) : '',
			'overview' => sanitize_text_field($item['overview'] ?? ''),
			'poster' => !empty($item['poster_path']) ? esc_url_raw('https://image.tmdb.org/t/p/w185' . $item['poster_path']) : '',
			'type' => $type,
		);
	}
	wp_send_json_success(array('results' => $items, 'total' => absint($data['total_results'] ?? 0)));
}
add_action('wp_ajax_p69_tmdb_search', 'pirate69_tmdb_ajax_search');

function pirate69_tmdb_ajax_assign() {
	if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Not allowed.'), 403);
	check_ajax_referer('p69_tmdb_manager', 'nonce');
	$post_id = absint($_POST['post_id'] ?? 0);
	$tmdb_id = absint($_POST['tmdb_id'] ?? 0);
	$type = sanitize_key($_POST['type'] ?? '');
	$post = get_post($post_id);
	if (!$post || !in_array($post->post_type, array('p69_movie', 'p69_tv_show'), true)) wp_send_json_error(array('message' => 'Catalog entry not found.'), 404);
	$expected = $post->post_type === 'p69_tv_show' ? 'tv' : 'movie';
	if (!$tmdb_id || $type !== $expected) wp_send_json_error(array('message' => 'Selected result type does not match this catalog entry.'), 400);
	update_post_meta($post_id, '_p69_tmdb_id', $tmdb_id);
	update_post_meta($post_id, '_p69_tmdb_type', $type);
	update_post_meta($post_id, '_p69_tmdb_match_source', 'manual');
	update_post_meta($post_id, '_p69_tmdb_status', 'pending');
	delete_post_meta($post_id, '_p69_tmdb_error');
	$result = pirate69_tmdb_sync_post($post_id, true);
	if (is_wp_error($result)) {
		update_post_meta($post_id, '_p69_tmdb_status', 'failed');
		update_post_meta($post_id, '_p69_tmdb_error', $result->get_error_message());
		wp_send_json_error(array('message' => $result->get_error_message()), 502);
	}
	wp_send_json_success(array('message' => 'TMDb match saved and metadata synchronized.', 'title' => get_the_title($post_id)));
}
add_action('wp_ajax_p69_tmdb_assign', 'pirate69_tmdb_ajax_assign');

function pirate69_render_tmdb_manager() {
	if (!current_user_can('manage_options')) return;
	$status = sanitize_key($_GET['status'] ?? 'unmatched');
	$allowed = array('unmatched', 'matched', 'failed', 'all');
	if (!in_array($status, $allowed, true)) $status = 'unmatched';
	$search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
	$meta_query = array();
	if ($status !== 'all') {
		if ($status === 'unmatched') {
			$meta_query = array('relation' => 'OR', array('key' => '_p69_tmdb_status', 'value' => 'unmatched'), array('key' => '_p69_tmdb_id', 'compare' => 'NOT EXISTS'));
		} else {
			$meta_query = array(array('key' => '_p69_tmdb_status', 'value' => $status));
		}
	}
	$args = array('post_type' => array('p69_movie', 'p69_tv_show'), 'post_status' => array('publish', 'draft', 'pending', 'private'), 'posts_per_page' => 30, 'orderby' => 'modified', 'order' => 'DESC', 'meta_query' => $meta_query);
	if ($search !== '') $args['s'] = $search;
	$items = new WP_Query($args);
	$nonce = wp_create_nonce('p69_tmdb_manager');
	$ajax = admin_url('admin-ajax.php');
	?>
	<div class="wrap">
		<h1>TMDb Manager</h1>
		<p>Review imported catalog entries, search TMDb and confirm the correct movie or TV show. Confirmed matches are saved by TMDb ID; GitHub download links are not changed.</p>
		<form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:16px 0">
			<input type="hidden" name="page" value="pirate69-tmdb-manager">
			<select name="status">
				<option value="unmatched" <?php selected($status, 'unmatched'); ?>>Unmatched / not assigned</option>
				<option value="matched" <?php selected($status, 'matched'); ?>>Matched</option>
				<option value="failed" <?php selected($status, 'failed'); ?>>Failed</option>
				<option value="all" <?php selected($status, 'all'); ?>>All entries</option>
			</select>
			<input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search imported titles">
			<button class="button">Filter</button>
		</form>
		<p><?php echo esc_html(number_format_i18n($items->found_posts)); ?> entries found. Showing up to 30 per page.</p>
		<table class="widefat striped">
			<thead><tr><th>Imported title</th><th>Type / year</th><th>Current TMDb match</th><th>Search and select</th></tr></thead><tbody>
			<?php if ($items->have_posts()) : while ($items->have_posts()) : $items->the_post();
				$post_id = get_the_ID();
				$is_tv = get_post_type($post_id) === 'p69_tv_show';
				$tmdb_id = absint(get_post_meta($post_id, '_p69_tmdb_id', true));
				$tmdb_title = get_post_meta($post_id, '_p69_tmdb_title', true);
				$tmdb_status = get_post_meta($post_id, '_p69_tmdb_status', true);
				$year = get_post_meta($post_id, '_p69_release_year', true);
				?>
				<tr data-p69-tmdb-row="<?php echo $post_id; ?>" data-type="<?php echo $is_tv ? 'tv' : 'movie'; ?>">
					<td><strong><a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php echo esc_html(get_the_title()); ?></a></strong><br><small>ID: <?php echo $post_id; ?> · <?php echo esc_html($tmdb_status ?: 'not checked'); ?></small>
						<?php $error = get_post_meta($post_id, '_p69_tmdb_error', true); if ($error) : ?><br><small style="color:#b32d2e"><?php echo esc_html($error); ?></small><?php endif; ?>
					</td>
					<td><?php echo $is_tv ? 'TV' : 'Movie'; ?><?php if ($year) echo ' · ' . esc_html($year); ?></td>
					<td><?php echo $tmdb_id ? esc_html(($tmdb_title ?: 'TMDb ID') . ' (#' . $tmdb_id . ')') : 'Not assigned'; ?></td>
					<td>
						<div style="display:flex;gap:6px;flex-wrap:wrap">
							<input type="search" class="p69-tmdb-query" value="<?php echo esc_attr(get_the_title()); ?>" placeholder="Search TMDb title" style="min-width:220px">
							<input type="number" class="p69-tmdb-year" value="<?php echo esc_attr($year); ?>" placeholder="Year" min="1888" max="2100" style="width:85px">
							<button type="button" class="button p69-tmdb-search">Search TMDb</button>
						</div>
						<div class="p69-tmdb-results" aria-live="polite" style="margin-top:8px"></div>
					</td>
				</tr>
			<?php endwhile; wp_reset_postdata(); else : ?><tr><td colspan="4">No catalog entries found for this filter.</td></tr><?php endif; ?>
			</tbody>
		</table>
		<?php
		echo paginate_links(array('total' => max(1, (int) $items->max_num_pages), 'current' => max(1, absint($_GET['paged'] ?? 1)), 'format' => '&paged=%#%'));
		?>
	</div>
	<style>
	.p69-tmdb-result{display:flex;gap:10px;align-items:center;padding:8px;margin:6px 0;background:#fff;border:1px solid #dcdcde;max-width:650px}
	.p69-tmdb-result img{width:48px;height:70px;object-fit:cover;background:#eee}
	.p69-tmdb-result-copy{flex:1;min-width:0}.p69-tmdb-result-copy small{display:block;color:#646970}.p69-tmdb-result-copy p{margin:4px 0 0}
	.p69-tmdb-results .notice{margin:6px 0}
	</style>
	<script>
	(function(){
		const endpoint=<?php echo wp_json_encode($ajax); ?>, nonce=<?php echo wp_json_encode($nonce); ?>;
		function post(data){const body=new URLSearchParams(Object.assign({nonce:nonce},data));return fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()}).then(r=>r.json());}
		document.querySelectorAll('[data-p69-tmdb-row]').forEach(function(row){
			const output=row.querySelector('.p69-tmdb-results'), search=row.querySelector('.p69-tmdb-search');
			search.addEventListener('click',function(){
				const query=row.querySelector('.p69-tmdb-query').value.trim(), year=row.querySelector('.p69-tmdb-year').value;
				output.textContent='Searching TMDb…'; search.disabled=true;
				post({action:'p69_tmdb_search',query:query,year:year,type:row.dataset.type}).then(function(response){
					output.innerHTML='';
					if(!response.success){output.textContent=response.data&&response.data.message?response.data.message:'TMDb search failed.';return;}
					if(!response.data.results.length){output.textContent='No TMDb results found.';return;}
					response.data.results.forEach(function(item){
						const card=document.createElement('div');card.className='p69-tmdb-result';
						if(item.poster){const img=document.createElement('img');img.src=item.poster;img.alt='';img.loading='lazy';card.appendChild(img);}
						const copy=document.createElement('div');copy.className='p69-tmdb-result-copy';
						const title=document.createElement('strong');title.textContent=item.title+' '+(item.year?'('+item.year+')':'');copy.appendChild(title);
						const original=document.createElement('small');original.textContent='TMDb ID: '+item.id+(item.original_title?' · '+item.original_title:'');copy.appendChild(original);
						const summary=document.createElement('p');summary.textContent=item.overview.slice(0,220);copy.appendChild(summary);card.appendChild(copy);
						const choose=document.createElement('button');choose.type='button';choose.className='button button-primary';choose.textContent='Use this match';choose.addEventListener('click',function(){
							choose.disabled=true;choose.textContent='Saving…';
							post({action:'p69_tmdb_assign',post_id:row.dataset.p69TmdbRow,tmdb_id:item.id,type:item.type}).then(function(saved){
								if(!saved.success){choose.disabled=false;choose.textContent='Use this match';window.alert(saved.data&&saved.data.message?saved.data.message:'Could not save match.');return;}
								choose.textContent='Matched';window.alert(saved.data.message);window.location.reload();
							}).catch(function(){choose.disabled=false;choose.textContent='Use this match';window.alert('Request failed. Try again.');});
						});card.appendChild(choose);output.appendChild(card);
					});
				}).catch(function(){output.textContent='TMDb request failed. Check your connection and API token.';}).finally(function(){search.disabled=false;});
			});
		});
	})();
	</script>
	<?php
}
