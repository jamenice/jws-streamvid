<?php

/**
 * "Sync Data Tables" admin page under Jws Settings.
 *
 * Copies the legacy `{post_type}_liked` / `post_watchlist` / `video_progress_data`
 * usermeta rows into the new jws_favorites / jws_watchlist / jws_history tables
 * (see class-jws-streamvid-tables.php). The migration only inserts — it never
 * touches the old usermeta — so it is safe to run more than once and the old
 * data stays as a backup until cleared explicitly.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Streamvid_Migration {

	const NONCE_MIGRATE = 'jws_sync_tables_migrate';
	const NONCE_CLEAR    = 'jws_sync_tables_clear_meta';

	public function __construct() {
		// The theme merges every sync tool into a single "Sync Data" page, so
		// register a tab there. Fall back to a standalone submenu on themes
		// that do not provide that page.
		add_filter( 'jws_sync_data_tabs', array( $this, 'register_tab' ) );
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 25 );
	}

	public function register_tab( $tabs ) {
		$tabs['data_tables'] = array(
			'label'    => __( 'Sync Data Tables', 'jws_streamvid' ),
			'callback' => array( $this, 'render_page' ),
		);

		return $tabs;
	}

	public function register_submenu() {
		if ( defined( 'JWS_SYNC_DATA_PAGE' ) ) {
			return;
		}

		add_submenu_page(
			'jws_settings',
			__( 'Sync Data Tables', 'jws_streamvid' ),
			__( 'Sync Data Tables', 'jws_streamvid' ),
			'manage_options',
			'jws_sync_tables',
			array( $this, 'render_page' )
		);
	}

	private function meta_user_count( $meta_keys ) {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders})",
			$meta_keys
		) );
	}

	private function table_row_count( $table ) {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$results = array();

		if ( isset( $_POST['jws_migrate'] ) && check_admin_referer( self::NONCE_MIGRATE, '_nonce_migrate' ) ) {
			$target = sanitize_text_field( $_POST['jws_migrate'] );
			if ( 'favorites' === $target ) {
				$results['favorites'] = Jws_Favorites::migrate_from_meta();
			} elseif ( 'watchlist' === $target ) {
				$results['watchlist'] = Jws_Watchlist::migrate_from_meta();
			} elseif ( 'history' === $target ) {
				$results['history'] = Jws_History::migrate_from_meta();
			}
		}

		if ( isset( $_POST['jws_clear_meta'] ) && check_admin_referer( self::NONCE_CLEAR, '_nonce_clear' ) ) {
			$target = sanitize_text_field( $_POST['jws_clear_meta'] );
			$meta_keys = array();
			if ( 'favorites' === $target ) {
				$meta_keys = array( 'movies_liked', 'tv_shows_liked', 'videos_liked' );
			} elseif ( 'watchlist' === $target ) {
				$meta_keys = array( 'post_watchlist' );
			} elseif ( 'history' === $target ) {
				$meta_keys = array( 'video_progress_data' );
			}
			if ( $meta_keys ) {
				global $wpdb;
				$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ({$placeholders})", $meta_keys ) );
				$results['cleared'] = $target;
			}
		}

		$blocks = array(
			'favorites' => array(
				'label'     => __( 'Favorites', 'jws_streamvid' ),
				'meta_keys' => array( 'movies_liked', 'tv_shows_liked', 'videos_liked' ),
				'table'     => Jws_Streamvid_Tables::table_favorites(),
			),
			'watchlist' => array(
				'label'     => __( 'Watchlist', 'jws_streamvid' ),
				'meta_keys' => array( 'post_watchlist' ),
				'table'     => Jws_Streamvid_Tables::table_watchlist(),
			),
			'history' => array(
				'label'     => __( 'History', 'jws_streamvid' ),
				'meta_keys' => array( 'video_progress_data' ),
				'table'     => Jws_Streamvid_Tables::table_history(),
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sync Data Tables', 'jws_streamvid' ); ?></h1>
			<p><?php esc_html_e( 'Favorites, Watchlist and History now read/write a dedicated table instead of user meta. Use this page to copy existing user meta data into those tables.', 'jws_streamvid' ); ?></p>

			<?php foreach ( $blocks as $key => $block ) : ?>
				<div class="card" style="max-width:800px;margin-top:20px;">
					<h2><?php echo esc_html( $block['label'] ); ?></h2>

					<?php if ( isset( $results[ $key ] ) ) : $r = $results[ $key ]; ?>
						<div class="notice notice-success" style="margin:15px 0;">
							<p>
								<?php
								printf(
									/* translators: 1: rows migrated, 2: users with old meta found */
									esc_html__( 'Migrated %1$d row(s) from %2$d user(s).', 'jws_streamvid' ),
									(int) ( $r['migrated'] ?? 0 ),
									(int) ( $r['users'] ?? 0 )
								);
								?>
							</p>
						</div>
					<?php elseif ( isset( $results['cleared'] ) && $results['cleared'] === $key ) : ?>
						<div class="notice notice-success" style="margin:15px 0;">
							<p><?php esc_html_e( 'Old user meta cleared.', 'jws_streamvid' ); ?></p>
						</div>
					<?php endif; ?>

					<table class="widefat" style="margin:10px 0;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Users with old meta', 'jws_streamvid' ); ?></th>
								<th><?php esc_html_e( 'Rows currently in table', 'jws_streamvid' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php echo esc_html( $this->meta_user_count( $block['meta_keys'] ) ); ?></td>
								<td><?php echo esc_html( $this->table_row_count( $block['table'] ) ); ?></td>
							</tr>
						</tbody>
					</table>

					<form method="post" style="display:inline-block;margin-right:10px;">
						<?php wp_nonce_field( self::NONCE_MIGRATE, '_nonce_migrate' ); ?>
						<input type="hidden" name="jws_migrate" value="<?php echo esc_attr( $key ); ?>">
						<?php submit_button( __( 'Migrate Now', 'jws_streamvid' ), 'primary', 'submit', false ); ?>
					</form>

					<form method="post" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete the old user meta for this feature? The table data migrated so far will not be affected.', 'jws_streamvid' ) ); ?>');">
						<?php wp_nonce_field( self::NONCE_CLEAR, '_nonce_clear' ); ?>
						<input type="hidden" name="jws_clear_meta" value="<?php echo esc_attr( $key ); ?>">
						<?php submit_button( __( 'Clear Old Meta (cleanup)', 'jws_streamvid' ), 'delete', 'submit', false ); ?>
					</form>

					<p class="description" style="margin-top:10px;">
						<?php esc_html_e( 'Safe to run Migrate Now more than once — rows already present are skipped. Old user meta is kept as backup until you clear it.', 'jws_streamvid' ); ?>
					</p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

new Jws_Streamvid_Migration();
