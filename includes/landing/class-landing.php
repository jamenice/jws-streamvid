<?php

/**
 * Landing page module: a full-width marketing page for the theme itself.
 *
 * Self-contained like the drama and Live TV modules — the page template, its
 * stylesheet and its webfont all live under includes/landing/ and are hooked up
 * from boot(). Nothing outside this folder is touched, so the module can be
 * switched off by not calling Jws_Landing::boot().
 *
 * The template is offered to WordPress as a page template, so it appears in
 * Page Attributes > Template on any page. WordPress only looks inside the theme
 * when it resolves that choice, which is why locate() has to hand the file over
 * on template_include. A theme can still take it over by dropping a file of the
 * same name in its root — the theme copy always wins.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/landing
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Landing {

	/**
	 * Value stored in the page's _wp_page_template meta.
	 *
	 * Prefixed because the theme may ship a template of its own one day and the
	 * two must not collide in the dropdown.
	 */
	const TEMPLATE = 'jws-landing.php';

	/** Set by locate() so enqueue() only fires on the landing page. */
	private $is_landing = false;

	public static function boot() {

		$module = new self();

		add_filter( 'theme_page_templates', array( $module, 'register' ) );

		/* Late so the theme's own template_include filters run first. */
		add_filter( 'template_include', array( $module, 'locate' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $module, 'enqueue' ), 20 );
		add_filter( 'body_class', array( $module, 'body_class' ) );
	}

	private function dir() {
		return plugin_dir_path( __FILE__ ) . 'templates/';
	}

	/**
	 * Adds the template to the Page Attributes dropdown.
	 */
	public function register( $templates ) {

		$templates[ self::TEMPLATE ] = esc_html__( 'StreamVid Landing Page', 'jws_streamvid' );

		return $templates;
	}

	/**
	 * True when the page being rendered asked for this template.
	 */
	private function is_selected() {

		if ( ! is_page() ) {
			return false;
		}

		return self::TEMPLATE === get_page_template_slug( get_queried_object_id() );
	}

	/**
	 * Points WordPress at the plugin's template unless the theme has its own.
	 */
	public function locate( $template ) {

		if ( ! $this->is_selected() ) {
			return $template;
		}

		$this->is_landing = true;

		$theme = locate_template( array( self::TEMPLATE ) );

		if ( $theme ) {
			return $theme;
		}

		$plugin = $this->dir() . 'page-landing.php';

		return file_exists( $plugin ) ? $plugin : $template;
	}

	/**
	 * Marks the body so a child theme can hang its own overrides off one class.
	 */
	public function body_class( $classes ) {

		if ( $this->is_landing || $this->is_selected() ) {
			$classes[] = 'jws-landing-page';
		}

		return $classes;
	}

	/**
	 * Turns one entry of the template's $jl_images into a URL.
	 *
	 * A number is a media library attachment ID, so a picture can be swapped
	 * from Media > Library without touching a path; anything else is used as
	 * given, which covers a file dropped in assets/img/ and a URL from
	 * anywhere else. An empty entry means "no picture yet" and the template
	 * falls back to the mockup it draws in CSS.
	 */
	public static function image_url( $image, $size = 'full' ) {

		if ( empty( $image ) ) {
			return '';
		}

		if ( is_numeric( $image ) ) {
			return (string) wp_get_attachment_image_url( (int) $image, $size );
		}

		return (string) $image;
	}

	/**
	 * Prints the screenshot that covers a mockup, or nothing.
	 *
	 * The picture is laid over the CSS mockup rather than replacing it, so the
	 * page still looks finished before any screenshot exists and every slot
	 * keeps a sensible shape once one arrives.
	 *
	 * @param string|int $image  Entry from $jl_images.
	 * @param string     $alt    Alternative text.
	 * @param array      $args   'class' for an extra class, 'lazy' false above the fold.
	 */
	public static function shot( $image, $alt = '', $args = array() ) {

		$url = self::image_url( $image );

		if ( ! $url ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'class' => '',
				'lazy'  => true,
			)
		);

		printf(
			'<img class="jl-shot %s" src="%s" alt="%s"%s>',
			esc_attr( $args['class'] ),
			esc_url( $url ),
			esc_attr( $alt ),
			$args['lazy'] ? ' loading="lazy" decoding="async"' : ''
		);
	}

	/**
	 * Loads the module's stylesheet.
	 *
	 * locate() has already run by the time wp_enqueue_scripts fires, but the
	 * flag is only set when this module served the file — a theme override
	 * returns early — so is_selected() is checked as well: the page still wants
	 * these styles when a child theme renders its own copy of the markup.
	 */
	public function enqueue() {

		if ( ! $this->is_landing && ! $this->is_selected() ) {
			return;
		}

		$version = defined( 'JWS_STREAMVID_VERSION' ) ? JWS_STREAMVID_VERSION : '1.0.0';
		$url     = plugin_dir_url( __FILE__ );

		wp_enqueue_style( 'jws-landing', $url . 'assets/landing.css', array(), $version );
	}
}
