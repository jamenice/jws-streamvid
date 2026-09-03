<?php
/**
 * A drama on its own opens the watch screen, on whichever episode this viewer
 * is up to — a series page with nothing playing is not what anyone comes here
 * for.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

get_header();

$drama_id   = get_the_ID();
$episode_id = Jws_Drama_Templates::entry_episode( $drama_id );
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main sv-short-single">
		<?php
		jws_streamvid_load_template(
			'../includes/drama/templates/parts/player-page.php',
			false,
			array( 'drama_id' => $drama_id, 'episode_id' => $episode_id )
		);
		?>
	</main>
</div>
<?php
get_footer();
