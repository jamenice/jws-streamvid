<?php

/**
 * "Content Badges" — a taxonomy shared by movies, tv_shows and drama, so the
 * poster labels (4K, ENSUB, NEW, Trending…) are picked from one site-wide
 * list instead of retyped per post, and each label can have its own color.
 *
 * Started out as a free-text field ({@see jws_mb_field_badges()} in an
 * earlier revision); moved to a taxonomy for the same reason genres/topics/
 * countries are taxonomies here — one editable vocabulary, real term ids, and
 * `Jws_Metabox`'s existing pill picker needs no new code to support it.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

const JWS_CONTENT_BADGE_TAX = 'content_badge';

add_action( 'init', 'jws_content_badges_register', 20 );
add_action( 'init', 'jws_content_badges_seed_and_migrate', 21 );
add_filter( 'manage_edit-' . JWS_CONTENT_BADGE_TAX . '_columns', 'jws_content_badges_column' );
add_filter( 'manage_' . JWS_CONTENT_BADGE_TAX . '_custom_column', 'jws_content_badges_column_content', 10, 3 );

function jws_content_badges_register() {
	$labels = array(
		'name'                  => _x( 'Content Badges', 'Taxonomy plural name', 'jws_streamvid' ),
		'singular_name'         => _x( 'Badge', 'Taxonomy singular name', 'jws_streamvid' ),
		'search_items'          => esc_html__( 'Search Badges', 'jws_streamvid' ),
		'all_items'             => esc_html__( 'All Badges', 'jws_streamvid' ),
		'edit_item'             => esc_html__( 'Edit Badge', 'jws_streamvid' ),
		'update_item'           => esc_html__( 'Update Badge', 'jws_streamvid' ),
		'add_new_item'          => esc_html__( 'Add New Badge', 'jws_streamvid' ),
		'new_item_name'         => esc_html__( 'New Badge', 'jws_streamvid' ),
		'add_or_remove_items'   => esc_html__( 'Add or remove badges', 'jws_streamvid' ),
		'choose_from_most_used' => esc_html__( 'Choose from the most used badges', 'jws_streamvid' ),
		'not_found'             => esc_html__( 'No badges found.', 'jws_streamvid' ),
		'menu_name'             => esc_html__( 'Content Badges', 'jws_streamvid' ),
	);

	register_taxonomy(
		JWS_CONTENT_BADGE_TAX,
		array( 'movies', 'tv_shows', class_exists( 'Jws_Drama_Post_Types' ) ? Jws_Drama_Post_Types::DRAMA : 'drama' ),
		array(
			'hierarchical'      => false, // Labels, not categories — no parent/child.
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => false, // The color swatch column below replaces the default term-list one.
			'query_var'         => true,
			'rewrite'           => false, // No public archive page for a badge.
		)
	);

	Jws_Metabox::register_term(
		'content_badge_terms',
		array(
			'title'      => __( 'Badge color', 'jws_streamvid' ),
			'taxonomies' => array( JWS_CONTENT_BADGE_TAX ),
			// Same switch as every other taxonomy field (genres, topics…) — already on if those are.
			'fields'     => array(
				array(
					'type'    => 'color',
					'name'    => 'badge_color',
					'key'     => 'field_content_badge_color',
					'label'   => __( 'Pill color', 'jws_streamvid' ),
					'default' => '#2271b1',
					'desc'    => __( 'Background of the pill on the poster. Text is always white, so pick a color dark or saturated enough to stay readable.', 'jws_streamvid' ),
				),
				array(
					'type'    => 'toggle',
					'name'    => 'show_in_meta_info',
					'key'     => 'field_content_badge_show_meta_info',
					'label'   => __( 'Show in the detail-page info row', 'jws_streamvid' ),
					'default' => '0',
					'desc'    => __( 'Off by default — the badge still shows on the poster corner either way. Turn this on to also repeat it next to IMDB/year/duration on the single page.', 'jws_streamvid' ),
				),
			),
		)
	);
}

/** Starter vocabulary so the taxonomy isn't empty on first use, and each comes with a sensible color. */
function jws_content_badges_defaults() {
	return array(
		'4K'        => '#2271b1',
		'HDR'       => '#0f766e',
		'Full HD'   => '#2271b1',
		'VietSub'   => '#7c3aed',
		'EngSub'    => '#7c3aed',
		'NEW'       => '#16a34a',
		'Trending'  => '#ea580c',
		'Exclusive' => '#b45309',
		'Uncut'     => '#b91c1c',
	);
}

/**
 * One-time, idempotent: seed the starter badges above, then fold any post
 * still holding the old free-text `content_badges` postmeta (from before this
 * became a taxonomy) into real term assignments. Runs once per site — guarded
 * by an option — then never touches the database again.
 */
function jws_content_badges_seed_and_migrate() {
	if ( get_option( 'jws_content_badges_migrated' ) || ! taxonomy_exists( JWS_CONTENT_BADGE_TAX ) ) {
		return;
	}

	foreach ( jws_content_badges_defaults() as $name => $color ) {
		if ( term_exists( $name, JWS_CONTENT_BADGE_TAX ) ) {
			continue;
		}
		$term = wp_insert_term( $name, JWS_CONTENT_BADGE_TAX );
		if ( ! is_wp_error( $term ) ) {
			update_term_meta( $term['term_id'], 'badge_color', $color );
		}
	}

	global $wpdb;
	$rows = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'content_badges'" );

	foreach ( $rows as $row ) {
		$labels = maybe_unserialize( $row->meta_value );
		if ( is_array( $labels ) && $labels ) {
			$term_ids = array();
			foreach ( $labels as $label ) {
				$label = trim( (string) $label );
				if ( '' === $label ) {
					continue;
				}
				$existing = term_exists( $label, JWS_CONTENT_BADGE_TAX );
				$term_id  = $existing ? (int) $existing['term_id'] : 0;
				if ( ! $term_id ) {
					$created = wp_insert_term( $label, JWS_CONTENT_BADGE_TAX );
					$term_id = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
				}
				if ( $term_id ) {
					$term_ids[] = $term_id;
				}
			}
			if ( $term_ids ) {
				wp_set_object_terms( (int) $row->post_id, $term_ids, JWS_CONTENT_BADGE_TAX, true );
			}
		}
		delete_post_meta( (int) $row->post_id, 'content_badges' );
		delete_post_meta( (int) $row->post_id, '_content_badges' );
	}

	update_option( 'jws_content_badges_migrated', 1, false );
}

/* -------------------------------------------------------------------------- */
/* Front end                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * Badge chips for the single-page meta-info row
 * (themes/streamvid/template-parts/content/movies_v2/post-meta-info.php,
 * `.jws-meta-info-extra`). That row already gives every direct `<div>` child
 * a bordered pill look — same as `.video-imdb` — so each chip only needs to
 * override the border/text color to its own badge color; no new CSS.
 *
 * @param int $post_id
 * @return string HTML, empty when the post has no badges.
 */
function jws_content_badges_meta_info_html( $post_id ) {
	if ( ! taxonomy_exists( JWS_CONTENT_BADGE_TAX ) ) {
		return '';
	}
	$terms = get_the_terms( $post_id, JWS_CONTENT_BADGE_TAX );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return '';
	}

	$html = '';
	foreach ( $terms as $term ) {
		// The toggle defaults to off; only an explicit "1" (saved through the term box) shows a badge here.
		if ( '1' !== get_term_meta( $term->term_id, 'show_in_meta_info', true ) ) {
			continue;
		}
		$color = get_term_meta( $term->term_id, 'badge_color', true );
		$html .= sprintf(
			'<div class="video-badge-item"%s>%s</div>',
			$color ? ' style="border-color: ' . esc_attr( $color ) . '; background:' . esc_attr( $color ) . ';"' : '',
			esc_html( $term->name )
		);
	}
	return $html;
}

/* -------------------------------------------------------------------------- */
/* Admin term list: a color swatch instead of the default post-count column   */
/* -------------------------------------------------------------------------- */

function jws_content_badges_column( $columns ) {
	$columns['badge_preview'] = __( 'Preview', 'jws_streamvid' );
	unset( $columns['posts'] );
	return $columns;
}

function jws_content_badges_column_content( $content, $column, $term_id ) {
	if ( 'badge_preview' !== $column ) {
		return $content;
	}
	$color = get_term_meta( $term_id, 'badge_color', true );
	$color = $color ? $color : '#2271b1';
	$term  = get_term( $term_id, JWS_CONTENT_BADGE_TAX );
	return sprintf(
		'<span style="display:inline-block;padding:2px 10px;border-radius:4px;background:%s;color:#fff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.02em;">%s</span>',
		esc_attr( $color ),
		esc_html( $term && ! is_wp_error( $term ) ? $term->name : '' )
	);
}
