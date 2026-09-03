<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! jws_streamvid()->get()->profile->_profile_is_owner() ) {
    return false;
}
?>

<div class="dashboard-profile">

    <div class="personal-information">
        <?php
            echo jws_streamvid()->get()->dashboard->personal_information();
        ?>
    </div>

    <div class="account-security">
        <?php
            echo jws_streamvid()->get()->dashboard->account_security();
        ?>
    </div>

    <?php
    if ( class_exists( 'NextendSocialLogin', false ) ) {
        $nextend_social = NextendSocialLogin::renderLinkAndUnlinkButtons();
        if ( empty( $nextend_social ) ) {
            return false;
        }
        ?>
        <div class="login-width-social">
            <h6><?php echo esc_html__( 'Social Login Accounts', 'jws_streamvid' ); ?></h6>
            <?php echo $nextend_social; ?>
        </div>
        <?php
    }
    ?>

</div>
