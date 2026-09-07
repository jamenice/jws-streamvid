<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}

/**
 * Unified payment system — settings screen.
 *
 * Right now this only holds the master switch and reports the state of the
 * three checkout paths that exist today (PMPro membership, buy/rent, drama
 * coins). Flipping "enabled" on does not yet reroute any of them — the
 * shared order/entitlement ledger and the PMPro/WooCommerce/coin hooks that
 * would actually keep them in sync are a separate, later change. This page
 * is the config surface that change will read from.
 */
class Jws_Payment_Settings {

	const OPTION = 'jws_payment_settings';
	const NONCE  = 'jws_payment_settings_save';
	const PAGE   = 'jws_payment_system';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
	}

	/**
	 * Priority 20: "Jws Settings" is registered by the theme, whose
	 * admin_menu callback runs after every plugin's at the default
	 * priority — registering earlier would attach this submenu to a
	 * parent slug that does not exist yet and it would silently vanish.
	 */
	public function register_submenu() {
		add_submenu_page(
			'jws_settings',
			esc_html__( 'Payment System', 'jws_streamvid' ),
			esc_html__( 'Payment System', 'jws_streamvid' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Storage                                                                 */
	/* ---------------------------------------------------------------------- */

	public static function defaults() {
		return array(
			'enabled' => 0,
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	public static function get( $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function save( array $settings ) {
		update_option( self::OPTION, $settings );
	}

	/** Whether the unified checkout should be treated as the source of truth. */
	public static function is_enabled() {
		return ! empty( self::get( 'enabled', 0 ) );
	}

	/* ---------------------------------------------------------------------- */
	/* Saving                                                                  */
	/* ---------------------------------------------------------------------- */

	private function handle_post() {

		if ( ! isset( $_POST['jws_payment_settings_nonce'] ) ) {
			return null;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jws_payment_settings_nonce'] ) ), self::NONCE ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'Security check failed. Nothing was saved.', 'jws_streamvid' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'You cannot change these settings.', 'jws_streamvid' ) );
		}

		$settings = self::sanitize( wp_unslash( $_POST ) );
		self::save( $settings );

		return array( 'type' => 'success', 'message' => esc_html__( 'Settings saved.', 'jws_streamvid' ) );
	}

	private static function sanitize( $raw ) {
		$out            = self::defaults();
		$out['enabled'] = ! empty( $raw['enabled'] ) ? 1 : 0;
		return $out;
	}

	/* ---------------------------------------------------------------------- */
	/* Rendering                                                               */
	/* ---------------------------------------------------------------------- */

	public function render_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = $this->handle_post();
		$s      = self::all();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Payment System', 'jws_streamvid' ); ?></h1>
			<p class="description" style="max-width:760px">
				<?php echo esc_html__( 'Central settings for the unified checkout system. Membership (Paid Memberships Pro), movie buy/rent and coin purchases currently run as three separate checkout paths — this switch is where they will be pointed at one shared, synced checkout going forward.', 'jws_streamvid' ); ?>
			</p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( self::NONCE, 'jws_payment_settings_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="enabled"><?php echo esc_html__( 'Unified checkout', 'jws_streamvid' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="enabled" name="enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
								<?php echo esc_html__( 'Enable the unified payment system', 'jws_streamvid' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'On: membership, buy/rent and coin purchases are synced through this checkout system. Off: each keeps working exactly as it does today, independently.', 'jws_streamvid' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( esc_html__( 'Save Settings', 'jws_streamvid' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'Current checkout paths', 'jws_streamvid' ); ?></h2>
			<table class="widefat striped" style="max-width:760px">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Checkout', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Handled by', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Access is still granted by', 'jws_streamvid' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html__( 'Membership', 'jws_streamvid' ); ?></td>
						<td>Paid Memberships Pro</td>
						<td>Paid Memberships Pro</td>
					</tr>
					<tr>
						<td><?php echo esc_html__( 'Buy / Rent movie', 'jws_streamvid' ); ?></td>
						<td>WooCommerce</td>
						<td><?php echo esc_html__( 'usermeta (unchanged)', 'jws_streamvid' ); ?></td>
					</tr>
					<tr>
						<td><?php echo esc_html__( 'Coins', 'jws_streamvid' ); ?></td>
						<td>WooCommerce / Stripe / PayPal (Drama)</td>
						<td><?php echo esc_html__( 'Drama coin wallet (unchanged)', 'jws_streamvid' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="description" style="max-width:760px">
				<?php echo esc_html__( 'While enabled, every completed purchase from the three paths above is also mirrored into the shared ledger below — nothing about how they grant access changes, this is only a shared record of what was bought, from anywhere.', 'jws_streamvid' ); ?>
			</p>

			<?php $this->render_recent_orders(); ?>
		</div>
		<?php
	}

	private function render_recent_orders() {

		$rows = class_exists( 'Jws_Payment_Ledger' ) ? Jws_Payment_Ledger::recent( 30 ) : array();
		?>
		<h2><?php echo esc_html__( 'Recently synced orders', 'jws_streamvid' ); ?></h2>
		<table class="widefat striped" style="max-width:960px">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Date', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'User', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Source', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Item', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Amount', 'jws_streamvid' ); ?></th>
					<th><?php echo esc_html__( 'Gateway', 'jws_streamvid' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr>
						<td colspan="7"><?php echo esc_html__( 'Nothing synced yet.', 'jws_streamvid' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php $user = get_userdata( $row->user_id ); ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><?php echo esc_html( $user ? $user->user_login : '#' . (int) $row->user_id ); ?></td>
							<td><?php echo esc_html( $row->type ); ?></td>
							<td><?php echo esc_html( $row->source ); ?></td>
							<td><?php echo esc_html( $row->item_label ); ?></td>
							<td><?php echo esc_html( $row->amount . ' ' . $row->currency ); ?></td>
							<td><?php echo esc_html( $row->gateway ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}
}
