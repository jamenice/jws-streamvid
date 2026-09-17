<?php

/**
 * Stand-in for ACF's get_field() / update_field() once ACF is removed.
 *
 * The theme, the REST API and the importers call those two functions ~135
 * times. When ACF is not active this file defines them (and only them), so
 * none of those call sites has to change.
 *
 * It follows ACF's own rules:
 * - get_field() finds the field definition through the `_{name}` reference
 *   meta. Without a reference the stored value is returned unformatted, as
 *   ACF does.
 * - Definitions come from a schema snapshot of every ACF field group. While
 *   ACF is active the snapshot is refreshed on admin screens and kept in the
 *   `jws_acf_schema` option; acf-schema.php ships a copy as a fallback.
 *   Fields declared only in Jws_Metabox are converted on the fly.
 * - Values are formatted the way ACF formats the field types this site uses.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Acf_Compat {

	const OPTION        = 'jws_acf_schema';
	const FRESH         = 'jws_acf_schema_fresh';
	const SCHEMA_FILE   = __DIR__ . '/acf-schema.php';

	/** @var array|null */
	private static $schema = null;

	public static function boot() {
		if ( did_action( 'plugins_loaded' ) ) {
			self::define_functions();
		} else {
			add_action( 'plugins_loaded', array( __CLASS__, 'define_functions' ), 0 );
		}
		add_action( 'admin_init', array( __CLASS__, 'maybe_refresh_schema' ) );
	}

	/** Define get_field() / update_field() unless ACF did. Runs after every plugin has loaded, so an active ACF always wins. */
	public static function define_functions() {
		if ( function_exists( 'get_field' ) || class_exists( 'ACF' ) || self::acf_loading_later() ) {
			return;
		}
		require_once __DIR__ . '/acf-compat-functions.php';
	}

	/** Plugin paths that ship their own get_field(): ACF (free/pro) and Secure Custom Fields. */
	private static function is_acf_plugin( $plugin ) {
		return is_string( $plugin ) && (bool) preg_match( '#(^|/)(advanced-custom-fields[^/]*|secure-custom-fields[^/]*)/#i', $plugin );
	}

	/**
	 * True when this request is about to include ACF after all plugins have
	 * loaded — activating it. ACF declares get_field() without a
	 * function_exists() guard, so defining ours first would be fatal.
	 */
	private static function acf_loading_later() {
		// phpcs:disable WordPress.Security.NonceVerification -- only reads which plugin is being activated.
		$candidates = array();
		$action     = isset( $_REQUEST['action'] ) ? (string) wp_unslash( $_REQUEST['action'] ) : '';
		$action2    = isset( $_REQUEST['action2'] ) ? (string) wp_unslash( $_REQUEST['action2'] ) : '';
		$script     = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : '';

		$activating = in_array( $action, array( 'activate', 'activate-selected', 'error_scrape', 'activate-plugin', 'activate-multi' ), true )
			|| in_array( $action2, array( 'activate-selected' ), true );

		// plugins.php, update.php (re-activate after an update) and admin-ajax.php (dependency "Activate").
		if ( $activating && in_array( $script, array( 'plugins.php', 'update.php', 'admin-ajax.php' ), true ) ) {
			if ( isset( $_REQUEST['plugin'] ) ) {
				$candidates[] = wp_unslash( $_REQUEST['plugin'] );
			}
			if ( isset( $_REQUEST['plugins'] ) ) {
				$candidates = array_merge( $candidates, explode( ',', (string) wp_unslash( $_REQUEST['plugins'] ) ) );
			}
			if ( isset( $_REQUEST['checked'] ) ) {
				$candidates = array_merge( $candidates, (array) wp_unslash( $_REQUEST['checked'] ) );
			}
		}
		// phpcs:enable

		// REST: POST/PUT /wp/v2/plugins/<plugin> with status=active.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( (string) $_SERVER['REQUEST_URI'] ) : '';
		if ( false !== strpos( $uri, 'wp/v2/plugins' ) && isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			$candidates[] = $uri . '/';
			$body         = (string) file_get_contents( 'php://input' );
			if ( '' !== $body ) {
				$candidates[] = $body . '/';
			}
		}

		// WP-CLI: wp plugin activate advanced-custom-fields-pro
		if ( defined( 'WP_CLI' ) && WP_CLI && ! empty( $GLOBALS['argv'] ) && in_array( 'activate', (array) $GLOBALS['argv'], true ) ) {
			foreach ( (array) $GLOBALS['argv'] as $arg ) {
				$candidates[] = $arg . '/';
			}
		}

		foreach ( $candidates as $plugin ) {
			if ( self::is_acf_plugin( is_string( $plugin ) ? $plugin : '' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function acf_active() {
		return function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' );
	}

	/* ---------------------------------------------------------------------- */
	/* Schema                                                                 */
	/* ---------------------------------------------------------------------- */

	public static function maybe_refresh_schema() {
		if ( self::acf_active() && ! get_transient( self::FRESH ) ) {
			self::refresh_schema();
		}
	}

	/** Snapshot every ACF field group into the option. Returns the schema. */
	public static function refresh_schema() {
		$schema = self::build_schema();
		if ( $schema['fields'] ) {
			update_option( self::OPTION, $schema, false );
			set_transient( self::FRESH, 1, 12 * HOUR_IN_SECONDS );
			self::$schema = $schema;
		}
		return $schema;
	}

	public static function build_schema() {
		$schema = array(
			'generated' => time(),
			'fields'    => array(),
			'names'     => array(),
		);
		if ( ! self::acf_active() ) {
			return $schema;
		}
		foreach ( acf_get_field_groups() as $group ) {
			$locations = array();
			foreach ( (array) $group['location'] as $and ) {
				foreach ( (array) $and as $rule ) {
					if ( '==' === $rule['operator'] ) {
						$locations[] = $rule['param'] . ':' . $rule['value'];
					}
				}
			}
			foreach ( (array) acf_get_fields( $group['key'] ) as $field ) {
				if ( in_array( $field['type'], array( 'tab', 'message', 'accordion' ), true ) || '' === $field['name'] ) {
					continue;
				}
				$def = self::export_field( $field );
				if ( ! isset( $schema['fields'][ $def['key'] ] ) ) {
					$schema['fields'][ $def['key'] ] = $def;
				}
				foreach ( $locations as $loc ) {
					if ( ! isset( $schema['names'][ $loc ][ $def['name'] ] ) ) {
						$schema['names'][ $loc ][ $def['name'] ] = $def['key'];
					}
				}
			}
		}
		return $schema;
	}

	private static function export_field( $field ) {
		$def = array(
			'key'  => $field['key'],
			'name' => $field['name'],
			'type' => $field['type'],
		);
		foreach ( array( 'return_format', 'multiple', 'field_type', 'taxonomy', 'load_terms', 'save_terms', 'save_format', 'post_type' ) as $prop ) {
			if ( isset( $field[ $prop ] ) && '' !== $field[ $prop ] ) {
				$def[ $prop ] = $field[ $prop ];
			}
		}
		if ( array_key_exists( 'default_value', $field ) ) {
			$def['default_value'] = $field['default_value'];
		}
		if ( ! empty( $field['sub_fields'] ) ) {
			$def['sub_fields'] = array();
			foreach ( $field['sub_fields'] as $sub ) {
				if ( '' !== $sub['name'] && 'tab' !== $sub['type'] ) {
					$def['sub_fields'][] = self::export_field( $sub );
				}
			}
		}
		return $def;
	}

	/** Write the current schema to acf-schema.php (the copy shipped with the plugin). */
	public static function export_schema_file() {
		$schema = self::acf_active() ? self::build_schema() : self::schema();
		if ( ! $schema['fields'] ) {
			return false;
		}
		$php = "<?php\n\n// Generated by Jws_Acf_Compat::export_schema_file() — snapshot of the ACF field groups.\n\nreturn " . var_export( $schema, true ) . ";\n";
		return false !== file_put_contents( self::SCHEMA_FILE, $php );
	}

	public static function schema() {
		if ( null !== self::$schema ) {
			return self::$schema;
		}
		$schema = get_option( self::OPTION );
		if ( ! is_array( $schema ) || empty( $schema['fields'] ) ) {
			$schema = file_exists( self::SCHEMA_FILE ) ? include self::SCHEMA_FILE : array();
		}
		self::$schema = wp_parse_args( is_array( $schema ) ? $schema : array(), array( 'fields' => array(), 'names' => array(), 'generated' => 0 ) );
		return self::$schema;
	}

	/* ---------------------------------------------------------------------- */
	/* Field lookup                                                           */
	/* ---------------------------------------------------------------------- */

	/** acf_is_field_key(): "field_…", or any string registered as a field key. */
	private static function is_field_key( $selector ) {
		if ( 0 === strpos( $selector, 'field_' ) ) {
			return true;
		}
		$schema = self::schema();
		return isset( $schema['fields'][ $selector ] );
	}

	private static function field_by_key( $key ) {
		$schema = self::schema();
		if ( isset( $schema['fields'][ $key ] ) ) {
			return $schema['fields'][ $key ];
		}
		return self::registry_field_by_key( $key );
	}

	/** Location keys to search, most specific first. */
	private static function locations( $type, $id ) {
		switch ( $type ) {
			case 'post':
				$locs = array( 'post_type:' . get_post_type( $id ) );
				$format = get_post_format( $id );
				if ( $format ) {
					$locs[] = 'post_format:' . $format;
				}
				return $locs;
			case 'term':
				$term = get_term( $id );
				return $term && ! is_wp_error( $term ) ? array( 'taxonomy:' . $term->taxonomy ) : array();
			case 'user':
				return array( 'user_form:all', 'user_role:all' );
			case 'option':
				return array( 'options_page:' . $id );
		}
		return array();
	}

	/** ACF's non-strict lookup (used by update_field): reference, then name. */
	private static function field_by_name( $name, $type, $id ) {
		$schema = self::schema();
		foreach ( self::locations( $type, $id ) as $loc ) {
			if ( isset( $schema['names'][ $loc ][ $name ] ) ) {
				return self::field_by_key( $schema['names'][ $loc ][ $name ] );
			}
		}
		if ( 'post' === $type ) {
			$reg = Jws_Metabox::find_field( $name, get_post_type( $id ) );
			if ( $reg && ! empty( $reg['key'] ) ) {
				return self::from_registry( $reg );
			}
		}
		foreach ( $schema['names'] as $names ) {
			if ( isset( $names[ $name ] ) ) {
				return self::field_by_key( $names[ $name ] );
			}
		}
		return null;
	}

	private static function registry_field_by_key( $key ) {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			$fields = array();
			foreach ( Jws_Metabox::supported_post_types() as $pt ) {
				$fields = array_merge( $fields, Jws_Metabox::fields_for( $pt ) );
			}
			foreach ( Jws_Metabox::term_taxonomies() as $tax ) {
				$fields = array_merge( $fields, Jws_Metabox::term_fields_for( $tax ) );
			}
			$fields = array_merge( $fields, Jws_Metabox::user_fields() );
			foreach ( $fields as $field ) {
				if ( ! empty( $field['key'] ) && ! isset( $map[ $field['key'] ] ) ) {
					$map[ $field['key'] ] = self::from_registry( $field );
				}
			}
		}
		return isset( $map[ $key ] ) ? $map[ $key ] : null;
	}

	/** Jws_Metabox field definition → the ACF-shaped definition used here. */
	public static function from_registry( $field ) {
		$def = array(
			'key'  => isset( $field['key'] ) ? $field['key'] : '',
			'name' => $field['name'],
			'type' => $field['type'],
		);
		switch ( $field['type'] ) {
			case 'toggle':
				$def['type']          = 'true_false';
				$def['default_value'] = 0;
				break;
			case 'image':
			case 'file':
				$def['return_format'] = isset( $field['return_format'] ) ? $field['return_format'] : 'array';
				break;
			case 'posts':
				$def['type']          = empty( $field['multiple'] ) ? 'post_object' : 'relationship';
				$def['return_format'] = 'id';
				$def['multiple']      = empty( $field['multiple'] ) ? 0 : 1;
				break;
			case 'taxonomy':
				$def['taxonomy']      = $field['taxonomy'];
				$def['field_type']    = empty( $field['multiple'] ) ? 'select' : 'multi_select';
				$def['return_format'] = 'id';
				$def['load_terms']    = 1;
				$def['save_terms']    = 1;
				break;
			case 'gallery':
				$def['return_format'] = isset( $field['return_format'] ) ? $field['return_format'] : 'array';
				break;
			case 'date':
				$def['type']          = 'date_picker';
				$def['return_format'] = isset( $field['return_format'] ) ? $field['return_format'] : 'd/m/Y';
				break;
			case 'url':
				$def['default_value'] = '';
				break;
			case 'color':
				$def['type']          = 'color_picker';
				$def['return_format'] = 'string';
				$def['default_value'] = '';
				break;
			case 'user':
				$def['return_format'] = isset( $field['return_format'] ) ? $field['return_format'] : 'array';
				$def['multiple']      = 0;
				break;
			case 'tags':
				// No ACF equivalent — a plain array of strings, passed through as-is by load()/format().
				$def['default_value'] = array();
				break;
			case 'group':
			case 'repeater':
				$def['sub_fields'] = array();
				foreach ( $field['sub_fields'] as $sub ) {
					$def['sub_fields'][] = self::from_registry( $sub );
				}
				break;
			default:
				$def['default_value'] = isset( $field['default'] ) ? $field['default'] : '';
		}
		return $def;
	}

	/* ---------------------------------------------------------------------- */
	/* Object ids                                                             */
	/* ---------------------------------------------------------------------- */

	/**
	 * Resolve an ACF-style id to array( meta type, object id, acf id string ).
	 * Meta type is post|term|user|comment|option.
	 */
	public static function decode_id( $id ) {
		if ( $id instanceof WP_Post ) {
			return array( 'post', $id->ID, $id->ID );
		}
		if ( $id instanceof WP_Term ) {
			return array( 'term', $id->term_id, 'term_' . $id->term_id );
		}
		if ( $id instanceof WP_User ) {
			return array( 'user', $id->ID, 'user_' . $id->ID );
		}
		if ( $id instanceof WP_Comment ) {
			return array( 'comment', (int) $id->comment_ID, 'comment_' . $id->comment_ID );
		}

		if ( empty( $id ) ) {
			$current = get_the_ID();
			if ( $current ) {
				return array( 'post', (int) $current, (int) $current );
			}
			$object = get_queried_object();
			return $object ? self::decode_id( $object ) : array( 'post', 0, 0 );
		}

		if ( is_numeric( $id ) ) {
			return array( 'post', (int) $id, (int) $id );
		}

		$id = (string) $id;
		if ( 'option' === $id || 'options' === $id ) {
			return array( 'option', 'options', 'options' );
		}
		if ( preg_match( '/^(.+)_(\d+)$/', $id, $m ) ) {
			$num = (int) $m[2];
			switch ( $m[1] ) {
				case 'user':
					return array( 'user', $num, $id );
				case 'comment':
					return array( 'comment', $num, $id );
				case 'post':
					return array( 'post', $num, $num );
			}
			// term_5, or {taxonomy}_5 which ACF still accepts.
			return array( 'term', $num, 'term_' . $num );
		}
		return array( 'option', $id, $id );
	}

	/* ---------------------------------------------------------------------- */
	/* Storage primitives                                                     */
	/* ---------------------------------------------------------------------- */

	private static function exists( $type, $id, $key ) {
		if ( 'option' === $type ) {
			return null !== get_option( $id . '_' . $key, null );
		}
		return metadata_exists( $type, $id, $key );
	}

	/** Stored value, or null when there is none (ACF's acf_get_metadata()). */
	private static function get( $type, $id, $key ) {
		if ( 'option' === $type ) {
			return get_option( $id . '_' . $key, null );
		}
		return metadata_exists( $type, $id, $key ) ? get_metadata( $type, $id, $key, true ) : null;
	}

	private static function set( $type, $id, $key, $value ) {
		if ( 'option' === $type ) {
			return update_option( $id . '_' . $key, $value, false );
		}
		return update_metadata( $type, $id, $key, $value );
	}

	private static function delete( $type, $id, $key ) {
		if ( 'option' === $type ) {
			return delete_option( $id . '_' . $key );
		}
		return delete_metadata( $type, $id, $key );
	}

	/* ---------------------------------------------------------------------- */
	/* get_field()                                                            */
	/* ---------------------------------------------------------------------- */

	public static function get_field( $selector, $id = false, $format = true ) {
		list( $type, $obj ) = self::decode_id( $id );
		if ( ! $obj ) {
			return null;
		}
		$selector = (string) $selector;

		if ( self::is_field_key( $selector ) ) {
			$field = self::field_by_key( $selector );
		} else {
			$ref   = self::get( $type, $obj, '_' . $selector );
			$field = is_string( $ref ) && '' !== $ref ? self::field_by_key( $ref ) : null;
			if ( $field ) {
				$field['name'] = $selector;
			}
		}

		if ( ! $field ) {
			// ACF's dummy field: the raw value, never formatted.
			return self::get( $type, $obj, $selector );
		}

		$value         = self::load( $type, $obj, $field );
		return $format ? self::format( $value, $field ) : $value;
	}

	/** acf_get_value() + the type's load_value(). */
	private static function load( $type, $id, $field ) {
		$value = self::get( $type, $id, $field['name'] );
		if ( null === $value && isset( $field['default_value'] ) ) {
			$value = $field['default_value'];
		}

		switch ( $field['type'] ) {
			case 'repeater':
				if ( empty( $value ) || ! is_numeric( $value ) || empty( $field['sub_fields'] ) ) {
					return false;
				}
				$rows = array();
				for ( $i = 0, $n = (int) $value; $i < $n; $i++ ) {
					$row = array();
					foreach ( $field['sub_fields'] as $sub ) {
						$sub['_name']       = $sub['name'];
						$sub['name']        = $field['name'] . '_' . $i . '_' . $sub['name'];
						$row[ $sub['key'] ] = array( $sub, self::load( $type, $id, $sub ) );
					}
					$rows[ $i ] = $row;
				}
				return $rows;

			case 'group':
				if ( empty( $field['sub_fields'] ) ) {
					return $value;
				}
				$row = array();
				foreach ( $field['sub_fields'] as $sub ) {
					$sub['_name']       = $sub['name'];
					$sub['name']        = $field['name'] . '_' . $sub['name'];
					$row[ $sub['key'] ] = array( $sub, self::load( $type, $id, $sub ) );
				}
				return $row;

			case 'taxonomy':
				$value = array_map( 'intval', self::to_array( $value ) );
				if ( ! empty( $field['load_terms'] ) && 'post' === $type ) {
					$terms = wp_get_object_terms( $id, $field['taxonomy'], array( 'fields' => 'ids', 'orderby' => 'none' ) );
					if ( empty( $terms ) || is_wp_error( $terms ) ) {
						return false;
					}
					if ( $value ) {
						$order = array();
						foreach ( $terms as $i => $term_id ) {
							$order[ $i ] = array_search( $term_id, $value, true );
						}
						array_multisort( $order, $terms );
					}
					$value = $terms;
				}
				if ( isset( $field['field_type'] ) && in_array( $field['field_type'], array( 'select', 'radio' ), true ) ) {
					$value = array_shift( $value );
				}
				return $value;
		}
		return $value;
	}

	/** acf_format_value() for the field types the site uses. Rows come from load(). */
	private static function format( $value, $field ) {
		$rf = isset( $field['return_format'] ) ? $field['return_format'] : '';

		switch ( $field['type'] ) {
			case 'repeater':
				if ( empty( $value ) || ! is_array( $value ) || empty( $field['sub_fields'] ) ) {
					return false;
				}
				$out = array();
				foreach ( $value as $i => $row ) {
					$out[ $i ] = self::format_cells( $row );
				}
				return $out;

			case 'group':
				return empty( $value ) ? false : self::format_cells( $value );

			case 'true_false':
				return ! empty( $value );

			case 'image':
			case 'file':
				if ( empty( $value ) || ! is_numeric( $value ) ) {
					return false;
				}
				$value = (int) $value;
				if ( 'url' === $rf ) {
					return wp_get_attachment_url( $value );
				}
				return 'array' === $rf ? self::attachment( $value ) : $value;

			case 'gallery':
				if ( ! $value ) {
					return false;
				}
				$ids   = array_map( 'intval', self::to_array( $value ) );
				$posts = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'any', 'post__in' => $ids, 'orderby' => 'post__in', 'posts_per_page' => -1 ) );
				if ( ! $posts ) {
					return false;
				}
				$out = array();
				foreach ( $posts as $post ) {
					if ( 'object' === $rf ) {
						$out[] = $post;
					} elseif ( 'array' === $rf ) {
						$out[] = self::attachment( $post );
					} elseif ( 'url' === $rf ) {
						$out[] = wp_get_attachment_url( $post->ID );
					} else {
						$out[] = $post->ID;
					}
				}
				return $out;

			case 'post_object':
				$ids = self::numeric( $value );
				if ( empty( $ids ) ) {
					return false;
				}
				if ( 'object' === $rf ) {
					$ids = array_filter( array_map( 'get_post', (array) $ids ) );
				}
				if ( empty( $field['multiple'] ) && is_array( $ids ) ) {
					$ids = current( $ids );
				}
				return $ids;

			case 'relationship':
				if ( empty( $value ) ) {
					return $value;
				}
				$ids = array_map( 'intval', self::to_array( $value ) );
				return 'object' === $rf ? array_values( array_filter( array_map( 'get_post', $ids ) ) ) : $ids;

			case 'taxonomy':
				if ( empty( $value ) ) {
					return false;
				}
				$value = self::to_array( $value );
				if ( 'object' === $rf ) {
					$value = array_values( array_filter( array_map( 'get_term', $value ) ) );
				}
				if ( isset( $field['field_type'] ) && in_array( $field['field_type'], array( 'select', 'radio' ), true ) ) {
					$value = array_shift( $value );
				}
				return $value;

			case 'user':
				if ( ! $value ) {
					return false;
				}
				$out = array();
				foreach ( array_map( 'intval', self::to_array( $value ) ) as $user_id ) {
					$user = get_userdata( $user_id );
					if ( ! $user ) {
						continue;
					}
					if ( 'object' === $rf ) {
						$out[] = $user;
					} elseif ( 'array' === $rf ) {
						$out[] = array(
							'ID'               => $user->ID,
							'user_firstname'   => $user->user_firstname,
							'user_lastname'    => $user->user_lastname,
							'nickname'         => $user->nickname,
							'user_nicename'    => $user->user_nicename,
							'display_name'     => $user->display_name,
							'user_email'       => $user->user_email,
							'user_url'         => $user->user_url,
							'user_registered'  => $user->user_registered,
							'user_description' => $user->user_description,
							'user_avatar'      => get_avatar( $user->ID ),
						);
					} else {
						$out[] = $user->ID;
					}
				}
				if ( ! $out ) {
					return false;
				}
				return empty( $field['multiple'] ) ? array_shift( $out ) : $out;

			case 'date_picker':
			case 'date_time_picker':
				if ( ! empty( $field['save_format'] ) || ! $rf || ! $value || ( ! is_string( $value ) && ! is_int( $value ) ) ) {
					return $value;
				}
				$ts = ( is_numeric( $value ) && 8 !== strlen( (string) $value ) ) ? $value : strtotime( $value );
				return date_i18n( $rf, $ts );
		}
		return $value;
	}

	private static function format_cells( $cells ) {
		$out = array();
		foreach ( $cells as $cell ) {
			list( $sub, $sub_value ) = $cell;
			$out[ $sub['_name'] ] = self::format( $sub_value, $sub );
		}
		return $out;
	}

	private static function to_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( null === $value || false === $value || '' === $value ) {
			return array();
		}
		return array( $value );
	}

	/** acf_get_numeric() */
	private static function numeric( $value ) {
		$numbers = array();
		foreach ( (array) $value as $v ) {
			if ( is_numeric( $v ) ) {
				$numbers[] = (int) $v;
			}
		}
		if ( ! $numbers ) {
			return false;
		}
		return is_array( $value ) ? $numbers : $numbers[0];
	}

	/** acf_get_attachment() */
	public static function attachment( $attachment ) {
		$attachment = get_post( $attachment );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}
		$meta = wp_get_attachment_metadata( $attachment->ID );
		$file = get_attached_file( $attachment->ID );
		list( $type, $subtype ) = false !== strpos( $attachment->post_mime_type, '/' ) ? explode( '/', $attachment->post_mime_type ) : array( $attachment->post_mime_type, '' );

		$response = array(
			'ID'          => $attachment->ID,
			'id'          => $attachment->ID,
			'title'       => $attachment->post_title,
			'filename'    => wp_basename( $file ),
			'filesize'    => 0,
			'url'         => wp_get_attachment_url( $attachment->ID ),
			'link'        => get_attachment_link( $attachment->ID ),
			'alt'         => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
			'author'      => $attachment->post_author,
			'description' => $attachment->post_content,
			'caption'     => $attachment->post_excerpt,
			'name'        => $attachment->post_name,
			'status'      => $attachment->post_status,
			'uploaded_to' => $attachment->post_parent,
			'date'        => $attachment->post_date_gmt,
			'modified'    => $attachment->post_modified_gmt,
			'menu_order'  => $attachment->menu_order,
			'mime_type'   => $attachment->post_mime_type,
			'type'        => $type,
			'subtype'     => $subtype,
			'icon'        => wp_mime_type_icon( $attachment->ID ),
		);
		if ( isset( $meta['filesize'] ) ) {
			$response['filesize'] = $meta['filesize'];
		} elseif ( $file && file_exists( $file ) ) {
			$response['filesize'] = filesize( $file );
		}

		$sizes_id = 0;
		if ( 'image' === $type ) {
			$sizes_id = $attachment->ID;
			$src      = wp_get_attachment_image_src( $attachment->ID, 'full' );
			if ( $src ) {
				$response['url']    = $src[0];
				$response['width']  = $src[1];
				$response['height'] = $src[2];
			}
		} elseif ( 'video' === $type ) {
			$response['width']  = isset( $meta['width'] ) ? $meta['width'] : 0;
			$response['height'] = isset( $meta['height'] ) ? $meta['height'] : 0;
			$sizes_id           = (int) get_post_thumbnail_id( $attachment->ID );
		} elseif ( 'audio' === $type ) {
			$sizes_id = (int) get_post_thumbnail_id( $attachment->ID );
		}
		if ( $sizes_id ) {
			$sizes = array();
			foreach ( get_intermediate_image_sizes() as $size ) {
				$src = wp_get_attachment_image_src( $sizes_id, $size );
				if ( $src ) {
					$sizes[ $size ]             = $src[0];
					$sizes[ $size . '-width' ]  = $src[1];
					$sizes[ $size . '-height' ] = $src[2];
				}
			}
			$response['sizes'] = $sizes;
		}
		return $response;
	}

	/* ---------------------------------------------------------------------- */
	/* update_field()                                                         */
	/* ---------------------------------------------------------------------- */

	public static function update_field( $selector, $value, $id = false ) {
		list( $type, $obj, $acf_id ) = self::decode_id( $id );
		if ( ! $obj ) {
			return false;
		}
		$selector = (string) $selector;

		if ( self::is_field_key( $selector ) ) {
			$field = self::field_by_key( $selector );
		} else {
			$ref   = self::get( $type, $obj, '_' . $selector );
			$field = is_string( $ref ) && '' !== $ref ? self::field_by_key( $ref ) : null;
			if ( ! $field ) {
				$field = self::field_by_name( $selector, $type, $obj );
			}
			if ( $field ) {
				$field['name'] = $selector;
			}
		}

		if ( null === $value ) {
			// acf_update_value(): null deletes the value and its reference.
			self::delete( $type, $obj, $selector );
			self::delete( $type, $obj, '_' . $selector );
			return true;
		}

		if ( ! $field ) {
			self::set( $type, $obj, $selector, $value );
			self::set( $type, $obj, '_' . $selector, '' );
			return true;
		}

		self::save( $type, $obj, $acf_id, $field, $value );
		return true;
	}

	/** acf_update_value() for one field (recursing into rows). */
	private static function save( $type, $id, $acf_id, $field, $value ) {
		$name = $field['name'];

		// Hooks ACF runs before storing — the theme's topic sync listens on these.
		$original = $value;
		$value    = apply_filters( 'acf/update_value', $value, $acf_id, $field, $original );
		$value    = apply_filters( 'acf/update_value/type=' . $field['type'], $value, $acf_id, $field, $original );
		$value    = apply_filters( 'acf/update_value/name=' . $name, $value, $acf_id, $field, $original );
		$value    = apply_filters( 'acf/update_value/key=' . $field['key'], $value, $acf_id, $field, $original );

		switch ( $field['type'] ) {
			case 'repeater':
				$rows      = is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
				$old_count = (int) self::get( $type, $id, $name );
				foreach ( $rows as $i => $row ) {
					foreach ( (array) $field['sub_fields'] as $sub ) {
						// Like ACF, a cell missing from the row keeps its stored value.
						if ( ! array_key_exists( $sub['name'], $row ) && ( empty( $sub['key'] ) || ! array_key_exists( $sub['key'], $row ) ) ) {
							continue;
						}
						$cell = self::cell( $row, $sub );
						$sub['name'] = $name . '_' . $i . '_' . $sub['name'];
						self::save_cell( $type, $id, $acf_id, $sub, $cell );
					}
				}
				for ( $i = count( $rows ); $i < $old_count; $i++ ) {
					foreach ( (array) $field['sub_fields'] as $sub ) {
						$sub['name'] = $name . '_' . $i . '_' . $sub['name'];
						self::remove( $type, $id, $sub );
					}
				}
				$value = $rows ? count( $rows ) : '';
				break;

			case 'group':
				foreach ( (array) $field['sub_fields'] as $sub ) {
					$cell = self::cell( is_array( $value ) ? $value : array(), $sub );
					$sub['name'] = $name . '_' . $sub['name'];
					self::save_cell( $type, $id, $acf_id, $sub, $cell );
				}
				$value = '';
				break;

			case 'true_false':
				$value = empty( $value ) ? 0 : 1;
				break;

			case 'image':
			case 'file':
				$value = self::attachment_id( $value );
				$value = $value ? $value : '';
				break;

			case 'gallery':
				$ids   = array_filter( array_map( array( __CLASS__, 'attachment_id' ), self::to_array( $value ) ) );
				$value = $ids ? array_map( 'strval', array_values( $ids ) ) : '';
				break;

			case 'post_object':
			case 'relationship':
			case 'user':
				$ids = array();
				foreach ( self::to_array( $value ) as $v ) {
					if ( is_object( $v ) && isset( $v->ID ) ) {
						$v = $v->ID;
					} elseif ( is_array( $v ) && isset( $v['ID'] ) ) {
						$v = $v['ID'];
					}
					if ( is_numeric( $v ) ) {
						$ids[] = (string) (int) $v;
					}
				}
				if ( ! $ids ) {
					$value = '';
				} elseif ( 'relationship' === $field['type'] || ! empty( $field['multiple'] ) ) {
					$value = $ids;
				} else {
					$value = $ids[0];
				}
				break;

			case 'taxonomy':
				// ACF stores the ids as given (ints from code, strings from a form).
				$ids = array();
				foreach ( self::to_array( $value ) as $v ) {
					$v = is_object( $v ) && isset( $v->term_id ) ? (int) $v->term_id : $v;
					if ( is_numeric( $v ) ) {
						$ids[] = $v;
					}
				}
				if ( ! empty( $field['save_terms'] ) && 'post' === $type ) {
					wp_set_object_terms( $id, array_map( 'intval', $ids ), $field['taxonomy'] );
				}
				if ( is_array( $value ) ) {
					$value = $ids;
				} else {
					$value = $ids ? $ids[0] : '';
				}
				break;
		}

		self::set( $type, $id, $name, $value );
		if ( ! empty( $field['key'] ) && 'option' !== $type ) {
			self::set( $type, $id, '_' . $name, $field['key'] );
		} elseif ( ! empty( $field['key'] ) ) {
			update_option( '_' . $id . '_' . $name, $field['key'], false );
		}
	}

	/** Row cells go through acf_update_value() too, so null removes them. */
	private static function save_cell( $type, $id, $acf_id, $sub, $cell ) {
		if ( null === $cell ) {
			self::delete( $type, $id, $sub['name'] );
			self::delete( $type, $id, '_' . $sub['name'] );
			return;
		}
		self::save( $type, $id, $acf_id, $sub, $cell );
	}

	/** A row's value for a sub field, keyed by sub field name or ACF key. */
	private static function cell( $row, $sub ) {
		if ( array_key_exists( $sub['name'], $row ) ) {
			return $row[ $sub['name'] ];
		}
		if ( ! empty( $sub['key'] ) && array_key_exists( $sub['key'], $row ) ) {
			return $row[ $sub['key'] ];
		}
		return null;
	}

	private static function remove( $type, $id, $field ) {
		if ( in_array( $field['type'], array( 'repeater', 'group' ), true ) && ! empty( $field['sub_fields'] ) ) {
			$count = 'repeater' === $field['type'] ? (int) self::get( $type, $id, $field['name'] ) : 1;
			for ( $i = 0; $i < $count; $i++ ) {
				foreach ( $field['sub_fields'] as $sub ) {
					$sub['name'] = $field['name'] . ( 'repeater' === $field['type'] ? '_' . $i : '' ) . '_' . $sub['name'];
					self::remove( $type, $id, $sub );
				}
			}
		}
		self::delete( $type, $id, $field['name'] );
		self::delete( $type, $id, '_' . $field['name'] );
	}

	public static function attachment_id( $value ) {
		if ( is_array( $value ) ) {
			$value = isset( $value['ID'] ) ? $value['ID'] : ( isset( $value['id'] ) ? $value['id'] : 0 );
		} elseif ( is_object( $value ) && isset( $value->ID ) ) {
			$value = $value->ID;
		} elseif ( is_string( $value ) && ! is_numeric( $value ) && '' !== $value ) {
			$value = attachment_url_to_postid( $value );
		}
		return is_numeric( $value ) ? (int) $value : 0;
	}
}

Jws_Acf_Compat::boot();
