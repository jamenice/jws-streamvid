<?php
/**
 * Profile page V3: Settings. Personal information and Account and security
 * as cards; every Change / Add button opens the existing edit modals
 * (#edit-personal, #edit-email, #edit-phone, #edit-password, printed on
 * dashboard pages by Jws_Streamvid_Public), so saving, the email and phone
 * codes and the password rules are unchanged.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_set_user      = wp_get_current_user();
$jws_set_id        = $jws_set_user->ID;
$jws_set_dashboard = jws_streamvid()->get()->dashboard;
$jws_set_genders   = $jws_set_dashboard->get_gender();
$jws_set_gender    = get_user_meta( $jws_set_id, 'jws_gender', true );
$jws_set_birth     = get_user_meta( $jws_set_id, 'jws_date_of_birth', true );
$jws_set_postcode  = get_user_meta( $jws_set_id, 'jws_postcode', true );
$jws_set_phone     = get_user_meta( $jws_set_id, 'user_phone', true );
$jws_set_no_email  = jws_streamvid_email_is_placeholder( $jws_set_id );
$jws_set_no_pass   = jws_streamvid_password_unknown( $jws_set_id );

$jws_set_meta = array();
if ( $jws_set_gender && isset( $jws_set_genders[ $jws_set_gender ] ) ) {
	$jws_set_meta[] = $jws_set_genders[ $jws_set_gender ];
}
if ( $jws_set_birth ) {
	/* translators: %s: date of birth */
	$jws_set_meta[] = sprintf( __( 'Born %s', 'jws_streamvid' ), date_i18n( get_option( 'date_format' ), strtotime( $jws_set_birth ) ) );
}
if ( $jws_set_postcode ) {
	/* translators: %s: postcode */
	$jws_set_meta[] = sprintf( __( 'Postcode %s', 'jws_streamvid' ), $jws_set_postcode );
}

$jws_set_rows = array(
	array(
		'label'  => __( 'Display name', 'jws_streamvid' ),
		'value'  => $jws_set_user->display_name,
		'action' => __( 'Change', 'jws_streamvid' ),
		'modal'  => '#edit-personal',
	),
	array(
		'label'  => __( 'Email', 'jws_streamvid' ),
		'value'  => $jws_set_no_email ? __( 'Email not set', 'jws_streamvid' ) : $jws_set_user->user_email,
		'action' => $jws_set_no_email ? __( 'Add', 'jws_streamvid' ) : __( 'Change', 'jws_streamvid' ),
		'modal'  => '#edit-email',
	),
	array(
		'label'  => __( 'Phone', 'jws_streamvid' ),
		'value'  => $jws_set_phone ? $jws_set_phone : __( 'Not added', 'jws_streamvid' ),
		'action' => $jws_set_phone ? __( 'Change', 'jws_streamvid' ) : __( 'Add', 'jws_streamvid' ),
		'modal'  => '#edit-phone',
	),
	array(
		'label'  => __( 'Password', 'jws_streamvid' ),
		'value'  => $jws_set_no_pass ? __( 'Password not set', 'jws_streamvid' ) : '••••••••',
		'action' => $jws_set_no_pass ? __( 'Add', 'jws_streamvid' ) : __( 'Change', 'jws_streamvid' ),
		'modal'  => '#edit-password',
	),
);
?>
<div class="jws-set">

	<section class="jws-set__card" aria-labelledby="jws-set-personal">
		<h2 id="jws-set-personal" class="jws-pf-section__title"><?php esc_html_e( 'Personal information', 'jws_streamvid' ); ?></h2>
		<div class="jws-set__me">
			<?php echo get_avatar( $jws_set_id, 80, '', '', array( 'class' => 'jws-set__avatar' ) ); ?>
			<span class="jws-set__me-text">
				<span class="jws-set__name"><?php echo esc_html( $jws_set_user->display_name ); ?></span>
				<?php if ( $jws_set_meta ) : ?>
					<span class="jws-set__meta"><?php echo esc_html( implode( ' · ', $jws_set_meta ) ); ?></span>
				<?php else : ?>
					<span class="jws-set__meta"><?php esc_html_e( 'Add your photo, date of birth and postcode.', 'jws_streamvid' ); ?></span>
				<?php endif; ?>
			</span>
			<button type="button" class="button-custom" data-modal-jws="#edit-personal">
				<i class="jws-icon-pencil-line" aria-hidden="true"></i>
				<?php esc_html_e( 'Edit', 'jws_streamvid' ); ?>
			</button>
		</div>
	</section>

	<section class="jws-set__card" aria-labelledby="jws-set-security">
		<h2 id="jws-set-security" class="jws-pf-section__title"><?php esc_html_e( 'Account and security', 'jws_streamvid' ); ?></h2>
		<ul class="jws-set__rows">
			<?php foreach ( $jws_set_rows as $jws_set_row ) : ?>
				<li class="jws-set__row">
					<span class="jws-set__field">
						<span class="jws-set__label"><?php echo esc_html( $jws_set_row['label'] ); ?></span>
						<span class="jws-set__value"><?php echo esc_html( $jws_set_row['value'] ); ?></span>
					</span>
					<button type="button" class="button-custom jws-btn-sm jws-set__change" data-modal-jws="<?php echo esc_attr( $jws_set_row['modal'] ); ?>">
						<?php echo esc_html( $jws_set_row['action'] ); ?>
						<span class="screen-reader-text"><?php echo esc_html( $jws_set_row['label'] ); ?></span>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<?php
	if ( class_exists( 'NextendSocialLogin', false ) ) {
		$jws_set_social = NextendSocialLogin::renderLinkAndUnlinkButtons();
		if ( ! empty( $jws_set_social ) ) {
			?>
			<section class="jws-set__card login-width-social">
				<h2 class="jws-pf-section__title"><?php esc_html_e( 'Social Login Accounts', 'jws_streamvid' ); ?></h2>
				<?php echo $jws_set_social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup from Nextend Social Login. ?>
			</section>
			<?php
		}
	}
	?>
</div>
