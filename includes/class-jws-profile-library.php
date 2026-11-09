<?php

/**
 * History, Watchlist and Favorites tabs of profile page Version 3.
 *
 * Builds the card list for the profile in use and handles the tabs' form
 * posts (remove one title, remove the selected titles, clear the history).
 * Layouts 1 and 2 keep their own templates and AJAX.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Profile_Library {

	const TABS = array( 'history', 'watchlist', 'favorites' );

	const PER_PAGE = 24;

	const NONCE = 'jws_library';

	public static function is_v3() {
		return function_exists( 'jws_theme_get_option' ) && 'v3' === jws_theme_get_option( 'profile_layout' );
	}

	/** The type filter in the URL (?type=), 'all' when missing or unknown. */
	public static function current_type() {

		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return in_array( $type, jws_profile_tab_post_types(), true ) ? $type : 'all';
	}

	/**
	 * Titles on a tab for the profile in use, newest first. Titles that are
	 * gone, unpublished or hidden from this profile are left out.
	 *
	 * @param string $tab  history, watchlist or favorites.
	 * @param string $type A post type, or 'all'.
	 * @return array[] Each array( 'id', 'type', 'when', 'history' ).
	 */
	public static function items( $tab, $user_id, $type = 'all' ) {

		$types = 'all' === $type ? jws_profile_tab_post_types() : array( $type );
		$raw   = array();

		if ( 'history' === $tab ) {
			foreach ( array_reverse( Jws_History::get_all( $user_id ), true ) as $id => $history ) {
				$raw[] = array( 'id' => (int) $id, 'when' => isset( $history['watched_at'] ) ? $history['watched_at'] : '', 'history' => $history );
			}
		} elseif ( 'watchlist' === $tab ) {
			foreach ( array_reverse( Jws_Watchlist::get_ids( $user_id ) ) as $id ) {
				$raw[] = array( 'id' => (int) $id, 'when' => '', 'history' => null );
			}
		} elseif ( 'favorites' === $tab ) {
			foreach ( Jws_Favorites::get_items( $user_id, $types ) as $row ) {
				$raw[] = array( 'id' => $row['post_id'], 'when' => $row['created_at'], 'history' => null );
			}
		}

		$items = array();

		foreach ( $raw as $item ) {
			$post_type = get_post_type( $item['id'] );

			if ( ! in_array( $post_type, $types, true ) || 'publish' !== get_post_status( $item['id'] ) || ! Jws_Profile_Filter::can_view( $item['id'] ) ) {
				continue;
			}

			$item['type'] = $post_type;
			$items[]      = $item;
		}

		return $items;
	}

	/**
	 * What a card shows: link, image, title, type, one meta line, progress.
	 *
	 * @param array $item From items().
	 * @return array
	 */
	public static function card( array $item ) {

		$id      = $item['id'];
		$type    = $item['type'];
		$history = $item['history'];
		$episode = ! empty( $history['episodes'] ) ? (int) $history['episodes'] : 0;
		$url     = get_permalink( $id );
		$meta    = array();

		// Movies layout v4 plays from {permalink}play; drama permalinks are already the player.
		if ( function_exists( 'jws_theme_get_option' ) && 'v4' === jws_theme_get_option( 'select-layout-movies' ) && 'movies' === $type ) {
			$url = trailingslashit( $url ) . 'play';
		}

		if ( $episode ) {
			$url = get_permalink( $episode );

			if ( 'drama' === $type ) {
				$number = class_exists( 'Jws_Drama_Wallet' ) ? Jws_Drama_Wallet::episode_number( $episode ) : get_post_meta( $episode, 'drama_ep_number', true );
				/* translators: %s: episode number */
				$meta[] = sprintf( __( 'Episode %s', 'jws_streamvid' ), $number );
			} else {
				$season = get_post_meta( $episode, 'season_number', true );
				$number = get_post_meta( $episode, 'episode_number', true );
				if ( '' !== $season && '' !== $number ) {
					/* translators: 1: season number, 2: episode number */
					$meta[] = sprintf( __( 'S%1$s E%2$s', 'jws_streamvid' ), $season, $number );
				}
			}
		}

		$progress = 0;

		if ( $history ) {
			$done     = isset( $history['time'] ) ? (int) $history['time'] : 0;
			$length   = isset( $history['endtime'] ) ? (int) $history['endtime'] : 0;
			$progress = $length > 0 ? min( 100, (int) round( 100 * $done / $length ) ) : 0;

			if ( $length > 0 && $progress >= 95 ) {
				$meta[] = __( 'Finished', 'jws_streamvid' );
			} elseif ( $length > $done ) {
				$meta[] = self::time_left( $length - $done );
			}
		} else {
			$year = get_post_meta( $id, 'videos_years', true );
			if ( $year ) {
				$meta[] = $year;
			}
		}

		$image = get_post_meta( $id, 'featured_image_two', true );
		$image = $image ? $image : get_post_thumbnail_id( $id );

		return array(
			'url'      => $url,
			'title'    => get_the_title( $id ),
			'type'     => jws_profile_tab_label( $type ),
			'meta'     => implode( ' · ', array_filter( $meta, 'strlen' ) ),
			'progress' => $history ? $progress : null,
			'image'    => $image ? (string) wp_get_attachment_image_url( $image, 'medium_large' ) : '',
		);
	}

	/**
	 * History grouped by when it was watched.
	 *
	 * @return array[] Each array( 'title' => string, 'items' => array[] ).
	 */
	public static function by_day( array $items ) {

		$now    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- watched_at is stored in site time.
		$today  = strtotime( 'today', $now );
		$labels = array(
			'today'     => __( 'Today', 'jws_streamvid' ),
			'yesterday' => __( 'Yesterday', 'jws_streamvid' ),
			'week'      => __( 'This week', 'jws_streamvid' ),
			'earlier'   => __( 'Earlier', 'jws_streamvid' ),
		);
		$groups = array();

		foreach ( $items as $item ) {
			$when = $item['when'] ? strtotime( $item['when'] ) : 0;

			if ( $when >= $today ) {
				$key = 'today';
			} elseif ( $when >= $today - DAY_IN_SECONDS ) {
				$key = 'yesterday';
			} elseif ( $when >= $today - 6 * DAY_IN_SECONDS ) {
				$key = 'week';
			} else {
				$key = 'earlier';
			}

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array( 'title' => $labels[ $key ], 'items' => array() );
			}
			$groups[ $key ]['items'][] = $item;
		}

		return array_values( $groups );
	}

	/**
	 * Removes titles from the profile in use: one (remove_one), the ticked
	 * ones (remove_selected) or, on History, all (clear_all). Runs before the
	 * profile page renders, only for the page's owner.
	 */
	public static function handle_post() {

		if ( ! is_author() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['_jws_library_nonce'] ) ) {
			return;
		}

		$back = remove_query_arg( array( 'jws_lib', 'n' ), self::current_url() );

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_jws_library_nonce'] ) ), self::NONCE ) ) {
			wp_safe_redirect( add_query_arg( 'jws_lib', 'expired', $back ) );
			exit;
		}

		$user_id = get_current_user_id();
		$tab     = isset( $_POST['library_tab'] ) ? sanitize_key( wp_unslash( $_POST['library_tab'] ) ) : '';

		if ( ! $user_id || (int) get_queried_object_id() !== $user_id || ! in_array( $tab, self::TABS, true ) ) {
			return;
		}

		if ( 'history' === $tab && ! empty( $_POST['clear_all'] ) ) {
			Jws_History::clear( $user_id );
			wp_safe_redirect( add_query_arg( 'jws_lib', 'cleared', $back ) );
			exit;
		}

		if ( ! empty( $_POST['remove_one'] ) ) {
			$ids = array( absint( wp_unslash( $_POST['remove_one'] ) ) );
		} elseif ( ! empty( $_POST['remove_selected'] ) && ! empty( $_POST['ids'] ) ) {
			$ids = array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) );
		} else {
			$ids = array();
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );

		if ( 'history' === $tab ) {
			Jws_History::delete_items( $user_id, $ids );
		} elseif ( 'watchlist' === $tab ) {
			foreach ( $ids as $id ) {
				Jws_Watchlist::remove( $user_id, $id );
			}
		} else {
			foreach ( $ids as $id ) {
				Jws_Favorites::remove( $user_id, $id, (string) get_post_type( $id ) );
			}
		}

		wp_safe_redirect( add_query_arg( array( 'jws_lib' => 'removed', 'n' => count( $ids ) ), $back ) );
		exit;
	}

	/**
	 * Devices tab: signs out one device (sign_out = device id) or every
	 * device but this one (sign_out_others). Uses the theme's
	 * jws_remove_user_devices(), which also signs the app out by push.
	 */
	public static function handle_devices_post() {

		if ( ! is_author() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['_jws_devices_nonce'] ) ) {
			return;
		}

		$back = remove_query_arg( array( 'jws_lib', 'n' ), self::current_url() );

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_jws_devices_nonce'] ) ), 'jws_devices' ) ) {
			wp_safe_redirect( add_query_arg( 'jws_lib', 'expired', $back ) );
			exit;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id || (int) get_queried_object_id() !== $user_id || Jws_Kids_Mode::is_active() || ! function_exists( 'jws_remove_user_devices' ) ) {
			return;
		}

		$devices = get_user_meta( $user_id, 'jws_user_devices', true );
		$devices = is_array( $devices ) ? $devices : array();
		// Device ids are compared exactly (the app's may be mixed-case), so no sanitize_key() here.
		$current = isset( $_COOKIE['jws_device_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['jws_device_id'] ) ) : '';
		$ids     = array();

		if ( ! empty( $_POST['sign_out_others'] ) ) {
			foreach ( $devices as $device ) {
				if ( isset( $device['device_id'] ) && (string) $device['device_id'] !== $current ) {
					$ids[] = (string) $device['device_id'];
				}
			}
		} elseif ( ! empty( $_POST['sign_out'] ) ) {
			$ids[] = sanitize_text_field( wp_unslash( $_POST['sign_out'] ) );
		}

		$removed = $ids ? jws_remove_user_devices( $user_id, $ids ) : 0;

		wp_safe_redirect( add_query_arg( array( 'jws_lib' => 'signed_out', 'n' => $removed ), $back ) );
		exit;
	}

	/** @return string Message for ?jws_lib=, or ''. */
	public static function notice() {

		$code = isset( $_GET['jws_lib'] ) ? sanitize_key( wp_unslash( $_GET['jws_lib'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n    = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'removed' === $code ) {
			/* translators: %d: number of titles removed */
			return sprintf( _n( '%d title removed.', '%d titles removed.', $n, 'jws_streamvid' ), $n );
		}
		if ( 'cleared' === $code ) {
			return __( 'History cleared.', 'jws_streamvid' );
		}
		if ( 'signed_out' === $code ) {
			/* translators: %d: number of devices signed out */
			return sprintf( _n( '%d device signed out.', '%d devices signed out.', $n, 'jws_streamvid' ), $n );
		}
		if ( 'expired' === $code ) {
			return __( 'This form has expired. Reload the page and try again.', 'jws_streamvid' );
		}

		return '';
	}

	/** @return string 'error' for an expired form, 'success' for the other ?jws_lib= notices. */
	public static function notice_type() {

		$code = isset( $_GET['jws_lib'] ) ? sanitize_key( wp_unslash( $_GET['jws_lib'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 'expired' === $code ? 'error' : 'success';
	}

	/** Prints the V3 tab when the site uses Version 3; returns whether it did. */
	public static function maybe_render( $tab ) {

		if ( ! self::is_v3() ) {
			return false;
		}

		jws_streamvid_load_template( 'user/profile/v3/library.php', false, array( 'tab' => $tab ) );

		return true;
	}

	private static function time_left( $seconds ) {

		$minutes = max( 1, (int) round( $seconds / MINUTE_IN_SECONDS ) );

		if ( $minutes >= 60 ) {
			/* translators: 1: hours, 2: minutes */
			return sprintf( __( '%1$dh %2$dm left', 'jws_streamvid' ), intdiv( $minutes, 60 ), $minutes % 60 );
		}

		/* translators: %d: minutes */
		return sprintf( __( '%dm left', 'jws_streamvid' ), $minutes );
	}

	private static function current_url() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return ( is_ssl() ? 'https://' : 'http://' ) . $host . $path;
	}
}

add_action( 'template_redirect', array( 'Jws_Profile_Library', 'handle_post' ), 15 );
add_action( 'template_redirect', array( 'Jws_Profile_Library', 'handle_devices_post' ), 15 );
