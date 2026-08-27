<?php
defined('ABSPATH') || exit;

/**
 * Advanced On-Page SEO & Schema Markup - Phase 7 (Master Checklist Point 6)
 *
 * JSON-LD (Movie/TVSeries/TVEpisode), OG/Twitter Cards, dynamic meta description & title.
 * Strict WPCS: escaping, sanitization, nonces not needed for frontend meta.
 *
 * @since 1.0.0
 */
class CineCore_SEO {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Run only on frontend.
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 5 );
		add_action( 'wp_head', array( $this, 'output_og_twitter' ), 5 );
		add_action( 'wp_head', array( $this, 'output_json_ld' ), 5 );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ), 20 );
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 20 );
	}

	/**
	 * Check if another SEO plugin is active (avoid duplication).
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_other_seo_active() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || class_exists( 'All_in_One_SEO_Pack' );
	}

	/**
	 * Get SEO data for current singular.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_seo_data( $post_id ) {
		$post_type = get_post_type( $post_id );
		$title     = get_the_title( $post_id );
		$permalink = get_permalink( $post_id );

		// Poster: prefer _cinecore_poster_url, fallback thumbnail.
		$poster = get_post_meta( $post_id, '_cinecore_poster_url', true );
		if ( empty( $poster ) && has_post_thumbnail( $post_id ) ) {
			$poster = get_the_post_thumbnail_url( $post_id, 'large' );
		}
		$poster = $poster ? esc_url_raw( $poster ) : '';

		// Backdrop for og:image fallback.
		$backdrop = get_post_meta( $post_id, '_cinecore_backdrop_url', true );
		$backdrop = $backdrop ? esc_url_raw( $backdrop ) : '';

		// Overview: excerpt or trimmed content.
		$overview = '';
		if ( has_excerpt( $post_id ) ) {
			$overview = get_the_excerpt( $post_id );
		} else {
			$post_obj = get_post( $post_id );
			if ( $post_obj ) {
				$overview = wp_trim_words( wp_strip_all_tags( $post_obj->post_content ), 30, '...' );
			}
		}
		$overview = sanitize_text_field( $overview );

		// Rating.
		$rating = get_post_meta( $post_id, '_cinecore_vote_average', true );
		if ( '' === $rating ) {
			$rating = get_post_meta( $post_id, '_cinecore_rating', true );
		}
		$rating = '' !== $rating ? (float) $rating : 0;

		// Runtime.
		$runtime = get_post_meta( $post_id, '_cinecore_runtime', true );
		$runtime = '' !== $runtime ? absint( $runtime ) : 0;

		// Year via release_year or date.
		$year = '';
		$terms = get_the_terms( $post_id, 'release_year' );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$year = $terms[0]->name;
		}
		if ( '' === $year ) {
			$year = get_the_date( 'Y', $post_id );
		}
		$year = sanitize_text_field( $year );

		// Director - optional meta.
		$director = get_post_meta( $post_id, '_cinecore_director', true );
		if ( '' === $director ) {
			$director = get_post_meta( $post_id, '_cinecore_crew_director', true );
		}
		$director = sanitize_text_field( $director );

		// Date published.
		$date_published = get_the_date( 'c', $post_id );

		// For episodes: season/episode numbers + parent show.
		$season_number  = '';
		$episode_number = '';
		$parent_show    = '';
		if ( 'episodes' === $post_type ) {
			$episode_number = get_post_meta( $post_id, '_cinecore_episode_number', true );
			$season_id = get_post_meta( $post_id, '_cinecore_season_id', true );
			if ( '' === $season_id ) {
				$season_id = get_post_meta( $post_id, '_cinecore_parent_season', true );
			}
			if ( '' !== $season_id ) {
				$season_number = get_post_meta( $season_id, '_cinecore_season_number', true );
				$tv_id = get_post_meta( $season_id, '_cinecore_tv_show_id', true );
				if ( '' === $tv_id ) {
					$tv_id = get_post_meta( $season_id, '_cinecore_parent_tv_show', true );
				}
				if ( '' !== $tv_id ) {
					$parent_show = get_the_title( (int) $tv_id );
				}
			}
		}

		return array(
			'post_id'        => (int) $post_id,
			'post_type'      => $post_type,
			'title'          => $title,
			'permalink'      => $permalink ? esc_url_raw( $permalink ) : '',
			'poster'         => $poster,
			'backdrop'       => $backdrop,
			'overview'       => $overview,
			'rating'         => $rating,
			'runtime'        => $runtime,
			'year'           => $year,
			'director'       => $director,
			'date_published' => $date_published,
			'season_number'  => $season_number,
			'episode_number' => $episode_number,
			'parent_show'    => $parent_show,
		);
	}

	/**
	 * Build dynamic title for movies/episodes/tv.
	 *
	 * @since 1.0.0
	 * @param int   $post_id Post ID.
	 * @param array $data SEO data.
	 * @return string
	 */
	private function build_dynamic_title( $post_id, $data ) {
		$site_name = get_bloginfo( 'name' );
		$title     = $data['title'];
		$year      = $data['year'];
		$type      = $data['post_type'];

		if ( 'movies' === $type ) {
			/* translators: %1$s title, %2$s year */
			$base = sprintf( __( 'Watch %1$s (%2$s) - 1080p & Sinhala Subtitles Free Download', 'cinecore' ), $title, $year );
		} elseif ( 'tv_shows' === $type ) {
			/* translators: %1$s title, %2$s year */
			$base = sprintf( __( 'Watch %1$s TV Series (%2$s) - All Seasons Sinhala Subtitles', 'cinecore' ), $title, $year );
		} elseif ( 'episodes' === $type ) {
			$season = $data['season_number'] ? 'S' . str_pad( $data['season_number'], 2, '0', STR_PAD_LEFT ) : '';
			$ep     = $data['episode_number'] ? 'E' . str_pad( $data['episode_number'], 2, '0', STR_PAD_LEFT ) : '';
			$prefix = trim( $season . $ep );
			if ( '' !== $prefix && '' !== $data['parent_show'] ) {
				/* translators: %1$s show, %2$s SxxExx, %3$s episode */
				$base = sprintf( __( 'Watch %1$s %2$s - %3$s Sinhala Subtitles', 'cinecore' ), $data['parent_show'], $prefix, $title );
			} elseif ( '' !== $prefix ) {
				/* translators: %1$s SxxExx, %2$s title */
				$base = sprintf( __( 'Watch %1$s - %2$s Sinhala Subtitles', 'cinecore' ), $prefix, $title );
			} else {
				/* translators: %s title */
				$base = sprintf( __( 'Watch %s - Sinhala Subtitles', 'cinecore' ), $title );
			}
		} else {
			return '';
		}

		// Append site name if not already present.
		if ( false === strpos( $base, $site_name ) ) {
			$base .= ' | ' . $site_name;
		}
		return $base;
	}

	/**
	 * Output meta description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function output_meta_description() {
		if ( ! is_singular( array( 'movies', 'tv_shows', 'episodes', 'seasons' ) ) ) {
			return;
		}
		if ( $this->is_other_seo_active() ) {
			return;
		}
		$post_id = get_queried_object_id();
		$data    = $this->get_seo_data( $post_id );

		// Allow custom SEO description via meta.
		$custom = get_post_meta( $post_id, '_cinecore_seo_description', true );
		$custom = $custom ? sanitize_text_field( $custom ) : '';

		if ( '' !== $custom ) {
			$desc = $custom;
		} elseif ( '' !== $data['overview'] ) {
			$desc = wp_trim_words( $data['overview'], 22, '...' );
			if ( 'movies' === $data['post_type'] ) {
				$desc = sprintf( __( 'Watch/Download %1$s (%2$s) in 1080p, 720p with Sinhala & English subtitles. %3$s', 'cinecore' ), $data['title'], $data['year'], $desc );
			} elseif ( 'tv_shows' === $data['post_type'] ) {
				$desc = sprintf( __( 'Watch %1$s TV Series (%2$s) - All Seasons & Episodes with Sinhala Subtitles. %2$s', 'cinecore' ), $data['title'], $data['year'] );
				$desc = $data['overview'] ? $desc . ' ' . wp_trim_words( $data['overview'], 18, '...' ) : $desc;
			} else {
				$desc = $data['overview'];
			}
		} else {
			$desc = sprintf( __( 'Watch %1$s (%2$s) on %3$s - High quality streaming & downloads.', 'cinecore' ), $data['title'], $data['year'], get_bloginfo( 'name' ) );
		}

		$desc = sanitize_text_field( wp_trim_words( $desc, 30, '...' ) );
		$desc = mb_substr( $desc, 0, 160 );

		echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
	}

	/**
	 * Output OG & Twitter Cards.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function output_og_twitter() {
		if ( ! is_singular( array( 'movies', 'tv_shows', 'episodes', 'seasons', 'subtitle_requests' ) ) ) {
			return;
		}
		if ( $this->is_other_seo_active() ) {
			return;
		}
		$post_id = get_queried_object_id();
		$data    = $this->get_seo_data( $post_id );

		$image = $data['poster'] ? $data['poster'] : $data['backdrop'];
		if ( '' === $image && has_post_thumbnail( $post_id ) ) {
			$image = get_the_post_thumbnail_url( $post_id, 'large' );
		}
		$image = $image ? esc_url( $image ) : esc_url( get_site_icon_url( 512 ) );

		$desc = $data['overview'] ? $data['overview'] : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 22, '...' );
		$desc = sanitize_text_field( $desc );
		$desc = mb_substr( $desc, 0, 160 );

		$og_type = 'video.movie';
		if ( 'tv_shows' === $data['post_type'] ) {
			$og_type = 'video.tv_show';
		} elseif ( 'episodes' === $data['post_type'] ) {
			$og_type = 'video.episode';
		}

		// OG.
		echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $data['title'] . ( $data['year'] ? ' (' . $data['year'] . ')' : '' ) ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $data['permalink'] ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
			echo '<meta property="og:image:secure_url" content="' . esc_url( $image ) . '" />' . "\n";
			echo '<meta property="og:image:width" content="500" />' . "\n";
			echo '<meta property="og:image:height" content="750" />' . "\n";
		}

		// Twitter.
		echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $data['title'] . ( $data['year'] ? ' (' . $data['year'] . ')' : '' ) ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		if ( $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" />' . "\n";
		}
		echo '<meta name="twitter:site" content="@nexcine" />' . "\n";
	}

	/**
	 * Output JSON-LD Schema.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function output_json_ld() {
		if ( ! is_singular( array( 'movies', 'tv_shows', 'episodes' ) ) ) {
			return;
		}
		if ( $this->is_other_seo_active() ) {
			return;
		}
		$post_id = get_queried_object_id();
		$data    = $this->get_seo_data( $post_id );

		$image = $data['poster'] ? $data['poster'] : $data['backdrop'];
		if ( '' === $image && has_post_thumbnail( $post_id ) ) {
			$image = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		$schema = array(
			'@context' => 'https://schema.org',
		);

		// Common aggregateRating.
		$aggregate = null;
		if ( $data['rating'] > 0 ) {
			$aggregate = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => number_format( (float) $data['rating'], 1 ),
				'bestRating'  => '10',
				'worstRating' => '1',
				'ratingCount' => '1',
			);
			// Try to get real count if stored.
			$vote_count = get_post_meta( $post_id, '_cinecore_vote_count', true );
			if ( '' !== $vote_count && (int) $vote_count > 0 ) {
				$aggregate['ratingCount'] = (string) absint( $vote_count );
			}
		}

		if ( 'movies' === $data['post_type'] ) {
			$schema['@type'] = 'Movie';
			$schema['name'] = $data['title'];
			if ( $image ) {
				$schema['image'] = $image;
			}
			if ( '' !== $data['overview'] ) {
				$schema['description'] = $data['overview'];
			}
			if ( '' !== $data['date_published'] ) {
				$schema['datePublished'] = $data['date_published'];
			}
			if ( $data['runtime'] > 0 ) {
				$schema['duration'] = 'PT' . (int) $data['runtime'] . 'M';
			}
			if ( '' !== $data['director'] ) {
				$schema['director'] = array(
					'@type' => 'Person',
					'name'  => $data['director'],
				);
			}
			if ( $aggregate ) {
				$schema['aggregateRating'] = $aggregate;
			}
			$schema['url'] = $data['permalink'];
		} elseif ( 'tv_shows' === $data['post_type'] ) {
			$schema['@type'] = 'TVSeries';
			$schema['name'] = $data['title'];
			if ( $image ) {
				$schema['image'] = $image;
			}
			if ( '' !== $data['overview'] ) {
				$schema['description'] = $data['overview'];
			}
			if ( '' !== $data['date_published'] ) {
				$schema['datePublished'] = $data['date_published'];
			}
			if ( $aggregate ) {
				$schema['aggregateRating'] = $aggregate;
			}
			// Number of seasons if we can count.
			$season_count = get_post_meta( $post_id, '_cinecore_season_count', true );
			if ( '' === $season_count ) {
				// Count via query fallback.
				$season_q = new WP_Query(
					array(
						'post_type'      => 'seasons',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
							array(
								'key'   => '_cinecore_tv_show_id',
								'value' => $post_id,
							),
						),
					)
				);
				if ( $season_q->post_count > 0 ) {
					$season_count = $season_q->post_count;
				}
			}
			if ( '' !== $season_count && (int) $season_count > 0 ) {
				$schema['numberOfSeasons'] = (int) $season_count;
			}
			$schema['url'] = $data['permalink'];
		} elseif ( 'episodes' === $data['post_type'] ) {
			$schema['@type'] = 'TVEpisode';
			$schema['name'] = $data['title'];
			if ( '' !== $data['episode_number'] ) {
				$schema['episodeNumber'] = (int) $data['episode_number'];
			}
			if ( '' !== $data['season_number'] ) {
				$schema['partOfSeason'] = array(
					'@type'       => 'TVSeason',
					'seasonNumber'=> (int) $data['season_number'],
				);
				if ( '' !== $data['parent_show'] ) {
					$schema['partOfSeason']['partOfSeries'] = array(
						'@type' => 'TVSeries',
						'name'  => $data['parent_show'],
					);
				}
			} elseif ( '' !== $data['parent_show'] ) {
				$schema['partOfSeries'] = array(
					'@type' => 'TVSeries',
					'name'  => $data['parent_show'],
				);
			}
			if ( $image ) {
				$schema['image'] = $image;
			}
			if ( '' !== $data['overview'] ) {
				$schema['description'] = $data['overview'];
			}
			if ( '' !== $data['date_published'] ) {
				$schema['datePublished'] = $data['date_published'];
			}
			if ( $data['runtime'] > 0 ) {
				$schema['timeRequired'] = 'PT' . (int) $data['runtime'] . 'M';
			}
			if ( $aggregate ) {
				$schema['aggregateRating'] = $aggregate;
			}
			$schema['url'] = $data['permalink'];
		}

		// Only output if we have at least name.
		if ( empty( $schema['name'] ) ) {
			return;
		}

		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( ! $json ) {
			return;
		}
		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Filter document_title_parts for optimized titles.
	 *
	 * @since 1.0.0
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function filter_title_parts( $parts ) {
		if ( is_admin() || ! is_singular( array( 'movies', 'tv_shows', 'episodes' ) ) ) {
			return $parts;
		}
		if ( $this->is_other_seo_active() ) {
			return $parts;
		}
		$post_id = get_queried_object_id();
		$data    = $this->get_seo_data( $post_id );
		$new_title = $this->build_dynamic_title( $post_id, $data );
		if ( '' !== $new_title ) {
			// Replace title, keep site/tagline handling.
			$parts['title'] = $new_title;
			// Unset site to avoid duplication since we already appended site name.
			if ( isset( $parts['site'] ) ) {
				unset( $parts['site'] );
			}
		}
		return $parts;
	}

	/**
	 * Fallback for pre_get_document_title (WP 4.4+).
	 *
	 * @since 1.0.0
	 * @param string $title Current title.
	 * @return string
	 */
	public function filter_document_title( $title ) {
		if ( is_admin() || ! is_singular( array( 'movies', 'tv_shows', 'episodes' ) ) ) {
			return $title;
		}
		if ( $this->is_other_seo_active() ) {
			return $title;
		}
		// Let document_title_parts handle it; this is fallback if parts filter missed.
		return $title;
	}
}

