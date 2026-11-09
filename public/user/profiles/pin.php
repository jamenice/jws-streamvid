<?php
/**
 * Profile Lock: enter a profile's PIN to switch to it, or reset the PIN
 * with the account password.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_pf_user    = get_current_user_id();
$jws_pf_profile = Jws_Profiles::get( $jws_pf_user, Jws_Profiles_Front::requested_id() );
$jws_pf_back    = Jws_Profiles_Front::back_url();
$jws_pf_error   = Jws_Profiles_Front::error();
$jws_pf_pin_err = Jws_Profiles_Front::error( 'pin' );
$jws_pf_notice  = Jws_Profiles_Front::notice();
$jws_pf_no_pass = function_exists( 'jws_streamvid_password_unknown' ) && jws_streamvid_password_unknown( $jws_pf_user );
?>
<section class="jws-pf-card jws-pf-pin" aria-labelledby="jws-pf-title">

	<a class="jws-pf-close" href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose', 0, array( 'back' => $jws_pf_back ) ) ); ?>">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
		<span class="screen-reader-text"><?php esc_html_e( 'Back to profiles', 'jws_streamvid' ); ?></span>
	</a>

	<div class="jws-pf-pin__who">
		<?php echo Jws_Profiles_Front::avatar_img( $jws_pf_profile, 56, 'jws-pf-avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar_img(). ?>
		<span><?php echo esc_html( $jws_pf_profile->name ); ?></span>
	</div>

	<?php if ( $jws_pf_profile->has_pin ) : ?>

		<h1 id="jws-pf-title" class="jws-pf__title jws-pf__title--sm"><?php esc_html_e( 'Profile Lock is on', 'jws_streamvid' ); ?></h1>
		<p class="jws-pf__lead"><?php esc_html_e( 'Enter your PIN to access this profile.', 'jws_streamvid' ); ?></p>

		<?php if ( $jws_pf_notice ) : ?>
			<p class="jws-pf-msg" role="status"><?php echo esc_html( $jws_pf_notice ); ?></p>
		<?php endif; ?>

		<form method="post" class="jws-pf-pin__form">
			<?php Jws_Profiles_Front::form_fields( 'switch', $jws_pf_profile->id ); ?>
			<input type="hidden" name="back" value="<?php echo esc_url( $jws_pf_back ); ?>">
			<label class="screen-reader-text" for="jws-pf-pin"><?php esc_html_e( 'PIN', 'jws_streamvid' ); ?></label>
			<input id="jws-pf-pin" class="jws-pf-pin__input<?php echo $jws_pf_error ? ' has-error' : ''; ?>" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" required autofocus<?php echo $jws_pf_error ? ' aria-invalid="true" aria-describedby="jws-pf-pin-error"' : ''; ?>>
			<?php if ( $jws_pf_error ) : ?>
				<p id="jws-pf-pin-error" class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_error ); ?></p>
			<?php endif; ?>
			<button type="submit" class="jws-pf-btn jws-pf-btn--block"><?php esc_html_e( 'Continue', 'jws_streamvid' ); ?></button>
		</form>

	<?php else : ?>

		<h1 id="jws-pf-title" class="jws-pf__title jws-pf__title--sm"><?php esc_html_e( 'This profile has no PIN', 'jws_streamvid' ); ?></h1>
		<form method="post">
			<?php Jws_Profiles_Front::form_fields( 'switch', $jws_pf_profile->id ); ?>
			<input type="hidden" name="back" value="<?php echo esc_url( $jws_pf_back ); ?>">
			<button type="submit" class="jws-pf-btn jws-pf-btn--block">
				<?php
				/* translators: %s: profile name */
				echo esc_html( sprintf( __( 'Continue as %s', 'jws_streamvid' ), $jws_pf_profile->name ) );
				?>
			</button>
		</form>

	<?php endif; ?>

	<?php if ( $jws_pf_profile->has_pin ) : ?>
		<details class="jws-pf-forgot"<?php echo $jws_pf_pin_err ? ' open' : ''; ?>>
			<summary><?php esc_html_e( 'Forgot PIN? Reset it with your account password', 'jws_streamvid' ); ?></summary>

			<?php if ( $jws_pf_no_pass ) : ?>
				<p class="jws-pf-help">
					<?php esc_html_e( 'Your account has no password yet, because you sign in with a phone number or a social account. Set a password in Settings first.', 'jws_streamvid' ); ?>
				</p>
			<?php else : ?>
				<form method="post" class="jws-pf-form jws-pf-form--tight">
					<?php Jws_Profiles_Front::form_fields( 'pin', $jws_pf_profile->id ); ?>
					<input type="hidden" name="back" value="<?php echo esc_url( $jws_pf_back ); ?>">
					<div class="jws-pf-field">
						<label for="jws-pf-new-pin"><?php esc_html_e( 'New 4-digit PIN', 'jws_streamvid' ); ?></label>
						<input id="jws-pf-new-pin" class="jws-pf-input" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off">
					</div>
					<div class="jws-pf-field">
						<label for="jws-pf-pass"><?php esc_html_e( 'Account password', 'jws_streamvid' ); ?></label>
						<input id="jws-pf-pass" class="jws-pf-input" name="account_password" type="password" autocomplete="current-password" required>
					</div>
					<?php if ( $jws_pf_pin_err ) : ?>
						<p class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_pin_err ); ?></p>
					<?php endif; ?>
					<div class="jws-pf-row">
						<button type="submit" class="jws-pf-btn"><?php esc_html_e( 'Save PIN', 'jws_streamvid' ); ?></button>
						<button type="submit" name="remove_pin" value="1" class="jws-pf-btn jws-pf-btn--ghost"><?php esc_html_e( 'Turn off Profile Lock', 'jws_streamvid' ); ?></button>
					</div>
				</form>
			<?php endif; ?>
		</details>
	<?php endif; ?>
</section>
