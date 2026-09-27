<?php

/**
 * Live TV › Import Demo: one click for a site that has no channels yet.
 *
 * The demo travels with the plugin — demo/demo.json (categories, channels,
 * their program guides) plus the logos and pictures in demo/logos and
 * demo/images — so it imports the same on any domain, without reaching back
 * to the site it was made on.
 *
 * The import runs as a few short admin-ajax steps (categories, then one
 * channel per request, then the menu link and sample page) so a host's time
 * limit never cuts it off halfway; demo-import.js drives them. Everything it
 * creates carries the `_jws_tv_demo` mark, which is how "Remove demo" finds
 * it again and how a second import updates the same channels rather than
 * doubling them. Pictures already imported are reused.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/tv_channel
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Tv_Channel_Demo {

	const PAGE         = 'jws-tv-demo';
	const NONCE        = 'jws_tv_demo';
	const MARK         = '_jws_tv_demo';
	const MEDIA_OPTION = 'jws_tv_demo_media';
	const DONE_OPTION  = 'jws_tv_demo_imported';
	const CAPABILITY   = 'manage_options';

	/** Field keys of the channel box, so get_field() reads the demo too. */
	const KEY = 'field_tv_channel_';

	/** @var string */
	private static $hook_suffix = '';

	/** @var string[] Problems worth telling the admin about, per request. */
	private static $warnings = array();

	public static function hook() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_jws_tv_demo_import', array( __CLASS__, 'ajax_import' ) );
		add_action( 'wp_ajax_jws_tv_demo_remove', array( __CLASS__, 'ajax_remove' ) );
		add_filter( 'display_post_states', array( __CLASS__, 'post_state' ), 10, 2 );
	}

	private static function dir() {
		return plugin_dir_path( __FILE__ ) . 'demo/';
	}

	/** @return array|null The demo package. */
	public static function data() {

		static $data = null;

		if ( null === $data ) {
			$file = self::dir() . 'demo.json';
			$data = file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
			$data = is_array( $data ) && ! empty( $data['channels'] ) ? $data : null;
		}

		return $data;
	}

	/* ---------------------------------------------------------------------- */
	/* Screen                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function menu() {

		self::$hook_suffix = add_submenu_page(
			'edit.php?post_type=' . Jws_Tv_Channel::POST_TYPE,
			__( 'Import Live TV Demo', 'jws_streamvid' ),
			__( 'Import Demo', 'jws_streamvid' ),
			self::CAPABILITY,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function assets( $hook ) {

		if ( ! self::$hook_suffix || $hook !== self::$hook_suffix ) {
			return;
		}

		$version = defined( 'JWS_STREAMVID_VERSION' ) ? JWS_STREAMVID_VERSION : '1.0.0';
		$file    = plugin_dir_path( __FILE__ ) . 'assets/demo-import.js';

		wp_enqueue_script(
			'jws-tv-demo',
			plugin_dir_url( __FILE__ ) . 'assets/demo-import.js',
			array(),
			file_exists( $file ) ? $version . '.' . filemtime( $file ) : $version,
			true
		);

		wp_localize_script(
			'jws-tv-demo',
			'jwsTvDemo',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'categories'    => __( 'Channel categories ready.', 'jws_streamvid' ),
					/* translators: %s: channel name */
					'importing'     => __( 'Importing %s…', 'jws_streamvid' ),
					'finishing'     => __( 'Adding the menu link and the sample page…', 'jws_streamvid' ),
					'done'          => __( 'Demo imported.', 'jws_streamvid' ),
					'view'          => __( 'View Live TV', 'jws_streamvid' ),
					'channels'      => __( 'Manage channels', 'jws_streamvid' ),
					'editPage'      => __( 'Edit the sample page', 'jws_streamvid' ),
					'failed'        => __( 'The import stopped. Run it again — it picks up where it left off.', 'jws_streamvid' ),
					'confirmRemove' => __( 'Delete the demo channels, their images, the sample page and the menu link? Channels you added yourself are kept.', 'jws_streamvid' ),
					'removing'      => __( 'Removing the demo…', 'jws_streamvid' ),
				),
			)
		);
	}

	public static function render() {

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		$data     = self::data();
		$imported = (int) get_option( self::DONE_OPTION );
		$levels   = self::membership_levels();
		$menus    = wp_get_nav_menus();
		$premium  = $data ? wp_list_pluck( array_filter( $data['channels'], function ( $c ) { return ! empty( $c['premium'] ); } ), 'name' ) : array();
		$programs = $data ? array_sum( array_map( function ( $c ) { return count( $c['programs'] ); }, $data['channels'] ) ) : 0;
		$media    = $data ? count( glob( self::dir() . 'images/*.jpg' ) ) + count( glob( self::dir() . 'logos/*.png' ) ) : 0;
		?>
		<div class="wrap jws-tv-demo">
			<h1><?php esc_html_e( 'Import Live TV Demo', 'jws_streamvid' ); ?></h1>

			<?php if ( ! $data ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'The demo package (includes/tv_channel/demo/demo.json) is missing or unreadable.', 'jws_streamvid' ); ?></p></div>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( $imported ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php
						/* translators: %s: date and time */
						echo esc_html( sprintf( __( 'The demo was imported on %s. Importing again updates the same channels instead of adding new ones.', 'jws_streamvid' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $imported ) ) );
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="card jws-tv-demo__card">
				<h2><?php esc_html_e( 'What gets created', 'jws_streamvid' ); ?></h2>
				<ul class="jws-tv-demo__list">
					<li>
						<?php
						/* translators: 1: number of channels, 2: number of programs */
						echo esc_html( sprintf( __( '%1$d channels with a logo, a cover, a stream and a full-day guide (%2$d programs, repeating every day).', 'jws_streamvid' ), count( $data['channels'] ), $programs ) );
						?>
					</li>
					<li>
						<?php
						/* translators: %s: category names */
						echo esc_html( sprintf( __( 'Channel categories: %s.', 'jws_streamvid' ), implode( ', ', $data['categories'] ) ) );
						?>
					</li>
					<li>
						<?php
						/* translators: %d: number of files */
						echo esc_html( sprintf( __( '%d images added to the Media Library.', 'jws_streamvid' ), $media ) );
						?>
					</li>
				</ul>
				<p class="description"><?php esc_html_e( 'The channels play public HLS test streams. Put your own stream on each channel under Channel Settings › Stream. Guide times follow the site timezone (Settings › General).', 'jws_streamvid' ); ?></p>

				<h2><?php esc_html_e( 'Options', 'jws_streamvid' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Premium channels', 'jws_streamvid' ); ?></th>
						<td>
							<?php if ( $levels ) : ?>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Membership levels for the premium channels', 'jws_streamvid' ); ?></legend>
									<?php foreach ( $levels as $level ) : ?>
										<label class="jws-tv-demo__level"><input type="checkbox" name="levels[]" value="<?php echo (int) $level->id; ?>"> <?php echo esc_html( $level->name ); ?></label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description">
									<?php
									/* translators: %s: channel names */
									echo esc_html( sprintf( __( '%s will require one of the ticked levels. Tick none to leave every channel free.', 'jws_streamvid' ), implode( ' & ', $premium ) ) );
									?>
								</p>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Paid Memberships Pro has no levels here, so every channel stays free.', 'jws_streamvid' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="jws-tv-demo-menu"><?php esc_html_e( 'Menu link', 'jws_streamvid' ); ?></label></th>
						<td>
							<select id="jws-tv-demo-menu" name="menu">
								<option value="0"><?php esc_html_e( '— Don’t add —', 'jws_streamvid' ); ?></option>
								<?php foreach ( $menus as $menu ) : ?>
									<option value="<?php echo (int) $menu->term_id; ?>"><?php echo esc_html( $menu->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Adds "Live TV" at the end of this menu, unless it already links there.', 'jws_streamvid' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sample page', 'jws_streamvid' ); ?></th>
						<td>
							<?php if ( did_action( 'elementor/loaded' ) ) : ?>
								<label><input type="checkbox" name="page" value="1" checked> <?php esc_html_e( 'Create a draft page with the three Live TV widget layouts (Spotlight, On now, Channel rail) to copy into your home page.', 'jws_streamvid' ); ?></label>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'Needs Elementor.', 'jws_streamvid' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="jws-tv-demo__actions">
					<button type="button" class="button button-primary button-hero" id="jws-tv-demo-import"><?php echo esc_html( $imported ? __( 'Import again', 'jws_streamvid' ) : __( 'Import demo', 'jws_streamvid' ) ); ?></button>
				</p>

				<div class="jws-tv-demo__progress" id="jws-tv-demo-progress" hidden>
					<progress max="100" value="0"></progress>
					<p class="jws-tv-demo__status" id="jws-tv-demo-status" role="status" aria-live="polite"></p>
					<ol class="jws-tv-demo__log" id="jws-tv-demo-log"></ol>
				</div>
			</div>

			<div class="card jws-tv-demo__card">
				<h2><?php esc_html_e( 'Remove the demo', 'jws_streamvid' ); ?></h2>
				<p><?php esc_html_e( 'Deletes the demo channels (with any changes made to them), their images, the sample page and the menu link. Channels you added yourself are kept.', 'jws_streamvid' ); ?></p>
				<p><button type="button" class="button button-link-delete" id="jws-tv-demo-remove"><?php esc_html_e( 'Remove demo content', 'jws_streamvid' ); ?></button></p>
				<p class="jws-tv-demo__status" id="jws-tv-demo-remove-status" role="status" aria-live="polite"></p>
			</div>
		</div>
		<style>
			.jws-tv-demo__card { max-width: 820px; padding: 8px 24px 20px; }
			.jws-tv-demo__list { list-style: disc; margin-left: 20px; }
			.jws-tv-demo__level { display: inline-block; margin: 0 18px 6px 0; }
			.jws-tv-demo__actions { margin: 20px 0 8px; }
			.jws-tv-demo__progress progress { width: 100%; height: 12px; }
			.jws-tv-demo__status { font-weight: 600; }
			.jws-tv-demo__status.is-error { color: #b32d2e; }
			.jws-tv-demo__status a { margin-left: 12px; font-weight: 400; }
			.jws-tv-demo__log { max-height: 240px; overflow: auto; margin: 8px 0 0 20px; color: #50575e; }
			.jws-tv-demo__log .is-warning { color: #996800; }
		</style>
		<?php
	}

	/** Channels and pages the demo made get a "Demo" label in their lists. */
	public static function post_state( $states, $post ) {

		if ( in_array( $post->post_type, array( Jws_Tv_Channel::POST_TYPE, 'page' ), true ) && get_post_meta( $post->ID, self::MARK, true ) ) {
			$states['jws_tv_demo'] = __( 'Demo', 'jws_streamvid' );
		}

		return $states;
	}

	private static function membership_levels() {

		if ( ! function_exists( 'pmpro_getAllLevels' ) ) {
			return array();
		}

		$levels = pmpro_getAllLevels( true, false );

		return is_array( $levels ) ? array_values( $levels ) : array();
	}

	/* ---------------------------------------------------------------------- */
	/* Import                                                                  */
	/* ---------------------------------------------------------------------- */

	private static function guard() {

		check_ajax_referer( self::NONCE );

		if ( ! current_user_can( self::CAPABILITY ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to import the demo.', 'jws_streamvid' ) ), 403 );
		}

		if ( ! self::data() ) {
			wp_send_json_error( array( 'message' => __( 'The demo package is missing or unreadable.', 'jws_streamvid' ) ), 500 );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- disabled on some hosts.
		}
	}

	public static function ajax_import() {

		self::guard();

		$data = self::data();
		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked in guard().

		switch ( $step ) {

			case 'start':
				self::import_categories( $data['categories'] );
				wp_send_json_success( array( 'channels' => wp_list_pluck( $data['channels'], 'name' ) ) );
				break;

			case 'channel':
				$index = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification
				if ( ! isset( $data['channels'][ $index ] ) ) {
					wp_send_json_error( array( 'message' => __( 'Unknown channel.', 'jws_streamvid' ) ), 400 );
				}
				$levels = isset( $_POST['levels'] ) ? array_filter( array_map( 'absint', (array) wp_unslash( $_POST['levels'] ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
				$result = self::import_channel( $data['channels'][ $index ], $levels );
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
				}
				wp_send_json_success(
					array(
						/* translators: 1: channel number, 2: channel name */
						'message'  => sprintf( __( 'CH %1$s · %2$s', 'jws_streamvid' ), $data['channels'][ $index ]['number'], $data['channels'][ $index ]['name'] ),
						'warnings' => self::$warnings,
					)
				);
				break;

			case 'finish':
				$menu     = isset( $_POST['menu'] ) ? absint( $_POST['menu'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
				$page     = ! empty( $_POST['page'] ); // phpcs:ignore WordPress.Security.NonceVerification
				$messages = array();

				if ( $menu ) {
					$messages[] = self::add_menu_item( $menu );
				}

				$page_id = $page ? self::create_page() : 0;
				if ( $page_id ) {
					$messages[] = __( 'Draft page "Live TV Home" is ready.', 'jws_streamvid' );
				}

				update_option( self::DONE_OPTION, time(), false );

				wp_send_json_success(
					array(
						'messages' => array_values( array_filter( $messages ) ),
						'warnings' => self::$warnings,
						'links'    => array(
							'archive'  => get_post_type_archive_link( Jws_Tv_Channel::POST_TYPE ),
							'channels' => admin_url( 'edit.php?post_type=' . Jws_Tv_Channel::POST_TYPE ),
							'page'     => $page_id ? admin_url( 'post.php?post=' . $page_id . '&action=elementor' ) : '',
						),
					)
				);
				break;
		}

		wp_send_json_error( array( 'message' => __( 'Unknown step.', 'jws_streamvid' ) ), 400 );
	}

	private static function import_categories( $names ) {

		foreach ( $names as $name ) {

			if ( term_exists( $name, Jws_Tv_Channel::TAX ) ) {
				continue;
			}

			$term = wp_insert_term( $name, Jws_Tv_Channel::TAX );

			if ( ! is_wp_error( $term ) ) {
				update_term_meta( $term['term_id'], self::MARK, 1 );
			}
		}
	}

	/** A value plus the `_name` → field key reference Jws_Metabox and get_field() use. */
	private static function field( $post_id, $name, $value, $key ) {
		update_post_meta( $post_id, $name, $value );
		update_post_meta( $post_id, '_' . $name, self::KEY . $key );
	}

	/**
	 * @param array $c      One channel of demo.json.
	 * @param int[] $levels PMPro levels for the channels flagged premium.
	 * @return int|WP_Error
	 */
	private static function import_channel( $c, $levels ) {

		$existing = get_posts(
			array(
				'post_type'      => Jws_Tv_Channel::POST_TYPE,
				'name'           => $c['slug'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		$post = array(
			'post_type'    => Jws_Tv_Channel::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $c['name'],
			'post_name'    => $c['slug'],
			'post_content' => $c['about'],
		);

		if ( $existing ) {
			$post['ID'] = (int) $existing[0];
			$id         = wp_update_post( wp_slash( $post ), true );
		} else {
			$post['post_author'] = get_current_user_id();
			$id                  = wp_insert_post( wp_slash( $post ), true );
		}

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::MARK, 1 );

		self::field( $id, 'tv_stream_url', $c['stream'], 'stream_url' );
		self::field( $id, 'tv_channel_number', (string) $c['number'], 'number' );
		self::field( $id, 'tv_channel_quality', $c['quality'], 'quality' );
		self::field( $id, 'tv_channel_language', $c['language'], 'language' );
		self::field( $id, 'tv_channel_featured', empty( $c['featured'] ) ? 0 : 1, 'featured' );

		$logo = self::media( 'logos/' . $c['logo'], $c['name'] );
		self::field( $id, 'tv_channel_logo', $logo ? (string) $logo : '', 'logo' );

		$cover = self::media( 'images/' . $c['cover'] . '.jpg', $c['name'] );
		if ( $cover ) {
			set_post_thumbnail( $id, $cover );
		}

		$term = term_exists( $c['category'], Jws_Tv_Channel::TAX );
		if ( $term ) {
			wp_set_object_terms( $id, array( (int) ( is_array( $term ) ? $term['term_id'] : $term ) ), Jws_Tv_Channel::TAX );
		}

		self::write_schedule( $id, $c['programs'] );
		self::set_premium( $id, ! empty( $c['premium'] ) ? $levels : array() );

		Jws_Tv_Channel_Schedule::flush( $id );

		return (int) $id;
	}

	private static function write_schedule( $id, $programs ) {

		$meta  = Jws_Tv_Channel_Schedule::META;
		$cells = array( 'start', 'end', 'days', 'date', 'title', 'image', 'desc' );
		$old   = (int) get_post_meta( $id, $meta, true );

		for ( $i = 0; $i < $old; $i++ ) {
			foreach ( $cells as $cell ) {
				delete_post_meta( $id, "{$meta}_{$i}_{$cell}" );
				delete_post_meta( $id, "_{$meta}_{$i}_{$cell}" );
			}
		}

		foreach ( array_values( $programs ) as $i => $p ) {

			$image = ! empty( $p['image'] ) ? self::media( 'images/' . $p['image'] . '.jpg', $p['title'] ) : 0;

			$values = array(
				'start' => $p['start'],
				'end'   => isset( $p['end'] ) ? $p['end'] : '',
				'days'  => isset( $p['days'] ) ? $p['days'] : 'daily',
				'date'  => '',
				'title' => $p['title'],
				'image' => $image ? (string) $image : '',
				'desc'  => isset( $p['desc'] ) ? $p['desc'] : '',
			);

			foreach ( $values as $cell => $value ) {
				self::field( $id, "{$meta}_{$i}_{$cell}", $value, 'sch_' . $cell );
			}
		}

		self::field( $id, $meta, count( $programs ), 'schedule' );
	}

	private static function set_premium( $id, $levels ) {

		global $wpdb;

		if ( ! function_exists( 'pmpro_has_membership_access' ) || empty( $wpdb->pmpro_memberships_pages ) ) {
			return;
		}

		$wpdb->delete( $wpdb->pmpro_memberships_pages, array( 'page_id' => $id ), array( '%d' ) );

		foreach ( $levels as $level ) {
			$wpdb->insert( $wpdb->pmpro_memberships_pages, array( 'membership_id' => (int) $level, 'page_id' => (int) $id ), array( '%d', '%d' ) );
		}
	}

	/** Only the sizes the Live TV templates use — a demo picture does not need twenty crops. */
	public static function limit_sizes( $sizes ) {
		return array_intersect_key( $sizes, array_flip( array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) );
	}

	/**
	 * A bundled file in the Media Library, imported once and reused after.
	 *
	 * @param string $file  Path under demo/.
	 * @param string $title Attachment title.
	 * @return int Attachment id, 0 when it could not be imported.
	 */
	private static function media( $file, $title ) {

		$map = get_option( self::MEDIA_OPTION, array() );
		$map = is_array( $map ) ? $map : array();

		if ( ! empty( $map[ $file ] ) && 'attachment' === get_post_type( $map[ $file ] ) ) {
			return (int) $map[ $file ];
		}

		$path = self::dir() . $file;

		if ( ! file_exists( $path ) ) {
			/* translators: %s: file name */
			self::$warnings[] = sprintf( __( 'Missing demo file: %s', 'jws_streamvid' ), $file );
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		wp_raise_memory_limit( 'image' );

		$tmp = wp_tempnam( basename( $file ) );
		copy( $path, $tmp );

		add_filter( 'intermediate_image_sizes_advanced', array( __CLASS__, 'limit_sizes' ) );
		$id = media_handle_sideload( array( 'name' => 'live-tv-' . basename( $file ), 'tmp_name' => $tmp ), 0, $title );
		remove_filter( 'intermediate_image_sizes_advanced', array( __CLASS__, 'limit_sizes' ) );

		if ( is_wp_error( $id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			/* translators: 1: file name, 2: error */
			self::$warnings[] = sprintf( __( 'Could not add %1$s to the Media Library: %2$s', 'jws_streamvid' ), $file, $id->get_error_message() );
			return 0;
		}

		update_post_meta( $id, self::MARK, 1 );

		$map[ $file ] = (int) $id;
		update_option( self::MEDIA_OPTION, $map, false );

		return (int) $id;
	}

	private static function add_menu_item( $menu_id ) {

		if ( ! is_nav_menu( $menu_id ) ) {
			return '';
		}

		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
			if ( 'post_type_archive' === $item->type && Jws_Tv_Channel::POST_TYPE === $item->object ) {
				return __( 'The menu already links to Live TV.', 'jws_streamvid' );
			}
		}

		$item = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => __( 'Live TV', 'jws_streamvid' ),
				'menu-item-type'   => 'post_type_archive',
				'menu-item-object' => Jws_Tv_Channel::POST_TYPE,
				'menu-item-status' => 'publish',
			)
		);

		if ( is_wp_error( $item ) ) {
			self::$warnings[] = $item->get_error_message();
			return '';
		}

		update_post_meta( $item, self::MARK, 1 );

		return __( '"Live TV" added to the menu.', 'jws_streamvid' );
	}

	/** A draft page holding the widget's three layouts, for copying into a home page. */
	private static function create_page() {

		if ( ! did_action( 'elementor/loaded' ) ) {
			return 0;
		}

		$section = function ( $n, $settings ) {
			return array(
				'id'       => 'tvdemo' . $n,
				'elType'   => 'container',
				'isInner'  => false,
				'settings' => array(
					'content_width'  => 'full',
					'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '70', 'bottom' => '0', 'left' => '70', 'isLinked' => false ),
					'padding_mobile' => array( 'unit' => 'px', 'top' => '0', 'right' => '16', 'bottom' => '0', 'left' => '16', 'isLinked' => false ),
					'margin'         => array( 'unit' => 'px', 'top' => '0', 'right' => 0, 'bottom' => '64', 'left' => 0, 'isLinked' => false ),
					'margin_mobile'  => array( 'unit' => 'px', 'top' => '0', 'right' => 0, 'bottom' => '36', 'left' => 0, 'isLinked' => false ),
				),
				'elements' => array(
					array(
						'id'         => 'tvdemw' . $n,
						'elType'     => 'widget',
						'widgetType' => 'jws-tv-channels',
						'settings'   => $settings,
						'elements'   => array(),
					),
				),
			);
		};

		$elements = array(
			$section( 1, array( 'layout' => 'spotlight', 'title' => __( 'Live TV', 'jws_streamvid' ), 'limit' => 12 ) ),
			$section( 2, array( 'layout' => 'on_now', 'title' => __( 'On now', 'jws_streamvid' ), 'live_badge' => '', 'limit' => 12 ) ),
			$section( 3, array( 'layout' => 'rail', 'title' => __( 'Browse channels', 'jws_streamvid' ), 'live_badge' => '', 'limit' => 12 ) ),
		);

		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		$page = array(
			'post_type'   => 'page',
			'post_status' => 'draft',
			'post_title'  => __( 'Live TV Home', 'jws_streamvid' ),
		);

		if ( $existing ) {
			$page['ID']          = (int) $existing[0];
			$page['post_status'] = get_post_status( $existing[0] );
			$id                  = wp_update_post( $page, true );
		} else {
			$id = wp_insert_post( $page, true );
		}

		if ( is_wp_error( $id ) ) {
			self::$warnings[] = $id->get_error_message();
			return 0;
		}

		update_post_meta( $id, self::MARK, 1 );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $elements ) ) );
		delete_post_meta( $id, '_elementor_css' );
		delete_post_meta( $id, '_elementor_element_cache' );

		return (int) $id;
	}

	/* ---------------------------------------------------------------------- */
	/* Remove                                                                  */
	/* ---------------------------------------------------------------------- */

	public static function ajax_remove() {

		self::guard();

		global $wpdb;

		$ids = get_posts(
			array(
				'post_type'      => array( Jws_Tv_Channel::POST_TYPE, 'attachment', 'page', 'nav_menu_item' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		$counts = array( 'channels' => 0, 'media' => 0 );

		foreach ( $ids as $id ) {

			$type = get_post_type( $id );

			if ( 'attachment' === $type ) {
				wp_delete_attachment( $id, true );
				++$counts['media'];
				continue;
			}

			if ( Jws_Tv_Channel::POST_TYPE === $type ) {
				++$counts['channels'];
				if ( ! empty( $wpdb->pmpro_memberships_pages ) ) {
					$wpdb->delete( $wpdb->pmpro_memberships_pages, array( 'page_id' => $id ), array( '%d' ) );
				}
			}

			wp_delete_post( $id, true );
		}

		/* Categories the demo made, once nothing is left in them. */
		$terms = get_terms(
			array(
				'taxonomy'   => Jws_Tv_Channel::TAX,
				'hide_empty' => false,
				'meta_key'   => self::MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( 0 === (int) $term->count ) {
					wp_delete_term( $term->term_id, Jws_Tv_Channel::TAX );
				}
			}
		}

		delete_option( self::MEDIA_OPTION );
		delete_option( self::DONE_OPTION );

		wp_send_json_success(
			array(
				/* translators: 1: number of channels, 2: number of images */
				'message' => sprintf( __( 'Removed %1$d demo channels and %2$d images.', 'jws_streamvid' ), $counts['channels'], $counts['media'] ),
			)
		);
	}
}
