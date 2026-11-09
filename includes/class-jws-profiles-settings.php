<?php

/**
 * Admin settings for viewer profiles.
 *
 * Site-wide options live in Theme Options > Profile > Viewer Profiles
 * (themes/streamvid/inc/admin/theme_option.php, ids profiles_*). Until an
 * admin saves them, every getter here returns the default the feature was
 * designed with. The per-level Profile limit is a field on PMPro's Edit
 * Level screen, stored as profile_limit_{level_id} next to the theme's
 * device_limit_{level_id}.
 *
 * The rest of the plugin reads these through filters (jws_profiles_*), so
 * a site without the theme keeps working with the defaults.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Profiles_Settings {

	/** A Theme Options value, or $default while the option has never been saved. */
	public static function get( $key, $default ) {

		if ( ! function_exists( 'jws_theme_get_option' ) ) {
			return $default;
		}

		$value = jws_theme_get_option( $key, null );

		return null === $value ? $default : $value;
	}

	public static function enabled( $enabled = true ) {
		return (bool) self::get( 'profiles_enabled', $enabled );
	}

	public static function default_limit( $limit ) {
		$value = (int) self::get( 'profiles_limit', $limit );
		return $value > 0 ? $value : $limit;
	}

	public static function kids_max( $age ) {
		$value = self::get( 'profiles_kids_max', $age );
		return is_numeric( $value ) ? (int) $value : $age;
	}

	public static function hide_untagged( $hide ) {
		return (bool) self::get( 'profiles_kids_untagged', $hide );
	}

	public static function gate_always() {
		return 'always' === self::get( 'profiles_gate', 'multi' );
	}

	public static function new_avatar_rule( $rule ) {
		return 'first' === self::get( 'profiles_avatar_new', $rule ) ? 'first' : 'random';
	}

	/** Where a Kids profile lands: the Kids Home Page when one is set, else the home page. */
	public static function kids_home_url() {

		$page = (int) self::get( 'profiles_kids_home', 0 );
		$url  = $page && 'publish' === get_post_status( $page ) ? get_permalink( $page ) : '';

		return $url ? $url : home_url( '/' );
	}

	/**
	 * Built-in avatars that are ticked, then the custom images, as stored
	 * values. Nothing ticked and no images falls back to every built-in, so
	 * the picker is never empty.
	 *
	 * @param string[] $avatars The plugin's built-in list.
	 * @return string[]
	 */
	public static function avatars( $avatars ) {

		$ticked = self::get( 'profiles_avatars', null );
		$list   = array();

		foreach ( $avatars as $value ) {
			$slug = substr( $value, strlen( 'builtin:' ) );
			if ( ! is_array( $ticked ) || ! empty( $ticked[ $slug ] ) ) {
				$list[] = $value;
			}
		}

		$custom = self::get( 'profiles_avatars_custom', '' );
		$ids    = is_array( $custom ) ? $custom : explode( ',', (string) $custom );

		foreach ( array_filter( array_map( 'absint', $ids ) ) as $id ) {
			if ( wp_attachment_is_image( $id ) ) {
				$list[] = 'att:' . $id;
			}
		}

		return $list ? $list : $avatars;
	}

	/* ------------------------------------------------------------------ *
	 * PMPro Edit Level: Profile limit
	 * ------------------------------------------------------------------ */

	public static function level_field( $level ) {

		$value = get_option( Jws_Profiles::OPTION_LEVEL_LIMIT . (int) $level->id, '' );
		?>
		<h3 class="topborder"><?php esc_html_e( 'Viewer Profiles', 'jws_streamvid' ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" valign="top"><label for="jws_profile_limit"><?php esc_html_e( 'Profile limit', 'jws_streamvid' ); ?></label></th>
					<td>
						<input id="jws_profile_limit" name="jws_profile_limit" type="number" min="1" max="<?php echo (int) Jws_Profiles::MAX_LIMIT; ?>" step="1" class="small-text" value="<?php echo esc_attr( $value ); ?>">
						<p class="description">
							<?php
							printf(
								/* translators: %d: site-wide profile limit */
								esc_html__( 'Profiles an account on this level can use, main profile included. Leave empty to use the Profile limit in Jws Settings > Theme Options > Profile > Viewer Profiles (now %d).', 'jws_streamvid' ),
								(int) self::default_limit( Jws_Profiles::DEFAULT_LIMIT )
							);
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Saves the field. PMPro has already checked its nonce before this hook;
	 * the capability and nonce are checked again so the field never relies on
	 * where it is called from.
	 */
	public static function save_level( $level_id ) {

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'pmpro_membershiplevels' ) ) {
			return;
		}

		$nonce = isset( $_REQUEST['pmpro_membershiplevels_nonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['pmpro_membershiplevels_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'save_membershiplevel' ) || ! isset( $_REQUEST['jws_profile_limit'] ) ) {
			return;
		}

		$key = Jws_Profiles::OPTION_LEVEL_LIMIT . (int) $level_id;
		$raw = trim( sanitize_text_field( wp_unslash( $_REQUEST['jws_profile_limit'] ) ) );

		if ( '' === $raw ) {
			delete_option( $key );
			return;
		}

		update_option( $key, max( 1, min( Jws_Profiles::MAX_LIMIT, absint( $raw ) ) ), false );
	}
}

add_filter( 'jws_profiles_enabled', array( 'Jws_Profiles_Settings', 'enabled' ) );
add_filter( 'jws_profiles_default_limit', array( 'Jws_Profiles_Settings', 'default_limit' ) );
add_filter( 'jws_profiles_kids_max_maturity', array( 'Jws_Profiles_Settings', 'kids_max' ) );
add_filter( 'jws_profiles_kids_hide_untagged', array( 'Jws_Profiles_Settings', 'hide_untagged' ) );
add_filter( 'jws_profiles_new_avatar_rule', array( 'Jws_Profiles_Settings', 'new_avatar_rule' ) );
add_filter( 'jws_profiles_avatars', array( 'Jws_Profiles_Settings', 'avatars' ), 5 );
add_action( 'pmpro_membership_level_after_other_settings', array( 'Jws_Profiles_Settings', 'level_field' ), 20 );
add_action( 'pmpro_save_membership_level', array( 'Jws_Profiles_Settings', 'save_level' ) );
