<?php
/**
 * A live stream that has ended, or one the viewer cannot open.
 *
 * @var int    $post_id
 * @var string $message Optional; defaults to "This live stream has ended."
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$message = ! empty( $message ) ? $message : __( 'This live stream has ended.', 'jws_streamvid' );
$image   = get_the_post_thumbnail_url( $post_id, 'full' );
?>
<div class="jws-livestream-notice jws-livestream-notice--ended"<?php if ( $image ) : ?> style="background-image: url('<?php echo esc_url( $image ); ?>');"<?php endif; ?>>
	<div class="jws-livestream-notice__inner">
		<h4><?php echo esc_html( $message ); ?></h4>
		<a class="button-default" href="<?php echo esc_url( get_post_type_archive_link( 'videos' ) ); ?>"><?php esc_html_e( 'Browse videos', 'jws_streamvid' ); ?></a>
	</div>
</div>
