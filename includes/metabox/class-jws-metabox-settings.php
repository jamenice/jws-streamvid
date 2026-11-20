<?php

/**
 * "Meta System" tab on the Sync Data page: shows which screens are handled by
 * Jws_Metabox instead of the ACF field groups, and the ACF compatibility state.
 *
 * The new meta box system is always on — every screen that has a box
 * registered uses it, and the matching ACF groups are hidden. Both systems
 * read and write the same post meta, so nothing needed migrating.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/metabox
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Metabox_Settings {

	const NONCE  = 'jws_meta_system_save';

	public function __construct() {
		add_filter( 'jws_sync_data_tabs', array( $this, 'register_tab' ) );
		add_filter( 'acf/load_field_groups', array( $this, 'hide_acf_groups' ), 30 );
	}

	/** @return string[] Every screen on the new system — i.e. all of them. */
	public static function enabled_post_types() {
		return array_merge( Jws_Metabox::supported_post_types(), array_keys( Jws_Metabox::term_toggles() ) );
	}

	/**
	 * The new meta box system is always used. Kept as a method because the box
	 * registry calls it per post type / toggle key.
	 */
	public static function is_enabled( $post_type ) {
		return true;
	}

	/** Every post type that will eventually move, in display order. */
	private function all_post_types() {
		$drama    = class_exists( 'Jws_Drama_Post_Types' ) ? Jws_Drama_Post_Types::DRAMA : 'drama';
		$drama_ep = class_exists( 'Jws_Drama_Post_Types' ) ? Jws_Drama_Post_Types::EPISODE : 'drama_ep';
		$types    = array(
			'tv_shows' => __( 'TV Shows', 'jws_streamvid' ),
			'movies'   => __( 'Movies', 'jws_streamvid' ),
			'videos'   => __( 'Videos', 'jws_streamvid' ),
			'episodes' => __( 'Episodes', 'jws_streamvid' ),
			$drama     => __( 'Drama', 'jws_streamvid' ),
			$drama_ep  => __( 'Drama Episodes', 'jws_streamvid' ),
		);
		// Anything else that has a meta box registered gets a row too.
		foreach ( Jws_Metabox::supported_post_types() as $type ) {
			if ( ! isset( $types[ $type ] ) ) {
				$obj            = get_post_type_object( $type );
				$types[ $type ] = $obj ? $obj->labels->name : $type;
			}
		}
		return $types + Jws_Metabox::term_toggles();
	}

	public function register_tab( $tabs ) {
		$tabs['meta_system'] = array(
			'label'    => __( 'Meta System', 'jws_streamvid' ),
			'callback' => array( $this, 'render_page' ),
		);
		return $tabs;
	}

	/**
	 * Drop the ACF groups the new boxes replace. Only on admin screens
	 * (and ACF's own screen-check ajax) — get_field() on the front end reads
	 * fields by key from the local store and is not affected either way.
	 */
	public function hide_acf_groups( $groups ) {
		if ( ! is_admin() ) {
			return $groups;
		}
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			if ( 0 !== strpos( $action, 'acf/' ) ) {
				return $groups;
			}
		}
		$hidden = Jws_Metabox::replaced_acf_groups();
		if ( ! $hidden ) {
			return $groups;
		}
		return array_values(
			array_filter(
				$groups,
				function ( $group ) use ( $hidden ) {
					return ! isset( $group['key'] ) || ! in_array( $group['key'], $hidden, true );
				}
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$supported = self::enabled_post_types();

		$schema_msg = '';
		if ( isset( $_POST['jws_acf_schema_export'] ) && check_admin_referer( self::NONCE, '_nonce_meta_system' ) ) {
			Jws_Acf_Compat::refresh_schema();
			$schema_msg = Jws_Acf_Compat::export_schema_file()
				? __( 'Field schema saved.', 'jws_streamvid' )
				: __( 'Could not write acf-schema.php — check file permissions.', 'jws_streamvid' );
		}
		?>
		<div class="card" style="max-width:800px;margin-top:20px;">
			<h2><?php esc_html_e( 'Meta System', 'jws_streamvid' ); ?></h2>
			<p><?php esc_html_e( 'The built-in meta box system is used everywhere it has boxes registered, and the ACF field groups it replaces are hidden. Both systems store data in the same meta keys, so nothing was migrated.', 'jws_streamvid' ); ?></p>

			<?php if ( ! function_exists( 'acf_add_local_field_group' ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'ACF is not active. Screens with no built-in meta box have no field UI.', 'jws_streamvid' ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped" style="margin:10px 0;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Screen', 'jws_streamvid' ); ?></th>
						<th><?php esc_html_e( 'Status', 'jws_streamvid' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $this->all_post_types() as $type => $label ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $label ); ?></strong> <code><?php echo esc_html( $type ); ?></code></td>
							<td>
								<?php
								if ( in_array( $type, $supported, true ) ) {
									echo '<span style="color:#008a20;">' . esc_html__( 'New meta system', 'jws_streamvid' ) . '</span>';
								} else {
									esc_html_e( 'No built-in box yet — still on ACF', 'jws_streamvid' );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		$schema    = Jws_Acf_Compat::schema();
		$acf_on    = Jws_Acf_Compat::acf_active();
		$file_time = file_exists( Jws_Acf_Compat::SCHEMA_FILE ) ? filemtime( Jws_Acf_Compat::SCHEMA_FILE ) : 0;
		?>
		<div class="card" style="max-width:800px;margin-top:20px;">
			<h2><?php esc_html_e( 'ACF compatibility', 'jws_streamvid' ); ?></h2>
			<p><?php esc_html_e( 'When ACF is deactivated, this plugin provides get_field() and update_field() itself, reading the same data with the same return values, so the theme, the app API and the importers keep working. Field types and return formats come from a snapshot of the ACF field groups, refreshed automatically while ACF is active.', 'jws_streamvid' ); ?></p>

			<?php if ( $schema_msg ) : ?>
				<div class="notice notice-info inline"><p><?php echo esc_html( $schema_msg ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped" style="margin:10px 0;">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'ACF plugin', 'jws_streamvid' ); ?></th>
						<td><?php echo $acf_on ? esc_html__( 'Active — ACF handles get_field()', 'jws_streamvid' ) : '<strong style="color:#008a20;">' . esc_html__( 'Not active — built-in get_field() / update_field() in use', 'jws_streamvid' ) . '</strong>'; ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Field schema', 'jws_streamvid' ); ?></th>
						<td>
							<?php
							printf(
								/* translators: 1: number of fields, 2: date */
								esc_html__( '%1$d fields, snapshot from %2$s', 'jws_streamvid' ),
								count( $schema['fields'] ),
								$schema['generated'] ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $schema['generated'] ) ) : '—'
							);
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Bundled copy (acf-schema.php)', 'jws_streamvid' ); ?></th>
						<td><?php echo $file_time ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $file_time ) ) : esc_html__( 'Missing', 'jws_streamvid' ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $acf_on ) : ?>
				<form method="post">
					<?php wp_nonce_field( self::NONCE, '_nonce_meta_system' ); ?>
					<?php submit_button( __( 'Refresh schema now', 'jws_streamvid' ), 'secondary', 'jws_acf_schema_export', false ); ?>
					<p class="description"><?php esc_html_e( 'Run this after changing any ACF field, before deactivating ACF.', 'jws_streamvid' ); ?></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}

new Jws_Metabox_Settings();
