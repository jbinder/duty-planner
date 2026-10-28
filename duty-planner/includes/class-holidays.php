<?php
namespace DutyPlanner;

/**
 * Austrian public holidays (gesetzliche Feiertage), pure logic (no WordPress dependencies).
 */
class Holidays {

	/** @var array<int,array<string,true>> Cache: year => set of Y-m-d. */
	private static $cache = array();

	public static function is_holiday( string $date ): bool {
		$year = (int) substr( $date, 0, 4 );
		return isset( self::for_year( $year )[ $date ] );
	}

	/**
	 * @return array<string,true> Set of Y-m-d dates.
	 */
	public static function for_year( int $year ): array {
		if ( isset( self::$cache[ $year ] ) ) {
			return self::$cache[ $year ];
		}

		$easter = self::easter_sunday( $year );
		$dates  = array(
			"{$year}-01-01", // Neujahr.
			"{$year}-01-06", // Heilige Drei Könige.
			"{$year}-05-01", // Staatsfeiertag.
			"{$year}-08-15", // Mariä Himmelfahrt.
			"{$year}-10-26", // Nationalfeiertag.
			"{$year}-11-01", // Allerheiligen.
			"{$year}-12-08", // Mariä Empfängnis.
			"{$year}-12-25", // Christtag.
			"{$year}-12-26", // Stefanitag.
		);
		// Ostersonntag, Ostermontag, Christi Himmelfahrt, Pfingstsonntag, Pfingstmontag, Fronleichnam.
		foreach ( array( 0, 1, 39, 49, 50, 60 ) as $offset ) {
			$dates[] = $easter->modify( "+{$offset} days" )->format( 'Y-m-d' );
		}

		self::$cache[ $year ] = array_fill_keys( $dates, true );
		return self::$cache[ $year ];
	}

	/** Gregorian Easter Sunday (anonymous Gregorian algorithm). */
	private static function easter_sunday( int $year ): \DateTimeImmutable {
		$a     = $year % 19;
		$b     = intdiv( $year, 100 );
		$c     = $year % 100;
		$d     = intdiv( $b, 4 );
		$e     = $b % 4;
		$f     = intdiv( $b + 8, 25 );
		$g     = intdiv( $b - $f + 1, 3 );
		$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i     = intdiv( $c, 4 );
		$k     = $c % 4;
		$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 );
		$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		return new \DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), new \DateTimeZone( 'UTC' ) );
	}
}
