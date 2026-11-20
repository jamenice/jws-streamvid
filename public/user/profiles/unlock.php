<?php
/**
 * Before editing a profile with Profile Lock (that is not the one in use):
 * its PIN, or the account password. Shown by edit.php; posts the unlock
 * action, which opens the profile for editing for a short while
 * (Jws_Profiles_Front::UNLOCK_TTL).
 *
 * @package Jws_Streamvid
 *
 * @var array $args { @type object $profile The locked profile. }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_pf_profile = $args['profile'];
$jws_pf_error   = Jws_Profiles_Front::error( 'unlock' );
$jws_pf_form    = Jws_Profiles_Front::error();
$jws_pf_no_pass = function_exists( 'jws_streamvid_password_unknown' ) && jws_streamvid_password_unknown( get_current_user_id() );
?>
<section class="jws-pf-card jws-pf-pin jws-pf-unlock" aria-labelledby="jws-pf-title">

	<a class="jws-pf-close" href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose', 0, array( 'manage' => 1 ) ) ); ?>">
		<i class="jws-icon-x" aria-hidden="true"></i>
		<span class="screen-reader-text"><?php esc_html_e( 'Back to profiles', 'jws_streamvid' ); ?></span>
	</a>

	<div class="jws-pf-pin__who">
		<?php echo Jws_Profiles_Front::avatar_img( $jws_pf_profile, 56, 'jws-pf-avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar_img(). ?>
		<span class="h5"><?php echo esc_html( $jws_pf_profile->name ); ?></span>
	</div>

	<h4 id="jws-pf-title" class="jws-pf__title jws-pf__title--sm">
		<?php
		/* translators: %s: profile name */
		echo esc_html( sprintf( __( 'Enter %s\'s PIN to edit this profile', 'jws_streamvid' ), $jws_pf_profile->name ) );
		?>
	</h4>
	<p class="jws-pf__lead"><?php esc_html_e( 'This profile has Profile Lock on, so changing or deleting it needs its PIN.', 'jws_streamvid' ); ?></p>

	<?php if ( $jws_pf_form ) : ?>
		<p class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_form ); ?></p>
	<?php endif; ?>

	<form method="post" class="jws-pf-pin__form">
		<?php Jws_Profiles_Front::form_fields( 'unlock', $jws_pf_profile->id ); ?>
		<label class="screen-reader-text" for="jws-pf-pin"><?php esc_html_e( 'PIN', 'jws_streamvid' ); ?></label>
		<input id="jws-pf-pin" class="jws-pf-pin__input h4<?php echo $jws_pf_error ? ' has-error' : ''; ?>" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" autofocus<?php echo $jws_pf_error ? ' aria-invalid="true" aria-describedby="jws-pf-pin-error"' : ''; ?>>
		<?php if ( $jws_pf_error ) : ?>
			<p id="jws-pf-pin-error" class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_error ); ?></p>
		<?php endif; ?>
		<button type="submit" class="button-default jws-btn-block"><?php esc_html_e( 'Continue', 'jws_streamvid' ); ?></button>
	</form>

	<?php if ( ! $jws_pf_no_pass ) : ?>
		<details class="jws-pf-forgot">
			<summary><?php esc_html_e( 'Use the account password instead', 'jws_streamvid' ); ?></summary>
			<form method="post" class="jws-pf-form jws-pf-form--tight">
				<?php Jws_Profiles_Front::form_fields( 'unlock', $jws_pf_profile->id ); ?>
				<div class="jws-pf-field">
					<label for="jws-pf-unlock-pass" class="h6"><?php esc_html_e( 'Account password', 'jws_streamvid' ); ?></label>
					<input type="text" autocomplete="username" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" hidden aria-hidden="true" tabindex="-1">
					<input id="jws-pf-unlock-pass" class="jws-pf-input" name="account_password" type="password" autocomplete="current-password" required>
				</div>
				<button type="submit" class="button-default"><?php esc_html_e( 'Continue', 'jws_streamvid' ); ?></button>
			</form>
		</details>
	<?php endif; ?>
</section>
