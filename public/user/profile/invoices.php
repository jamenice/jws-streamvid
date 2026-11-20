<?php
if( ! defined('ABSPATH' ) ){
    exit;
}
if(!jws_streamvid()->get()->profile->_profile_is_owner()) return false;

?>
<div class="invoices-page-profile">
<?php // Profile Version 3 names the page in its title bar already. ?>
<?php if ( ! ( class_exists( 'Jws_Profile_Library' ) && Jws_Profile_Library::is_v3() ) ) : ?>
	<h5><?php esc_html_e( 'Invoices', 'jws_streamvid' ); ?></h5>
<?php endif; ?>
<?php
 
require_once( PMPRO_DIR . '/preheaders/invoice.php' );

ob_start();

get_template_part( 'paid-memberships-pro/pages/invoice' );

$output = ob_get_clean();


echo $output;


?>

</div>

<?php jws_streamvid_load_template( 'user/profile/parts/invoice-download-pmpro.php', false ); ?>