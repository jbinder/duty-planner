<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Date/time helpers, always in the site timezone.
 */
class Time {

	public static function tz(): \DateTimeZone {
		return wp_timezone();
	}

	public static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', self::tz() );
	}

	public static function today(): string {
		return self::now()->format( 'Y-m-d' );
	}

	public static function is_date( $value ): bool {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/** Add days to a Y-m-d date string. */
	public static function add_days( string $date, int $days ): string {
		$d = new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) );
		return $d->modify( ( $days >= 0 ? '+' : '' ) . $days . ' days' )->format( 'Y-m-d' );
	}

	public static function start( $spot, string $date ): \DateTimeImmutable {
		$time = $spot->all_day ? '00:00:00' : $spot->start_time;
		return new \DateTimeImmutable( "{$date} {$time}", self::tz() );
	}

	public static function end( $spot, string $date ): \DateTimeImmutable {
		$start = self::start( $spot, $date );
		return $spot->all_day
			? $start->modify( '+1 day' )
			: $start->modify( '+' . (int) $spot->duration_min . ' minutes' );
	}

	/** Point in time reminders are counted back from (all-day spots use the configured reference time). */
	public static function reminder_anchor( $spot, string $date ): \DateTimeImmutable {
		if ( $spot->all_day ) {
			return new \DateTimeImmutable( $date . ' ' . Settings::get( 'allday_reference_time' ), self::tz() );
		}
		return self::start( $spot, $date );
	}

	public static function format_date( string $date, string $format = '' ): string {
		$ts = ( new \DateTimeImmutable( $date . ' 12:00:00', self::tz() ) )->getTimestamp();
		return I18n::date( $format ?: I18n::date_format(), $ts );
	}

	/** Date with weekday, e.g. "Tuesday, October 6, 2026". */
	public static function format_long_date( string $date ): string {
		return self::format_date( $date, I18n::long_date_format() );
	}

	/** Date and time of a timestamp, e.g. for "next run" displays. */
	public static function format_datetime( int $timestamp, bool $weekday = false ): string {
		$format = ( $weekday ? I18n::long_date_format() : I18n::date_format() ) . ' ' . I18n::time_format();
		return I18n::date( $format, $timestamp );
	}

	public static function format_start( $spot, string $date ): string {
		if ( $spot->all_day ) {
			return __( 'All day', 'duty-planner' );
		}
		return I18n::date( I18n::time_format(), self::start( $spot, $date )->getTimestamp() );
	}

	public static function format_time_range( $spot, string $date ): string {
		if ( $spot->all_day ) {
			return __( 'All day', 'duty-planner' );
		}
		$format = I18n::time_format();
		return I18n::date( $format, self::start( $spot, $date )->getTimestamp() )
			. ' – '
			. I18n::date( $format, self::end( $spot, $date )->getTimestamp() );
	}

	/** @return array<int,string> ISO weekday (1 = Monday) => localized name. */
	public static function weekday_names( bool $short = false ): array {
		$names = array();
		for ( $i = 1; $i <= 7; $i++ ) {
			$names[ $i ] = I18n::weekday( $i, $short );
		}
		return $names;
	}
}
