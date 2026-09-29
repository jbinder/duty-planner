<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * WP-Cron: registrant reminders and the weekly admin alert.
 */
class Scheduler {

	const HOOK            = 'dutyplan_tick';
	const RECURRENCE      = 'dutyplan_15min';
	const LAST_ALERT_OPT  = 'dutyplan_last_alert_week';

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

	/**
	 * Alert moment and checked period for the week containing $now.
	 *
	 * @return array{moment:\DateTimeImmutable,from:string,to:string,week:string}
	 */
	public static function alert_window( \DateTimeImmutable $now ): array {
		$monday = $now->setTime( 0, 0 )->modify( '-' . ( (int) $now->format( 'N' ) - 1 ) . ' days' );
		list( $h, $m ) = array_map( 'intval', explode( ':', Settings::get( 'alert_time' ) ) );
		$moment        = $monday->modify( '+' . ( (int) Settings::get( 'alert_weekday' ) - 1 ) . ' days' )->setTime( $h, $m );

		if ( 'current' === Settings::get( 'alert_target' ) ) {
			$from = $now->format( 'Y-m-d' );
			$to   = $monday->modify( '+6 days' )->format( 'Y-m-d' );
		} else {
			$from = $monday->modify( '+7 days' )->format( 'Y-m-d' );
			$to   = $monday->modify( '+13 days' )->format( 'Y-m-d' );
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
}
