<?php
/**
 * Plain-PHP tests for the recurrence logic (no WordPress needed).
 *
 * Run: php tests/recurrence-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/../duty-planner/includes/class-holidays.php';
require __DIR__ . '/../duty-planner/includes/class-recurrence.php';
require __DIR__ . '/../duty-planner/includes/class-allowlist.php';

use DutyPlanner\Recurrence;

$failures = 0;
$count    = 0;

function check( string $name, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$name}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$name}\n       expected: " . json_encode( $expected ) . "\n       actual:   " . json_encode( $actual ) . "\n";
}

function spot( array $props ): object {
	return (object) array_merge(
		array(
			'frequency'  => 'weekly',
			'weekdays'   => array(),
			'interval_n' => 1,
			'start_date' => '2026-01-01',
			'end_date'   => null,
		),
		$props
	);
}

echo "Recurrence\n";

// Tue + Thu weekly.
$s = spot( array( 'weekdays' => array( 2, 4 ), 'start_date' => '2026-09-01' ) );
check( 'weekly Tue+Thu', array( '2026-09-01', '2026-09-03', '2026-09-08', '2026-09-10' ), Recurrence::dates( $s, '2026-09-01', '2026-09-11' ) );
check( 'nothing before start', array(), Recurrence::dates( $s, '2026-08-01', '2026-08-31' ) );

// Every other week, anchored at the start date's week (start on a Wednesday).
$s = spot( array( 'weekdays' => array( 1, 5 ), 'interval_n' => 2, 'start_date' => '2026-09-02' ) );
check(
	'bi-weekly Mon+Fri, start mid-week',
	array( '2026-09-04', '2026-09-14', '2026-09-18', '2026-09-28', '2026-10-02' ),
	Recurrence::dates( $s, '2026-08-25', '2026-10-04' )
);
check( 'bi-weekly queried mid-range', array( '2026-09-28', '2026-10-02' ), Recurrence::dates( $s, '2026-09-21', '2026-10-04' ) );

// Daily with interval.
$s = spot( array( 'frequency' => 'daily', 'interval_n' => 3, 'start_date' => '2026-09-01' ) );
check( 'every 3 days', array( '2026-09-01', '2026-09-04', '2026-09-07', '2026-09-10' ), Recurrence::dates( $s, '2026-09-01', '2026-09-10' ) );
check( 'every 3 days, query offset', array( '2026-09-04', '2026-09-07' ), Recurrence::dates( $s, '2026-09-02', '2026-09-08' ) );

$s = spot( array( 'frequency' => 'daily', 'start_date' => '2026-09-28' ) );
check( 'daily', array( '2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01' ), Recurrence::dates( $s, '2026-09-20', '2026-10-01' ) );

// End date.
$s = spot( array( 'frequency' => 'daily', 'start_date' => '2026-09-01', 'end_date' => '2026-09-03' ) );
check( 'respects end date', array( '2026-09-01', '2026-09-02', '2026-09-03' ), Recurrence::dates( $s, '2026-08-30', '2026-09-30' ) );

// DST switch (Europe: 2026-10-25) and month/year boundaries must not shift dates.
$s = spot( array( 'frequency' => 'daily', 'interval_n' => 2, 'start_date' => '2026-10-23' ) );
check( 'across DST switch', array( '2026-10-23', '2026-10-25', '2026-10-27' ), Recurrence::dates( $s, '2026-10-22', '2026-10-28' ) );
$s = spot( array( 'weekdays' => array( 4 ), 'interval_n' => 2, 'start_date' => '2026-12-24' ) );
check( 'across year boundary', array( '2026-12-24', '2027-01-07', '2027-01-21' ), Recurrence::dates( $s, '2026-12-01', '2027-01-31' ) );

// Weekly without weekdays yields nothing.
check( 'weekly without weekdays', array(), Recurrence::dates( spot( array() ), '2026-01-01', '2026-02-01' ) );

// occurs_on.
$s = spot( array( 'weekdays' => array( 2 ), 'start_date' => '2026-09-01' ) );
check( 'occurs_on true', true, Recurrence::occurs_on( $s, '2026-09-08' ) );
check( 'occurs_on false', false, Recurrence::occurs_on( $s, '2026-09-09' ) );

// Status.
check( 'status needs', 'needs', Recurrence::status( 1, 2, 4 ) );
check( 'status open', 'open', Recurrence::status( 2, 2, 4 ) );
check( 'status full', 'full', Recurrence::status( 4, 2, 4 ) );
check( 'status min 0', 'open', Recurrence::status( 0, 0, 1 ) );

echo "\nAustrian public holidays\n";
use DutyPlanner\Holidays;
check(
	'2026 holidays',
	array( '2026-01-01', '2026-01-06', '2026-04-05', '2026-04-06', '2026-05-01', '2026-05-14', '2026-05-24', '2026-05-25', '2026-06-04', '2026-08-15', '2026-10-26', '2026-11-01', '2026-12-08', '2026-12-25', '2026-12-26' ),
	( static function () { $d = array_keys( Holidays::for_year( 2026 ) ); sort( $d ); return $d; } )()
);
check( 'Easter Monday 2027', true, Holidays::is_holiday( '2027-03-29' ) );
check( 'Corpus Christi 2025', true, Holidays::is_holiday( '2025-06-19' ) );
check( 'Good Friday is no holiday', false, Holidays::is_holiday( '2026-04-03' ) );
check( 'Christmas Eve is no holiday', false, Holidays::is_holiday( '2026-12-24' ) );

$s = spot( array( 'frequency' => 'daily', 'start_date' => '2026-10-24', 'skip_holidays' => true ) );
check( 'daily skips National Day', array( '2026-10-24', '2026-10-25', '2026-10-27' ), Recurrence::dates( $s, '2026-10-24', '2026-10-27' ) );
$s = spot( array( 'weekdays' => array( 1 ), 'start_date' => '2026-03-30', 'skip_holidays' => true ) );
check( 'weekly Mon skips Easter Monday', array( '2026-03-30', '2026-04-13' ), Recurrence::dates( $s, '2026-03-30', '2026-04-13' ) );
check( 'occurs_on holiday false', false, Recurrence::occurs_on( $s, '2026-04-06' ) );
check( 'booked holiday is kept', array( '2026-03-30', '2026-04-06', '2026-04-13' ), Recurrence::dates( $s, '2026-03-30', '2026-04-13', array( '2026-04-06' => true ) ) );
check( 'occurs_on booked holiday true', true, Recurrence::occurs_on( $s, '2026-04-06', true ) );
$s = spot( array( 'weekdays' => array( 1 ), 'start_date' => '2026-03-30' ) );
check( 'without flag holiday stays', array( '2026-03-30', '2026-04-06', '2026-04-13' ), Recurrence::dates( $s, '2026-03-30', '2026-04-13' ) );

echo "\nAllowlist parsing\n";
check( 'parse', array( 'a@x.org', '@y.org', 'b@z.org' ), DutyPlanner\Allowlist::parse( " A@x.org\n@Y.org, b@z.org ;\n\n" ) );

echo "\n{$count} checks, {$failures} failed\n";
exit( $failures ? 1 : 0 );
