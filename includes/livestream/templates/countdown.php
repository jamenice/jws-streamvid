<?php
/**
 * Countdown to a scheduled live stream. livekit.js counts down and reloads
 * the page at zero, so the stream opens without a manual refresh.
 *
 * @var int    $post_id
 * @var int    $timestamp Start, Unix time.
 * @var string $title
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$units = array(
	'days'    => __( 'Days', 'jws_streamvid' ),
	'hours'   => __( 'Hrs', 'jws_streamvid' ),
	'minutes' => __( 'Min', 'jws_streamvid' ),
	'seconds' => __( 'Sec', 'jws_streamvid' ),
);
?>
<div class="jws-livestream-notice">
	<?php if ( ! empty( $title ) ) : ?>
		<h4><?php echo esc_html( $title ); ?></h4>
	<?php endif; ?>
	<div class="video-time-live fs-small">
		<?php echo esc_html( Jws_Livestream_Frontend::format_start( $timestamp ) ); ?>
	</div>
	<div class="countdown-box jws-live-countdown" data-date="<?php echo esc_attr( $timestamp * 1000 ); ?>">
		<?php
		$last = array_key_last( $units );
		foreach ( $units as $unit => $label ) :
			?>
			<div class="countdown-item">
				<div class="count-num <?php echo esc_attr( $unit ); ?>">00</div>
				<div class="count-label"><?php echo esc_html( $label ); ?></div>
			</div>
			<?php if ( $unit !== $last ) : ?>
				<span class="count-sep">:</span>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
