Screenshots for the landing page.

Drop your captures here and point $jl_images at them in
../../templates/page-landing.php:

    'hero' => plugin_dir_url( dirname( __FILE__ ) ) . 'assets/img/hero.jpg',

Or skip this folder: put a number in the entry and it is read as a media
library attachment ID, so the picture can be replaced from Media > Library
without editing any file.

Slots and the size each is drawn at on a 1440px screen (supply about double
for retina):

    hero        1384 x 888   home page, below the browser bar
    demo_1..8    564 x 344   demo thumbnails
    drama        564 x 980   portrait, sits UNDER the unlock panel
    player      1096 x 472   the player with a frame showing
    import      1050 x 360   the TMDb import screen
    livetv      1050 x 360   a channel and its guide
    app_phone    112 x 204   portrait
    app_tv       244 x 136   landscape

An empty entry is fine: the CSS mockup underneath shows through instead.

A screenshot that carries real film posters is a copyright risk on Envato.
Use your own artwork or public-domain stills in anything you publish.
