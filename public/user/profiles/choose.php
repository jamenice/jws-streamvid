<?php
/**
 * "Who's watching?": pick a profile, or with ?manage=1, pick one to edit.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_pf_user     = get_current_user_id();
$jws_pf_profiles = Jws_Profiles::get_all( $jws_pf_user );
$jws_pf_active   = Jws_Profiles::active_ids( $jws_pf_user );
$jws_pf_kid      = Jws_Profiles_Front::current_is_kid();
$jws_pf_manage   = ! empty( $_GET['manage'] ) && ! $jws_pf_kid; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$jws_pf_back     = Jws_Profiles_Front::back_url();
$jws_pf_limit    = Jws_Profiles::get_limit( $jws_pf_user );
$jws_pf_can_add  = ! $jws_pf_kid && Jws_Profiles::can_create( $jws_pf_user );
$jws_pf_over     = count( $jws_pf_profiles ) > $jws_pf_limit;
$jws_pf_notice   = Jws_Profiles_Front::notice();
$jws_pf_error    = Jws_Profiles_Front::error();
?>
<section class="jws-pf-choose" aria-labelledby="jws-pf-title">

	<h1 id="jws-pf-title" class="jws-pf__title">
		<?php echo $jws_pf_manage ? esc_html__( 'Manage Profiles', 'jws_streamvid' ) : esc_html__( "Who's watching?", 'jws_streamvid' ); ?>
	</h1>

	<?php if ( $jws_pf_notice ) : ?>
		<p class="jws-pf-msg" role="status"><?php echo esc_html( $jws_pf_notice ); ?></p>
	<?php endif; ?>
	<?php if ( $jws_pf_error ) : ?>
		<p class="jws-pf-msg jws-pf-msg--error" role="alert"><?php echo esc_html( $jws_pf_error ); ?></p>
	<?php endif; ?>

	<ul class="jws-pf-grid">
		<?php foreach ( $jws_pf_profiles as $jws_pf_p ) : ?>
			<?php
			$jws_pf_locked = ! in_array( $jws_pf_p->id, $jws_pf_active, true );
			$jws_pf_inner  = Jws_Profiles_Front::avatar_img( $jws_pf_p, 160, 'jws-pf-tile__img' );
			$jws_pf_badge  = '';

			if ( $jws_pf_manage ) {
				$jws_pf_badge = '<span class="jws-pf-tile__overlay" aria-hidden="true"><i class="jws-icon-pencil-line" aria-hidden="true"></i></span>';
			} elseif ( $jws_pf_locked || $jws_pf_p->has_pin ) {
				$jws_pf_badge = '<span class="jws-pf-tile__lock" aria-hidden="true"><i class="jws-icon-lock-key-fill" aria-hidden="true"></i></span>';
			}
			?>
			<li class="jws-pf-tile<?php echo $jws_pf_locked ? ' is-locked' : ''; ?><?php echo $jws_pf_p->is_kid ? ' is-kid' : ''; ?>">
				<?php if ( $jws_pf_manage ) : ?>

					<a class="jws-pf-tile__btn" href="<?php echo esc_url( Jws_Profiles_Front::url( 'edit', $jws_pf_p->id ) ); ?>">
						<span class="jws-pf-tile__art"><?php echo $jws_pf_inner . $jws_pf_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?></span>
						<span class="jws-pf-tile__name">
							<?php
							/* translators: %s: profile name */
							printf( '<span class="screen-reader-text">%s</span>', esc_html( sprintf( __( 'Edit %s', 'jws_streamvid' ), $jws_pf_p->name ) ) );
							?>
							<span aria-hidden="true"><?php echo esc_html( $jws_pf_p->name ); ?></span>
						</span>
					</a>

				<?php elseif ( $jws_pf_locked ) : ?>

					<span class="jws-pf-tile__btn" aria-disabled="true">
						<span class="jws-pf-tile__art"><?php echo $jws_pf_inner . $jws_pf_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?></span>
						<span class="jws-pf-tile__name"><?php echo esc_html( $jws_pf_p->name ); ?></span>
						<span class="jws-pf-tile__note"><?php esc_html_e( 'Not on your plan', 'jws_streamvid' ); ?></span>
					</span>

				<?php elseif ( $jws_pf_p->has_pin ) : ?>

					<a class="jws-pf-tile__btn" href="<?php echo esc_url( Jws_Profiles_Front::url( 'pin', $jws_pf_p->id, array( 'back' => $jws_pf_back ) ) ); ?>">
						<span class="jws-pf-tile__art"><?php echo $jws_pf_inner . $jws_pf_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?></span>
						<span class="jws-pf-tile__name"><?php echo esc_html( $jws_pf_p->name ); ?></span>
					</a>

				<?php else : ?>

					<form method="post" class="jws-pf-tile__form">
						<?php Jws_Profiles_Front::form_fields( 'switch', $jws_pf_p->id ); ?>
						<input type="hidden" name="back" value="<?php echo esc_url( $jws_pf_back ); ?>">
						<button type="submit" class="jws-pf-tile__btn">
							<span class="jws-pf-tile__art"><?php echo $jws_pf_inner . $jws_pf_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?></span>
							<span class="jws-pf-tile__name"><?php echo esc_html( $jws_pf_p->name ); ?></span>
						</button>
					</form>

				<?php endif; ?>

				<?php if ( $jws_pf_p->is_kid ) : ?>
					<span class="jws-pf-kids-badge"><?php esc_html_e( 'Kids', 'jws_streamvid' ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>

		<?php if ( $jws_pf_can_add ) : ?>
			<li class="jws-pf-tile jws-pf-tile--add">
				<a class="jws-pf-tile__btn" href="<?php echo esc_url( Jws_Profiles_Front::url( 'add' ) ); ?>">
					<span class="jws-pf-tile__art">
						<i class="jws-icon-plus" aria-hidden="true"></i>
					</span>
					<span class="jws-pf-tile__name"><?php esc_html_e( 'Add Profile', 'jws_streamvid' ); ?></span>
				</a>
			</li>
		<?php endif; ?>
	</ul>

	<?php if ( $jws_pf_manage && $jws_pf_over ) : ?>
		<form method="post" class="jws-pf-keep">
			<?php Jws_Profiles_Front::form_fields( 'keep' ); ?>
			<h2 class="jws-pf-keep__title">
				<?php
				printf(
					/* translators: %d: number of profiles the plan allows */
					esc_html( _n( 'Your plan allows %d profile', 'Your plan allows %d profiles', $jws_pf_limit, 'jws_streamvid' ) ),
					(int) $jws_pf_limit
				);
				?>
			</h2>
			<p><?php esc_html_e( 'Choose which profiles to keep using. The others keep their history and lists, and come back when you change your plan.', 'jws_streamvid' ); ?></p>
			<ul class="jws-pf-keep__list">
				<?php foreach ( $jws_pf_profiles as $jws_pf_p ) : ?>
					<?php if ( $jws_pf_p->is_main ) { continue; } ?>
					<li>
						<label>
							<input type="checkbox" name="keep[]" value="<?php echo (int) $jws_pf_p->id; ?>" <?php checked( in_array( $jws_pf_p->id, $jws_pf_active, true ) ); ?>>
							<?php echo esc_html( $jws_pf_p->name ); ?>
						</label>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="submit" class="button-default"><?php esc_html_e( 'Save', 'jws_streamvid' ); ?></button>
		</form>
	<?php endif; ?>

	<div class="jws-pf-actions">
		<?php if ( $jws_pf_manage ) : ?>
			<a class="button-default" href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose', 0, array( 'back' => $jws_pf_back ) ) ); ?>"><?php esc_html_e( 'Done', 'jws_streamvid' ); ?></a>
		<?php elseif ( ! $jws_pf_kid ) : ?>
			<a class="button-custom" href="<?php echo esc_url( Jws_Profiles_Front::url( 'choose', 0, array( 'manage' => 1, 'back' => $jws_pf_back ) ) ); ?>"><?php esc_html_e( 'Manage Profiles', 'jws_streamvid' ); ?></a>
		<?php endif; ?>
	</div>
</section>
