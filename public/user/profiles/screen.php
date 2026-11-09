<?php
/**
 * Page shell for the profile screens (Who's watching?, PIN, add, edit).
 * Each screen's content is in a file of the same name next to this one.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_pf_screen = Jws_Profiles_Front::screen();

get_header();
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main jws-pf jws-pf--<?php echo esc_attr( $jws_pf_screen ); ?>">
		<div class="jws-pf__wrap">
			<?php jws_streamvid_load_template( 'user/profiles/' . $jws_pf_screen . '.php', false ); ?>
		</div>
	</main>
</div>
<?php
get_footer();
