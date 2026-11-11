<?php
/**
 * Sent in place of a page while "Who's watching?" waits for a profile, on an
 * account whose main profile has Profile Lock (Jws_Profiles_Front::waiting_page()).
 * The dialog opens over it by itself and cannot be closed; picking a profile
 * reloads the address, which then sends the page itself.
 *
 * $args['gate']: the "Who's watching?" screen URL.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jws_pw_gate = ! empty( $args['gate'] ) ? (string) $args['gate'] : Jws_Profiles_Front::url();

get_header();
?>
<div id="primary" class="content-area jws-pf-waiting">
	<main id="main" class="site-main">
		<div class="container">
			<h1 class="h3"><?php esc_html_e( "Who's watching?", 'jws_streamvid' ); ?></h1>
			<p><?php esc_html_e( 'Choose a profile to continue.', 'jws_streamvid' ); ?></p>
			<a class="button-default" href="<?php echo esc_url( $jws_pw_gate ); ?>"><?php esc_html_e( 'Choose a profile', 'jws_streamvid' ); ?></a>
		</div>
	</main>
</div>
<?php
get_footer();
