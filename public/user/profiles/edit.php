<?php
/**
 * Add or edit a profile: name, avatar, Kids, maturity, autoplay; then, for
 * an existing profile, its PIN and deleting it.
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

$jws_pf_name     = Jws_Profiles_Front::posted( 'name', $jws_pf_profile ? $jws_pf_profile->name : '' );
$jws_pf_avatar   = Jws_Profiles_Front::posted( 'avatar', $jws_pf_profile ? $jws_pf_profile->avatar : Jws_Profiles::suggest_avatar( $jws_pf_user ) );
$jws_pf_kid      = (bool) Jws_Profiles_Front::posted( 'is_kid', $jws_pf_profile ? $jws_pf_profile->is_kid : false );
$jws_pf_maturity = (int) Jws_Profiles_Front::posted( 'max_maturity', $jws_pf_profile ? $jws_pf_profile->max_maturity : Jws_Profiles::MATURITY_ALL );
$jws_pf_autoplay = (bool) Jws_Profiles_Front::posted( 'autoplay_next', $jws_pf_profile ? $jws_pf_profile->autoplay_next : true );
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
$jws_pf_manage   = Jws_Profiles_Front::url( 'choose', 0, array( 'manage' => 1 ) );
$jws_pf_no_pass  = function_exists( 'jws_streamvid_password_unknown' ) && jws_streamvid_password_unknown( $jws_pf_user );
?>
<section class="jws-pf-edit" aria-labelledby="jws-pf-title">

	<h1 id="jws-pf-title" class="jws-pf__title jws-pf__title--sm">
		<?php echo $jws_pf_is_add ? esc_html__( 'Add Profile', 'jws_streamvid' ) : esc_html__( 'Edit Profile', 'jws_streamvid' ); ?>
	</h1>
	<?php if ( $jws_pf_is_add ) : ?>
		<p class="jws-pf__lead"><?php esc_html_e( 'Add a profile for another person watching on this account. Each profile has its own history, Watchlist and Favorites.', 'jws_streamvid' ); ?></p>
	<?php endif; ?>

	<?php if ( $jws_pf_notice ) : ?>
		<p class="jws-pf-msg" role="status"><?php echo esc_html( $jws_pf_notice ); ?></p>
	<?php endif; ?>
	<?php if ( $jws_pf_err_form ) : ?>
		<p class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_err_form ); ?></p>
	<?php endif; ?>

	<form method="post" class="jws-pf-form">
		<?php Jws_Profiles_Front::form_fields( 'save', $jws_pf_profile ? $jws_pf_profile->id : 0 ); ?>

		<div class="jws-pf-field">
			<label for="jws-pf-name"><?php esc_html_e( 'Profile name', 'jws_streamvid' ); ?></label>
			<input id="jws-pf-name" class="jws-pf-input" type="text" name="name" value="<?php echo esc_attr( $jws_pf_name ); ?>" maxlength="<?php echo (int) Jws_Profiles::NAME_MAX; ?>" required autocomplete="off"<?php echo $jws_pf_err_name ? ' aria-invalid="true" aria-describedby="jws-pf-name-error"' : ''; ?>>
			<?php if ( $jws_pf_err_name ) : ?>
				<p id="jws-pf-name-error" class="jws-pf-msg jws-pf-msg--error"><?php echo esc_html( $jws_pf_err_name ); ?></p>
			<?php endif; ?>
		</div>

		<fieldset class="jws-pf-field jws-pf-avatars">
			<legend><?php esc_html_e( 'Avatar', 'jws_streamvid' ); ?></legend>
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

		<?php if ( ! $jws_pf_main ) : ?>
			<div class="jws-pf-field jws-pf-toggle">
				<input id="jws-pf-kid" type="checkbox" name="is_kid" value="1" <?php checked( $jws_pf_kid ); ?>>
				<label for="jws-pf-kid">
					<span class="jws-pf-toggle__title"><?php esc_html_e( 'Kids profile', 'jws_streamvid' ); ?></span>
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
			<legend><?php esc_html_e( 'Maturity rating', 'jws_streamvid' ); ?></legend>
			<p class="jws-pf-help"><?php esc_html_e( 'This profile only shows titles at or below this rating.', 'jws_streamvid' ); ?></p>
			<?php foreach ( Jws_Profiles::MATURITY_LEVELS as $jws_pf_age ) : ?>
				<label class="jws-pf-radio<?php echo $jws_pf_age > $jws_pf_kids_max ? ' is-adult-only' : ''; ?>">
					<input type="radio" name="max_maturity" value="<?php echo (int) $jws_pf_age; ?>" <?php checked( $jws_pf_maturity, $jws_pf_age ); ?>>
					<?php echo esc_html( Jws_Profiles_Front::maturity_label( $jws_pf_age ) ); ?>
				</label>
			<?php endforeach; ?>
			<?php if ( $jws_pf_err_mat ) : ?>
				<p class="jws-pf-msg jws-pf-msg--error"><?php echo esc_html( $jws_pf_err_mat ); ?></p>
			<?php endif; ?>
		</fieldset>

		<div class="jws-pf-field jws-pf-toggle">
			<input id="jws-pf-autoplay" type="checkbox" name="autoplay_next" value="1" <?php checked( $jws_pf_autoplay ); ?>>
			<label for="jws-pf-autoplay">
				<span class="jws-pf-toggle__title"><?php esc_html_e( 'Autoplay next episode', 'jws_streamvid' ); ?></span>
			</label>
		</div>

		<div class="jws-pf-row">
			<button type="submit" class="jws-pf-btn"><?php esc_html_e( 'Save', 'jws_streamvid' ); ?></button>
			<a class="jws-pf-btn jws-pf-btn--ghost" href="<?php echo esc_url( $jws_pf_manage ); ?>"><?php esc_html_e( 'Cancel', 'jws_streamvid' ); ?></a>
		</div>
	</form>

	<?php if ( $jws_pf_profile ) : ?>

		<section id="pin" class="jws-pf-section" aria-labelledby="jws-pf-pin-title">
			<h2 id="jws-pf-pin-title" class="jws-pf-section__title"><?php esc_html_e( 'Profile Lock', 'jws_streamvid' ); ?></h2>
			<p class="jws-pf-help">
				<?php
				echo $jws_pf_profile->has_pin
					? esc_html__( 'On. A 4-digit PIN is needed to open this profile.', 'jws_streamvid' )
					: esc_html__( 'Off. Add a 4-digit PIN so others on this account, including Kids profiles, cannot open this profile.', 'jws_streamvid' );
				?>
			</p>

			<?php if ( $jws_pf_no_pass ) : ?>
				<p class="jws-pf-help">
					<?php esc_html_e( 'Your account has no password yet, because you sign in with a phone number or a social account. Set a password in Settings first.', 'jws_streamvid' ); ?>
				</p>
			<?php else : ?>
				<form method="post" class="jws-pf-form jws-pf-form--tight">
					<?php Jws_Profiles_Front::form_fields( 'pin', $jws_pf_profile->id ); ?>
					<div class="jws-pf-field">
						<label for="jws-pf-new-pin"><?php echo $jws_pf_profile->has_pin ? esc_html__( 'New PIN', 'jws_streamvid' ) : esc_html__( 'PIN', 'jws_streamvid' ); ?></label>
						<input id="jws-pf-new-pin" class="jws-pf-input jws-pf-input--pin" name="new_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off">
					</div>
					<div class="jws-pf-field">
						<label for="jws-pf-pass"><?php esc_html_e( 'Account password', 'jws_streamvid' ); ?></label>
						<input id="jws-pf-pass" class="jws-pf-input" name="account_password" type="password" autocomplete="current-password" required>
					</div>
					<?php if ( $jws_pf_err_pin ) : ?>
						<p class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_err_pin ); ?></p>
					<?php endif; ?>
					<div class="jws-pf-row">
						<button type="submit" class="jws-pf-btn"><?php echo $jws_pf_profile->has_pin ? esc_html__( 'Change PIN', 'jws_streamvid' ) : esc_html__( 'Turn on Profile Lock', 'jws_streamvid' ); ?></button>
						<?php if ( $jws_pf_profile->has_pin ) : ?>
							<button type="submit" name="remove_pin" value="1" class="jws-pf-btn jws-pf-btn--ghost"><?php esc_html_e( 'Turn off Profile Lock', 'jws_streamvid' ); ?></button>
						<?php endif; ?>
					</div>
				</form>
			<?php endif; ?>
		</section>

		<?php if ( ! $jws_pf_main ) : ?>
			<section class="jws-pf-section jws-pf-section--danger" aria-labelledby="jws-pf-del-title">
				<h2 id="jws-pf-del-title" class="jws-pf-section__title"><?php esc_html_e( 'Delete profile', 'jws_streamvid' ); ?></h2>
				<p class="jws-pf-help"><?php esc_html_e( 'Deleting a profile also removes its history, Watchlist and Favorites. Purchases stay on the account.', 'jws_streamvid' ); ?></p>
				<details class="jws-pf-confirm">
					<summary class="jws-pf-btn jws-pf-btn--danger-ghost"><?php esc_html_e( 'Delete Profile', 'jws_streamvid' ); ?></summary>
					<form method="post">
						<?php Jws_Profiles_Front::form_fields( 'delete', $jws_pf_profile->id ); ?>
						<p>
							<?php
							/* translators: %s: profile name */
							echo esc_html( sprintf( __( 'Delete %s? This cannot be undone.', 'jws_streamvid' ), $jws_pf_profile->name ) );
							?>
						</p>
						<button type="submit" class="jws-pf-btn jws-pf-btn--danger">
							<?php
							/* translators: %s: profile name */
							echo esc_html( sprintf( __( 'Delete %s', 'jws_streamvid' ), $jws_pf_profile->name ) );
							?>
						</button>
					</form>
				</details>
			</section>
		<?php endif; ?>

	<?php endif; ?>
</section>
