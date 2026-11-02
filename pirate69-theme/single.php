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
	$source_id = get_post_meta($post_id, '_p69_source_id', true);
	$poster_fallback = esc_url(get_post_meta($post_id, '_p69_original_poster_url', true));
	$links = array();
	global $wpdb;
	$table = $wpdb->prefix . 'p69_download_links';
	$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
	if ($table_exists === $table) {
		$links = $wpdb->get_results($wpdb->prepare(
			"SELECT id, label, url, quality, file_size, audio, server FROM {$table} WHERE post_id = %d AND is_enabled = 1 ORDER BY sort_order ASC, id ASC",
			$post_id
		), ARRAY_A);
	}
	$groups = array();
	foreach ($links as $link) {
		$group = $link['quality'] ?: 'Other links';
		$groups[$group][] = $link;
	}
	$post_type = get_post_type_object(get_post_type());
?>
<article <?php post_class('p69-detail'); ?>>
	<nav class="p69-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'pirate69'); ?>">
		<a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'pirate69'); ?></a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url(get_post_type_archive_link(get_post_type())); ?>"><?php echo esc_html($post_type ? $post_type->labels->name : __('Catalog', 'pirate69')); ?></a>
		<span aria-hidden="true">/</span>
		<span><?php the_title(); ?></span>
	</nav>

	<section class="p69-detail-hero">
		<div class="p69-detail-poster">
			<?php if (has_post_thumbnail()) : the_post_thumbnail('large', array('loading' => 'eager')); elseif ($poster_fallback) : ?><img src="<?php echo esc_url($poster_fallback); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="eager" decoding="async"><?php else : ?>
				<div class="poster-placeholder"><b>P69</b><small><?php esc_html_e('POSTER PENDING', 'pirate69'); ?></small></div>
			<?php endif; ?>
		</div>
		<div class="p69-detail-main">
			<p class="eyebrow"><?php echo esc_html($post_type ? $post_type->labels->singular_name : __('Catalog title', 'pirate69')); ?></p>
			<h1><?php the_title(); ?></h1>
			<div class="p69-detail-tags">
				<?php if ($year) : ?><span><?php echo esc_html($year); ?></span><?php endif; ?>
				<?php if ($quality) : ?><span><?php echo esc_html($quality); ?></span><?php endif; ?>
				<?php
				$industries = get_the_terms($post_id, 'p69_industry');
				if ($industries && !is_wp_error($industries)) :
					foreach ($industries as $industry) : ?><span><?php echo esc_html($industry->name); ?></span><?php endforeach;
				elseif ($category) : ?><span><?php echo esc_html(ucwords(str_replace('-', ' ', $category))); ?></span><?php endif; ?>
			</div>
			<?php if (has_excerpt() || get_the_content()) : ?>
				<div class="p69-description"><?php the_excerpt(); ?></div>
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
			<?php if ($quality) : ?><div><dt><?php esc_html_e('Available quality', 'pirate69'); ?></dt><dd><?php echo esc_html($quality); ?></dd></div><?php endif; ?>
			<?php if ($source_id) : ?><div><dt><?php esc_html_e('Catalog ID', 'pirate69'); ?></dt><dd><?php echo esc_html($source_id); ?></dd></div><?php endif; ?>
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
	</section>

	<section id="p69-downloads" class="p69-download-section">
		<div class="section-heading"><div><p class="eyebrow"><?php esc_html_e('GET YOUR COPY', 'pirate69'); ?></p><h2><?php esc_html_e('Download links', 'pirate69'); ?></h2></div><span class="p69-link-count"><?php echo esc_html(sprintf(_n('%s link', '%s links', count($links), 'pirate69'), number_format_i18n(count($links)))); ?></span></div>
		<?php if ($groups) : foreach ($groups as $group_name => $group_links) : ?>
			<div class="p69-download-group">
				<h3><?php echo esc_html($group_name); ?></h3>
				<div class="p69-download-list">
					<?php foreach ($group_links as $link) :
						$label = $link['label'] ?: ($link['server'] ?: __('Download', 'pirate69'));
						$meta = array_filter(array($link['server'], $link['file_size'], $link['audio']));
					?>
						<div class="p69-download-row">
							<div class="p69-download-copy"><strong><?php echo esc_html($label); ?></strong><?php if ($meta) : ?><small><?php echo esc_html(implode(' · ', $meta)); ?></small><?php endif; ?></div>
							<a class="p69-download-button" href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="nofollow sponsored noopener noreferrer"><?php esc_html_e('Open link', 'pirate69'); ?> <span aria-hidden="true">↗</span></a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; else : ?>
			<div class="empty-state"><div class="empty-icon">↗</div><h3><?php esc_html_e('No download links yet', 'pirate69'); ?></h3><p><?php esc_html_e('Download options will appear here when they are added to this title.', 'pirate69'); ?></p></div>
		<?php endif; ?>
	</section>

	<?php if (trim(get_the_content())) : ?><section class="p69-editorial-content"><h2><?php esc_html_e('About this title', 'pirate69'); ?></h2><div class="entry-content"><?php the_content(); ?></div></section><?php endif; ?>
</article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
