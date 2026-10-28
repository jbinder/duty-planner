<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * WP-Cron: registrant reminders, the weekly admin alert, the weekly mailing list mail and contact lists.
 */
class Scheduler {

	const HOOK            = 'dutyplan_tick';
	const RECURRENCE      = 'dutyplan_15min';
	const LAST_ALERT_OPT  = 'dutyplan_last_alert_week';
	const LAST_LIST_OPT   = 'dutyplan_last_list_week';

	public static function init() {
		add_filter( 'cron_schedules', array( self::class, 'schedules' ) );
		add_action( self::HOOK, array( self::class, 'tick' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			self::schedule();
		}
	}

	public static function schedules( $schedules ) {
		$schedules[ self::RECURRENCE ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'Every 15 minutes (Duty Planner)',
		);
		return $schedules;
	}

	public static function schedule() {
		// Also needed during activation, before init() ran.
		add_filter( 'cron_schedules', array( self::class, 'schedules' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::RECURRENCE, self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function tick() {
		self::send_reminders();
		self::maybe_send_admin_alert();
		self::maybe_send_list_mail();
		Contacts::maybe_send();
	}

	/** @return int Number of reminders sent. */
	public static function send_reminders( ?\DateTimeImmutable $now = null ): int {
		$now   = $now ?: Time::now();
		$spots = Repository::get_spots( true );
		$sent  = 0;

		foreach ( Repository::pending_reminders( $now->format( 'Y-m-d' ) ) as $reg ) {
			$spot = $spots[ (int) $reg->spot_id ] ?? null;
			if ( ! $spot || $spot->reminder_offset_hours <= 0 ) {
				continue;
			}
			$due = Time::reminder_anchor( $spot, $reg->occurrence_date )
				->modify( '-' . $spot->reminder_offset_hours . ' hours' );
			if ( $now < $due ) {
				continue;
			}

			// People who signed up inside the reminder window already got the confirmation mail.
			$created = new \DateTimeImmutable( $reg->created_at, new \DateTimeZone( 'UTC' ) );
			if ( $created < $due && $now < Time::end( $spot, $reg->occurrence_date ) ) {
				if ( ! Mailer::send_registration_mail( 'reminder', $spot, $reg ) ) {
					continue; // Retry on the next tick.
				}
				$sent++;
			}
			Repository::mark_reminded( (int) $reg->id );
		}
		return $sent;
	}

	/** @return array{moment:\DateTimeImmutable,from:string,to:string,week:string} */
	public static function alert_window( \DateTimeImmutable $now ): array {
		return self::window( $now, 'alert' );
	}

	/** @return array{moment:\DateTimeImmutable,from:string,to:string,week:string} */
	public static function list_window( \DateTimeImmutable $now ): array {
		return self::window( $now, 'list' );
	}

	/**
	 * Send moment and covered period for the week containing $now.
	 *
	 * @param string $prefix Settings prefix: 'alert' or 'list'.
	 * @return array{moment:\DateTimeImmutable,from:string,to:string,week:string}
	 */
	private static function window( \DateTimeImmutable $now, string $prefix ): array {
		$monday = $now->setTime( 0, 0 )->modify( '-' . ( (int) $now->format( 'N' ) - 1 ) . ' days' );
		list( $h, $m ) = array_map( 'intval', explode( ':', Settings::get( "{$prefix}_time" ) ) );
		$moment        = $monday->modify( '+' . ( (int) Settings::get( "{$prefix}_weekday" ) - 1 ) . ' days' )->setTime( $h, $m );

		// The admin alert always covers one week, the mailing list mail a configurable number.
		$weeks = 'list' === $prefix ? max( 1, (int) Settings::get( 'list_weeks' ) ) : 1;
		if ( 'current' === Settings::get( "{$prefix}_target" ) ) {
			$from = $now->format( 'Y-m-d' );
			$to   = $monday->modify( '+' . ( 7 * $weeks - 1 ) . ' days' )->format( 'Y-m-d' );
		} else {
			$from = $monday->modify( '+7 days' )->format( 'Y-m-d' );
			$to   = $monday->modify( '+' . ( 7 * $weeks + 6 ) . ' days' )->format( 'Y-m-d' );
		}
		return array(
			'moment' => $moment,
			'from'   => $from,
			'to'     => $to,
			'week'   => $now->format( 'o-\WW' ),
		);
	}

	public static function maybe_send_admin_alert( ?\DateTimeImmutable $now = null ): void {
		$now    = $now ?: Time::now();
		$window = self::alert_window( $now );
		if ( $now < $window['moment'] || get_option( self::LAST_ALERT_OPT ) === $window['week'] ) {
			return;
		}
		// Record first so overlapping cron runs don't double-send.
		update_option( self::LAST_ALERT_OPT, $window['week'], false );
		self::send_admin_alert( $window['from'], $window['to'] );
	}

	/** Occurrences in the period that have not reached their minimum. */
	public static function understaffed( string $from, string $to ): array {
		return array_values(
			array_filter(
				Occurrences::between( $from, $to ),
				static function ( $o ) {
					return ! $o['skipped'] && ! $o['past'] && $o['count'] < $o['min'];
				}
			)
		);
	}

	/**
	 * @return int Number of understaffed duties reported (0 = nothing sent).
	 */
	public static function send_admin_alert( string $from, string $to ): int {
		$recipients = Settings::alert_recipients();
		$items      = self::understaffed( $from, $to );
		if ( ! $recipients || ! $items ) {
			return 0;
		}

		$lines = array();
		foreach ( $items as $o ) {
			$lines[] = sprintf(
				/* translators: 1: date, 2: time, 3: duty title, 4: people signed up, 5: minimum, 6: maximum */
				__( '- %1$s, %2$s: %3$s – %4$d signed up, at least %5$d needed (max. %6$d)', 'duty-planner' ),
				$o['date_label'],
				$o['time_label'],
				$o['title'],
				$o['count'],
				$o['min'],
				$o['max']
			);
		}

		Mailer::send_template(
			'alert',
			$recipients,
			array(
				'week'      => Time::format_date( $from ) . ' – ' . Time::format_date( $to ),
				'count'     => count( $items ),
				'list'      => implode( "\n", $lines ),
				'admin_url' => add_query_arg( array( 'page' => 'dutyplan', 'from' => $from, 'days' => 7 ), admin_url( 'admin.php' ) ),
			)
		);
		return count( $items );
	}

	/** "Anna, Ben and Cem" – in the plugin language (wp_sprintf_l() would follow the site language). */
	public static function join_names( array $names ): string {
		$last = array_pop( $names );
		/* translators: 1: comma-separated names, 2: last name */
		return $names ? sprintf( __( '%1$s and %2$s', 'duty-planner' ), implode( ', ', $names ), $last ) : (string) $last;
	}

	/**
	 * Short names for the mailing list mail, so last names don't go out to everyone:
	 * "Laura Müller" and "Müller, Laura" become "Laura". If two different people in the
	 * same mail share a first name, the initial of the last name is added ("Laura M.").
	 *
	 * @param string[] $full Full display names.
	 * @return array<string,string> Full name => short name.
	 */
	private static function short_names( array $full ): array {
		$split = array();
		foreach ( array_unique( $full ) as $name ) {
			$clean = trim( preg_replace( '/\s+/u', ' ', $name ) );
			if ( false !== strpos( $clean, ',' ) ) {
				list( $last, $first ) = array_map( 'trim', explode( ',', $clean, 2 ) );
				$clean                = trim( $first . ' ' . $last );
			}
			$parts = explode( ' ', $clean );
			// Skip titles such as "Dr." in front of the first name.
			while ( count( $parts ) > 1 && '.' === substr( $parts[0], -1 ) ) {
				array_shift( $parts );
			}
			$split[ $name ] = array( $parts[0], count( $parts ) > 1 ? end( $parts ) : '' );
		}

		$per_first = array_count_values(
			array_map(
				static function ( $parts ) {
					return mb_strtolower( $parts[0] );
				},
				$split
			)
		);
		$short = array();
		foreach ( $split as $name => list( $first, $last ) ) {
			$short[ $name ] = $per_first[ mb_strtolower( $first ) ] > 1 && '' !== $last
				? $first . ' ' . mb_substr( $last, 0, 1 ) . '.'
				: $first;
		}
		return $short;
	}

	/** "Laura: bring cake · Ben: plays guitar" – or just the note, if only one person is named. */
	private static function notes( array $people, array $short ): string {
		$notes = array();
		foreach ( $people as $p ) {
			if ( '' !== $p['note'] ) {
				$notes[] = 1 === count( $people ) ? $p['note'] : $short[ $p['name'] ] . ': ' . $p['note'];
			}
		}
		return implode( ' · ', $notes );
	}

	public static function maybe_send_list_mail( ?\DateTimeImmutable $now = null ): void {
		$now    = $now ?: Time::now();
		$window = self::list_window( $now );
		if ( ! Settings::list_recipients() || $now < $window['moment'] || get_option( self::LAST_LIST_OPT ) === $window['week'] ) {
			return;
		}
		update_option( self::LAST_LIST_OPT, $window['week'], false );
		self::send_list_mail( $window['from'], $window['to'] );
	}

	/**
	 * Weekly overview for the mailing list: asks everyone to jump in for duties below their
	 * minimum, and thanks the people who signed up for the others.
	 *
	 * @return array{open:int,covered:int} Number of duties listed (both 0 = nothing sent).
	 */
	public static function send_list_mail( string $from, string $to ): array {
		$result     = array( 'open' => 0, 'covered' => 0 );
		$recipients = Settings::list_recipients();
		$items      = array_filter(
			Occurrences::between( $from, $to ),
			static function ( $o ) {
				return ! $o['skipped'] && ! $o['past'];
			}
		);
		if ( ! $recipients || ! $items ) {
			return $result;
		}

		$all_names = array();
		foreach ( $items as $o ) {
			$all_names = array_merge( $all_names, array_column( $o['people'], 'name' ) );
		}
		$short = self::short_names( $all_names );

		$open    = array();
		$covered = array();
		foreach ( $items as $o ) {
			$when  = $o['date_label'] . ( $o['all_day'] ? '' : ', ' . $o['time_label'] );
			$names = self::join_names(
				array_map(
					static function ( $p ) use ( $short ) {
						return $short[ $p['name'] ];
					},
					$o['people']
				)
			);
			$notes = self::notes( $o['people'], $short );
			/* translators: %s: names of the people signed up */
			$thanks = sprintf( __( 'thank you %s!', 'duty-planner' ), $names ) . ( '' !== $notes ? ' ' . $notes : '' );
			$line   = '• ' . $when . ': ' . $o['title'] . ' – ';
			if ( $o['count'] < $o['min'] ) {
				$missing = $o['min'] - $o['count'];
				$open[]  = $line . ( 1 === $missing
					? __( 'one more person needed', 'duty-planner' )
					/* translators: %d: number of people still needed */
					: sprintf( _n( '%d more person needed', '%d more people needed', $missing, 'duty-planner' ), $missing )
				) . ( $o['count'] ? ' – ' . $thanks : '' );
			} else {
				$covered[] = $line . $thanks;
			}
		}

		$open_block = $open
			? __( 'These duties still need people – please jump in!', 'duty-planner' ) . "\n\n" . implode( "\n", $open )
			: __( 'Every duty is covered – nothing left to do.', 'duty-planner' );
		$covered_block = $covered
			? __( 'Already covered – a big thank you to everyone who signed up:', 'duty-planner' ) . "\n\n" . implode( "\n", $covered )
			: '';
		$status = $open
			/* translators: %d: number of duties that still need people */
			? sprintf( _n( '%d duty still needs you', '%d duties still need you', count( $open ), 'duty-planner' ), count( $open ) )
			: __( 'everything covered – thank you!', 'duty-planner' );

		Mailer::send_template(
			'list',
			$recipients,
			array(
				'week'     => Time::format_date( $from ) . ' – ' . Time::format_date( $to ),
				'weeks'    => max( 1, (int) Settings::get( 'list_weeks' ) ),
				'location' => (string) Settings::get( 'list_location' ) ?: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'status'   => $status,
				'open'     => $open_block,
				'covered'  => $covered_block,
			)
		);
		return array( 'open' => count( $open ), 'covered' => count( $covered ) );
	}
}
