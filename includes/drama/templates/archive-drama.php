<?php
/**
 * Drama archive.
 *
 * Same shell as archive-movies.php — sidebar, filter bar, result count, theme
 * pagination — so it inherits every archive option the theme already has. Only
 * the item inside the loop is different.
 *
 * @package Jws_Streamvid
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

get_header();

if ( defined( 'JWS_URI_PATH' ) ) {
	wp_enqueue_script( 'stick-content', JWS_URI_PATH . '/assets/js/sticky_content.js', array(), '', true );
}

$drama = function_exists( 'jws_archive_option' ) ? jws_archive_option( 'drama' ) : array();

$drama = wp_parse_args(
	$drama,
	array(
		'check-content-sidebar' => false,
		'column'                => 'jws-post-item col-xl-20 col-lg-4 col-md-6 col-12',
		'position_sidebar'      => 'right',
		'content_col'           => 'post_content col-12',
		'sidebar_col'           => 'post_sidebar col-xl-2 col-lg-12 col-12',
		'layout'                => 'layout1',
		'select-sidebar-post'   => '',
	)
);

/*
 * jws_archive_option() builds the column class from a `drama_column` Redux
 * option that does not exist, which leaves a bare "col-xl-" behind. Five per row
 * is what the movie archive uses and it suits a portrait card just as well.
 */
if ( false !== strpos( $drama['column'], 'col-xl- ' ) ) {
	$drama['column'] = str_replace( 'col-xl- ', 'col-xl-20 ', $drama['column'] );
}

/*
 * Drama has no sidebar options of its own, so it shares the movie archive's —
 * same widget, same side, same column split. Give drama its own Redux options
 * later and they take over, because only an empty widget id falls through here.
 */
if ( empty( $drama['select-sidebar-post'] ) && function_exists( 'jws_archive_option' ) ) {

	$movies = jws_archive_option( 'movies' );

	if ( ! empty( $movies['select-sidebar-post'] ) ) {
		$drama['select-sidebar-post']   = $movies['select-sidebar-post'];
		$drama['position_sidebar']      = $movies['position_sidebar'];
		$drama['check-content-sidebar'] = $movies['check-content-sidebar'];
		$drama['content_col']           = $movies['content_col'];
		$drama['sidebar_col']           = $movies['sidebar_col'];
	}
}

$has_sidebar = ! empty( $drama['check-content-sidebar'] ) && ! empty( $drama['select-sidebar-post'] );
?>
<div id="primary" class="content-area">
	<main id="main" class="site-main jws-drama-archive jws-movies-archive jws-movies_advanced-element sidebar-<?php echo esc_attr( $drama['position_sidebar'] ); ?>">
		<div class="container">
			<div class="row">

				<?php if ( 'left' === $drama['position_sidebar'] && $has_sidebar ) : ?>
					<div class="<?php echo esc_attr( $drama['sidebar_col'] ); ?>">
						<?php jws_sidebar_content( $drama['select-sidebar-post'], 'sidebar-main' ); ?>
					</div>
				<?php endif; ?>

				<div class="<?php echo esc_attr( $has_sidebar ? $drama['content_col'] : 'post_content col-12' ); ?>">

					<div class="archive-nav row row-end-height">
						<div class="col-xl-6 col-lg-12">
							<a class="show_filter_shop" href="javascript:void(0)">
								<i aria-hidden="true" class="jws-icon-plus"></i>
								<span><?php echo esc_html__( 'Filters', 'jws_streamvid' ); ?></span>
							</a>
							<?php
							if ( function_exists( 'jws_post_result' ) ) {
								jws_post_result();
							}
							?>
						</div>
						<div class="col-xl-6 col-lg-12">
							<?php do_action( 'streamvid/videos/filter', array( 'post_type' => 'drama', 'year' => true, 'category' => true ) ); ?>
						</div>
					</div>

					<div class="jws-archive-posts-wrap">
						<?php
						/*
						 * layout10 is fixed, not $drama['layout']: the card below is the
						 * theme's Layout 10 and its styling hangs off that class. The rest
						 * of the classes are the movie archive's, so the grid, the filter
						 * response and the pagination all behave the same here.
						 */
						?>
						<div class="jws-posts-grid movies_advanced_content row layout10">
							<?php
							if ( have_posts() ) :
								while ( have_posts() ) :
									the_post();

									echo '<div class="' . esc_attr( $drama['column'] ) . '">';
									get_template_part(
										'template-parts/content/movies/layout/layout10',
										'',
										array( 'post_id' => get_the_ID() )
									);
									echo '</div>';

								endwhile;
							else :
								get_template_part( 'template-parts/content/content', 'none' );
							endif;
							?>
						</div>

						<?php
						global $wp_query;
						echo function_exists( 'jws_query_pagination' ) ? jws_query_pagination( $wp_query ) : '';
						?>
					</div>
				</div>

				<?php if ( 'right' === $drama['position_sidebar'] && $has_sidebar ) : ?>
					<div class="<?php echo esc_attr( $drama['sidebar_col'] ); ?>">
						<?php jws_sidebar_content( $drama['select-sidebar-post'], 'sidebar-main' ); ?>
					</div>
				<?php endif; ?>

			</div>
		</div>
	</main>
</div>
<?php
get_footer();
