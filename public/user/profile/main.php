<?php
get_header();

$profile_layout = jws_theme_get_option('profile_layout');
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php do_action('streamvid/profile/header'); ?>

        <?php if ($profile_layout === 'v2') : ?>
            <div class="profile-v2">
                  <div class="profile-nav-vertical">
                    <?php do_action("streamvid/profile/header/menu"); ?>
                  </div>
                  <div class="profile-main">
                        <?php do_action('streamvid/profile/main'); ?>
                    </div>
            </div>
          
        <?php else : ?>
            <div class="profile-main">
                <?php do_action('streamvid/profile/main'); ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php get_footer(); ?>
