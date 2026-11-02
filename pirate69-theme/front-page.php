<?php
defined('ABSPATH') || exit;
get_header();
$settings = wp_parse_args(get_option('pirate69_settings', array()), array('posts_per_page' => 24, 'show_hero' => 1));
?>
<main id="primary" class="site-main">
<?php if (!empty($settings['show_hero'])) : ?>
<section class="hero container">
	<div class="hero-copy"><span class="eyebrow"><i class="live-dot"></i> YOUR ENTERTAINMENT CATALOG</span><h1>Discover your next<br><span>great watch.</span></h1><p>Explore movies and series across industries, languages and quality formats.</p><div class="hero-actions"><a class="button button-primary" href="#latest">Explore catalog <span>→</span></a><a class="button button-quiet" href="<?php echo esc_url(get_post_type_archive_link('p69_movie') ?: '#latest'); ?>">Browse movies</a></div></div>
	<div class="hero-art" aria-hidden="true"><div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div><div class="art-core"><span>P</span><b>69</b></div><span class="art-caption">CINEMA · SERIES · DISCOVERY</span></div>
</section>
<?php endif; ?>
<section id="latest" class="catalog-section container"><div class="section-heading"><div><p class="eyebrow">FRESHLY ADDED</p><h2>Latest titles</h2></div><a class="text-link" href="<?php echo esc_url(get_post_type_archive_link('p69_movie') ?: home_url('/')); ?>">View catalog →</a></div>
<?php
$is_static_front_page = ('page' === get_option('show_on_front') && (int) get_option('page_on_front') > 0);
$pagination_var = $is_static_front_page ? 'page' : 'paged';
// Static front pages use `page`; normal blog homepages use `paged`.
// Accept either query value to keep older ?paged=2 links working.
$current_page = max(1, absint(get_query_var($pagination_var)), absint(get_query_var('paged')), absint(get_query_var('page')), isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 0);
$latest = new WP_Query(array('post_type' => array('p69_movie', 'p69_tv_show'), 'posts_per_page' => max(1, absint($settings['posts_per_page'])), 'paged' => $current_page, 'post_status' => 'publish', 'ignore_sticky_posts' => true));
if ($latest->have_posts()) :
?>
<div class="movie-grid"><?php while ($latest->have_posts()) : $latest->the_post(); get_template_part('template-parts/content', 'card'); endwhile; ?></div>
<?php
$pagination_base = add_query_arg($pagination_var, '%#%', home_url('/'));
$pagination = paginate_links(array(
    'base' => $pagination_base,
    'format' => '',
    'total' => max(1, (int) $latest->max_num_pages),
    'current' => $current_page,
    'type' => 'list',
    'prev_text' => '← Previous',
    'next_text' => 'Next →',
    'mid_size' => 2,
));
if ($pagination) : ?><nav class="p69-pagination" aria-label="<?php esc_attr_e('Catalog pages', 'pirate69'); ?>"><?php echo wp_kses_post($pagination); ?></nav><?php endif; ?>
<?php else : ?>
<div class="empty-state"><span class="empty-icon">✦</span><h3>The screen is yours.</h3><p>Your movie and series catalog will appear here once you add titles.</p><span class="empty-hint">Start an import from Pirate69 Importer.</span></div>
<?php endif; wp_reset_postdata(); ?>
</section></main>
<?php get_footer(); ?>
