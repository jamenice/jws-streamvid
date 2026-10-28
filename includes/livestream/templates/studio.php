<?php
/**
 * The host's Live Studio.
 *
 * livekit.js: #start-webcam-live, #stop-webcam-live, #livekit-video,
 * .webcam-player(.open), #webcam-action, #mic-action, #get_stream_key,
 * #studio-server, #studio-key, .view-count span. studio.js: tabs, copy,
 * show / hide key, the Live / Offline status.
 *
 * @var int    $post_id
 * @var string $room_name
 * @var string $stream_key
 * @var string $rtmp_url
 * @var bool   $started
 * @var int    $timestamp  Scheduled start, 0 once started.
 * @var string $viewer_url
 * @var bool   $configured
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$disabled  = $started && $configured ? '' : ' disabled';
$copy_icon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
?>
<div class="jws-livestream-host jws-studio" data-copied="<?php esc_attr_e( 'Copied', 'jws_streamvid' ); ?>">

	<div class="studio-head">

		<div class="studio-head__text">
			<span class="studio-status" data-live="<?php esc_attr_e( 'Live', 'jws_streamvid' ); ?>" data-offline="<?php esc_attr_e( 'Offline', 'jws_streamvid' ); ?>">
				<span class="studio-status__dot"></span>
				<span class="studio-status__text"><?php esc_html_e( 'Offline', 'jws_streamvid' ); ?></span>
			</span>
			<h4 class="studio-title"><?php esc_html_e( 'Live Studio', 'jws_streamvid' ); ?></h4>
			<p class="studio-subtitle"><?php echo esc_html( get_the_title( $post_id ) ); ?></p>
		</div>

		<div class="studio-head__actions">
			<span class="view-count studio-chip" title="<?php esc_attr_e( 'Viewers', 'jws_streamvid' ); ?>">
				<i class="jws-icon-eye"></i> <span>0</span>
			</span>
			<button type="button" class="studio-btn studio-btn--ghost studio-copy" data-copy="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
				<i class="jws-icon-link"></i> <span><?php esc_html_e( 'Copy link', 'jws_streamvid' ); ?></span>
			</button>
			<a target="_blank" rel="noopener" href="<?php echo esc_url( $viewer_url ); ?>" class="studio-btn studio-btn--ghost">
				<i class="jws-icon-monitor-play"></i> <span><?php esc_html_e( 'View as viewer', 'jws_streamvid' ); ?></span>
			</a>
		</div>

	</div>

	<?php if ( ! $configured ) : ?>
		<div class="studio-schedule studio-schedule--warning" role="alert">
			<div class="studio-schedule__text">
				<strong><?php esc_html_e( 'Live streaming is not configured', 'jws_streamvid' ); ?></strong>
				<small><?php esc_html_e( 'An administrator needs to fill in LiveKit URL, LiveKit API Key and LiveKit API Secret in Theme Options > Videos > Livestream.', 'jws_streamvid' ); ?></small>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $timestamp ) : ?>
		<div class="studio-schedule">
			<div class="studio-schedule__text">
				<strong><?php esc_html_e( 'Scheduled', 'jws_streamvid' ); ?></strong>
				<div class="video-time-live fs-small"><?php echo esc_html( Jws_Livestream_Frontend::format_start( $timestamp ) ); ?></div>
				<small><?php esc_html_e( 'You can go live once the countdown ends.', 'jws_streamvid' ); ?></small>
			</div>
			<div class="countdown-box jws-live-countdown" data-date="<?php echo esc_attr( $timestamp * 1000 ); ?>">
				<div class="countdown-item"><div class="count-num days">00</div><div class="count-label"><?php esc_html_e( 'Days', 'jws_streamvid' ); ?></div></div>
				<span class="count-sep">:</span>
				<div class="countdown-item"><div class="count-num hours">00</div><div class="count-label"><?php esc_html_e( 'Hrs', 'jws_streamvid' ); ?></div></div>
				<span class="count-sep">:</span>
				<div class="countdown-item"><div class="count-num minutes">00</div><div class="count-label"><?php esc_html_e( 'Min', 'jws_streamvid' ); ?></div></div>
				<span class="count-sep">:</span>
				<div class="countdown-item"><div class="count-num seconds">00</div><div class="count-label"><?php esc_html_e( 'Sec', 'jws_streamvid' ); ?></div></div>
			</div>
		</div>
	<?php endif; ?>

	<div class="studio-tabs" role="tablist">
		<button type="button" role="tab" class="studio-tab is-active" aria-selected="true" aria-controls="studio-panel-webcam" id="studio-tab-webcam">
			<i class="jws-icon-video-camera"></i>
			<span>
				<strong><?php esc_html_e( 'Webcam', 'jws_streamvid' ); ?></strong>
				<small><?php esc_html_e( 'Go live from this browser', 'jws_streamvid' ); ?></small>
			</span>
		</button>
		<button type="button" role="tab" class="studio-tab" aria-selected="false" aria-controls="studio-panel-software" id="studio-tab-software" tabindex="-1">
			<i class="jws-icon-television"></i>
			<span>
				<strong><?php esc_html_e( 'Streaming software', 'jws_streamvid' ); ?></strong>
				<small><?php esc_html_e( 'OBS, Streamlabs, vMix', 'jws_streamvid' ); ?></small>
			</span>
		</button>
	</div>

	<div class="studio-panel live-camera" role="tabpanel" id="studio-panel-webcam" aria-labelledby="studio-tab-webcam">

		<div class="webcam-player">
			<div class="webcam-player-inner">
				<div class="studio-placeholder">
					<i class="jws-icon-video-camera"></i>
					<strong><?php esc_html_e( 'Your camera preview appears here', 'jws_streamvid' ); ?></strong>
					<small><?php esc_html_e( 'Your browser asks for camera and microphone access when you start.', 'jws_streamvid' ); ?></small>
				</div>
				<div id="livekit-video"></div>
				<span class="studio-onair"><span class="studio-status__dot"></span><?php esc_html_e( 'Live', 'jws_streamvid' ); ?></span>
			</div>
		</div>

		<div class="webcam-controls studio-actions">
			<button type="button" id="webcam-action" class="studio-media-toggle enabled" aria-pressed="true" title="<?php esc_attr_e( 'Turn camera off', 'jws_streamvid' ); ?>" aria-label="<?php esc_attr_e( 'Turn camera off', 'jws_streamvid' ); ?>">
				<svg class="icon-on" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
				<svg class="icon-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2m5.66 0H14a2 2 0 0 1 2 2v3.34l1 1L23 7v10"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
			</button>
			<button type="button" id="mic-action" class="studio-media-toggle enabled" aria-pressed="true" title="<?php esc_attr_e( 'Mute microphone', 'jws_streamvid' ); ?>" aria-label="<?php esc_attr_e( 'Mute microphone', 'jws_streamvid' ); ?>">
				<svg class="icon-on" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
				<svg class="icon-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="1" y1="1" x2="23" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V4a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/><line x1="12" y1="19" x2="12" y2="23"/></svg>
			</button>
			<button type="button" id="start-webcam-live" class="button-default studio-btn studio-btn--primary" data-id="<?php echo esc_attr( $post_id ); ?>"<?php echo $disabled; ?>>
				<span><?php esc_html_e( 'Go live with webcam', 'jws_streamvid' ); ?></span>
			</button>
			<button type="button" id="stop-webcam-live" class="button-default studio-btn studio-btn--danger" data-id="<?php echo esc_attr( $post_id ); ?>">
				<?php esc_html_e( 'End stream', 'jws_streamvid' ); ?>
			</button>
		</div>

	</div>

	<div class="studio-panel live-rtmp" role="tabpanel" id="studio-panel-software" aria-labelledby="studio-tab-software" hidden>

		<ol class="studio-steps">
			<li><?php esc_html_e( 'Generate your stream key below (once per stream).', 'jws_streamvid' ); ?></li>
			<li><?php esc_html_e( 'In OBS open Settings > Stream, set Service to "Custom", then paste the Server and the Stream key below.', 'jws_streamvid' ); ?></li>
			<li><?php esc_html_e( 'Click Start Streaming in OBS. Viewers see you within a few seconds.', 'jws_streamvid' ); ?></li>
		</ol>

		<div class="rtmp-data">
			<div class="stream-field">
				<label for="studio-server"><?php esc_html_e( 'Server', 'jws_streamvid' ); ?></label>
				<div class="studio-field">
					<input id="studio-server" type="text" readonly value="<?php echo esc_attr( $rtmp_url ); ?>" placeholder="<?php esc_attr_e( 'Generate a key to get the server URL', 'jws_streamvid' ); ?>" />
					<button type="button" class="studio-icon-btn studio-copy" data-copy-from="#studio-server" title="<?php esc_attr_e( 'Copy', 'jws_streamvid' ); ?>" aria-label="<?php esc_attr_e( 'Copy server', 'jws_streamvid' ); ?>"><?php echo $copy_icon; ?></button>
				</div>
			</div>
			<div class="stream-field">
				<label for="studio-key"><?php esc_html_e( 'Stream key', 'jws_streamvid' ); ?></label>
				<div class="studio-field">
					<input id="studio-key" type="password" readonly autocomplete="off" value="<?php echo esc_attr( $stream_key ); ?>" placeholder="<?php esc_attr_e( 'Not generated yet', 'jws_streamvid' ); ?>" />
					<button type="button" class="studio-icon-btn studio-reveal" data-target="#studio-key" title="<?php esc_attr_e( 'Show or hide', 'jws_streamvid' ); ?>" aria-label="<?php esc_attr_e( 'Show stream key', 'jws_streamvid' ); ?>" aria-pressed="false"><i class="jws-icon-eye"></i></button>
					<button type="button" class="studio-icon-btn studio-copy" data-copy-from="#studio-key" title="<?php esc_attr_e( 'Copy', 'jws_streamvid' ); ?>" aria-label="<?php esc_attr_e( 'Copy stream key', 'jws_streamvid' ); ?>"><?php echo $copy_icon; ?></button>
				</div>
				<small class="studio-note"><?php esc_html_e( 'Keep your stream key private. Anyone with it can stream to this page.', 'jws_streamvid' ); ?></small>
			</div>
		</div>

		<div class="studio-actions">
			<?php // One button, two jobs: create the first key, or replace one that leaked or stopped working. ?>
			<button type="button" id="get_stream_key"
				class="button-default studio-btn <?php echo $stream_key ? 'studio-btn--ghost' : 'studio-btn--primary'; ?>"
				data-id="<?php echo esc_attr( $post_id ); ?>"
				data-regenerate="<?php echo $stream_key ? '1' : '0'; ?>"
				data-confirm="<?php esc_attr_e( 'Create a new stream key? The current key stops working, so update it in OBS.', 'jws_streamvid' ); ?>"<?php echo $disabled; ?>><?php echo $stream_key ? esc_html__( 'Reset stream key', 'jws_streamvid' ) : esc_html__( 'Generate stream key', 'jws_streamvid' ); ?></button>
			<button type="button" class="button-default studio-btn studio-btn--danger studio-end-rtmp" data-id="<?php echo esc_attr( $post_id ); ?>"<?php echo $disabled; ?>>
				<?php esc_html_e( 'End stream', 'jws_streamvid' ); ?>
			</button>
		</div>

	</div>

</div>
