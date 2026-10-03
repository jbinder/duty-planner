<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Expands spots into concrete occurrences and joins booking data.
 */
class Occurrences {

	/**
	 * @param int[] $spot_ids       Restrict to these spots (empty = all active).
	 * @param bool  $include_emails Include registration IDs and emails (admin only!).
	 */
	public static function between( string $from, string $to, array $spot_ids = array(), bool $include_emails = false ): array {
		$spots = Repository::get_spots( true, $spot_ids );
		if ( ! $spots ) {
			return array();
		}

		$skips = Repository::skips( array_keys( $spots ), $from, $to );
		$regs  = array();
		foreach ( Repository::registrations_in_range( $from, $to, array_keys( $spots ) ) as $reg ) {
			$regs[ $reg->spot_id . '|' . $reg->occurrence_date ][] = $reg;
		}

		$now = Time::now();
		$out = array();
		foreach ( $spots as $spot ) {
			$restricted = Allowlist::is_restricted( $spot );
			foreach ( Recurrence::dates( $spot, $from, $to ) as $date ) {
				$list   = $regs[ $spot->id . '|' . $date ] ?? array();
				$people = array();
				foreach ( $list as $reg ) {
					$person = array( 'name' => $reg->display_name );
					if ( $include_emails ) {
						$person['id']    = (int) $reg->id;
						$person['email'] = $reg->email;
					}
					$people[] = $person;
				}
				$start = Time::start( $spot, $date );
				$end   = Time::end( $spot, $date );
				$count = count( $list );

				$out[] = array(
					'spot_id'     => $spot->id,
					'date'        => $date,
					'title'       => $spot->title,
					'description' => $spot->description,
					'location'    => $spot->location,
					'all_day'     => $spot->all_day,
					'start'       => $start->format( DATE_ATOM ),
					'end'         => $end->format( DATE_ATOM ),
					'date_label'  => Time::format_long_date( $date ),
					'start_label' => Time::format_start( $spot, $date ),
					'time_label'  => Time::format_time_range( $spot, $date ),
					'min'         => $spot->min_people,
					'max'         => $spot->max_people,
					'count'       => $count,
					'people'      => $people,
					'status'      => Recurrence::status( $count, $spot->min_people, $spot->max_people ),
					'skipped'     => isset( $skips[ $spot->id ][ $date ] ),
					'past'        => $end <= $now,
					'restricted'  => $restricted,
				);
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return array( $a['date'], ! $a['all_day'], $a['start'], $a['title'] )
					<=> array( $b['date'], ! $b['all_day'], $b['start'], $b['title'] );
			}
		);
		return $out;
	}

	public static function find( int $spot_id, string $date, bool $include_emails = false ): ?array {
		$items = self::between( $date, $date, array( $spot_id ), $include_emails );
		return $items[0] ?? null;
	}
}
