<?php

/**
 * Reusable metabox system — the replacement for the ACF groups on movies,
 * tv shows, videos, drama and episodes.
 *
 * A box is declared once with Jws_Metabox::register() and the class renders,
 * saves and reads it. Values are stored in the exact layout ACF uses
 * (`name` + `_name` => field key, repeaters as `name` = row count and
 * `name_{i}_{sub}` per cell, relationships as arrays of numeric strings), so
 * the theme, the REST API and every `get_field()` call keep working while both
 * systems live side by side, and switching a post type back to ACF loses
 * nothing.
 *
 * Which post types use it is decided in Jws_Metabox_Settings; a box whose post
 * type is not switched on is simply never added. Term boxes (register_term())
 * work the same way on the taxonomy add/edit screens, storing term meta, and
 * user boxes (register_user()) on the profile screens, storing user meta.
 * A field with `formats` => [...] only shows for those post formats.
 *
 * Field types: tab, text, textarea, number, select, toggle, image, file,
 * heading (a section title), url, date, color, user, gallery, posts, taxonomy, group, repeater (layout "rows" or "seasons"), html (output of a
 * `render` callback, nothing stored). A field with `virtual` => true is
 * rendered but never stored — the box's on_save callback gets its value.
 * `conditions` is one { field, value } rule or a list of them (all must match).
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Metabox {

	const INPUT         = 'jws_mb';
	const NONCE_ACTION  = 'jws_metabox_save';
	const AJAX_NONCE    = 'jws_metabox_ajax';
	const ASSET_VERSION = '1.3.2';

	/** @var array Registered boxes keyed by id. */
	private static $boxes = array();

	/** @var array Registered term boxes keyed by id. */
	private static $term_boxes = array();

	/** @var array Registered user boxes keyed by id. */
	private static $user_boxes = array();

	/** @var string Meta type the value helpers read and write: post|term. */
	private static $meta_type = 'post';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_jws_mb_search_posts', array( __CLASS__, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_jws_mb_search_terms', array( __CLASS__, 'ajax_search_terms' ) );
		add_action( 'wp_ajax_jws_mb_create_posts', array( __CLASS__, 'ajax_create_posts' ) );
		add_action( 'wp_ajax_jws_mb_search_users', array( __CLASS__, 'ajax_search_users' ) );
		add_action( 'init', array( __CLASS__, 'hook_terms' ), 99 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_after_title_boxes' ) );
		self::hook_users();
	}

	/** Run $callback with the value helpers pointed at another meta type. */
	public static function with_meta_type( $type, $callback ) {
		$previous        = self::$meta_type;
		self::$meta_type = $type;
		try {
			return $callback();
		} finally {
			self::$meta_type = $previous;
		}
	}

	private static function meta_get( $id, $key ) {
		return get_metadata( self::$meta_type, $id, $key, true );
	}

	private static function meta_exists( $id, $key ) {
		return $id && metadata_exists( self::$meta_type, $id, $key );
	}

	private static function meta_set( $id, $key, $value ) {
		return update_metadata( self::$meta_type, $id, $key, $value );
	}

	private static function meta_delete( $id, $key ) {
		return delete_metadata( self::$meta_type, $id, $key );
	}

	/**
	 * @param string $id   Unique box id.
	 * @param array  $args {
	 *     @type string   $title      Box title.
	 *     @type string[] $post_types Post types the box shows on.
	 *     @type string   $context    normal|side.
	 *     @type string[] $acf_groups ACF group keys this box replaces (hidden while it is active).
	 *     @type string[] $hide_boxes Core meta box ids to remove while it is active.
	 *     @type callable $on_save    fn( int $post_id, array $box, array $data ) after the values are stored; $data is the box's posted input.
	 *     @type array    $fields     Field definitions.
	 * }
	 */
	public static function register( $id, $args ) {
		self::$boxes[ $id ] = wp_parse_args(
			$args,
			array(
				'id'         => $id,
				'title'      => '',
				'post_types' => array(),
				'context'    => 'normal',
				'priority'   => 'high',
				'acf_groups' => array(),
				'hide_boxes' => array(),
				'on_save'    => null,
				'fields'     => array(),
			)
		);
	}

	/**
	 * A box on the add/edit screens of one or more taxonomies.
	 *
	 * @param string $id
	 * @param array  $args {
	 *     @type string   $title        Row heading on the edit screen.
	 *     @type string[] $taxonomies
	 *     @type string   $toggle       Meta System setting key that switches it on.
	 *     @type string   $toggle_label Label of that setting.
	 *     @type string[] $acf_groups   ACF group keys it replaces.
	 *     @type callable $on_save      fn( int $term_id, array $box, array $data ).
	 *     @type array    $fields
	 * }
	 */
	public static function register_term( $id, $args ) {
		self::$term_boxes[ $id ] = wp_parse_args(
			$args,
			array(
				'id'           => $id,
				'title'        => '',
				'taxonomies'   => array(),
				'toggle'       => 'taxonomy_fields',
				'toggle_label' => __( 'Taxonomy fields', 'jws_streamvid' ),
				'acf_groups'   => array(),
				'on_save'      => null,
				'fields'       => array(),
				'context'      => 'term',
			)
		);
	}

	/** A box on the user profile / edit / add screens. Same args as register_term() minus taxonomies. */
	public static function register_user( $id, $args ) {
		self::$user_boxes[ $id ] = wp_parse_args(
			$args,
			array(
				'id'           => $id,
				'title'        => '',
				'toggle'       => 'user_fields',
				'toggle_label' => __( 'User profile fields', 'jws_streamvid' ),
				'acf_groups'   => array(),
				'on_save'      => null,
				'fields'       => array(),
				'context'      => 'user',
			)
		);
	}

	/** Setting keys of the term and user boxes => label. */
	public static function term_toggles() {
		$out = array();
		foreach ( array_merge( self::$term_boxes, self::$user_boxes ) as $box ) {
			$out[ $box['toggle'] ] = $box['toggle_label'];
		}
		return $out;
	}

	public static function active_user_boxes() {
		return array_filter(
			self::$user_boxes,
			function ( $box ) {
				return Jws_Metabox_Settings::is_enabled( $box['toggle'] );
			}
		);
	}

	public static function user_fields() {
		$fields = array();
		foreach ( self::$user_boxes as $box ) {
			foreach ( $box['fields'] as $field ) {
				if ( ! in_array( $field['type'], array( 'tab', 'html', 'heading' ), true ) && empty( $field['virtual'] ) ) {
					$fields[] = self::field_defaults( $field );
				}
			}
		}
		return $fields;
	}

	public static function active_term_boxes( $taxonomy ) {
		return array_filter(
			self::$term_boxes,
			function ( $box ) use ( $taxonomy ) {
				return in_array( $taxonomy, $box['taxonomies'], true ) && Jws_Metabox_Settings::is_enabled( $box['toggle'] );
			}
		);
	}

	public static function term_taxonomies() {
		$out = array();
		foreach ( self::$term_boxes as $box ) {
			$out = array_merge( $out, $box['taxonomies'] );
		}
		return array_values( array_unique( $out ) );
	}

	/** Term box fields for a taxonomy (switched on or not). */
	public static function term_fields_for( $taxonomy ) {
		$fields = array();
		foreach ( self::$term_boxes as $box ) {
			if ( in_array( $taxonomy, $box['taxonomies'], true ) ) {
				foreach ( $box['fields'] as $field ) {
					if ( ! in_array( $field['type'], array( 'tab', 'html', 'heading' ), true ) && empty( $field['virtual'] ) ) {
						$fields[] = self::field_defaults( $field );
					}
				}
			}
		}
		return $fields;
	}

	/** Every box switched on anywhere (post and term). */
	private static function all_active_boxes() {
		$boxes = array();
		foreach ( self::$boxes as $id => $box ) {
			foreach ( $box['post_types'] as $pt ) {
				if ( Jws_Metabox_Settings::is_enabled( $pt ) ) {
					$boxes[ $id ] = $box;
				}
			}
		}
		foreach ( self::$term_boxes as $id => $box ) {
			if ( Jws_Metabox_Settings::is_enabled( $box['toggle'] ) ) {
				$boxes[ 'term:' . $id ] = $box;
			}
		}
		foreach ( self::active_user_boxes() as $id => $box ) {
			$boxes[ 'user:' . $id ] = $box;
		}
		return $boxes;
	}

	/** Boxes currently switched on for a post type. */
	public static function active_boxes( $post_type ) {
		if ( ! Jws_Metabox_Settings::is_enabled( $post_type ) ) {
			return array();
		}
		return array_filter(
			self::$boxes,
			function ( $box ) use ( $post_type ) {
				return in_array( $post_type, $box['post_types'], true );
			}
		);
	}

	/** Post types that have at least one registered box (i.e. can be switched on). */
	public static function supported_post_types() {
		$types = array();
		foreach ( self::$boxes as $box ) {
			$types = array_merge( $types, $box['post_types'] );
		}
		return array_values( array_unique( $types ) );
	}

	/** ACF group keys that must be hidden for a post type. */
	public static function replaced_acf_groups( $post_type = null ) {
		$keys = array();
		foreach ( self::$boxes as $box ) {
			foreach ( $box['post_types'] as $pt ) {
				if ( ( null === $post_type || $pt === $post_type ) && Jws_Metabox_Settings::is_enabled( $pt ) ) {
					$keys = array_merge( $keys, $box['acf_groups'] );
				}
			}
		}
		foreach ( array_merge( self::$term_boxes, self::$user_boxes ) as $box ) {
			if ( null === $post_type && Jws_Metabox_Settings::is_enabled( $box['toggle'] ) ) {
				$keys = array_merge( $keys, $box['acf_groups'] );
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Registration on the edit screen                                        */
	/* ---------------------------------------------------------------------- */

	public static function add_meta_boxes( $post_type, $post ) {
		if ( ! Jws_Acf_Compat::acf_active() ) {
			add_filter( "get_user_option_meta-box-order_{$post_type}", array( __CLASS__, 'merge_after_title_order' ) );
		}
		foreach ( self::active_boxes( $post_type ) as $box ) {
			add_meta_box(
				'jws-mb-' . $box['id'],
				$box['title'],
				array( __CLASS__, 'render_box' ),
				$post_type,
				$box['context'],
				$box['priority'],
				array( 'box' => $box )
			);
			add_filter( "postbox_classes_{$post_type}_jws-mb-{$box['id']}", array( __CLASS__, 'postbox_classes' ) );
			foreach ( $box['hide_boxes'] as $core_box ) {
				remove_meta_box( $core_box, $post_type, 'side' );
				remove_meta_box( $core_box, $post_type, 'normal' );
			}
		}
	}

	public static function postbox_classes( $classes ) {
		$classes[] = 'jws-mb-postbox';
		return $classes;
	}

	/**
	 * A box the user once dragged into ACF's own "after title" position is
	 * remembered there in `meta-box-order_{post_type}`, and do_meta_boxes()
	 * replays that saved order over the context the box was registered with.
	 * Only ACF ever prints that context, so with ACF gone the box would stay
	 * registered and never be output. Fold those ids back into `normal`, the
	 * way ACF itself does on the block editor screen.
	 */
	public static function merge_after_title_order( $order ) {
		if ( ! is_array( $order ) || empty( $order['acf_after_title'] ) ) {
			return $order;
		}
		$after           = $order['acf_after_title'];
		$normal          = isset( $order['normal'] ) ? $order['normal'] : '';
		$order['normal'] = $normal ? $after . ',' . $normal : $after;
		unset( $order['acf_after_title'] );
		return $order;
	}

	/** Print anything still registered in ACF's context while ACF is away. */
	public static function render_after_title_boxes( $post ) {
		global $wp_meta_boxes;
		$screen = get_current_screen();
		if ( ! $screen || Jws_Acf_Compat::acf_active() || empty( $wp_meta_boxes[ $screen->id ]['acf_after_title'] ) ) {
			return;
		}
		do_meta_boxes( $screen, 'acf_after_title', $post );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			if ( ! self::active_boxes( $screen->post_type ) ) {
				return;
			}
		} elseif ( in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			if ( ! $screen->taxonomy || ! self::active_term_boxes( $screen->taxonomy ) ) {
				return;
			}
		} elseif ( in_array( $hook, array( 'profile.php', 'user-edit.php', 'user-new.php' ), true ) ) {
			if ( ! self::active_user_boxes() ) {
				return;
			}
		} else {
			return;
		}

		$base = plugin_dir_url( __FILE__ ) . 'assets/';
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style( 'jws-metabox', $base . 'metabox.css', array(), self::ASSET_VERSION );
		wp_enqueue_script( 'jws-metabox', $base . 'metabox.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), self::ASSET_VERSION, true );
		wp_localize_script(
			'jws-metabox',
			'JwsMetabox',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::AJAX_NONCE ),
				'postId'  => in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ? get_the_ID() : 0,
				'i18n'    => array(
					'select'        => __( 'Select', 'jws_streamvid' ),
					'change'        => __( 'Change', 'jws_streamvid' ),
					'remove'        => __( 'Remove', 'jws_streamvid' ),
					'noResults'     => __( 'No results found.', 'jws_streamvid' ),
					'loading'       => __( 'Loading…', 'jws_streamvid' ),
					'confirmRemove' => __( 'Remove this item?', 'jws_streamvid' ),
					'confirmSeason' => __( 'Remove this season? The episodes themselves are not deleted.', 'jws_streamvid' ),
					'inOtherShow'   => __( 'Already in: %s', 'jws_streamvid' ),
					'inThisShow'    => __( 'In this show: %s', 'jws_streamvid' ),
					'addSelected'   => __( 'Add %d selected', 'jws_streamvid' ),
					'createN'       => __( 'Create & add %d', 'jws_streamvid' ),
					'episodes'      => __( '%d episodes', 'jws_streamvid' ),
					'season'        => __( 'Season %d', 'jws_streamvid' ),
					'created'       => __( '%d episode(s) created.', 'jws_streamvid' ),
					'untitled'      => __( '(no title)', 'jws_streamvid' ),
					'newSeason'     => __( 'New season', 'jws_streamvid' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Rendering                                                              */
	/* ---------------------------------------------------------------------- */

	public static function render_box( $post, $metabox ) {
		self::render_fields( $post, $post->ID, $metabox['args']['box'] );
	}

	/**
	 * @param WP_Post|WP_Term|null $object Passed to html callbacks.
	 * @param int                  $id     Object id the values are read from (0 = new).
	 * @param array                $box
	 */
	public static function render_fields( $object, $id, $box ) {
		$meta_type = in_array( $box['context'], array( 'term', 'user' ), true ) ? $box['context'] : 'post';
		self::with_meta_type(
			$meta_type,
			function () use ( $object, $id, $box ) {
				self::render_fields_inner( $object, $id, $box );
			}
		);
	}

	private static function render_fields_inner( $post, $object_id, $box ) {
		$fields = $box['fields'];

		wp_nonce_field( self::NONCE_ACTION . $box['id'], '_jws_mb_nonce_' . $box['id'] );

		// Split into tabs; fields declared before the first tab form an untabbed panel.
		$panels = array();
		$panel  = array( 'label' => '', 'icon' => '', 'fields' => array() );
		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] ) {
				if ( $panel['fields'] || $panel['label'] ) {
					$panels[] = $panel;
				}
				$panel = array(
					'label'  => $field['label'],
					'icon'   => isset( $field['icon'] ) ? $field['icon'] : '',
					'fields' => array(),
				);
				continue;
			}
			$panel['fields'][] = $field;
		}
		$panels[] = $panel;

		$tabbed = count( $panels ) > 1;
		$base   = self::INPUT . '[' . $box['id'] . ']';

		echo '<div class="jws-mb' . ( $tabbed ? ' jws-mb--tabbed' : '' ) . ' jws-mb--' . esc_attr( $box['context'] ) . '" data-box="' . esc_attr( $box['id'] ) . '">';

		if ( $tabbed ) {
			echo '<ul class="jws-mb__tabs" role="tablist">';
			foreach ( $panels as $i => $p ) {
				printf(
					'<li><button type="button" role="tab" class="jws-mb__tab%s" data-tab="%d">%s%s</button></li>',
					0 === $i ? ' is-active' : '',
					(int) $i,
					$p['icon'] ? '<span class="dashicons ' . esc_attr( $p['icon'] ) . '"></span>' : '',
					esc_html( $p['label'] )
				);
			}
			echo '</ul>';
		}

		echo '<div class="jws-mb__panels">';
		foreach ( $panels as $i => $p ) {
			printf( '<div class="jws-mb__panel%s" data-panel="%d">', 0 === $i ? ' is-active' : '', (int) $i );
			echo '<div class="jws-mb__grid">';
			foreach ( $p['fields'] as $field ) {
				$value = ( ! empty( $field['virtual'] ) || in_array( $field['type'], array( 'html', 'heading' ), true ) ) ? ( isset( $field['default'] ) ? $field['default'] : '' ) : self::get_value( $object_id, $field );
				self::render_field( $field, $value, $base . '[' . $field['name'] . ']', $post );
			}
			echo '</div></div>';
		}
		echo '</div></div>';
	}

	private static function field_defaults( $field ) {
		return wp_parse_args(
			$field,
			array(
				'name'        => '',
				'key'         => '',
				'label'       => '',
				'desc'        => '',
				'placeholder' => '',
				'width'       => 100,
				'default'     => '',
				'conditions'  => array(),
				'choices'     => array(),
				'multiple'    => false,
				'post_type'   => array(),
				'taxonomy'    => '',
				'sub_fields'  => array(),
				'layout'      => 'rows',
				'attrs'       => array(),
				'virtual'     => false,
				'max'         => 0,
				'formats'     => array(),
				'store_format' => 'Ymd',
				'render'      => null,
			)
		);
	}

	/**
	 * @param array   $field
	 * @param mixed   $value Stored value (repeaters: list of row arrays).
	 * @param string  $input Input name.
	 * @param WP_Post $post
	 */
	public static function render_field( $field, $value, $input, $post ) {
		$field = self::field_defaults( $field );
		$type  = $field['type'];

		$wrap_attrs = array(
			'class'      => 'jws-mb__field jws-mb__field--' . $type,
			'data-name'  => $field['name'],
			'style'      => '--jws-mb-w:' . (int) $field['width'] . '%',
		);
		if ( $field['conditions'] ) {
			$wrap_attrs['data-conditions'] = wp_json_encode( $field['conditions'] );
		}
		if ( $field['formats'] ) {
			$wrap_attrs['data-formats'] = implode( ',', (array) $field['formats'] );
			$current                    = $post instanceof WP_Post ? get_post_format( $post ) : false;
			if ( ! in_array( $current ? $current : 'standard', (array) $field['formats'], true ) ) {
				$wrap_attrs['class'] .= ' is-format-hidden';
			}
		}

		echo '<div';
		foreach ( $wrap_attrs as $k => $v ) {
			echo ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		echo '>';

		$id = 'jws-mb-' . md5( $input );
		if ( $field['label'] && 'repeater' !== $type ) {
			printf( '<label class="jws-mb__label" for="%s">%s</label>', esc_attr( $id ), esc_html( $field['label'] ) );
		}

		switch ( $type ) {
			case 'text':
			case 'number':
			case 'url':
				$extra = '';
				foreach ( $field['attrs'] as $k => $v ) {
					$extra .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
				}
				printf(
					'<input type="%s" id="%s" class="jws-mb__input" name="%s" value="%s" placeholder="%s"%s>',
					esc_attr( $type ),
					esc_attr( $id ),
					esc_attr( $input ),
					esc_attr( $value ),
					esc_attr( $field['placeholder'] ),
					$extra // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
				);
				break;

			case 'date':
				// Stored in `store_format` (ACF: Ymd). Anything that does not parse stays editable as plain text.
				$raw    = (string) $value;
				$parsed = '' === $raw ? false : DateTime::createFromFormat( '!' . $field['store_format'], $raw );
				if ( '' === $raw || ( $parsed && $parsed->format( $field['store_format'] ) === $raw ) ) {
					printf(
						'<input type="date" id="%s" class="jws-mb__input" name="%s" value="%s">',
						esc_attr( $id ),
						esc_attr( $input ),
						$parsed ? esc_attr( $parsed->format( 'Y-m-d' ) ) : ''
					);
				} else {
					printf( '<input type="text" id="%s" class="jws-mb__input" name="%s" value="%s" placeholder="YYYY-MM-DD">', esc_attr( $id ), esc_attr( $input ), esc_attr( $raw ) );
				}
				break;

			case 'group':
				$value = is_array( $value ) ? $value : array();
				echo '<div class="jws-mb__group jws-mb__grid">';
				foreach ( $field['sub_fields'] as $sub ) {
					$sub = self::field_defaults( $sub );
					self::render_field( $sub, isset( $value[ $sub['name'] ] ) ? $value[ $sub['name'] ] : $sub['default'], $input . '[' . $sub['name'] . ']', $post );
				}
				echo '</div>';
				break;

			case 'textarea':
				printf(
					'<textarea id="%s" class="jws-mb__input" name="%s" rows="%d" placeholder="%s">%s</textarea>',
					esc_attr( $id ),
					esc_attr( $input ),
					isset( $field['rows'] ) ? (int) $field['rows'] : 3,
					esc_attr( $field['placeholder'] ),
					esc_textarea( $value )
				);
				break;

			case 'select':
				printf( '<select id="%s" class="jws-mb__input" name="%s">', esc_attr( $id ), esc_attr( $input ) );
				foreach ( $field['choices'] as $k => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, (string) $k, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'toggle':
				printf(
					'<label class="jws-mb__switch"><input type="hidden" name="%1$s" value="0"><input type="checkbox" id="%2$s" name="%1$s" value="1"%3$s><span class="jws-mb__switch-track"></span></label>',
					esc_attr( $input ),
					esc_attr( $id ),
					// Like ACF: any non-empty value counts as on (older data has "on", "yes"…).
					checked( ! empty( $value ), true, false )
				);
				break;

			case 'image':
			case 'file':
				self::render_media( $field, $value, $input, $id );
				break;

			case 'html':
				if ( is_callable( $field['render'] ) ) {
					call_user_func( $field['render'], $post, $field, $input );
				}
				break;

			case 'posts':
				self::render_posts( $field, $value, $input );
				break;

			case 'user':
				self::render_user( $field, $value, $input );
				break;

			case 'color':
				printf(
					'<input type="text" id="%s" class="jws-mb__color" name="%s" value="%s" data-default-color="%s">',
					esc_attr( $id ),
					esc_attr( $input ),
					esc_attr( $value ),
					esc_attr( $field['default'] )
				);
				break;

			case 'gallery':
				self::render_gallery( $field, $value, $input );
				break;

			case 'tags':
				self::render_tags( $field, $value, $input );
				break;

			case 'taxonomy':
				self::render_terms( $field, $value, $input );
				break;

			case 'repeater':
				if ( 'seasons' === $field['layout'] ) {
					self::render_seasons( $field, $value, $input, $post );
				} else {
					self::render_repeater( $field, $value, $input, $post );
				}
				break;
		}

		if ( $field['desc'] ) {
			printf( '<p class="jws-mb__desc">%s</p>', esc_html( $field['desc'] ) );
		}

		echo '</div>';
	}

	private static function render_media( $field, $value, $input, $id ) {
		$value   = absint( $value );
		$is_img  = 'image' === $field['type'];
		$preview = '';
		if ( $value ) {
			if ( $is_img ) {
				$src     = wp_get_attachment_image_url( $value, 'medium' );
				$preview = $src ? '<img src="' . esc_url( $src ) . '" alt="">' : '';
			} else {
				$preview = '<span class="dashicons dashicons-media-default"></span><span class="jws-mb__media-name">' . esc_html( wp_basename( (string) get_attached_file( $value ) ) ) . '</span>';
			}
		}
		$library = isset( $field['library'] ) ? $field['library'] : ( $is_img ? 'image' : '' );

		printf(
			'<div class="jws-mb__media%s" data-kind="%s" data-library="%s">',
			$value ? ' has-value' : '',
			esc_attr( $field['type'] ),
			esc_attr( $library )
		);
		printf( '<input type="hidden" id="%s" name="%s" value="%s" class="jws-mb__media-id">', esc_attr( $id ), esc_attr( $input ), $value ? esc_attr( $value ) : '' );
		echo '<div class="jws-mb__media-preview">' . $preview . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built escaped above.
		echo '<div class="jws-mb__media-actions">';
		printf( '<button type="button" class="button jws-mb__media-pick">%s</button>', $is_img ? esc_html__( 'Select image', 'jws_streamvid' ) : esc_html__( 'Select file', 'jws_streamvid' ) );
		printf( '<button type="button" class="button-link jws-mb__media-clear">%s</button>', esc_html__( 'Remove', 'jws_streamvid' ) );
		echo '</div></div>';
	}

	/** Compact data for a post shown in a picker chip / episode row. */
	public static function post_card( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$thumb = get_the_post_thumbnail_url( $post, 'thumbnail' );
		$card  = array(
			'id'     => $post->ID,
			'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status' => $post->post_status,
			'thumb'  => $thumb ? $thumb : '',
			'edit'   => get_edit_post_link( $post->ID, 'raw' ),
			'meta'   => '',
			'type'   => $post->post_type,
		);
		$type_obj           = get_post_type_object( $post->post_type );
		$card['type_label'] = $type_obj ? $type_obj->labels->singular_name : $post->post_type;
		if ( 'drama_ep' === $post->post_type ) {
			$number = (int) get_post_meta( $post->ID, 'drama_ep_number', true );
			$drama  = (int) get_post_meta( $post->ID, 'drama_id', true );
			$bits   = array();
			if ( $number ) {
				/* translators: %d: episode number */
				$bits[] = sprintf( __( 'Ep %d', 'jws_streamvid' ), $number );
			}
			$bits[]       = $drama ? html_entity_decode( get_the_title( $drama ), ENT_QUOTES, 'UTF-8' ) : __( 'No drama', 'jws_streamvid' );
			$card['meta'] = implode( ' · ', $bits );
		}
		if ( 'episodes' === $post->post_type ) {
			$number = get_post_meta( $post->ID, 'episodes_number', true );
			$time   = get_post_meta( $post->ID, 'videos_time', true );
			$bits   = array();
			if ( '' !== $number ) {
				/* translators: %s: episode number */
				$bits[] = sprintf( __( 'Ep %s', 'jws_streamvid' ), $number );
			}
			if ( $time ) {
				$bits[] = $time;
			}
			$card['meta'] = implode( ' · ', $bits );

			$show = (int) get_post_meta( $post->ID, 'tv_show_id', true );
			if ( $show ) {
				$card['show_id']    = $show;
				$card['show_title'] = html_entity_decode( get_the_title( $show ), ENT_QUOTES, 'UTF-8' );
				$card['season']     = (int) get_post_meta( $post->ID, 'season_number', true );
			}
		}
		return $card;
	}

	/** Load cards for many ids with primed caches, preserving order. */
	private static function post_cards( $ids ) {
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		_prime_post_caches( $ids, true, true );
		update_post_thumbnail_cache( new WP_Query( array( 'post__in' => $ids, 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => count( $ids ), 'no_found_rows' => true ) ) );
		$cards = array();
		foreach ( $ids as $id ) {
			$card = self::post_card( $id );
			if ( $card ) {
				$cards[] = $card;
			}
		}
		return $cards;
	}

	private static function render_posts( $field, $value, $input ) {
		$ids      = $field['multiple'] ? (array) $value : ( $value ? array( $value ) : array() );
		$cards    = self::post_cards( $ids );
		$multiple = (bool) $field['multiple'];

		printf(
			'<div class="jws-mb__posts%s" data-post-type="%s" data-multiple="%d" data-max="%d" data-input="%s">',
			$multiple ? ' is-multiple' : '',
			esc_attr( implode( ',', (array) $field['post_type'] ) ),
			$multiple ? 1 : 0,
			(int) $field['max'],
			esc_attr( $multiple ? $input . '[]' : $input )
		);
		if ( ! $multiple ) {
			// Guarantees the key is posted even when nothing is selected.
			printf( '<input type="hidden" name="%s" value="" class="jws-mb__posts-empty">', esc_attr( $input ) );
		} else {
			printf( '<input type="hidden" name="%s" value="">', esc_attr( $input . '[]' ) );
		}
		echo '<ul class="jws-mb__chips">';
		foreach ( $cards as $card ) {
			echo self::chip_html( $card, $multiple ? $input . '[]' : $input ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		}
		echo '</ul>';
		echo '<div class="jws-mb__search">';
		printf( '<input type="search" class="jws-mb__search-input" placeholder="%s" autocomplete="off">', esc_attr( $field['placeholder'] ? $field['placeholder'] : __( 'Search to add…', 'jws_streamvid' ) ) );
		echo '<div class="jws-mb__dropdown" hidden></div></div>';
		echo '</div>';
	}

	/**
	 * Free-text label chips — "4K", "ENSUB", "NEW", "Trending"… — stored as a
	 * plain array of strings under the field's own meta key. Not tied to a
	 * taxonomy: nothing to create, nothing shared across post types unless
	 * they use the same field name on purpose.
	 */
	private static function render_tags( $field, $value, $input ) {
		$values = array_values( array_filter( array_map( 'strval', (array) $value ), 'strlen' ) );
		$lower  = array_map( 'jws_mb_tags_lower', $values );

		$suggestions = array();
		foreach ( (array) $field['choices'] as $k => $v ) {
			$suggestions[] = (string) ( is_int( $k ) ? $v : $k );
		}
		$suggestions = array_merge( $suggestions, self::distinct_tag_values( $field['name'] ) );
		$seen        = array();
		foreach ( $suggestions as $i => $s ) {
			$s_lower = jws_mb_tags_lower( $s );
			if ( '' === $s_lower || isset( $seen[ $s_lower ] ) || in_array( $s_lower, $lower, true ) ) {
				unset( $suggestions[ $i ] );
				continue;
			}
			$seen[ $s_lower ] = true;
		}

		printf(
			'<div class="jws-mb__tags" data-input="%s" data-max="%d">',
			esc_attr( $input . '[]' ),
			(int) $field['max']
		);
		printf( '<input type="hidden" name="%s" value="">', esc_attr( $input . '[]' ) );
		echo '<ul class="jws-mb__chips">';
		foreach ( $values as $v ) {
			printf(
				'<li class="jws-mb__chip jws-mb__chip--term" data-value="%1$s"><input type="hidden" name="%2$s" value="%1$s"><span class="jws-mb__chip-title">%1$s</span><button type="button" class="jws-mb__chip-remove" aria-label="%3$s">&times;</button></li>',
				esc_attr( $v ),
				esc_attr( $input . '[]' ),
				esc_attr__( 'Remove', 'jws_streamvid' )
			);
		}
		echo '</ul>';
		printf(
			'<input type="text" class="jws-mb__tags-input" placeholder="%s" autocomplete="off" maxlength="40">',
			esc_attr( $field['placeholder'] ? $field['placeholder'] : __( 'Type a label, press Enter…', 'jws_streamvid' ) )
		);
		if ( $suggestions ) {
			echo '<div class="jws-mb__tags-suggest">';
			foreach ( $suggestions as $s ) {
				printf( '<button type="button" class="jws-mb__tag-suggest">%s</button>', esc_html( $s ) );
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Every distinct value already used for a "tags" field, so an editor sees
	 * the labels others have already typed instead of retyping "trending"
	 * with a different case. Cached briefly — this is a suggestion list, not
	 * a source of truth — and invalidated as soon as a save adds something new.
	 */
	private static function distinct_tag_values( $meta_key ) {
		$cache_key = 'jws_mb_tags_' . $meta_key;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' LIMIT 500",
				$meta_key
			)
		);

		$values = array();
		foreach ( $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row ) as $v ) {
				$v = trim( (string) $v );
				if ( '' !== $v ) {
					$values[ jws_mb_tags_lower( $v ) ] = $v;
				}
			}
		}
		$values = array_values( $values );
		sort( $values, SORT_STRING | SORT_FLAG_CASE );

		set_transient( $cache_key, $values, HOUR_IN_SECONDS );
		return $values;
	}

	public static function chip_html( $card, $input ) {
		return sprintf(
			'<li class="jws-mb__chip" data-id="%1$d"><input type="hidden" name="%2$s" value="%1$d">%3$s<span class="jws-mb__chip-title">%4$s</span>%5$s<button type="button" class="jws-mb__chip-remove" aria-label="%6$s">&times;</button></li>',
			(int) $card['id'],
			esc_attr( $input ),
			$card['thumb'] ? '<img src="' . esc_url( $card['thumb'] ) . '" alt="">' : '',
			esc_html( $card['title'] ),
			$card['meta'] ? '<small>' . esc_html( $card['meta'] ) . '</small>' : '',
			esc_attr__( 'Remove', 'jws_streamvid' )
		);
	}

	public static function user_card( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			// Keep the stored id visible (and saved) even if the account is gone.
			return (int) $user_id ? array(
				/* translators: %d: user id */
				'id'    => (int) $user_id,
				'title' => sprintf( __( 'Deleted user #%d', 'jws_streamvid' ), (int) $user_id ),
				'meta'  => '',
				'thumb' => '',
			) : null;
		}
		return array(
			'id'    => $user->ID,
			'title' => $user->display_name,
			'meta'  => $user->user_email,
			'thumb' => get_avatar_url( $user->ID, array( 'size' => 48 ) ),
		);
	}

	private static function render_user( $field, $value, $input ) {
		$card = $value ? self::user_card( $value ) : null;
		printf( '<div class="jws-mb__posts" data-source="users" data-multiple="0" data-input="%s">', esc_attr( $input ) );
		printf( '<input type="hidden" name="%s" value="">', esc_attr( $input ) );
		echo '<ul class="jws-mb__chips">';
		if ( $card ) {
			echo self::chip_html( $card, $input ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		}
		echo '</ul><div class="jws-mb__search">';
		printf( '<input type="search" class="jws-mb__search-input" placeholder="%s" autocomplete="off">', esc_attr__( 'Search users…', 'jws_streamvid' ) );
		echo '<div class="jws-mb__dropdown" hidden></div></div></div>';
	}

	private static function render_gallery( $field, $value, $input ) {
		$ids = array_filter( array_map( 'absint', is_array( $value ) ? $value : array() ) );
		printf(
			'<div class="jws-mb__gallery" data-input="%s" data-max="%d" data-library="%s">',
			esc_attr( $input . '[]' ),
			(int) $field['max'],
			esc_attr( isset( $field['library'] ) ? $field['library'] : 'image' )
		);
		printf( '<input type="hidden" name="%s" value="">', esc_attr( $input . '[]' ) );
		echo '<ul class="jws-mb__gallery-list">';
		foreach ( $ids as $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( ! $src ) {
				continue;
			}
			printf(
				'<li class="jws-mb__gallery-item" data-id="%1$d"><input type="hidden" name="%2$s" value="%1$d"><img src="%3$s" alt=""><button type="button" class="jws-mb__gallery-remove" aria-label="%4$s">&times;</button></li>',
				(int) $id,
				esc_attr( $input . '[]' ),
				esc_url( $src ),
				esc_attr__( 'Remove', 'jws_streamvid' )
			);
		}
		echo '</ul>';
		printf(
			'<button type="button" class="button jws-mb__gallery-add"><span class="dashicons dashicons-images-alt2"></span> %s</button> <span class="jws-mb__gallery-count">%d</span>',
			esc_html__( 'Add images', 'jws_streamvid' ),
			count( $ids )
		);
		echo '</div>';
	}

	/**
	 * Every term as a toggle pill — these taxonomies are small, so no search
	 * round trip. Hidden inputs carry the selection in the order it was made
	 * (the order ACF keeps).
	 */
	private static function render_terms( $field, $value, $input ) {
		$selected = array_values( array_filter( array_map( 'absint', (array) $value ) ) );
		$multiple = (bool) $field['multiple'];
		$taxonomy = get_taxonomy( $field['taxonomy'] );
		$terms    = $taxonomy ? get_terms( array( 'taxonomy' => $field['taxonomy'], 'hide_empty' => false, 'orderby' => 'name' ) ) : array();
		$terms    = is_wp_error( $terms ) ? array() : $terms;
		$can_add  = $taxonomy && current_user_can( $taxonomy->cap->edit_terms );

		// Parents first, children right after their parent.
		$by_parent = array();
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}
		$ordered = array();
		$walk    = function ( $parent, $depth ) use ( &$walk, &$ordered, $by_parent ) {
			foreach ( isset( $by_parent[ $parent ] ) ? $by_parent[ $parent ] : array() as $term ) {
				$ordered[] = array( $term, $depth );
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		if ( count( $ordered ) < count( $terms ) ) {
			$ordered = array_map(
				function ( $term ) {
					return array( $term, 0 );
				},
				$terms
			); // Orphaned parents: fall back to a flat list.
		}

		printf(
			'<div class="jws-mb__tax%s" data-multiple="%d" data-input="%s">',
			$multiple ? ' is-multiple' : ' is-single',
			$multiple ? 1 : 0,
			esc_attr( $input . '[]' )
		);
		printf( '<input type="hidden" name="%s" value="">', esc_attr( $input . '[]' ) );
		echo '<div class="jws-mb__tax-values">';
		foreach ( $selected as $id ) {
			printf( '<input type="hidden" name="%s" value="%d">', esc_attr( $input . '[]' ), (int) $id );
		}
		echo '</div>';

		if ( count( $terms ) > 12 || $can_add ) {
			echo '<div class="jws-mb__tax-tools">';
			if ( count( $terms ) > 12 ) {
				printf( '<input type="search" class="jws-mb__tax-filter" placeholder="%s" autocomplete="off">', esc_attr__( 'Filter…', 'jws_streamvid' ) );
			}
			printf(
				'<span class="jws-mb__tax-count" data-none="%s" data-some="%s"></span>',
				esc_attr__( 'None selected', 'jws_streamvid' ),
				/* translators: %d: number of selected terms */
				esc_attr__( '%d selected', 'jws_streamvid' )
			);
			printf( '<button type="button" class="button-link jws-mb__tax-clear">%s</button>', esc_html__( 'Clear', 'jws_streamvid' ) );
			echo '</div>';
		}

		echo '<div class="jws-mb__tax-list" role="group">';
		foreach ( $ordered as $item ) {
			list( $term, $depth ) = $item;
			$is_on                = in_array( (int) $term->term_id, $selected, true );
			printf(
				'<button type="button" class="jws-mb__tax-pill%s" data-id="%d" data-name="%s" aria-pressed="%s"%s>%s<span>%s</span>%s</button>',
				$is_on ? ' is-on' : '',
				(int) $term->term_id,
				esc_attr( function_exists( 'mb_strtolower' ) ? mb_strtolower( $term->name ) : strtolower( $term->name ) ),
				$is_on ? 'true' : 'false',
				$depth ? ' style="--jws-depth:' . (int) $depth . '"' : '',
				'<span class="jws-mb__tax-check" aria-hidden="true"></span>',
				esc_html( $term->name ),
				$term->count ? '<small>' . (int) $term->count . '</small>' : ''
			);
		}
		if ( ! $terms ) {
			printf( '<span class="jws-mb__desc">%s</span>', esc_html__( 'No terms yet.', 'jws_streamvid' ) );
		}
		if ( $can_add ) {
			printf(
				'<span class="jws-mb__tax-add"><input type="text" placeholder="%s" autocomplete="off"><button type="button" class="jws-mb__tax-add-btn" aria-label="%s"><span class="dashicons dashicons-plus-alt2"></span></button></span>',
				esc_attr__( 'New term', 'jws_streamvid' ),
				esc_attr__( 'Add term', 'jws_streamvid' )
			);
		}
		echo '</div></div>';
	}

	private static function render_repeater( $field, $rows, $input, $post ) {
		$rows      = is_array( $rows ) ? $rows : array();
		$row_title = isset( $field['row_title'] ) ? $field['row_title'] : '';
		$add_label = isset( $field['add_label'] ) ? $field['add_label'] : __( 'Add row', 'jws_streamvid' );

		printf( '<div class="jws-mb__repeater" data-max="%d" data-row-title="%s">', isset( $field['max'] ) ? (int) $field['max'] : 0, esc_attr( $row_title ) );
		echo '<div class="jws-mb__repeater-head">';
		if ( $field['label'] ) {
			printf( '<span class="jws-mb__label">%s</span>', esc_html( $field['label'] ) );
		}
		printf( '<span class="jws-mb__count">%d</span>', count( $rows ) );
		echo '</div>';
		// Present so an emptied repeater still posts and gets cleared.
		printf( '<input type="hidden" name="%s" value="">', esc_attr( $input . '[__present]' ) );
		echo '<ol class="jws-mb__rows">';
		foreach ( $rows as $row ) {
			self::render_repeater_row( $field, $row, $input . '[' . self::row_key() . ']', $post );
		}
		echo '</ol>';
		echo '<script type="text/html" class="jws-mb__tpl">';
		ob_start();
		self::render_repeater_row( $field, array(), $input . '[__ROW__]', $post );
		echo str_replace( '</script>', '<\/script>', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered escaped.
		echo '</script>';
		printf( '<button type="button" class="button jws-mb__add-row"><span class="dashicons dashicons-plus-alt2"></span> %s</button>', esc_html( $add_label ) );
		echo '</div>';
	}

	private static function render_repeater_row( $field, $row, $input, $post ) {
		echo '<li class="jws-mb__row">';
		echo '<div class="jws-mb__row-bar"><span class="jws-mb__handle dashicons dashicons-menu" title="' . esc_attr__( 'Drag to reorder', 'jws_streamvid' ) . '"></span>';
		echo '<span class="jws-mb__row-index"></span><span class="jws-mb__row-title"></span>';
		echo '<button type="button" class="jws-mb__row-toggle dashicons dashicons-arrow-up-alt2" aria-label="' . esc_attr__( 'Collapse', 'jws_streamvid' ) . '"></button>';
		echo '<button type="button" class="jws-mb__row-remove dashicons dashicons-trash" aria-label="' . esc_attr__( 'Remove', 'jws_streamvid' ) . '"></button></div>';
		echo '<div class="jws-mb__collapse"><div class="jws-mb__row-body jws-mb__grid">';
		foreach ( $field['sub_fields'] as $sub ) {
			$sub = self::field_defaults( $sub );
			$val = isset( $row[ $sub['name'] ] ) ? $row[ $sub['name'] ] : $sub['default'];
			self::render_field( $sub, $val, $input . '[' . $sub['name'] . ']', $post );
		}
		echo '</div></div></li>';
	}

	private static function row_key() {
		return 'r' . wp_generate_password( 8, false, false );
	}

	/**
	 * Seasons editor: one card per season, each holding a sortable episode
	 * list that episodes can be dragged between, plus the picker / quick
	 * create modal. Sub fields are fixed by name: season_thumbnail,
	 * season_name, episodes.
	 */
	private static function render_seasons( $field, $rows, $input, $post ) {
		$rows = is_array( $rows ) ? $rows : array();
		$ep_type = 'episodes';
		foreach ( $field['sub_fields'] as $sub ) {
			if ( 'episodes' === $sub['name'] && ! empty( $sub['post_type'] ) ) {
				$ep_type = (array) $sub['post_type'];
				$ep_type = $ep_type[0];
			}
		}

		$all_ids = array();
		foreach ( $rows as $row ) {
			$all_ids = array_merge( $all_ids, (array) ( isset( $row['episodes'] ) ? $row['episodes'] : array() ) );
		}
		$cards = array();
		foreach ( self::post_cards( $all_ids ) as $card ) {
			$cards[ $card['id'] ] = $card;
		}

		$total = 0;
		foreach ( $rows as $row ) {
			$total += count( array_filter( (array) ( isset( $row['episodes'] ) ? $row['episodes'] : array() ) ) );
		}

		printf( '<div class="jws-mb__seasons" data-post-type="%s" data-input="%s">', esc_attr( $ep_type ), esc_attr( $input ) );
		printf( '<input type="hidden" name="%s" value="">', esc_attr( $input . '[__present]' ) );

		echo '<div class="jws-mb__seasons-toolbar">';
		printf(
			'<div class="jws-mb__seasons-stats"><strong class="jws-mb__stat-seasons">%d</strong> %s · <strong class="jws-mb__stat-episodes">%d</strong> %s</div>',
			count( $rows ),
			esc_html__( 'seasons', 'jws_streamvid' ),
			(int) $total,
			esc_html__( 'episodes', 'jws_streamvid' )
		);
		echo '<div class="jws-mb__seasons-actions">';
		printf( '<button type="button" class="button-link jws-mb__seasons-collapse">%s</button>', esc_html__( 'Collapse all', 'jws_streamvid' ) );
		printf( '<button type="button" class="button button-primary jws-mb__season-add"><span class="dashicons dashicons-plus-alt2"></span> %s</button>', esc_html__( 'Add season', 'jws_streamvid' ) );
		echo '</div></div>';

		echo '<div class="jws-mb__season-list">';
		foreach ( $rows as $row ) {
			self::render_season( $row, $input . '[' . self::row_key() . ']', $cards );
		}
		echo '</div>';

		printf(
			'<div class="jws-mb__seasons-empty"%s><span class="dashicons dashicons-playlist-video"></span><p>%s</p><button type="button" class="button button-primary jws-mb__season-add">%s</button></div>',
			$rows ? ' hidden' : '',
			esc_html__( 'No seasons yet. Add the first season, then pick or create its episodes.', 'jws_streamvid' ),
			esc_html__( 'Add first season', 'jws_streamvid' )
		);

		echo '<script type="text/html" class="jws-mb__season-tpl">';
		ob_start();
		self::render_season( array(), $input . '[__ROW__]', array() );
		echo str_replace( '</script>', '<\/script>', ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput -- rendered escaped.
		echo '</script>';

		self::render_episode_modal();
		echo '</div>';
	}

	private static function render_season( $row, $input, $cards ) {
		$name     = isset( $row['season_name'] ) ? $row['season_name'] : '';
		$thumb_id = isset( $row['season_thumbnail'] ) ? absint( $row['season_thumbnail'] ) : 0;
		$episodes = array_filter( array_map( 'absint', (array) ( isset( $row['episodes'] ) ? $row['episodes'] : array() ) ) );
		$thumb    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';

		echo '<div class="jws-mb__season">';
		echo '<div class="jws-mb__season-head">';
		echo '<span class="jws-mb__handle dashicons dashicons-menu" title="' . esc_attr__( 'Drag to reorder seasons', 'jws_streamvid' ) . '"></span>';

		printf(
			'<div class="jws-mb__media jws-mb__season-thumb%s" data-kind="image" data-library="image"><input type="hidden" class="jws-mb__media-id" name="%s" value="%s"><div class="jws-mb__media-preview jws-mb__media-pick" title="%s">%s</div></div>',
			$thumb ? ' has-value' : '',
			esc_attr( $input . '[season_thumbnail]' ),
			$thumb_id ? esc_attr( $thumb_id ) : '',
			esc_attr__( 'Season thumbnail', 'jws_streamvid' ),
			$thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '<span class="dashicons dashicons-format-image"></span>'
		);

		echo '<div class="jws-mb__season-title">';
		printf( '<span class="jws-mb__season-no"></span>' );
		printf( '<input type="text" class="jws-mb__season-name" name="%s" value="%s" placeholder="%s">', esc_attr( $input . '[season_name]' ), esc_attr( $name ), esc_attr__( 'Season name', 'jws_streamvid' ) );
		echo '</div>';

		printf( '<span class="jws-mb__badge jws-mb__season-count">%d</span>', count( $episodes ) );
		echo '<div class="jws-mb__season-tools">';
		printf( '<button type="button" class="button jws-mb__episodes-open"><span class="dashicons dashicons-plus-alt2"></span> %s</button>', esc_html__( 'Add episodes', 'jws_streamvid' ) );
		printf( '<button type="button" class="jws-mb__icon-btn jws-mb__season-clear-thumb" title="%s"><span class="dashicons dashicons-format-image"></span></button>', esc_attr__( 'Remove thumbnail', 'jws_streamvid' ) );
		printf( '<button type="button" class="jws-mb__icon-btn jws-mb__season-remove" title="%s"><span class="dashicons dashicons-trash"></span></button>', esc_attr__( 'Remove season', 'jws_streamvid' ) );
		printf( '<button type="button" class="jws-mb__icon-btn jws-mb__season-toggle" title="%s"><span class="dashicons dashicons-arrow-up-alt2"></span></button>', esc_attr__( 'Collapse', 'jws_streamvid' ) );
		echo '</div></div>';

		echo '<div class="jws-mb__collapse"><div class="jws-mb__season-body">';
		printf( '<input type="hidden" class="jws-mb__episodes-empty" name="%s" value="">', esc_attr( $input . '[episodes][]' ) );
		printf( '<ol class="jws-mb__episodes" data-input="%s">', esc_attr( $input . '[episodes][]' ) );
		foreach ( $episodes as $ep_id ) {
			if ( isset( $cards[ $ep_id ] ) ) {
				echo self::episode_row_html( $cards[ $ep_id ], $input . '[episodes][]' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
			}
		}
		echo '</ol>';
		printf( '<div class="jws-mb__episodes-drop">%s</div>', esc_html__( 'No episodes — use “Add episodes” or drag episodes here from another season.', 'jws_streamvid' ) );
		echo '</div></div></div>';
	}

	public static function episode_row_html( $card, $input ) {
		$status = '';
		if ( 'publish' !== $card['status'] ) {
			$obj    = get_post_status_object( $card['status'] );
			$status = '<span class="jws-mb__status jws-mb__status--' . esc_attr( $card['status'] ) . '">' . esc_html( $obj ? $obj->label : $card['status'] ) . '</span>';
		}
		return sprintf(
			'<li class="jws-mb__episode" data-id="%1$d"><input type="hidden" name="%2$s" value="%1$d"><span class="jws-mb__handle dashicons dashicons-menu"></span><span class="jws-mb__ep-no"></span><span class="jws-mb__ep-thumb">%3$s</span><span class="jws-mb__ep-info"><a href="%4$s" target="_blank" class="jws-mb__ep-title">%5$s</a><small>%6$s</small></span>%7$s<span class="jws-mb__ep-tools"><a href="%4$s" target="_blank" class="jws-mb__icon-btn" title="%8$s"><span class="dashicons dashicons-edit"></span></a><button type="button" class="jws-mb__icon-btn jws-mb__episode-remove" title="%9$s"><span class="dashicons dashicons-no-alt"></span></button></span></li>',
			(int) $card['id'],
			esc_attr( $input ),
			$card['thumb'] ? '<img src="' . esc_url( $card['thumb'] ) . '" alt="" loading="lazy">' : '<span class="dashicons dashicons-format-video"></span>',
			esc_url( $card['edit'] ),
			esc_html( '' !== $card['title'] ? $card['title'] : __( '(no title)', 'jws_streamvid' ) ),
			esc_html( $card['meta'] . ' · #' . $card['id'] ),
			$status,
			esc_attr__( 'Edit episode', 'jws_streamvid' ),
			esc_attr__( 'Remove from season', 'jws_streamvid' )
		);
	}

	private static function render_episode_modal() {
		?>
		<div class="jws-mb__modal" hidden>
			<div class="jws-mb__modal-backdrop"></div>
			<div class="jws-mb__modal-dialog" role="dialog" aria-modal="true">
				<div class="jws-mb__modal-head">
					<h2><?php esc_html_e( 'Add episodes to', 'jws_streamvid' ); ?> <span class="jws-mb__modal-season"></span></h2>
					<button type="button" class="jws-mb__icon-btn jws-mb__modal-close" aria-label="<?php esc_attr_e( 'Close', 'jws_streamvid' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
				</div>
				<ul class="jws-mb__modal-tabs">
					<li><button type="button" class="is-active" data-mode="pick"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Pick existing', 'jws_streamvid' ); ?></button></li>
					<li><button type="button" data-mode="create"><span class="dashicons dashicons-welcome-add-page"></span> <?php esc_html_e( 'Quick create', 'jws_streamvid' ); ?></button></li>
				</ul>

				<div class="jws-mb__modal-pane" data-pane="pick">
					<div class="jws-mb__picker-filters">
						<input type="search" class="jws-mb__picker-search" placeholder="<?php esc_attr_e( 'Search by title or ID…', 'jws_streamvid' ); ?>">
						<select class="jws-mb__picker-scope">
							<option value="unassigned"><?php esc_html_e( 'Not in any show', 'jws_streamvid' ); ?></option>
							<option value="all"><?php esc_html_e( 'All episodes', 'jws_streamvid' ); ?></option>
							<option value="this"><?php esc_html_e( 'Linked to this show', 'jws_streamvid' ); ?></option>
						</select>
						<select class="jws-mb__picker-order">
							<option value="date"><?php esc_html_e( 'Newest first', 'jws_streamvid' ); ?></option>
							<option value="title"><?php esc_html_e( 'Title A→Z', 'jws_streamvid' ); ?></option>
						</select>
					</div>
					<div class="jws-mb__picker-bar">
						<label><input type="checkbox" class="jws-mb__picker-all"> <?php esc_html_e( 'Select all on this page', 'jws_streamvid' ); ?></label>
						<span class="jws-mb__picker-total"></span>
					</div>
					<ul class="jws-mb__picker-list"></ul>
					<div class="jws-mb__picker-pager">
						<button type="button" class="button jws-mb__picker-prev">&lsaquo;</button>
						<span class="jws-mb__picker-page"></span>
						<button type="button" class="button jws-mb__picker-next">&rsaquo;</button>
					</div>
				</div>

				<div class="jws-mb__modal-pane" data-pane="create" hidden>
					<div class="jws-mb__create-mode">
						<label><input type="radio" name="jws_mb_create_mode" value="range" checked> <?php esc_html_e( 'Numbered range', 'jws_streamvid' ); ?></label>
						<label><input type="radio" name="jws_mb_create_mode" value="list"> <?php esc_html_e( 'One title per line', 'jws_streamvid' ); ?></label>
					</div>
					<div class="jws-mb__create-range jws-mb__grid">
						<div class="jws-mb__field" style="--jws-mb-w:50%">
							<label class="jws-mb__label"><?php esc_html_e( 'Title pattern', 'jws_streamvid' ); ?></label>
							<input type="text" class="jws-mb__input jws-mb__create-pattern" value="{show} - S{season}E{n}">
							<p class="jws-mb__desc"><?php esc_html_e( 'Placeholders: {show}, {season}, {n}, {nn} (zero-padded).', 'jws_streamvid' ); ?></p>
						</div>
						<div class="jws-mb__field" style="--jws-mb-w:25%">
							<label class="jws-mb__label"><?php esc_html_e( 'From', 'jws_streamvid' ); ?></label>
							<input type="number" min="1" class="jws-mb__input jws-mb__create-from" value="1">
						</div>
						<div class="jws-mb__field" style="--jws-mb-w:25%">
							<label class="jws-mb__label"><?php esc_html_e( 'To', 'jws_streamvid' ); ?></label>
							<input type="number" min="1" class="jws-mb__input jws-mb__create-to" value="10">
						</div>
					</div>
					<div class="jws-mb__create-list" hidden>
						<textarea class="jws-mb__input jws-mb__create-titles" rows="8" placeholder="<?php esc_attr_e( "Pilot\nThe Second Episode\n…", 'jws_streamvid' ); ?>"></textarea>
					</div>
					<div class="jws-mb__create-options">
						<label><?php esc_html_e( 'Status', 'jws_streamvid' ); ?>
							<select class="jws-mb__create-status">
								<option value="draft"><?php esc_html_e( 'Draft', 'jws_streamvid' ); ?></option>
								<option value="publish"><?php esc_html_e( 'Published', 'jws_streamvid' ); ?></option>
							</select>
						</label>
						<label><input type="checkbox" class="jws-mb__create-number" checked> <?php esc_html_e( 'Fill “Episodes Number” with the position', 'jws_streamvid' ); ?></label>
						<label><input type="checkbox" class="jws-mb__create-thumb"> <?php esc_html_e( 'Use the show’s featured image', 'jws_streamvid' ); ?></label>
					</div>
					<p class="jws-mb__create-preview"></p>
				</div>

				<div class="jws-mb__modal-foot">
					<span class="jws-mb__modal-msg"></span>
					<button type="button" class="button jws-mb__modal-close"><?php esc_html_e( 'Cancel', 'jws_streamvid' ); ?></button>
					<button type="button" class="button button-primary jws-mb__modal-submit" disabled><?php esc_html_e( 'Add selected', 'jws_streamvid' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------- */
	/* Reading (ACF-compatible storage)                                       */
	/* ---------------------------------------------------------------------- */

	public static function get_value( $post_id, $field, $prefix = '' ) {
		$field = self::field_defaults( $field );
		$key   = $prefix . $field['name'];

		switch ( $field['type'] ) {
			case 'group':
				$out = array();
				foreach ( $field['sub_fields'] as $sub ) {
					$out[ $sub['name'] ] = self::get_value( $post_id, $sub, $key . '_' );
				}
				return $out;

			case 'repeater':
				$count = (int) self::meta_get( $post_id, $key );
				$rows  = array();
				for ( $i = 0; $i < $count; $i++ ) {
					$row = array();
					foreach ( $field['sub_fields'] as $sub ) {
						$row[ $sub['name'] ] = self::get_value( $post_id, $sub, $key . '_' . $i . '_' );
					}
					$rows[] = $row;
				}
				return $rows;

			case 'taxonomy':
				// Mirror ACF's load_terms: the post's real terms are the source of truth.
				$saved = array_map( 'intval', (array) self::meta_get( $post_id, $key ) );
				if ( ! empty( $field['taxonomy'] ) && '' === $prefix && 'post' === self::$meta_type ) {
					$terms = wp_get_object_terms( $post_id, $field['taxonomy'], array( 'fields' => 'ids', 'orderby' => 'none' ) );
					if ( is_wp_error( $terms ) ) {
						return $saved;
					}
					// Same order ACF gives them: as last saved in the meta.
					$terms = array_map( 'intval', $terms );
					if ( $terms && $saved ) {
						$order = array();
						foreach ( $terms as $i => $term_id ) {
							$order[ $i ] = array_search( $term_id, $saved, true );
						}
						array_multisort( $order, $terms );
					}
					return $terms;
				}
				return $saved;

			case 'gallery':
				$raw = self::meta_get( $post_id, $key );
				return array_filter( array_map( 'absint', is_array( $raw ) ? $raw : array() ) );

			case 'tags':
				$raw = self::meta_get( $post_id, $key );
				$raw = array_map( 'trim', array_map( 'strval', is_array( $raw ) ? $raw : array() ) );
				return array_values( array_filter( $raw, 'strlen' ) );

			case 'posts':
				$raw = self::meta_get( $post_id, $key );
				if ( $field['multiple'] ) {
					return array_filter( array_map( 'absint', is_array( $raw ) ? $raw : array() ) );
				}
				return absint( is_array( $raw ) ? reset( $raw ) : $raw );
		}

		if ( ! self::meta_exists( $post_id, $key ) ) {
			return $field['default'];
		}
		$value = self::meta_get( $post_id, $key );
		return 'user' === $field['type'] ? absint( is_array( $value ) ? reset( $value ) : $value ) : $value;
	}

	/**
	 * ACF-free equivalent of get_field() for fields declared here (repeaters
	 * come back as row arrays, posts/taxonomy as id arrays). Falls back to raw
	 * post meta for names this system does not know.
	 */
	public static function get_field( $name, $post_id ) {
		$field = self::find_field( $name, get_post_type( $post_id ) );
		return $field ? self::get_value( $post_id, $field ) : get_post_meta( $post_id, $name, true );
	}

	/** All top-level fields (tabs excluded) declared for a post type. */
	public static function fields_for( $post_type ) {
		$fields = array();
		foreach ( self::$boxes as $box ) {
			if ( in_array( $post_type, $box['post_types'], true ) ) {
				foreach ( $box['fields'] as $field ) {
					if ( ! in_array( $field['type'], array( 'tab', 'html', 'heading' ), true ) && empty( $field['virtual'] ) ) {
						$fields[] = self::field_defaults( $field );
					}
				}
			}
		}
		return $fields;
	}

	/** Top-level field declared under this name for a post type, whether or not the type is switched on. */
	public static function find_field( $name, $post_type ) {
		foreach ( self::$boxes as $box ) {
			if ( ! in_array( $post_type, $box['post_types'], true ) ) {
				continue;
			}
			foreach ( $box['fields'] as $field ) {
				if ( isset( $field['name'] ) && $field['name'] === $name && ! in_array( $field['type'], array( 'tab', 'html', 'heading' ), true ) && empty( $field['virtual'] ) ) {
					return self::field_defaults( $field );
				}
			}
		}
		return null;
	}

	/* ---------------------------------------------------------------------- */
	/* Saving                                                                 */
	/* ---------------------------------------------------------------------- */

	public static function save_post( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( empty( $_POST[ self::INPUT ] ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Only the post the form belongs to — not other posts saved during the same request.
		if ( ! isset( $_POST['post_ID'] ) || (int) $_POST['post_ID'] !== (int) $post_id ) {
			return;
		}

		foreach ( self::active_boxes( $post->post_type ) as $box ) {
			$nonce = '_jws_mb_nonce_' . $box['id'];
			if ( empty( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ $nonce ] ), self::NONCE_ACTION . $box['id'] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field is sanitized in save_field().
			$data = isset( $_POST[ self::INPUT ][ $box['id'] ] ) ? wp_unslash( $_POST[ self::INPUT ][ $box['id'] ] ) : array();

			foreach ( $box['fields'] as $field ) {
				if ( in_array( $field['type'], array( 'tab', 'html', 'heading' ), true ) || ! empty( $field['virtual'] ) || ! array_key_exists( $field['name'], $data ) ) {
					continue;
				}
				self::save_field( $post_id, $field, $data[ $field['name'] ] );
			}

			if ( is_callable( $box['on_save'] ) ) {
				call_user_func( $box['on_save'], $post_id, $box, is_array( $data ) ? $data : array() );
			}
			do_action( 'jws_metabox_saved', $post_id, $box['id'], $box );
		}
	}

	private static function update_meta( $post_id, $meta_key, $value, $field_key ) {
		self::meta_set( $post_id, $meta_key, $value );
		if ( $field_key ) {
			self::meta_set( $post_id, '_' . $meta_key, $field_key );
		}
	}

	public static function save_field( $post_id, $field, $raw, $prefix = '' ) {
		$field = self::field_defaults( $field );
		$key   = $prefix . $field['name'];

		switch ( $field['type'] ) {
			case 'text':
			case 'select':
				$value = sanitize_text_field( (string) $raw );
				if ( 'select' === $field['type'] && $field['choices'] && ! array_key_exists( $value, $field['choices'] ) ) {
					$value = (string) $field['default'];
				}
				self::update_meta( $post_id, $key, $value, $field['key'] );
				break;

			case 'url':
				self::update_meta( $post_id, $key, trim( sanitize_text_field( (string) $raw ) ), $field['key'] );
				break;

			case 'color':
				$color = sanitize_hex_color( trim( (string) $raw ) );
				self::update_meta( $post_id, $key, $color ? $color : '', $field['key'] );
				break;

			case 'user':
				$user = absint( is_array( $raw ) ? reset( $raw ) : $raw );
				self::update_meta( $post_id, $key, $user ? (string) $user : '', $field['key'] );
				break;

			case 'date':
				$raw = trim( sanitize_text_field( (string) $raw ) );
				$date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? DateTime::createFromFormat( '!Y-m-d', $raw ) : false;
				if ( $date ) {
					$raw = $date->format( $field['store_format'] );
				}
				self::update_meta( $post_id, $key, $raw, $field['key'] );
				break;

			case 'group':
				$raw = is_array( $raw ) ? $raw : array();
				foreach ( $field['sub_fields'] as $sub ) {
					self::save_field( $post_id, $sub, isset( $raw[ $sub['name'] ] ) ? $raw[ $sub['name'] ] : '', $key . '_' );
				}
				// ACF keeps an empty value on the group key itself.
				self::update_meta( $post_id, $key, '', $field['key'] );
				break;

			case 'number':
				$value = is_numeric( $raw ) ? (string) ( $raw + 0 ) : '';
				self::update_meta( $post_id, $key, $value, $field['key'] );
				break;

			case 'textarea':
				// Video/trailer urls may hold iframe or shortcode markup, as they could with ACF.
				$value = current_user_can( 'unfiltered_html' ) ? (string) $raw : wp_kses_post( (string) $raw );
				self::update_meta( $post_id, $key, $value, $field['key'] );
				break;

			case 'toggle':
				self::update_meta( $post_id, $key, empty( $raw ) ? 0 : 1, $field['key'] );
				break;

			case 'image':
			case 'file':
				$id = absint( $raw );
				self::update_meta( $post_id, $key, $id ? (string) $id : '', $field['key'] );
				break;

			case 'gallery':
				$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
				if ( $field['max'] ) {
					$ids = array_slice( $ids, 0, (int) $field['max'] );
				}
				self::update_meta( $post_id, $key, $ids ? array_map( 'strval', $ids ) : '', $field['key'] );
				break;

			case 'tags':
				$items = array();
				$seen  = array();
				foreach ( (array) $raw as $v ) {
					$v = trim( wp_strip_all_tags( (string) $v ) );
					if ( '' === $v ) {
						continue;
					}
					$v       = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 40 ) : substr( $v, 0, 40 );
					$v_lower = jws_mb_tags_lower( $v );
					if ( isset( $seen[ $v_lower ] ) ) {
						continue;
					}
					$seen[ $v_lower ] = true;
					$items[]          = $v;
				}
				if ( $field['max'] ) {
					$items = array_slice( $items, 0, (int) $field['max'] );
				}
				self::update_meta( $post_id, $key, $items ? $items : '', $field['key'] );
				delete_transient( 'jws_mb_tags_' . $key );
				break;

			case 'posts':
				$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $raw ) ) ) );
				if ( $field['max'] ) {
					$ids = array_slice( $ids, 0, (int) $field['max'] );
				}
				if ( $field['multiple'] ) {
					// ACF relationship layout: array of numeric strings, '' when empty.
					$value = $ids ? array_map( 'strval', $ids ) : '';
				} else {
					$value = $ids ? (string) $ids[0] : '';
				}
				self::update_meta( $post_id, $key, $value, $field['key'] );
				break;

			case 'taxonomy':
				$ids = self::resolve_terms( (array) $raw, $field['taxonomy'] );
				if ( ! $field['multiple'] ) {
					$ids = array_slice( $ids, 0, 1 );
				}
				if ( 'post' === self::$meta_type ) {
					wp_set_object_terms( $post_id, $ids, $field['taxonomy'] );
				}
				$value = $ids ? ( $field['multiple'] ? array_map( 'strval', $ids ) : (string) $ids[0] ) : '';
				self::update_meta( $post_id, $key, $value, $field['key'] );
				break;

			case 'repeater':
				$raw = is_array( $raw ) ? $raw : array();
				unset( $raw['__present'], $raw['__ROW__'] );
				$rows      = array_values( array_filter( $raw, 'is_array' ) );
				$old_count = (int) self::meta_get( $post_id, $key );

				foreach ( $rows as $i => $row ) {
					foreach ( $field['sub_fields'] as $sub ) {
						self::save_field( $post_id, $sub, isset( $row[ $sub['name'] ] ) ? $row[ $sub['name'] ] : '', $key . '_' . $i . '_' );
					}
				}
				for ( $i = count( $rows ); $i < $old_count; $i++ ) {
					foreach ( $field['sub_fields'] as $sub ) {
						self::delete_field( $post_id, $sub, $key . '_' . $i . '_' );
					}
				}
				if ( $rows ) {
					self::update_meta( $post_id, $key, count( $rows ), $field['key'] );
				} else {
					// ACF stores an emptied repeater as ''.
					self::update_meta( $post_id, $key, '', $field['key'] );
				}
				break;
		}
	}

	private static function delete_field( $post_id, $field, $prefix ) {
		$key = $prefix . $field['name'];
		if ( 'group' === $field['type'] ) {
			foreach ( $field['sub_fields'] as $sub ) {
				self::delete_field( $post_id, $sub, $key . '_' );
			}
		}
		if ( 'repeater' === $field['type'] ) {
			$count = (int) self::meta_get( $post_id, $key );
			for ( $i = 0; $i < $count; $i++ ) {
				foreach ( $field['sub_fields'] as $sub ) {
					self::delete_field( $post_id, $sub, $key . '_' . $i . '_' );
				}
			}
		}
		self::meta_delete( $post_id, $key );
		self::meta_delete( $post_id, '_' . $key );
	}

	/** Term ids from posted values; "new:Name" entries are created. */
	private static function resolve_terms( $values, $taxonomy ) {
		$ids = array();
		foreach ( $values as $v ) {
			$v = (string) $v;
			if ( 0 === strpos( $v, 'new:' ) ) {
				$name = sanitize_text_field( substr( $v, 4 ) );
				if ( '' === $name || ! current_user_can( get_taxonomy( $taxonomy )->cap->edit_terms ) ) {
					continue;
				}
				$existing = term_exists( $name, $taxonomy );
				$created  = $existing ? $existing : wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $created ) ) {
					$ids[] = (int) $created['term_id'];
				}
				continue;
			}
			$id = absint( $v );
			if ( $id && term_exists( $id, $taxonomy ) ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Term screens                                                           */
	/* ---------------------------------------------------------------------- */

	public static function hook_terms() {
		$done = array();
		foreach ( self::$term_boxes as $box ) {
			foreach ( $box['taxonomies'] as $taxonomy ) {
				if ( isset( $done[ $taxonomy ] ) || ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$done[ $taxonomy ] = true;
				add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'render_term_add' ) );
				add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'render_term_edit' ), 10, 2 );
				add_action( "created_{$taxonomy}", array( __CLASS__, 'save_term' ) );
				add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_term' ) );
			}
		}
	}

	public static function render_term_add( $taxonomy ) {
		foreach ( self::active_term_boxes( $taxonomy ) as $box ) {
			echo '<div class="form-field jws-mb-term">';
			self::render_fields( null, 0, $box );
			echo '</div>';
		}
	}

	public static function render_term_edit( $term, $taxonomy ) {
		foreach ( self::active_term_boxes( $taxonomy ) as $box ) {
			printf( '<tr class="form-field jws-mb-term-row"><th scope="row">%s</th><td>', esc_html( $box['title'] ) );
			self::render_fields( $term, $term->term_id, $box );
			echo '</td></tr>';
		}
	}

	public static function save_term( $term_id ) {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) || empty( $_POST[ self::INPUT ] ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		// Only the term the form belongs to: the edit form posts tag_ID, the add form comes through the add-tag ajax action.
		if ( isset( $_POST['tag_ID'] ) ? (int) $_POST['tag_ID'] !== (int) $term_id : ( ! isset( $_POST['action'] ) || 'add-tag' !== $_POST['action'] ) ) {
			return;
		}

		$acf_id = 'term_' . $term_id;
		foreach ( self::active_term_boxes( $term->taxonomy ) as $box ) {
			$nonce = '_jws_mb_nonce_' . $box['id'];
			if ( empty( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ $nonce ] ), self::NONCE_ACTION . $box['id'] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field is sanitized in save_field().
			$data = isset( $_POST[ self::INPUT ][ $box['id'] ] ) ? wp_unslash( $_POST[ self::INPUT ][ $box['id'] ] ) : array();

			self::with_meta_type(
				'term',
				function () use ( $box, $data, $term_id, $acf_id ) {
					foreach ( $box['fields'] as $field ) {
						if ( in_array( $field['type'], array( 'tab', 'html', 'heading' ), true ) || ! empty( $field['virtual'] ) || ! array_key_exists( $field['name'], $data ) ) {
							continue;
						}
						$raw = $data[ $field['name'] ];
						if ( is_array( $raw ) && 'repeater' !== $field['type'] && 'group' !== $field['type'] ) {
							$raw = array_values( array_filter( $raw, 'strlen' ) );
						}
						// The ACF hooks theme code listens on (topic sync).
						$acf_field = Jws_Acf_Compat::from_registry( self::field_defaults( $field ) );
						$raw       = apply_filters( 'acf/update_value/name=' . $field['name'], $raw, $acf_id, $acf_field, $raw );
						if ( ! empty( $field['key'] ) ) {
							$raw = apply_filters( 'acf/update_value/key=' . $field['key'], $raw, $acf_id, $acf_field, $raw );
						}
						self::save_field( $term_id, $field, $raw );
					}
				}
			);

			if ( is_callable( $box['on_save'] ) ) {
				call_user_func( $box['on_save'], $term_id, $box, is_array( $data ) ? $data : array() );
			}
		}
		// ACF (while active) caches values per request; listeners must see what was just saved.
		if ( function_exists( 'acf_get_store' ) ) {
			$store = acf_get_store( 'values' );
			foreach ( self::term_fields_for( $term->taxonomy ) as $field ) {
				// Callers use both "term_5" and the older "{taxonomy}_5" ids.
				foreach ( array( $acf_id, $term->taxonomy . '_' . $term_id ) as $cache_id ) {
					$store->remove( $cache_id . ':' . $field['name'] );
					$store->remove( $cache_id . ':' . $field['name'] . ':formatted' );
				}
			}
		}
		do_action( 'acf/save_post', $acf_id );
		do_action( 'jws_metabox_term_saved', $term_id, $term->taxonomy );
	}

	/* ---------------------------------------------------------------------- */
	/* User screens                                                           */
	/* ---------------------------------------------------------------------- */

	public static function hook_users() {
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_edit' ), 20 );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_edit' ), 20 );
		add_action( 'user_new_form', array( __CLASS__, 'render_user_new' ), 20 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user' ) );
		add_action( 'user_register', array( __CLASS__, 'save_user' ) );
	}

	public static function render_user_edit( $user ) {
		self::render_user_table( $user, $user->ID );
	}

	public static function render_user_new( $context ) {
		if ( 'add-new-user' === $context ) {
			self::render_user_table( null, 0 );
		}
	}

	private static function render_user_table( $user, $user_id ) {
		foreach ( self::active_user_boxes() as $box ) {
			printf( '<h2>%s</h2><table class="form-table" role="presentation"><tr class="jws-mb-user-row"><td colspan="2">', esc_html( $box['title'] ) );
			self::render_fields( $user, $user_id, $box );
			echo '</td></tr></table>';
		}
	}

	public static function save_user( $user_id ) {
		if ( empty( $_POST[ self::INPUT ] ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		// Only the user the form belongs to: profile/edit forms post user_id, the add form posts action=createuser.
		$is_new = 'user_register' === current_action();
		if ( $is_new ? ( ! is_admin() || ! isset( $_POST['action'] ) || 'createuser' !== $_POST['action'] ) : ( ! isset( $_POST['user_id'] ) || (int) $_POST['user_id'] !== (int) $user_id ) ) {
			return;
		}

		foreach ( self::active_user_boxes() as $box ) {
			$nonce = '_jws_mb_nonce_' . $box['id'];
			if ( empty( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ $nonce ] ), self::NONCE_ACTION . $box['id'] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field is sanitized in save_field().
			$data = isset( $_POST[ self::INPUT ][ $box['id'] ] ) ? wp_unslash( $_POST[ self::INPUT ][ $box['id'] ] ) : array();
			self::with_meta_type(
				'user',
				function () use ( $box, $data, $user_id ) {
					foreach ( $box['fields'] as $field ) {
						if ( in_array( $field['type'], array( 'tab', 'html', 'heading' ), true ) || ! empty( $field['virtual'] ) || ! array_key_exists( $field['name'], $data ) ) {
							continue;
						}
						self::save_field( $user_id, $field, $data[ $field['name'] ] );
					}
				}
			);
			if ( is_callable( $box['on_save'] ) ) {
				call_user_func( $box['on_save'], $user_id, $box, is_array( $data ) ? $data : array() );
			}
		}
		if ( function_exists( 'acf_get_store' ) ) {
			foreach ( self::user_fields() as $field ) {
				acf_get_store( 'values' )->remove( 'user_' . $user_id . ':' . $field['name'] );
				acf_get_store( 'values' )->remove( 'user_' . $user_id . ':' . $field['name'] . ':formatted' );
			}
		}
		do_action( 'acf/save_post', 'user_' . $user_id );
	}

	/* ---------------------------------------------------------------------- */
	/* AJAX                                                                   */
	/* ---------------------------------------------------------------------- */

	/** Post types some active field is allowed to look up. */
	private static function searchable_post_types() {
		$types = array();
		$walk  = function ( $fields ) use ( &$walk, &$types ) {
			foreach ( $fields as $f ) {
				if ( 'posts' === $f['type'] && ! empty( $f['post_type'] ) ) {
					$types = array_merge( $types, (array) $f['post_type'] );
				}
				if ( ! empty( $f['sub_fields'] ) ) {
					$walk( $f['sub_fields'] );
				}
			}
		};
		foreach ( self::all_active_boxes() as $box ) {
			$walk( $box['fields'] );
		}
		return array_values( array_unique( $types ) );
	}

	private static function searchable_taxonomies() {
		$taxes = array();
		foreach ( self::supported_post_types() as $pt ) {
			foreach ( self::active_boxes( $pt ) as $box ) {
				foreach ( $box['fields'] as $f ) {
					if ( 'taxonomy' === $f['type'] ) {
						$taxes[] = $f['taxonomy'];
					}
				}
			}
		}
		return array_unique( $taxes );
	}

	public static function ajax_search_posts() {
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}

		$types = array_intersect(
			array_map( 'sanitize_key', explode( ',', isset( $_POST['post_type'] ) ? wp_unslash( $_POST['post_type'] ) : '' ) ),
			self::searchable_post_types()
		);
		if ( ! $types ) {
			wp_send_json_error( null, 400 );
		}

		$search   = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$page     = max( 1, isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1 );
		$per_page = min( 100, max( 5, isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20 ) );
		$scope    = isset( $_POST['scope'] ) ? sanitize_key( $_POST['scope'] ) : 'all';
		$parent   = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$order    = isset( $_POST['order'] ) && 'title' === $_POST['order'] ? 'title' : 'date';
		$exclude  = isset( $_POST['exclude'] ) ? array_filter( array_map( 'absint', (array) $_POST['exclude'] ) ) : array();

		$args = array(
			'post_type'        => $types,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'   => $per_page,
			'paged'            => $page,
			'orderby'          => $order,
			'order'            => 'title' === $order ? 'ASC' : 'DESC',
			'suppress_filters' => false,
		);
		if ( $exclude ) {
			$args['post__not_in'] = $exclude;
		}
		if ( ctype_digit( $search ) ) {
			$args['post__in'] = array( (int) $search );
		} elseif ( '' !== $search ) {
			$args['s'] = $search;
		}
		if ( 'unassigned' === $scope ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => 'tv_show_id', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'tv_show_id', 'value' => array( '', '0' ), 'compare' => 'IN' ),
			);
		} elseif ( 'this' === $scope && $parent ) {
			$args['meta_query'] = array( array( 'key' => 'tv_show_id', 'value' => $parent ) );
		}

		$query = new WP_Query( $args );
		update_post_thumbnail_cache( $query );

		$items = array();
		foreach ( $query->posts as $p ) {
			$items[] = self::post_card( $p );
		}

		wp_send_json_success(
			array(
				'items' => $items,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'page'  => $page,
			)
		);
	}

	public static function ajax_search_users() {
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
		if ( ! current_user_can( 'list_users' ) ) {
			wp_send_json_error( null, 403 );
		}
		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$args   = array( 'number' => 20, 'orderby' => 'display_name', 'fields' => 'ID' );
		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name', 'ID' );
		}
		$items = array();
		foreach ( get_users( $args ) as $user_id ) {
			$items[] = self::user_card( $user_id );
		}
		wp_send_json_success( array( 'items' => array_values( array_filter( $items ) ) ) );
	}

	public static function ajax_search_terms() {
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( $_POST['taxonomy'] ) : '';
		if ( ! current_user_can( 'edit_posts' ) || ! in_array( $taxonomy, self::searchable_taxonomies(), true ) ) {
			wp_send_json_error( null, 403 );
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 30,
				'search'     => isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '',
				'orderby'    => 'name',
			)
		);
		$items = array();
		foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
			$items[] = array(
				'id'    => $t->term_id,
				'title' => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ),
				'meta'  => $t->parent ? html_entity_decode( get_term( $t->parent )->name, ENT_QUOTES, 'UTF-8' ) : '',
			);
		}
		$tax = get_taxonomy( $taxonomy );
		wp_send_json_success(
			array(
				'items'   => $items,
				'canAdd'  => current_user_can( $tax->cap->edit_terms ),
			)
		);
	}

	/** Quick-create episodes (or any searchable post type) from the seasons modal. */
	public static function ajax_create_posts() {
		check_ajax_referer( self::AJAX_NONCE, 'nonce' );

		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';
		$pt_obj    = get_post_type_object( $post_type );
		if ( ! $pt_obj || ! in_array( $post_type, self::searchable_post_types(), true ) || ! current_user_can( $pt_obj->cap->create_posts ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to create these posts.', 'jws_streamvid' ) ), 403 );
		}

		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		if ( $parent && ! current_user_can( 'edit_post', $parent ) ) {
			wp_send_json_error( null, 403 );
		}

		$status = isset( $_POST['status'] ) && 'publish' === $_POST['status'] && current_user_can( $pt_obj->cap->publish_posts ) ? 'publish' : 'draft';
		$items  = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array();
		$items  = array_slice( $items, 0, 200 );
		$thumb  = ! empty( $_POST['thumb'] ) && $parent ? get_post_thumbnail_id( $parent ) : 0;

		$created = array();
		foreach ( $items as $item ) {
			$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
			if ( '' === $title ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'   => $post_type,
					'post_title'  => $title,
					'post_status' => $status,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				continue;
			}
			if ( isset( $item['number'] ) && '' !== $item['number'] ) {
				update_post_meta( $id, 'episodes_number', sanitize_text_field( $item['number'] ) );
				update_post_meta( $id, '_episodes_number', 'field_ep_episodes_number' );
			}
			if ( $thumb ) {
				set_post_thumbnail( $id, $thumb );
			}
			$created[] = self::post_card( $id );
		}

		wp_send_json_success( array( 'items' => $created ) );
	}
}

Jws_Metabox::init();

if ( ! function_exists( 'jws_mb_tags_lower' ) ) {
	/** Case-insensitive key for a "tags" field label ("4K" and "4k" are the same tag). */
	function jws_mb_tags_lower( $value ) {
		$value = trim( (string) $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}
}

if ( ! function_exists( 'jws_get_field' ) ) {
	/**
	 * get_field() that works with or without ACF (same ids and return shapes).
	 *
	 * @param string          $name
	 * @param int|string|null $post_id
	 * @return mixed
	 */
	function jws_get_field( $name, $post_id = false ) {
		return Jws_Acf_Compat::get_field( $name, $post_id );
	}
}
