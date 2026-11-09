<?php
/**
 * Top bar while a restricted profile is in use: on a Kids profile, who is
 * watching, which ratings show and Exit Kids; on any restricted profile, why
 * the page changed after a redirect (?jws_blocked=).
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_kb_profile = Jws_Profiles::current_profile();
$jws_kb_kids    = Jws_Kids_Mode::is_active();
$jws_kb_message = Jws_Kids_Mode::blocked_message();

if ( ! $jws_kb_kids && ! $jws_kb_message ) {
	return;
}
?>
<div class="jws-kids-bar<?php echo $jws_kb_kids ? '' : ' jws-kids-bar--notice-only'; ?>">
	<?php if ( $jws_kb_kids ) : ?>
		<div class="jws-kids-bar__in" role="region" aria-label="<?php esc_attr_e( 'Kids profile', 'jws_streamvid' ); ?>">
			<span class="jws-pf-kids-badge"><?php esc_html_e( 'Kids', 'jws_streamvid' ); ?></span>
			<span class="jws-kids-bar__who">
				<?php echo Jws_Profiles_Front::avatar_img( $jws_kb_profile, 28, 'jws-kids-bar__avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in avatar_img(). ?>
				<strong><?php echo esc_html( $jws_kb_profile->name ); ?></strong>
			</span>
			<span class="jws-kids-bar__rating"><?php echo esc_html( Jws_Kids_Mode::ratings_text( $jws_kb_profile->max_maturity ) ); ?></span>
			<a class="jws-kids-bar__exit" href="<?php echo esc_url( Jws_Profiles_Front::url() ); ?>"><?php esc_html_e( 'Exit Kids', 'jws_streamvid' ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( $jws_kb_message ) : ?>
		<div class="jws-kids-bar__notice" role="status">
			<i class="jws-icon-info-light" aria-hidden="true"></i>
			<span><?php echo esc_html( $jws_kb_message ); ?></span>
			<a class="jws-kids-bar__dismiss" href="<?php echo esc_url( remove_query_arg( 'jws_blocked' ) ); ?>">
				<i class="jws-icon-x" aria-hidden="true"></i>
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'jws_streamvid' ); ?></span>
			</a>
		</div>
	<?php endif; ?>
</div>
