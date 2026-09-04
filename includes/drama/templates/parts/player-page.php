<?php
/**
 * The drama watch screen: vertical stage on the left, series panel on the right.
 *
 * The player itself is the Video.js 10 web-component markup that
 * public/movies/player.php emits for the v10 engine, with the same
 * `data-jws-v10` contract — so jws_player_v10.js picks it up and resume
 * position, watch history and the rest come along for free.
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

if ( ! $drama_id ) {
	return;
}

$episodes = Jws_Drama_Post_Types::episodes_of( $drama_id );
$total    = count( $episodes );
$number   = $episode_id ? Jws_Drama_Wallet::episode_number( $episode_id ) : 0;
$index    = $episode_id ? array_search( $episode_id, array_map( 'intval', $episodes ), true ) : false;

/* Ranges of 30, the way a 40+ episode list stays scannable. */
$per_range = 30;
$ranges    = array_chunk( $episodes, $per_range );
$unlocked  = Jws_Drama_Wallet::unlocked_episodes( get_current_user_id(), $drama_id );
$free_to   = Jws_Drama_Wallet::free_episodes( $drama_id );
$price     = Jws_Drama_Wallet::coin_price( $drama_id );
$bypass    = Jws_Drama_Wallet::membership_unlocks_all( get_current_user_id() );
?>
<div class="sv-short-page">

	<div class="sv-short-stage">
		<?php
		jws_streamvid_load_template(
			'../includes/drama/templates/parts/stage.php',
			false,
			array( 'drama_id' => $drama_id, 'episode_id' => $episode_id )
		);
		?>
	</div>

	<aside class="sv-short-panel jws-scrollbar">

		<ul class="jws-breadcrumbs sv-short-breadcrumbs">
			<li class="jws-breadcrumbs__item">
				<a class="jws-breadcrumbs__crumb jws-breadcrumbs__crumb--link" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<span class="jws-breadcrumbs__text"><?php echo esc_html__( 'Home', 'jws_streamvid' ); ?></span>
				</a>
			</li>
			<li class="jws-breadcrumbs__separator"><span class="jws-breadcrumbs__separator__text">/</span></li>
			<li class="jws-breadcrumbs__item">
				<a class="jws-breadcrumbs__crumb jws-breadcrumbs__crumb--link" href="<?php echo esc_url( get_permalink( $drama_id ) ); ?>">
					<span class="jws-breadcrumbs__text"><?php echo esc_html( get_the_title( $drama_id ) ); ?></span>
				</a>
			</li>
			<?php if ( $number ) : ?>
				<li class="jws-breadcrumbs__separator"><span class="jws-breadcrumbs__separator__text">/</span></li>
				<li class="jws-breadcrumbs__item jws-breadcrumbs__item--current">
					<span class="jws-breadcrumbs__crumb">
						<span class="jws-breadcrumbs__text">
							<?php
							/* translators: %d: episode number */
							printf( esc_html__( 'Episode %d', 'jws_streamvid' ), (int) $number );
							?>
						</span>
					</span>
				</li>
			<?php endif; ?>
		</ul>

		<h1 class="sv-short-title">
			<?php
			echo esc_html( get_the_title( $drama_id ) );
			if ( $number ) {
				/* translators: %d: episode number */
				echo ' – ' . esc_html( sprintf( esc_html__( 'Episode %d', 'jws_streamvid' ), $number ) );
			}
			?>
		</h1>

		<?php $genre_terms = Jws_Drama_Templates::genre_terms( $drama_id, 5 ); ?>
		<?php if ( $genre_terms ) : ?>
			<div class="jws-category-extra">
				<?php foreach ( $genre_terms as $genre_term ) :
					$genre_link = get_term_link( $genre_term );
					if ( is_wp_error( $genre_link ) ) {
						continue;
					}
				?>
					<a href="<?php echo esc_url( $genre_link ); ?>" rel="tag"><?php echo esc_html( $genre_term->name ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="jws-tool fs-small">
			<?php if ( function_exists( 'jws_like_button' ) ) jws_like_button( 'drama', $drama_id ); ?>
			<?php if ( function_exists( 'jws_watchlist_button' ) ) jws_watchlist_button( $drama_id ); ?>
			<?php if ( function_exists( 'jws_share_button' ) ) jws_share_button( $drama_id ); ?>
		</div>

		<?php $overview = get_the_excerpt( $drama_id ); ?>
		<?php if ( $overview ) : ?>
			<div class="sv-short-plot">
				<h6><?php echo esc_html__( 'Overview', 'jws_streamvid' ); ?></h6>
				<p><?php echo esc_html( $overview ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $total ) : ?>
			<div class="sv-short-episodes">

				<div class="sv-short-episodes-head">
					<h6><?php echo esc_html__( 'Episode List', 'jws_streamvid' ); ?></h6>
					<span class="sv-short-episodes-count">
						<?php
						/* translators: %d: episode count */
						printf( esc_html__( '%d EP', 'jws_streamvid' ), (int) $total );
						?>
					</span>
				</div>

				<?php if ( count( $ranges ) > 1 ) : ?>
					<div class="sv-short-ranges">
						<?php foreach ( $ranges as $r => $chunk ) : ?>
							<?php
							$from = ( $r * $per_range ) + 1;
							$to   = $from + count( $chunk ) - 1;
							/* Open on the range holding the episode being watched. */
							$is_current = ( false !== $index ) && $index >= ( $r * $per_range ) && $index < ( ( $r + 1 ) * $per_range );
							?>
							<button type="button" class="sv-short-range<?php echo $is_current ? ' active' : ''; ?>" data-range="<?php echo (int) $r; ?>">
								<?php echo esc_html( $from . ' – ' . $to ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php foreach ( $ranges as $r => $chunk ) : ?>
					<?php $is_current = ( false !== $index ) && $index >= ( $r * $per_range ) && $index < ( ( $r + 1 ) * $per_range ); ?>
					<div class="sv-short-episode-grid" data-range="<?php echo (int) $r; ?>"<?php echo ( count( $ranges ) > 1 && ! $is_current ) ? ' hidden' : ''; ?>>
						<?php foreach ( $chunk as $ep_id ) : ?>
							<?php
							$ep_id     = (int) $ep_id;
							$ep_number = Jws_Drama_Wallet::episode_number( $ep_id );
							$is_free   = get_post_meta( $ep_id, 'drama_ep_free', true ) || $ep_number <= $free_to;
							$is_open   = $is_free || $bypass || in_array( $ep_id, $unlocked, true );
							$classes   = 'sv-short-episode';

							if ( $ep_id === $episode_id ) {
								$classes .= ' active';
							}

							if ( ! $is_open ) {
								$classes .= ' locked';
							}
							?>
							<a class="<?php echo esc_attr( $classes ); ?>" data-episode="<?php echo (int) $ep_id; ?>" data-ep-number="<?php echo (int) $ep_number; ?>" href="<?php echo esc_url( get_permalink( $ep_id ) ); ?>">
								<?php if ( $ep_id === $episode_id ) : ?>
									<span class="sv-short-playing"><span></span><span></span><span></span></span>
								<?php else : ?>
									<?php echo (int) $ep_number; ?>
									<?php if ( ! $is_open ) : ?>
										<i class="jws-icon-lock-key-fill sv-short-lock"></i>
									<?php endif; ?>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</aside>
</div>
