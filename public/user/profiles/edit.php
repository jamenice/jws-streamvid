<?php
/**
 * Add or edit a profile in the "Who's watching?" modal, kept short so it
 * fits without much scrolling:
 *
 * - avatar and name on one row; the avatar choices open from the avatar;
 * - Kids, maturity (chips) and autoplay in one block;
 * - Profile Lock in the same form (also when adding): a PIN, and the
 *   account password, which the server checks before saving anything;
 * - Save and Cancel stay at the bottom of the modal while it scrolls;
 * - deleting, for an existing profile that is not the main one, last.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_pf_user    = get_current_user_id();
$jws_pf_is_add  = 'add' === Jws_Profiles_Front::screen();
$jws_pf_profile = $jws_pf_is_add ? null : Jws_Profiles::get( $jws_pf_user, Jws_Profiles_Front::requested_id() );
$jws_pf_main    = $jws_pf_profile && $jws_pf_profile->is_main;
$jws_pf_has_pin = $jws_pf_profile && $jws_pf_profile->has_pin;

// A profile with Profile Lock that is not the one in use opens for editing only after its PIN (or the account password).
if ( $jws_pf_profile && Jws_Profiles_Front::needs_unlock( $jws_pf_user, $jws_pf_profile ) ) {
	jws_streamvid_load_template( 'user/profiles/unlock.php', false, array( 'profile' => $jws_pf_profile ) );
	return;
}

$jws_pf_name     = Jws_Profiles_Front::posted( 'name', $jws_pf_profile ? $jws_pf_profile->name : '' );
$jws_pf_avatar   = Jws_Profiles_Front::posted( 'avatar', $jws_pf_profile ? $jws_pf_profile->avatar : Jws_Profiles::suggest_avatar( $jws_pf_user ) );
$jws_pf_kid      = (bool) Jws_Profiles_Front::posted( 'is_kid', $jws_pf_profile ? $jws_pf_profile->is_kid : false );
$jws_pf_maturity = (int) Jws_Profiles_Front::posted( 'max_maturity', $jws_pf_profile ? $jws_pf_profile->max_maturity : Jws_Profiles::MATURITY_ALL );
$jws_pf_autoplay = (bool) Jws_Profiles_Front::posted( 'autoplay_next', $jws_pf_profile ? $jws_pf_profile->autoplay_next : true );
$jws_pf_lock     = (bool) Jws_Profiles_Front::posted( 'lock_pin', $jws_pf_has_pin );
$jws_pf_kids_max = Jws_Profiles::kids_max_maturity();
$jws_pf_groups   = Jws_Profiles::avatar_groups();
$jws_pf_offered  = Jws_Profiles::avatars();

// A profile keeps an avatar the admin has since switched off: show it first.
if ( $jws_pf_avatar && ! in_array( $jws_pf_avatar, $jws_pf_offered, true ) ) {
	$jws_pf_groups = array(
		'current' => array(
			'label'   => __( 'Current', 'jws_streamvid' ),
			'avatars' => array( $jws_pf_avatar ),
		),
	) + $jws_pf_groups;
}

$jws_pf_err_form = Jws_Profiles_Front::error();
$jws_pf_err_name = Jws_Profiles_Front::error( 'name' );
$jws_pf_err_av   = Jws_Profiles_Front::error( 'avatar' );
$jws_pf_err_mat  = Jws_Profiles_Front::error( 'max_maturity' );
$jws_pf_err_pin  = Jws_Profiles_Front::error( 'pin' );
$jws_pf_notice   = Jws_Profiles_Front::notice();
$jws_pf_bad_lock = isset( $_GET['notice'] ) && 'pin_failed' === sanitize_key( wp_unslash( $_GET['notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$jws_pf_manage   = Jws_Profiles_Front::url( 'choose', 0, array( 'manage' => 1 ) );
$jws_pf_no_pass  = function_exists( 'jws_streamvid_password_unknown' ) && jws_streamvid_password_unknown( $jws_pf_user );
?>
<section class="jws-pf-edit" aria-labelledby="jws-pf-title">

	<h4 id="jws-pf-title" class="jws-pf__title jws-pf__title--sm">
		<?php echo $jws_pf_is_add ? esc_html__( 'Add Profile', 'jws_streamvid' ) : esc_html__( 'Edit Profile', 'jws_streamvid' ); ?>
	</h4>
	<?php if ( $jws_pf_is_add ) : ?>
		<p class="jws-pf__lead"><?php esc_html_e( 'Add a profile for another person watching on this account. Each profile has its own history, Watchlist and Favorites.', 'jws_streamvid' ); ?></p>
	<?php endif; ?>

	<?php if ( $jws_pf_notice ) : ?>
		<p class="jws-pf-msg<?php echo $jws_pf_bad_lock ? ' jws-pf-msg--error' : ''; ?>" role="status"><?php echo esc_html( $jws_pf_notice ); ?></p>
	<?php endif; ?>
	<?php if ( $jws_pf_err_form ) : ?>
		<p class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_err_form ); ?></p>
	<?php endif; ?>

	<form method="post" class="jws-pf-form">
		<?php Jws_Profiles_Front::form_fields( 'save', $jws_pf_profile ? $jws_pf_profile->id : 0 ); ?>

		<div class="jws-pf-ident">
			<button type="button" class="jws-pf-avatar-btn" aria-expanded="<?php echo $jws_pf_err_av ? 'true' : 'false'; ?>" aria-controls="jws-pf-avatars">
				<img class="jws-pf-avatar-btn__img" src="<?php echo esc_url( Jws_Profiles::avatar_url( $jws_pf_avatar ) ); ?>" alt="" width="88" height="88" decoding="async">
				<span class="jws-pf-avatar-btn__edit" aria-hidden="true"><i class="jws-icon-pencil-line"></i></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Change avatar', 'jws_streamvid' ); ?></span>
			</button>

			<div class="jws-pf-field jws-pf-ident__name">
				<label for="jws-pf-name" class="h6"><?php esc_html_e( 'Profile name', 'jws_streamvid' ); ?></label>
				<input id="jws-pf-name" class="jws-pf-input" type="text" name="name" value="<?php echo esc_attr( $jws_pf_name ); ?>" maxlength="<?php echo (int) Jws_Profiles::NAME_MAX; ?>" required autocomplete="off" data-1p-ignore data-lpignore="true"<?php echo $jws_pf_err_name ? ' aria-invalid="true" aria-describedby="jws-pf-name-error"' : ''; ?>>
				<?php if ( $jws_pf_err_name ) : ?>
					<p id="jws-pf-name-error" class="jws-pf-msg jws-pf-msg--error"><?php echo esc_html( $jws_pf_err_name ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<fieldset id="jws-pf-avatars" class="jws-pf-field jws-pf-avatars"<?php echo $jws_pf_err_av ? '' : ' hidden'; ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Avatar', 'jws_streamvid' ); ?></legend>
			<?php if ( $jws_pf_err_av ) : ?>
				<p class="jws-pf-msg jws-pf-msg--error"><?php echo esc_html( $jws_pf_err_av ); ?></p>
			<?php endif; ?>
			<?php foreach ( $jws_pf_groups as $jws_pf_group ) : ?>
				<p class="jws-pf-avatars__label"><?php echo esc_html( $jws_pf_group['label'] ); ?></p>
				<div class="jws-pf-avatars__grid">
					<?php foreach ( $jws_pf_group['avatars'] as $jws_pf_value ) : ?>
						<?php $jws_pf_slug = preg_replace( '/^(builtin|att):/', '', $jws_pf_value ); ?>
						<label class="jws-pf-avatar-opt">
							<input type="radio" name="avatar" value="<?php echo esc_attr( $jws_pf_value ); ?>" <?php checked( $jws_pf_avatar, $jws_pf_value ); ?>>
							<img src="<?php echo esc_url( Jws_Profiles::avatar_url( $jws_pf_value ) ); ?>" alt="" width="72" height="72" loading="lazy" decoding="async">
							<span class="screen-reader-text"><?php echo esc_html( ucfirst( $jws_pf_slug ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</fieldset>

		<div class="jws-pf-group">
			<?php if ( ! $jws_pf_main ) : ?>
				<div class="jws-pf-field jws-pf-toggle">
					<input id="jws-pf-kid" type="checkbox" role="switch" name="is_kid" value="1" <?php checked( $jws_pf_kid ); ?>>
					<label for="jws-pf-kid">
						<span class="jws-pf-toggle__title h6"><?php esc_html_e( 'Kids profile', 'jws_streamvid' ); ?></span>
						<span class="jws-pf-help">
							<?php
							printf(
								/* translators: %s: highest rating a Kids profile can watch, such as "PG and below" */
								esc_html__( 'Shows only titles rated %s. Kids cannot buy, rent, review or change account settings.', 'jws_streamvid' ),
								esc_html( Jws_Profiles_Front::maturity_label( $jws_pf_kids_max ) )
							);
							?>
						</span>
					</label>
				</div>
			<?php endif; ?>

			<fieldset class="jws-pf-field jws-pf-maturity">
				<legend class="h6"><?php esc_html_e( 'Maturity rating', 'jws_streamvid' ); ?></legend>
				<p class="jws-pf-help"><?php esc_html_e( 'This profile only shows titles at or below this rating.', 'jws_streamvid' ); ?></p>
				<div class="jws-pf-choices">
					<?php foreach ( Jws_Profiles::MATURITY_LEVELS as $jws_pf_age ) : ?>
						<label class="jws-pf-choice<?php echo $jws_pf_age > $jws_pf_kids_max ? ' is-adult-only' : ''; ?>">
							<input type="radio" name="max_maturity" value="<?php echo (int) $jws_pf_age; ?>" <?php checked( $jws_pf_maturity, $jws_pf_age ); ?>>
							<span><?php echo esc_html( Jws_Profiles_Front::maturity_label( $jws_pf_age ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<?php if ( $jws_pf_err_mat ) : ?>
					<p class="jws-pf-msg jws-pf-msg--error"><?php echo esc_html( $jws_pf_err_mat ); ?></p>
				<?php endif; ?>
			</fieldset>

			<div class="jws-pf-field jws-pf-toggle">
				<input id="jws-pf-autoplay" type="checkbox" role="switch" name="autoplay_next" value="1" <?php checked( $jws_pf_autoplay ); ?>>
				<label for="jws-pf-autoplay">
					<span class="jws-pf-toggle__title h6"><?php esc_html_e( 'Autoplay next episode', 'jws_streamvid' ); ?></span>
				</label>
			</div>
		</div>

		<div class="jws-pf-group jws-pf-lock<?php echo $jws_pf_has_pin ? ' has-pin' : ''; ?><?php echo $jws_pf_err_pin ? ' is-open' : ''; ?>" id="pin">
			<?php if ( $jws_pf_no_pass ) : ?>
				<p class="jws-pf-toggle__title h6"><?php esc_html_e( 'Profile Lock', 'jws_streamvid' ); ?></p>
				<p class="jws-pf-help">
					<?php esc_html_e( 'Your account has no password yet, because you sign in with a phone number or a social account. Set a password in Settings first.', 'jws_streamvid' ); ?>
				</p>
			<?php else : ?>
				<input type="hidden" name="lock_form" value="1">
				<div class="jws-pf-field jws-pf-toggle">
					<input id="jws-pf-lock" type="checkbox" role="switch" name="lock_pin" value="1" <?php checked( $jws_pf_lock ); ?>>
					<label for="jws-pf-lock">
						<span class="jws-pf-toggle__title h6"><?php esc_html_e( 'Profile Lock', 'jws_streamvid' ); ?></span>
						<span class="jws-pf-help"><?php esc_html_e( 'Ask for a 4-digit PIN before anyone opens this profile, including Kids profiles.', 'jws_streamvid' ); ?></span>
					</label>
				</div>

				<div class="jws-pf-lock__fields">
					<div class="jws-pf-field jws-pf-lock__pin">
						<label for="jws-pf-new-pin" class="h6"><?php echo $jws_pf_has_pin ? esc_html__( 'New PIN (leave empty to keep it)', 'jws_streamvid' ) : esc_html__( 'PIN', 'jws_streamvid' ); ?></label>
						<input id="jws-pf-new-pin" class="jws-pf-input jws-pf-input--pin" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="&#8226;&#8226;&#8226;&#8226;" autocomplete="off"<?php echo $jws_pf_err_pin ? ' aria-invalid="true" aria-describedby="jws-pf-pin-error"' : ''; ?>>
					</div>
					<div class="jws-pf-field jws-pf-lock__pass">
						<label for="jws-pf-pass" class="h6"><?php esc_html_e( 'Account password', 'jws_streamvid' ); ?></label>
						<?php // For password managers: the account this password belongs to, so they leave the profile name alone. Not sent. ?>
						<input type="text" autocomplete="username" value="<?php echo esc_attr( wp_get_current_user()->user_login ); ?>" hidden aria-hidden="true" tabindex="-1">
						<input id="jws-pf-pass" class="jws-pf-input" name="account_password" type="password" autocomplete="current-password">
						<p class="jws-pf-help"><?php esc_html_e( 'Needed to set, change or turn off the PIN.', 'jws_streamvid' ); ?></p>
					</div>
					<?php if ( $jws_pf_err_pin ) : ?>
						<p id="jws-pf-pin-error" class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_err_pin ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="jws-pf-savebar">
			<button type="submit" class="button-default"><?php echo $jws_pf_is_add ? esc_html__( 'Add Profile', 'jws_streamvid' ) : esc_html__( 'Save', 'jws_streamvid' ); ?></button>
			<a class="button-custom" href="<?php echo esc_url( $jws_pf_manage ); ?>"><?php esc_html_e( 'Cancel', 'jws_streamvid' ); ?></a>
		</div>
	</form>

	<?php if ( $jws_pf_profile && ! $jws_pf_main ) : ?>
		<details class="jws-pf-confirm">
			<summary class="jws-pf-confirm__open"><i class="jws-icon-trash" aria-hidden="true"></i><?php esc_html_e( 'Delete Profile', 'jws_streamvid' ); ?></summary>
			<form method="post">
				<?php Jws_Profiles_Front::form_fields( 'delete', $jws_pf_profile->id ); ?>
				<p>
					<?php
					/* translators: %s: profile name */
					echo esc_html( sprintf( __( 'Delete %s? Its history, Watchlist and Favorites go too. Purchases stay on the account. This cannot be undone.', 'jws_streamvid' ), $jws_pf_profile->name ) );
					?>
				</p>
				<button type="submit" class="button-default jws-btn-danger">
					<?php
					/* translators: %s: profile name */
					echo esc_html( sprintf( __( 'Delete %s', 'jws_streamvid' ), $jws_pf_profile->name ) );
					?>
				</button>
			</form>
		</details>
	<?php endif; ?>
</section>
