<?php
/**
 * One episode. Same screen as single-drama.php, pinned to this episode.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

get_header();

$episode_id = get_the_ID();
$drama_id   = Jws_Drama_Wallet::drama_id_of( $episode_id );
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main sv-short-single">
		<?php
		if ( $drama_id ) {
			jws_streamvid_load_template(
				'../includes/drama/templates/parts/player-page.php',
				false,
				array( 'drama_id' => $drama_id, 'episode_id' => $episode_id )
			);
		} else {
			echo '<div class="container"><p class="sv-short-empty">'
				. esc_html__( 'This episode is not linked to a drama.', 'jws_streamvid' )
				. '</p></div>';
		}
		?>
	</main>
</div>
<?php
get_footer();
