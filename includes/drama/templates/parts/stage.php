<?php
/**
 * Inside of .sv-short-stage: the player (or the lock panel) plus the side
 * controls.
 *
 * Split out of player-page.php so the AJAX episode switch renders exactly the
 * same markup the first page load did, instead of a second copy that drifts.
 *
 * @var int $drama_id
 * @var int $episode_id
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$drama_id   = isset( $args['drama_id'] ) ? (int) $args['drama_id'] : 0;
$episode_id = isset( $args['episode_id'] ) ? (int) $args['episode_id'] : 0;

/* A neighbour rendered ahead of the viewer asking for it is not a visit: it
   must not go through remember_episode() below, or opening the drama again
   would resume on an episode nobody ever watched. */
$prefetch = ! empty( $args['prefetch'] );

$episodes = Jws_Drama_Post_Types::episodes_of( $drama_id );
$access   = $episode_id
	? Jws_Drama_Wallet::access( $episode_id )
	: array( 'can_watch' => false, 'reason' => 'invalid', 'price' => 0, 'balance' => 0, 'number' => 0 );

$poster = get_the_post_thumbnail_url( $drama_id, 'full' ) ?: '';
$price  = Jws_Drama_Wallet::coin_price( $drama_id );

$index = $episode_id ? array_search( $episode_id, array_map( 'intval', $episodes ), true ) : false;
$prev  = ( false !== $index && isset( $episodes[ $index - 1 ] ) ) ? (int) $episodes[ $index - 1 ] : 0;
$next  = ( false !== $index && isset( $episodes[ $index + 1 ] ) ) ? (int) $episodes[ $index + 1 ] : 0;

if ( $episode_id && ! empty( $access['can_watch'] ) ) {

	if ( ! $prefetch ) {
		Jws_Drama_Templates::remember_episode( $drama_id, $episode_id );
	}

	jws_streamvid_load_template(
		'../includes/drama/templates/parts/player.php',
		false,
		array( 'episode_id' => $episode_id, 'drama_id' => $drama_id, 'poster' => $poster )
	);

} else {
	?>
	<div class="sv-short-player sv-short-player--locked" <?php echo $poster ? 'style="background-image:url(' . esc_url( $poster ) . ')"' : ''; ?>>
		<div class="sv-short-lock-panel">
			<i class="jws-icon-lock-key-fill"></i>
			<?php if ( ! $episode_id ) : ?>
				<p><?php echo esc_html__( 'This drama has no episodes yet.', 'jws_streamvid' ); ?></p>
			<?php elseif ( ! is_user_logged_in() ) : ?>
				<p>
					<?php
					/* translators: %d: coin price */
					printf( esc_html__( 'Sign in to unlock this episode for %d coins.', 'jws_streamvid' ), (int) $price );
					?>
				</p>
				<?php
				/*
				 * The episode's own URL, not "#": the login popup posts it as
				 * the redirect, so signing in comes back to the episode that
				 * asked for it rather than to the home page.
				 */
				?>
				<a class="jws-open-login button-default" href="<?php echo esc_url( get_permalink( $episode_id ) ); ?>"><span><?php echo esc_html__( 'Sign In', 'jws_streamvid' ); ?></span></a>
			<?php else : ?>
				<p><?php echo esc_html__( 'This is a paid episode. Please unlock to watch.', 'jws_streamvid' ); ?></p>
				<button type="button" class="sv-short-unlock button-default" data-episode="<?php echo (int) $episode_id; ?>">
					<?php echo Jws_Drama_Settings::coin_icon_html(); ?><span class="sv-short-unlock-price"><?php echo esc_html( number_format_i18n( $price ) ); ?></span>
					<span><?php echo esc_html__( 'Unlock Now', 'jws_streamvid' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
?>
<div class="sv-short-stage-controls">
	<button type="button" class="sv-short-stage-btn sv-short-panel-toggle" aria-label="<?php echo esc_attr__( 'Episodes', 'jws_streamvid' ); ?>" aria-expanded="false">
		<i class="jws-icon-list" aria-hidden="true"></i>
	</button>
	<button type="button" class="sv-short-stage-btn sv-short-fullscreen" aria-label="<?php echo esc_attr__( 'Fullscreen', 'jws_streamvid' ); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentcolor" viewBox="0 0 256 256"><path d="M216,48V96a8,8,0,0,1-16,0V67.31l-50.34,50.35a8,8,0,0,1-11.32-11.32L188.69,56H160a8,8,0,0,1,0-16h48A8,8,0,0,1,216,48ZM106.34,138.34,56,188.69V160a8,8,0,0,0-16,0v48a8,8,0,0,0,8,8H96a8,8,0,0,0,0-16H67.31l50.35-50.34a8,8,0,0,0-11.32-11.32Z"></path></svg>
	</button>
	<?php if ( $prev ) : ?>
		<a class="sv-short-stage-btn sv-short-nav" data-episode="<?php echo (int) $prev; ?>" href="<?php echo esc_url( get_permalink( $prev ) ); ?>" aria-label="<?php echo esc_attr__( 'Previous episode', 'jws_streamvid' ); ?>"><i class="jws-icon-caret-up"></i></a>
	<?php else : ?>
		<span class="sv-short-stage-btn is-disabled" aria-hidden="true"><i class="jws-icon-caret-up"></i></span>
	<?php endif; ?>
	<?php if ( $next ) : ?>
		<a class="sv-short-stage-btn sv-short-nav" data-episode="<?php echo (int) $next; ?>" href="<?php echo esc_url( get_permalink( $next ) ); ?>" aria-label="<?php echo esc_attr__( 'Next episode', 'jws_streamvid' ); ?>"><i class="jws-icon-caret-down"></i></a>
	<?php else : ?>
		<span class="sv-short-stage-btn is-disabled" aria-hidden="true"><i class="jws-icon-caret-down"></i></span>
	<?php endif; ?>
</div>
