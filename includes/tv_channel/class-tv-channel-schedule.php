<?php

/**
 * The program guide of a channel.
 *
 * A channel's schedule is a repeater (`tv_schedule`) of programs, each with a
 * start time, an optional end time and either a weekly rule (every day,
 * weekdays, weekend or one weekday) or one date. Dated rows are one-off
 * changes: on their day they push out any weekly program they overlap.
 *
 * Times are wall-clock times in the site's timezone (Settings > General), so
 * "20:00 every day" stays 20:00 across a daylight-saving change. Everything
 * this class returns is in Unix timestamps; the theme formats them.
 *
 * A program without an end runs until the next one starts — on the following
 * day if it is the last of its day — which is how most guides are typed in.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/tv_channel
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Tv_Channel_Schedule {

	const META = 'tv_schedule';

	/** @var array Parsed rows per channel, for this request. */
	private static $rows = array();

	/** @var array Programs per channel and day, for this request. */
	private static $days = array();

	public static function day_choices() {

		return array(
			'daily'    => __( 'Every day', 'jws_streamvid' ),
			'weekdays' => __( 'Weekdays (Mon – Fri)', 'jws_streamvid' ),
			'weekend'  => __( 'Weekend (Sat & Sun)', 'jws_streamvid' ),
			'mon'      => __( 'Monday', 'jws_streamvid' ),
			'tue'      => __( 'Tuesday', 'jws_streamvid' ),
			'wed'      => __( 'Wednesday', 'jws_streamvid' ),
			'thu'      => __( 'Thursday', 'jws_streamvid' ),
			'fri'      => __( 'Friday', 'jws_streamvid' ),
			'sat'      => __( 'Saturday', 'jws_streamvid' ),
			'sun'      => __( 'Sunday', 'jws_streamvid' ),
		);
	}

	public static function flush( $post_id = 0 ) {

		if ( $post_id ) {
			unset( self::$rows[ $post_id ] );
			foreach ( array_keys( self::$days ) as $key ) {
				if ( 0 === strpos( $key, $post_id . ':' ) ) {
					unset( self::$days[ $key ] );
				}
			}
			return;
		}

		self::$rows = array();
		self::$days = array();
	}

	/* ---------------------------------------------------------------------- */
	/* Rows                                                                    */
	/* ---------------------------------------------------------------------- */

	/** "20:00", "8:30", "20.00" → minutes after midnight, or null. */
	public static function parse_time( $value ) {

		if ( ! preg_match( '/^\s*(\d{1,2})[:.h](\d{2})\s*$/', (string) $value, $m ) ) {
			return null;
		}

		$h = (int) $m[1];
		$i = (int) $m[2];

		/* 24:00 is a valid end ("until midnight"). */
		if ( $i > 59 || $h > 24 || ( 24 === $h && $i > 0 ) ) {
			return null;
		}

		return $h * 60 + $i;
	}

	/**
	 * The stored rows, cleaned: rows without a title or a readable start are
	 * skipped rather than guessed at.
	 */
	public static function rows( $id ) {

		$id = (int) $id;

		if ( isset( self::$rows[ $id ] ) ) {
			return self::$rows[ $id ];
		}

		$rows  = array();
		$count = (int) get_post_meta( $id, self::META, true );

		for ( $i = 0; $i < $count; $i++ ) {

			$prefix = self::META . '_' . $i . '_';
			$title  = trim( (string) get_post_meta( $id, $prefix . 'title', true ) );
			$start  = self::parse_time( get_post_meta( $id, $prefix . 'start', true ) );

			if ( '' === $title || null === $start || $start >= 1440 ) {
				continue;
			}

			$date = preg_replace( '/\D/', '', (string) get_post_meta( $id, $prefix . 'date', true ) );

			$rows[] = array(
				'row'   => $i,
				'title' => $title,
				'desc'  => trim( (string) get_post_meta( $id, $prefix . 'desc', true ) ),
				'image' => (int) get_post_meta( $id, $prefix . 'image', true ),
				'days'  => (string) get_post_meta( $id, $prefix . 'days', true ),
				'date'  => 8 === strlen( $date ) ? $date : '',
				'start' => $start,
				'end'   => self::parse_time( get_post_meta( $id, $prefix . 'end', true ) ),
			);
		}

		self::$rows[ $id ] = $rows;

		return $rows;
	}

	private static function on_weekday( $rule, DateTimeImmutable $day ) {

		$dow = (int) $day->format( 'N' );

		switch ( $rule ) {
			case 'weekdays':
				return $dow <= 5;
			case 'weekend':
				return $dow >= 6;
			case 'mon':
			case 'tue':
			case 'wed':
			case 'thu':
			case 'fri':
			case 'sat':
			case 'sun':
				return array_search( $rule, array( 1 => 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ), true ) === $dow;
			default:
				return true;
		}
	}

	/** Local midnight of the day a timestamp or Y-m-d string falls on. */
	public static function midnight( $when = null ) {

		$tz = wp_timezone();

		if ( is_string( $when ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $when ) ) {
			$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $when, $tz );
			return $day ? $day : new DateTimeImmutable( 'today', $tz );
		}

		$ts = null === $when ? time() : (int) $when;

		return ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( $tz )->setTime( 0, 0 );
	}

	private static function at( DateTimeImmutable $midnight, $minutes ) {

		if ( $minutes >= 1440 ) {
			return $midnight->modify( '+1 day' )->setTime( 0, 0 )->getTimestamp() + ( $minutes - 1440 ) * 60;
		}

		return $midnight->setTime( intdiv( $minutes, 60 ), $minutes % 60 )->getTimestamp();
	}

	/**
	 * The rows airing on one day, dated ones having pushed out the weekly
	 * programs they overlap. Start and explicit end are resolved; a missing end
	 * is left null for day() to fill in.
	 */
	private static function raw_day( $id, DateTimeImmutable $midnight ) {

		$ymd    = $midnight->format( 'Ymd' );
		$dated  = array();
		$weekly = array();

		foreach ( self::rows( $id ) as $row ) {

			if ( $row['date'] ) {
				if ( $row['date'] !== $ymd ) {
					continue;
				}
				$bucket = 'dated';
			} elseif ( self::on_weekday( $row['days'], $midnight ) ) {
				$bucket = 'weekly';
			} else {
				continue;
			}

			$start = self::at( $midnight, $row['start'] );
			$end   = null;

			if ( null !== $row['end'] ) {
				$end = self::at( $midnight, $row['end'] );
				if ( $end <= $start ) {
					$end = self::at( $midnight->modify( '+1 day' ), $row['end'] );
				}
			}

			$program = array(
				'title' => $row['title'],
				'desc'  => $row['desc'],
				'image' => $row['image'],
				'start' => $start,
				'end'   => $end,
				'row'   => $row['row'],
			);

			if ( 'dated' === $bucket ) {
				$dated[] = $program;
			} else {
				$weekly[] = $program;
			}
		}

		if ( $dated ) {
			$weekly = array_filter(
				$weekly,
				function ( $w ) use ( $dated ) {
					$w_end = null === $w['end'] ? $w['start'] + 1 : $w['end'];
					foreach ( $dated as $d ) {
						$d_end = null === $d['end'] ? $d['start'] + 1 : $d['end'];
						if ( $w['start'] < $d_end && $w_end > $d['start'] ) {
							return false;
						}
					}
					return true;
				}
			);
		}

		$programs = array_merge( $dated, $weekly );

		usort(
			$programs,
			function ( $a, $b ) {
				return $a['start'] <=> $b['start'];
			}
		);

		return $programs;
	}

	/**
	 * Programs starting on one local day, in order, each with an end.
	 *
	 * @param int                           $id
	 * @param DateTimeImmutable|string|int $day Midnight, a Y-m-d string or any timestamp that day.
	 * @return array[] { title, desc, image (attachment id), start, end, row }
	 */
	public static function day( $id, $day = null ) {

		$midnight = $day instanceof DateTimeImmutable ? $day->setTime( 0, 0 ) : self::midnight( $day );
		$key      = (int) $id . ':' . $midnight->format( 'Ymd' );

		if ( isset( self::$days[ $key ] ) ) {
			return self::$days[ $key ];
		}

		$programs = self::raw_day( $id, $midnight );
		$count    = count( $programs );

		for ( $i = 0; $i < $count; $i++ ) {

			$next = null;

			if ( isset( $programs[ $i + 1 ] ) ) {
				$next = $programs[ $i + 1 ]['start'];
			} else {
				$tomorrow = self::raw_day( $id, $midnight->modify( '+1 day' ) );
				$next     = $tomorrow ? $tomorrow[0]['start'] : $midnight->modify( '+1 day' )->getTimestamp();
			}

			if ( null === $programs[ $i ]['end'] ) {
				$programs[ $i ]['end'] = $next;
			} elseif ( $programs[ $i ]['end'] > $next && isset( $programs[ $i + 1 ] ) ) {
				/* Two programs typed over each other: the later one wins. */
				$programs[ $i ]['end'] = $next;
			}

			/* A program is never longer than a day, whatever was typed. */
			$programs[ $i ]['end'] = min( $programs[ $i ]['end'], $programs[ $i ]['start'] + DAY_IN_SECONDS );
		}

		$programs = array_values(
			array_filter(
				$programs,
				function ( $p ) {
					return $p['end'] > $p['start'];
				}
			)
		);

		self::$days[ $key ] = $programs;

		return $programs;
	}

	/**
	 * Programs overlapping [from, to), whichever day they started on.
	 *
	 * @return array[]
	 */
	public static function between( $id, $from, $to ) {

		$day      = self::midnight( $from )->modify( '-1 day' );
		$programs = array();

		while ( $day->getTimestamp() < $to ) {

			foreach ( self::day( $id, $day ) as $program ) {
				if ( $program['end'] > $from && $program['start'] < $to ) {
					$programs[ $program['start'] . ':' . $program['row'] ] = $program;
				}
			}

			$day = $day->modify( '+1 day' );
		}

		ksort( $programs, SORT_NATURAL );

		return array_values( $programs );
	}

	/**
	 * What is on now, what follows, and the few after that.
	 *
	 * @return array { now: array|null, next: array|null, upcoming: array[] }
	 */
	public static function now_next( $id, $ts = null ) {

		$ts       = null === $ts ? time() : (int) $ts;
		$programs = self::between( $id, $ts, $ts + 18 * HOUR_IN_SECONDS );
		$now      = null;
		$next     = null;
		$upcoming = array();

		foreach ( $programs as $program ) {

			if ( $program['end'] <= $ts ) {
				continue;
			}

			if ( null === $now && $program['start'] <= $ts ) {
				$now = $program;
			} elseif ( null === $next && $program['start'] >= $ts ) {
				$next = $program;
			}

			if ( count( $upcoming ) < 8 ) {
				$upcoming[] = $program;
			}
		}

		return array(
			'now'      => $now,
			'next'     => $next,
			'upcoming' => $upcoming,
		);
	}

	/** How far through a program we are, 0–100. */
	public static function progress( $program, $ts = null ) {

		if ( ! $program ) {
			return 0;
		}

		$ts   = null === $ts ? time() : (int) $ts;
		$span = max( 1, $program['end'] - $program['start'] );

		return (int) max( 0, min( 100, round( ( $ts - $program['start'] ) / $span * 100 ) ) );
	}
}
