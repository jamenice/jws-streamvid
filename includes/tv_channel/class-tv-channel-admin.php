<?php

/**
 * Channel list screen: logo, number and what is on air right now, sorted by
 * channel number the way the guide shows them.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/tv_channel
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Tv_Channel_Admin {

	public static function hook() {

		$type = Jws_Tv_Channel::POST_TYPE;

		add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( "manage_edit-{$type}_sortable_columns", array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'order' ) );
		add_action( 'admin_head-edit.php', array( __CLASS__, 'styles' ) );
	}

	public static function columns( $columns ) {

		$out = array();

		foreach ( $columns as $key => $label ) {

			if ( 'title' === $key ) {
				$out['tv_logo']   = '<span class="screen-reader-text">' . esc_html__( 'Logo', 'jws_streamvid' ) . '</span>';
				$out['tv_number'] = esc_html__( 'No.', 'jws_streamvid' );
			}

			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['tv_now'] = esc_html__( 'On air', 'jws_streamvid' );
			}
		}

		return $out;
	}

	public static function column( $column, $post_id ) {

		switch ( $column ) {

			case 'tv_logo':
				$logo = (int) get_post_meta( $post_id, 'tv_channel_logo', true );
				echo $logo ? wp_get_attachment_image( $logo, 'thumbnail', false, array( 'class' => 'jws-tv-admin-logo' ) ) : '<span class="jws-tv-admin-logo is-empty"></span>';
				break;

			case 'tv_number':
				echo esc_html( (string) get_post_meta( $post_id, 'tv_channel_number', true ) );
				break;

			case 'tv_now':
				$state = Jws_Tv_Channel_Schedule::now_next( $post_id );

				if ( ! $state['now'] ) {
					echo '<span class="description">' . esc_html__( 'Nothing scheduled', 'jws_streamvid' ) . '</span>';
					break;
				}

				printf(
					'<strong>%1$s</strong><br><span class="description">%2$s – %3$s</span>',
					esc_html( $state['now']['title'] ),
					esc_html( wp_date( get_option( 'time_format' ), $state['now']['start'] ) ),
					esc_html( wp_date( get_option( 'time_format' ), $state['now']['end'] ) )
				);
				break;
		}
	}

	public static function sortable( $columns ) {

		$columns['tv_number'] = 'tv_number';

		return $columns;
	}

	/** Channel number order by default, and when the No. column is clicked. */
	public static function order( $query ) {

		if ( ! is_admin() || ! $query->is_main_query() || Jws_Tv_Channel::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( $orderby && 'tv_number' !== $orderby ) {
			return;
		}

		/* NOT EXISTS keeps channels that were never given a number in the list. */
		$query->set(
			'meta_query',
			array(
				'relation'  => 'OR',
				'tv_number' => array( 'key' => 'tv_channel_number', 'type' => 'NUMERIC' ),
				array( 'key' => 'tv_channel_number', 'compare' => 'NOT EXISTS' ),
			)
		);
		$query->set( 'orderby', array( 'tv_number' => $query->get( 'order' ) ? $query->get( 'order' ) : 'ASC', 'title' => 'ASC' ) );
	}

	public static function styles() {

		if ( Jws_Tv_Channel::POST_TYPE !== get_current_screen()->post_type ) {
			return;
		}

		echo '<style>.column-tv_logo{width:64px}.column-tv_number{width:56px}.jws-tv-admin-logo{display:block;width:48px;height:48px;object-fit:contain;border-radius:8px;background:#191c33;padding:4px;box-sizing:border-box}.jws-tv-admin-logo.is-empty{background:#dcdcde}</style>';
	}
}
