<?php
namespace DutyPlanner;

/**
 * Pure recurrence logic (no WordPress dependencies, unit-testable).
 *
 * A spot needs: frequency ('daily'|'weekly'), weekdays (int[] ISO 1-7), interval_n,
 * start_date (Y-m-d), end_date (Y-m-d|null).
 */
class Recurrence {

	/**
	 * Dates (Y-m-d) on which the spot occurs within [$from, $to], both inclusive.
	 *
	 * @return string[]
	 */
	public static function dates( $spot, string $from, string $to ): array {
		$utc = new \DateTimeZone( 'UTC' );
		$lo  = max( $from, $spot->start_date );
		$hi  = $spot->end_date ? min( $to, $spot->end_date ) : $to;
		if ( $lo > $hi ) {
			return array();
		}

		$interval = max( 1, (int) $spot->interval_n );
		$start    = new \DateTimeImmutable( $spot->start_date, $utc );
		$cursor   = new \DateTimeImmutable( $lo, $utc );
		$end      = new \DateTimeImmutable( $hi, $utc );
		$dates    = array();

		if ( 'daily' === $spot->frequency ) {
			$offset = (int) $start->diff( $cursor )->days % $interval;
			if ( $offset ) {
				$cursor = $cursor->modify( '+' . ( $interval - $offset ) . ' days' );
			}
			while ( $cursor <= $end ) {
				$dates[] = $cursor->format( 'Y-m-d' );
				$cursor  = $cursor->modify( "+{$interval} days" );
			}
			return $dates;
		}

		$weekdays = array_map( 'intval', (array) $spot->weekdays );
		if ( ! $weekdays ) {
			return array();
		}
		// Week numbering is anchored at the Monday of the start date's week.
		$anchor = $start->modify( '-' . ( (int) $start->format( 'N' ) - 1 ) . ' days' );

		while ( $cursor <= $end ) {
			$n = (int) $cursor->format( 'N' );
			if ( in_array( $n, $weekdays, true ) ) {
				$monday = $cursor->modify( '-' . ( $n - 1 ) . ' days' );
				$weeks  = intdiv( (int) $anchor->diff( $monday )->days, 7 );
				if ( 0 === $weeks % $interval ) {
					$dates[] = $cursor->format( 'Y-m-d' );
				}
			}
			$cursor = $cursor->modify( '+1 day' );
		}
		return $dates;
	}

	public static function occurs_on( $spot, string $date ): bool {
		return self::dates( $spot, $date, $date ) === array( $date );
	}

	/** 'needs' (below minimum), 'open' (places free) or 'full'. */
	public static function status( int $count, int $min, int $max ): string {
		if ( $count >= $max ) {
			return 'full';
		}
		if ( $count < $min ) {
			return 'needs';
		}
		return 'open';
	}
}
