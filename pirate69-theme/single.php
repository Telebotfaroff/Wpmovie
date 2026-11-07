<?php
defined('ABSPATH') || exit;
get_header();
?>
<main id="primary" class="site-main container single-content">
<?php while (have_posts()) : the_post();
	$post_id = get_the_ID();
	$year = get_post_meta($post_id, '_p69_release_year', true);
	$quality = get_post_meta($post_id, '_p69_quality', true);
	$category = get_post_meta($post_id, '_p69_source_category', true);
	$source_url = get_post_meta($post_id, '_p69_source_url', true);
	$single_design = get_option('pirate69_settings', array());
	$single_design = is_array($single_design) ? sanitize_key($single_design['single_design'] ?? 'cinematic') : 'cinematic';
	if (!in_array($single_design, array('cinematic', 'poster', 'compact'), true)) $single_design = 'cinematic';
	$poster_fallback = esc_url(get_post_meta($post_id, '_p69_tmdb_poster_url', true));
	if (!$poster_fallback) $poster_fallback = esc_url(get_post_meta($post_id, '_p69_original_poster_url', true));
	$tmdb_rating = get_post_meta($post_id, '_p69_tmdb_rating', true);
	$tmdb_runtime = absint(get_post_meta($post_id, '_p69_tmdb_runtime', true));
	$tmdb_tagline = get_post_meta($post_id, '_p69_tmdb_tagline', true);
	$tmdb_cast = get_post_meta($post_id, '_p69_tmdb_cast', true);
	$tmdb_directors = get_post_meta($post_id, '_p69_tmdb_directors', true);
	$tmdb_genres = get_post_meta($post_id, '_p69_tmdb_genres', true);
	$tmdb_backdrop = esc_url(get_post_meta($post_id, '_p69_tmdb_backdrop_url', true));
	$tmdb_companies = get_post_meta($post_id, '_p69_tmdb_companies', true);
	$tmdb_countries = get_post_meta($post_id, '_p69_tmdb_countries', true);
	$tmdb_languages = get_post_meta($post_id, '_p69_tmdb_languages', true);
	$tmdb_networks = get_post_meta($post_id, '_p69_tmdb_networks', true);
	$tmdb_creators = get_post_meta($post_id, '_p69_tmdb_creators', true);
	$tmdb_seasons = absint(get_post_meta($post_id, '_p69_tmdb_season_count', true));
	$tmdb_episodes = absint(get_post_meta($post_id, '_p69_tmdb_episode_count', true));
	$tmdb_trailers = get_post_meta($post_id, '_p69_tmdb_trailers', true);
	$tmdb_title = trim((string) get_post_meta($post_id, '_p69_tmdb_title', true));
	$season_number = absint(get_post_meta($post_id, '_p69_season', true));
	$display_title = $tmdb_title ?: get_the_title($post_id);
	$explicit_excerpt = trim((string) get_post_field('post_excerpt', $post_id));
	$content_text = trim(wp_strip_all_tags((string) get_post_field('post_content', $post_id)));
	$show_hero_excerpt = $explicit_excerpt && ('' === $content_text || wp_strip_all_tags($explicit_excerpt) !== $content_text);
	$links = array();
	global $wpdb;
	$table = $wpdb->prefix . 'p69_download_links';
	$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
	if ($table_exists === $table) {
		$links = $wpdb->get_results($wpdb->prepare(
			"SELECT id, label, url, quality, file_size, audio, server, season, episode, is_batch FROM {$table} WHERE post_id = %d AND is_enabled = 1 ORDER BY sort_order ASC, id ASC",
			$post_id
		), ARRAY_A);
	}
	$groups = array();
	foreach ($links as $link) {
		$group = $link['quality'] ?: 'Other links';
		$groups[$group][] = $link;
	}
	$post_type = get_post_type_object(get_post_type());
	$quality_summary = $groups ? implode(', ', array_keys($groups)) : ($quality ?: '');
	$audio_languages = array();
	foreach ($links as $download_link) {
		$audio_value = trim((string) ($download_link['audio'] ?? ''));
		if ($audio_value !== '') $audio_languages[$audio_value] = true;
	}
	$audio_summary = implode(', ', array_keys($audio_languages));
?>
<article <?php post_class('p69-detail p69-design-' . $single_design); ?>>
	<nav class="p69-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'pirate69'); ?>">
		<a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'pirate69'); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url(get_post_type_archive_link(get_post_type())); ?>"><?php echo esc_html($post_type ? $post_type->labels->name : __('Catalog', 'pirate69')); ?></a>
		<span aria-hidden="true">/</span>
		<span aria-current="page"><?php echo esc_html($display_title); ?></span>
	</nav>

	<section class="p69-detail-hero"<?php if ($tmdb_backdrop) : ?> style="--p69-tmdb-backdrop:url('<?php echo esc_url($tmdb_backdrop); ?>')"<?php endif; ?>>
		<div class="p69-detail-poster">
			<?php if (has_post_thumbnail()) : the_post_thumbnail('large', array('loading' => 'eager')); elseif ($poster_fallback) : ?><img src="<?php echo esc_url($poster_fallback); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="eager" decoding="async"><?php else : ?>
				<div class="poster-placeholder"><b>P69</b><small><?php esc_html_e('POSTER PENDING', 'pirate69'); ?></small></div>
			<?php endif; ?>
		</div>
		<div class="p69-detail-main">
			<p class="eyebrow"><?php echo esc_html($post_type ? $post_type->labels->singular_name : __('Catalog title', 'pirate69')); ?></p>
			<h1><?php echo esc_html($display_title); ?><?php if ($year) : ?> <span class="p69-title-year">(<?php echo esc_html($year); ?>)</span><?php endif; ?><?php if ($season_number) : ?> <span class="p69-title-season">– <?php echo esc_html(sprintf(__('Season %s', 'pirate69'), number_format_i18n($season_number))); ?></span><?php endif; ?></h1>
			<?php if ($tmdb_tagline) : ?><p class="p69-tmdb-tagline"><?php echo esc_html($tmdb_tagline); ?></p><?php endif; ?>
			<div class="p69-detail-tags">
				<?php if ($year) : ?><span><?php echo esc_html($year); ?></span><?php endif; ?>
				<?php if ($quality) : ?><span><?php echo esc_html($quality); ?></span><?php endif; ?>
				<?php if ($tmdb_rating !== '' && $tmdb_rating !== false) : ?><span>★ <?php echo esc_html(number_format_i18n((float) $tmdb_rating, 1)); ?>/10 TMDB</span><?php endif; ?>
				<?php if ($tmdb_runtime) : ?><span><?php echo esc_html($tmdb_runtime . ' min'); ?></span><?php endif; ?>
				<?php
				$industries = get_the_terms($post_id, 'p69_industry');
				if ($industries && !is_wp_error($industries)) :
					foreach ($industries as $industry) : ?><span><?php echo esc_html($industry->name); ?></span><?php endforeach;
				elseif ($category) : ?><span><?php echo esc_html(ucwords(str_replace('-', ' ', $category))); ?></span><?php endif; ?>
			</div>
			<?php if ($show_hero_excerpt) : ?>
				<div class="p69-description"><?php echo esc_html(wp_trim_words($explicit_excerpt, 34)); ?></div>
			<?php endif; ?>
			<div class="p69-detail-actions">
				<a class="button button-primary" href="#p69-downloads"><?php esc_html_e('View download links', 'pirate69'); ?> <span aria-hidden="true">↓</span></a>
				<?php if ($source_url && wp_http_validate_url($source_url)) : ?><a class="button button-quiet" href="<?php echo esc_url($source_url); ?>" target="_blank" rel="nofollow noopener noreferrer"><?php esc_html_e('Source page', 'pirate69'); ?> ↗</a><?php endif; ?>
			</div>
		</div>
	</section>

	<section class="p69-info-panel" aria-label="<?php esc_attr_e('Title information', 'pirate69'); ?>">
		<h2><?php esc_html_e('Title information', 'pirate69'); ?></h2>
		<dl class="p69-info-grid">
			<?php if ($year) : ?><div><dt><?php esc_html_e('Release year', 'pirate69'); ?></dt><dd><?php echo esc_html($year); ?></dd></div><?php endif; ?>
			<?php if ($quality_summary) : ?><div><dt><?php esc_html_e('Available quality', 'pirate69'); ?></dt><dd><?php echo esc_html($quality_summary); ?></dd></div><?php endif; ?>
			<?php if ($season_number) : ?><div><dt><?php esc_html_e('Season', 'pirate69'); ?></dt><dd><?php echo esc_html(number_format_i18n($season_number)); ?></dd></div><?php endif; ?>
			<?php if ($audio_summary) : ?><div><dt><?php esc_html_e('Download audio', 'pirate69'); ?></dt><dd><?php echo esc_html($audio_summary); ?></dd></div><?php endif; ?>
			<?php if ($tmdb_rating !== '' && $tmdb_rating !== false) : ?><div><dt>TMDB rating</dt><dd><?php echo esc_html(number_format_i18n((float) $tmdb_rating, 1)); ?>/10</dd></div><?php endif; ?>
			<?php if ($tmdb_runtime) : ?><div><dt>Runtime</dt><dd><?php echo esc_html($tmdb_runtime . ' minutes'); ?></dd></div><?php endif; ?>
			<?php if ($tmdb_seasons) : ?><div><dt>Seasons</dt><dd><?php echo esc_html(number_format_i18n($tmdb_seasons)); ?></dd></div><?php endif; ?>
			<?php if ($tmdb_episodes) : ?><div><dt>Episodes</dt><dd><?php echo esc_html(number_format_i18n($tmdb_episodes)); ?></dd></div><?php endif; ?>
			<div><dt><?php esc_html_e('Added', 'pirate69'); ?></dt><dd><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time></dd></div>
		</dl>
		<?php
		$terms = get_the_terms($post_id, 'p69_genre');
		if ($terms && !is_wp_error($terms)) : ?>
			<div class="p69-term-list"><strong><?php esc_html_e('Genres', 'pirate69'); ?></strong><?php foreach ($terms as $term) : ?><a href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a><?php endforeach; ?></div>
		<?php endif; ?>
		<?php
		$languages = get_the_terms($post_id, 'p69_language');
		if ($languages && !is_wp_error($languages)) : ?>
			<div class="p69-term-list"><strong><?php esc_html_e('Languages', 'pirate69'); ?></strong><?php foreach ($languages as $term) : ?><a href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a><?php endforeach; ?></div>
		<?php endif; ?>
		<?php if (is_array($tmdb_genres) && $tmdb_genres) : ?><div class="p69-term-list"><strong>TMDB genres</strong><?php foreach ($tmdb_genres as $genre) : ?><span><?php echo esc_html($genre); ?></span><?php endforeach; ?></div><?php endif; ?>
		<?php if (is_array($tmdb_directors) && $tmdb_directors) : ?><div class="p69-term-list"><strong>Director</strong><span><?php echo esc_html(implode(', ', $tmdb_directors)); ?></span></div><?php endif; ?>
		<?php if (is_array($tmdb_cast) && $tmdb_cast) : ?><div class="p69-term-list"><strong>Cast</strong><span><?php echo esc_html(implode(', ', $tmdb_cast)); ?></span></div><?php endif; ?>
		<?php if (is_array($tmdb_creators) && $tmdb_creators) : ?><div class="p69-term-list"><strong>Created by</strong><span><?php echo esc_html(implode(', ', $tmdb_creators)); ?></span></div><?php endif; ?>
		<?php if (is_array($tmdb_companies) && $tmdb_companies) : ?><div class="p69-term-list"><strong>Production</strong><span><?php echo esc_html(implode(', ', $tmdb_companies)); ?></span></div><?php endif; ?>
		<?php if (is_array($tmdb_networks) && $tmdb_networks) : ?><div class="p69-term-list"><strong>Networks</strong><span><?php echo esc_html(implode(', ', $tmdb_networks)); ?></span></div><?php endif; ?>
		<?php if (is_array($tmdb_countries) && $tmdb_countries) : ?><div class="p69-term-list"><strong>Countries</strong><span><?php echo esc_html(implode(', ', $tmdb_countries)); ?></span></div><?php endif; ?>
		<?php if (is_array($tmdb_languages) && $tmdb_languages) : ?><div class="p69-term-list"><strong>Original languages</strong><span><?php echo esc_html(implode(', ', $tmdb_languages)); ?></span></div><?php endif; ?>
		<?php if (is_array($tmdb_trailers) && $tmdb_trailers) : ?><div class="p69-term-list"><strong>Trailer</strong><?php foreach (array_slice($tmdb_trailers, 0, 2) as $trailer) : ?><a href="<?php echo esc_url($trailer); ?>" target="_blank" rel="noopener noreferrer">Watch trailer ↗</a><?php endforeach; ?></div><?php endif; ?>
	</section>

	<section id="p69-downloads" class="p69-download-section">
		<div class="section-heading"><div><p class="eyebrow"><?php esc_html_e('GET YOUR COPY', 'pirate69'); ?></p><h2><?php esc_html_e('Download links', 'pirate69'); ?></h2></div><span class="p69-link-count"><?php echo esc_html(sprintf(_n('%s link', '%s links', count($links), 'pirate69'), number_format_i18n(count($links)))); ?></span></div>
		<?php if ($groups) : ?>
			<div class="p69-download-toolbar">
				<div><strong><?php esc_html_e('Choose your quality', 'pirate69'); ?></strong><small><?php esc_html_e('Select a quality to find available servers', 'pirate69'); ?></small></div>
				<div class="p69-quality-filters" role="group" aria-label="<?php esc_attr_e('Filter by quality', 'pirate69'); ?>">
					<button type="button" class="is-active" data-p69-quality="all">All</button>
					<?php foreach (array_keys($groups) as $group_name) : ?><button type="button" data-p69-quality="<?php echo esc_attr($group_name); ?>"><?php echo esc_html($group_name); ?></button><?php endforeach; ?>
				</div>
			</div>
			<div class="p69-download-groups">
			<?php foreach ($groups as $group_name => $group_links) : ?>
				<section class="p69-download-group" data-p69-download-group="<?php echo esc_attr($group_name); ?>">
					<div class="p69-download-group-heading"><h3><?php echo esc_html($group_name); ?></h3><span><?php echo esc_html(sprintf(_n('%s server', '%s servers', count($group_links), 'pirate69'), number_format_i18n(count($group_links)))); ?></span></div>
					<div class="p69-download-list">
					<?php foreach ($group_links as $link) :
						$label = $link['label'] ?: ($link['server'] ?: __('Download', 'pirate69'));
						$server = $link['server'] ?: __('Direct link', 'pirate69');
						?>
						<article class="p69-download-card">
							<div class="p69-download-server-icon" aria-hidden="true">↗</div>
							<div class="p69-download-copy"><strong><?php echo esc_html($label); ?></strong><div class="p69-download-meta"><span class="p69-server-badge"><?php echo esc_html($server); ?></span><?php if ($link['file_size']) : ?><span><?php echo esc_html($link['file_size']); ?></span><?php endif; ?><?php if ($link['audio']) : ?><span><?php echo esc_html($link['audio']); ?></span><?php endif; ?><?php if (!empty($link['season'])) : ?><span><?php echo esc_html(sprintf(__('Season %s', 'pirate69'), number_format_i18n(absint($link['season'])))); ?></span><?php endif; ?><?php if (!empty($link['episode'])) : ?><span><?php echo esc_html(sprintf(__('Episode %s', 'pirate69'), sanitize_text_field($link['episode']))); ?></span><?php endif; ?><?php if (!empty($link['is_batch'])) : ?><span class="p69-batch-badge"><?php esc_html_e('Full season pack', 'pirate69'); ?></span><?php endif; ?></div></div>
							<a class="p69-download-button" href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="nofollow sponsored noopener noreferrer"><?php esc_html_e('Download', 'pirate69'); ?> <span aria-hidden="true">↗</span></a>
						</article>
					<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="empty-state"><div class="empty-icon">↗</div><h3><?php esc_html_e('No download links yet', 'pirate69'); ?></h3><p><?php esc_html_e('Download options will appear here when they are added to this title.', 'pirate69'); ?></p></div>
		<?php endif; ?>
	</section>

	<?php if ($content_text) : ?><section class="p69-editorial-content"><h2><?php esc_html_e('About this title', 'pirate69'); ?></h2><div class="entry-content"><?php the_content(); ?></div></section><?php endif; ?>
</article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
