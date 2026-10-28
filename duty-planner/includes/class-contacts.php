<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Contact lists: everyone on duty gets the names, emails and phone numbers of the others,
 * so they can coordinate. Spots with the same contact group share one list per day.
 *
 * A list goes out once a spot's trigger fires (N days before, and/or when the minimum is
 * reached) and at least two people are on it. After that, everyone gets an updated list
 * whenever the group changes.
 */
class Contacts {

	/** How far ahead registrations are checked (the "minimum reached" trigger has no lead time). */
	const LOOKAHEAD_DAYS = 366;

	public static function enabled( $spot ): bool {
		return null !== $spot->contacts_days || $spot->contacts_on_min;
	}

	/** Spots with the same group (case-insensitive) on the same date share one list. */
	public static function unit_key( $spot, string $date ): string {
		$group = mb_strtolower( trim( $spot->contact_group ) );
		return ( '' === $group ? 's:' . $spot->id : 'g:' . $group ) . '|' . $date;
	}

	/** @return int Number of contact lists sent. */
	public static function maybe_send( ?\DateTimeImmutable $now = null ): int {
		$now   = $now ?: Time::now();
		$today = $now->format( 'Y-m-d' );
		$spots = array_filter( Repository::get_spots( true ), array( self::class, 'enabled' ) );
		Repository::purge_contacts_sent( Time::add_days( $today, -7 ) );
		if ( ! $spots ) {
			return 0;
		}

		// Only dates with registrations can need a list.
		$dates = array();
		foreach ( Repository::registrations_in_range( $today, Time::add_days( $today, self::LOOKAHEAD_DAYS ), array_keys( $spots ) ) as $reg ) {
			$dates[ $reg->occurrence_date ] = true;
		}

		$sent_before = Repository::contacts_sent( $today );
		$sent        = 0;
		foreach ( array_keys( $dates ) as $date ) {
			$units = array();
			foreach ( Occurrences::between( $date, $date, array_keys( $spots ), true ) as $o ) {
				if ( ! $o['skipped'] && ! $o['past'] ) {
					$units[ self::unit_key( $spots[ $o['spot_id'] ], $date ) ][] = $o;
				}
			}
			foreach ( $units as $key => $items ) {
				$previous = $sent_before[ $key ] ?? null;
				if ( ! $previous && ! self::triggered( $items, $spots, $today ) ) {
					continue;
				}
				$members = self::members( $items );
				$hash    = md5( wp_json_encode( $members ) );
				// A first list with only yourself on it would be pointless; updates still go out.
				if ( count( $members ) < ( $previous ? 1 : 2 ) || ( $previous && $previous->hash === $hash ) ) {
					continue;
				}
				// Record first so overlapping cron runs don't double-send.
				Repository::save_contacts_sent( $key, $date, self::names( $members ), $hash );
				if ( self::send( $date, $items, $members, $previous ? $previous->members : null ) ) {
					$sent++;
				}
			}
		}
		return $sent;
	}

	private static function triggered( array $items, array $spots, string $today ): bool {
		foreach ( $items as $o ) {
			$spot = $spots[ $o['spot_id'] ];
			if ( null !== $spot->contacts_days && $today >= Time::add_days( $o['date'], -$spot->contacts_days ) ) {
				return true;
			}
			if ( $spot->contacts_on_min && $o['count'] >= max( 1, $o['min'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** @return array<int,array> Registration ID => person (name, email, phone), in list order. */
	private static function members( array $items ): array {
		$members = array();
		foreach ( $items as $o ) {
			foreach ( $o['people'] as $p ) {
				$members[ $p['id'] ] = array( $p['name'], $p['email'], $p['phone'] );
			}
		}
		return $members;
	}

	/** @return array<int,string> Registration ID => display name. */
	private static function names( array $members ): array {
		return array_map(
			static function ( $m ) {
				return $m[0];
			},
			$members
		);
	}

	/**
	 * @param array<int,string>|null $previous Members of the last list sent (null = first list).
	 */
	private static function send( string $date, array $items, array $members, ?array $previous ): bool {
		$blocks = array();
		foreach ( $items as $o ) {
			if ( ! $o['people'] ) {
				continue;
			}
			$lines = array( $o['title'] . ' – ' . $o['time_label'] . ( $o['location'] ? ', ' . $o['location'] : '' ) );
			foreach ( $o['people'] as $p ) {
				$lines[] = '• ' . implode( ' · ', array_filter( array( $p['name'], $p['email'], $p['phone'] ) ) );
			}
			$blocks[] = implode( "\n", $lines );
		}

		$update = '';
		if ( null !== $previous ) {
			$names  = self::names( $members );
			$joined = array_diff_key( $names, $previous );
			$left   = array_diff_key( $previous, $names );
			$parts  = array();
			if ( $joined ) {
				/* translators: %s: names */
				$parts[] = sprintf( __( 'New: %s.', 'duty-planner' ), Scheduler::join_names( array_values( $joined ) ) );
			}
			if ( $left ) {
				/* translators: %s: names */
				$parts[] = sprintf( __( 'No longer on duty: %s.', 'duty-planner' ), Scheduler::join_names( array_values( $left ) ) );
			}
			$update = __( 'The list has changed since our last email.', 'duty-planner' ) . ( $parts ? ' ' . implode( ' ', $parts ) : '' );
		}

		$emails = array_values( array_unique( array_column( $members, 1 ) ) );
		$titles = array_values( array_unique( array_column( array_filter( $items, static function ( $o ) {
			return (bool) $o['people'];
		} ), 'title' ) ) );
		return Mailer::send_template(
			'contacts',
			$emails,
			array(
				'spot'     => Scheduler::join_names( $titles ),
				'date'     => Time::format_long_date( $date ),
				'location' => implode( ', ', array_unique( array_filter( array_column( $items, 'location' ) ) ) ) ?: '–',
				'contacts' => implode( "\n\n", $blocks ),
				'update'   => $update,
			),
			// Replies reach everyone on the list, not the plugin's sender address.
			array( 'Reply-To: ' . implode( ', ', $emails ) )
		);
	}
}
