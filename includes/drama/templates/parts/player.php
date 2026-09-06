<?php
/**
 * The Video.js 10 player for one drama episode.
 *
 * Emits the same markup and the same `data-jws-v10` payload as
 * public/movies/player.php does on the v10 engine, so jws_player_v10.js
 * initialises it without knowing drama exists — resume position, watch history
 * and the click shield all apply.
 *
 * The episode's video meta keys are the site-wide ones (`videos_type`,
 * `videos_url`, `videos_file`), which is what lets the encode / Bunny /
 * Cloudflare pipeline feed a drama episode unchanged.
 *
 * @var int    $episode_id
 * @var int    $drama_id
 * @var string $poster
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$episode_id = isset( $args['episode_id'] ) ? (int) $args['episode_id'] : 0;
$drama_id   = isset( $args['drama_id'] ) ? (int) $args['drama_id'] : 0;
$poster     = isset( $args['poster'] ) ? $args['poster'] : '';

if ( ! $episode_id ) {
	return;
}

$videos_type = get_post_meta( $episode_id, 'videos_type', true );
$video_url   = '';
$type        = 'video/mp4';

if ( 'file' === $videos_type ) {

	$video_id  = get_post_meta( $episode_id, 'videos_file', true );
	$video_url = $video_id ? wp_get_attachment_url( $video_id ) : '';

	/* Same precedence as the movie template: an encoded or CDN copy wins over
	   the original upload. */
	$encoded    = $video_id ? get_post_meta( $video_id, 'encode_url', true ) : '';
	$bunny      = $video_id ? get_post_meta( $video_id, 'bunny_id', true ) : '';
	$cloudflare = $video_id ? get_post_meta( $video_id, 'cloudflare_id', true ) : '';
	$advanced   = function_exists( 'jws_theme_get_option' ) ? jws_theme_get_option( 'video_advenced' ) : '';

	if ( ! empty( $encoded ) && 'encode' === $advanced ) {
		$video_url = get_site_url() . strstr( $encoded, '/wp-content' );
		$type      = 'application/x-mpegURL';
	} elseif ( ! empty( $bunny ) && 'bunny' === $advanced ) {
		$video_url = '//' . jws_theme_get_option( 'bn_host_name' ) . "/{$bunny}/playlist.m3u8";
		$type      = 'application/x-mpegURL';
	} elseif ( ! empty( $cloudflare ) && 'cloudflare' === $advanced ) {
		$video_url = '//' . jws_theme_get_option( 'cl_host_name' ) . "/{$cloudflare}/manifest/video.m3u8";
		$type      = 'application/x-mpegURL';
	}
} else {

	$video_url = get_post_meta( $episode_id, 'videos_url', true );

	if ( function_exists( 'jws_is_youtube_url' ) && jws_is_youtube_url( $video_url ) ) {
		$type = 'video/youtube';
	} elseif ( function_exists( 'jws_is_vimeo_url' ) && jws_is_vimeo_url( $video_url ) ) {
		$type = 'video/vimeo';
	} elseif ( function_exists( 'jws_check_m3u8_video' ) && jws_check_m3u8_video( $video_url ) ) {
		$type = 'application/x-mpegURL';
	}
}

/* Falls back to the site-wide "Drama Short Default Url" (Jws Settings →
   Video Options → Video Default) when this episode never got its own video,
   so a half-imported or demo series still plays something instead of
   showing the locked panel below. */
if ( empty( $video_url ) && function_exists( 'jws_theme_get_option' ) ) {

	$video_url = jws_theme_get_option( 'video_player_default_drama_url' );

	if ( function_exists( 'jws_is_youtube_url' ) && jws_is_youtube_url( $video_url ) ) {
		$type = 'video/youtube';
	} elseif ( function_exists( 'jws_is_vimeo_url' ) && jws_is_vimeo_url( $video_url ) ) {
		$type = 'video/vimeo';
	} elseif ( function_exists( 'jws_check_m3u8_video' ) && jws_check_m3u8_video( $video_url ) ) {
		$type = 'application/x-mpegURL';
	} else {
		$type = 'video/mp4';
	}
}

if ( function_exists( 'jws_get_security_video_url' ) ) {
	$video_url = jws_get_security_video_url( $video_url );
}

if ( empty( $video_url ) ) {
	echo '<div class="sv-short-player sv-short-player--locked"><div class="sv-short-lock-panel"><p>'
		. esc_html__( 'No video on this episode yet.', 'jws_streamvid' )
		. '</p></div></div>';
	return;
}

switch ( $type ) {
	case 'video/youtube':
		$tag    = 'youtube-video';
		$module = 'media/youtube-video.js';
		break;
	case 'video/vimeo':
		$tag    = 'vimeo-video';
		$module = 'media/vimeo-video.js';
		break;
	case 'application/x-mpegURL':
		$tag    = 'hlsjs-video';
		$module = 'media/hlsjs-video.js';
		break;
	default:
		$tag    = 'video';
		$module = '';
}

$autoplay = function_exists( 'jws_theme_get_option' ) && jws_theme_get_option( 'video_autoplay' ) ? true : false;
$muted    = function_exists( 'jws_theme_get_option' ) && jws_theme_get_option( 'video_muted' ) ? true : false;

$current_time = 0;

if ( is_user_logged_in() ) {
	$progress = Jws_History::get_item( get_current_user_id(), $episode_id );

	if ( ! empty( $progress['time'] ) ) {
		$current_time = (float) $progress['time'];
	}
}

$config = array(
	'postId'      => $episode_id,
	'src'         => $video_url,
	'type'        => $type,
	'mediaTag'    => $tag,
	'mediaModule' => $module,
	'poster'      => $poster,
	'autoplay'    => $autoplay,
	'muted'       => $muted,
	'currentTime' => $current_time,
	'qualities'   => array(),
	'adsTagUrl'   => '',
);

$subtitles = function_exists( 'get_field' ) ? get_field( 'sub_titles', $episode_id ) : array();
?>
<div class="videos_player sv-short-player vjs-waiting" data-playerid="<?php echo (int) $episode_id; ?>">
	<video-player id="videos_player"
		class="jws_player jws_player_v10"
		data-playerid="<?php echo (int) $episode_id; ?>"
		data-jws-v10='<?php echo esc_attr( wp_json_encode( $config ) ); ?>'
		<?php if ( $poster ) : ?>poster="<?php echo esc_url( $poster ); ?>"<?php endif; ?>
	>
		<video-skin>
			<<?php echo esc_html( $tag ); ?>
				src="<?php echo esc_url( $video_url ); ?>"
				playsinline
				preload="auto"
				<?php echo $config['autoplay'] ? 'autoplay' : ''; ?>
				<?php echo $config['muted'] ? 'muted' : ''; ?>
			>
				<?php
				if ( ! empty( $subtitles ) && is_array( $subtitles ) ) {
					foreach ( $subtitles as $key => $subtitle ) {
						$url = isset( $subtitle['vtt_file']['url'] ) ? $subtitle['vtt_file']['url'] : '';

						if ( ! empty( $subtitle['vtt_url'] ) ) {
							$url = $subtitle['vtt_url'];
						}

						if ( empty( $url ) ) {
							continue;
						}

						printf(
							'<track label="%1$s" kind="subtitles" srclang="%1$s" src="%2$s" %3$s />',
							esc_attr( $subtitle['language'] ),
							esc_url( $url ),
							0 === $key ? 'default' : ''
						);
					}
				}
				?>
			</<?php echo esc_html( $tag ); ?>>
			<?php if ( $poster ) : ?>
				<img slot="poster" src="<?php echo esc_url( $poster ); ?>" alt="" />
			<?php endif; ?>
		</video-skin>
	</video-player>
</div>
