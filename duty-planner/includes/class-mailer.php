<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text emails built from editable templates with {placeholders}.
 */
class Mailer {

	/** Template keys that are sent to registrants (as opposed to admins). */
	const REGISTRANT_TEMPLATES = array( 'confirmation', 'reminder', 'cancellation', 'skip' );

	public static function default_templates(): array {
		return array(
			'tpl_confirmation_subject' => __( 'You are signed up: {spot} on {date}', 'duty-planner' ),
			'tpl_confirmation_body'    => __(
				"Hi {name},\n\nthank you for signing up for \"{spot}\".\n\nDate: {date}\nTime: {time}\nLocation: {location}\n\nWe will send you a reminder beforehand.\nCan't make it? Please cancel here so someone else can take over:\n{cancel_url}\n\nCalendar: {calendar_url}\n\n{stats}",
				'duty-planner'
			),
			'tpl_reminder_subject'     => __( 'Reminder: {spot} on {date}', 'duty-planner' ),
			'tpl_reminder_body'        => __(
				"Hi {name},\n\na friendly reminder that you are signed up for \"{spot}\".\n\nDate: {date}\nTime: {time}\nLocation: {location}\n\nCan't make it after all? Please cancel here:\n{cancel_url}\n\nCalendar: {calendar_url}",
				'duty-planner'
			),
			'tpl_cancellation_subject' => __( 'Cancelled: {spot} on {date}', 'duty-planner' ),
			'tpl_cancellation_body'    => __(
				"Hi {name},\n\nyour registration for \"{spot}\" on {date} ({time}) has been cancelled.\n\nCalendar: {calendar_url}",
				'duty-planner'
			),
			'tpl_skip_subject'         => __( 'Does not take place: {spot} on {date}', 'duty-planner' ),
			'tpl_skip_body'            => __(
				"Hi {name},\n\n\"{spot}\" on {date} ({time}) will not take place. Your registration has been removed – there is nothing you need to do.\n\nCalendar: {calendar_url}",
				'duty-planner'
			),
			'tpl_alert_subject'        => __( '{count} duties still need people ({week})', 'duty-planner' ),
			'tpl_alert_body'           => __(
				"Hello,\n\nthe following duties for {week} have not reached their minimum number of people yet:\n\n{list}\n\nManage the schedule: {admin_url}\nCalendar: {calendar_url}",
				'duty-planner'
			),
		);
	}

	/** @return array<string,string> placeholder => description, for the settings screen. */
	public static function placeholders(): array {
		return array(
			'{name}'         => __( 'Display name of the registrant', 'duty-planner' ),
			'{spot}'         => __( 'Title of the duty', 'duty-planner' ),
			'{date}'         => __( 'Date of the duty', 'duty-planner' ),
			'{time}'         => __( 'Time range, or "All day"', 'duty-planner' ),
			'{location}'     => __( 'Location', 'duty-planner' ),
			'{stats}'        => __( 'How often the person has already done each duty, with a thank-you (or a welcome for first-timers)', 'duty-planner' ),
			'{note}'         => __( 'Note the registrant left (if the duty has a note field)', 'duty-planner' ),
			'{cancel_url}'   => __( 'Personal cancellation link', 'duty-planner' ),
			'{calendar_url}' => __( 'Link to the calendar page', 'duty-planner' ),
			'{site}'         => __( 'Site title', 'duty-planner' ),
			'{week}'         => __( 'Admin alert only: the period checked', 'duty-planner' ),
			'{count}'        => __( 'Admin alert only: number of understaffed duties', 'duty-planner' ),
			'{list}'         => __( 'Admin alert only: list of understaffed duties', 'duty-planner' ),
			'{admin_url}'    => __( 'Admin alert only: link to the schedule screen', 'duty-planner' ),
		);
	}

	public static function render( string $template, array $vars ): string {
		$map = array();
		foreach ( $vars as $key => $value ) {
			$map[ '{' . $key . '}' ] = (string) $value;
		}
		return strtr( $template, $map );
	}

	/**
	 * @param string|string[] $to
	 */
	public static function send_template( string $key, $to, array $vars ): bool {
		$settings = Settings::all();
		$vars    += array(
			'site'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'calendar_url' => Settings::calendar_url(),
		);
		$subject = self::render( $settings[ "tpl_{$key}_subject" ], $vars );
		$body    = self::render( $settings[ "tpl_{$key}_body" ], $vars );
		return self::send( $to, $subject, $body );
	}

	public static function registration_vars( $spot, $reg ): array {
		return array(
			'name'       => $reg->display_name,
			'spot'       => $spot->title,
			'date'       => Time::format_long_date( $reg->occurrence_date ),
			'time'       => Time::format_time_range( $spot, $reg->occurrence_date ),
			'location'   => $spot->location ?: '–',
			'note'       => '' !== (string) $reg->note ? $reg->note : '–',
			'cancel_url' => Registration_Service::cancel_url( $reg ),
		);
	}

	public static function send_registration_mail( string $key, $spot, $reg ): bool {
		$vars = self::registration_vars( $spot, $reg );
		// Only compute the statistics when the template actually uses them.
		if ( false !== strpos( Settings::get( "tpl_{$key}_body" ) . Settings::get( "tpl_{$key}_subject" ), '{stats}' ) ) {
			$vars['stats'] = self::stats_box( $reg->email );
		}
		return self::send_template( $key, $reg->email, $vars );
	}

	/**
	 * How often an email address has already done each duty (occurrences that are over).
	 *
	 * @return array<string,int> Spot title => count, most frequent first.
	 */
	public static function participation( string $email, ?\DateTimeImmutable $now = null ): array {
		$now    = $now ?: Time::now();
		$counts = array();
		$spots  = array();
		foreach ( Repository::registrations_by_email( $email ) as $reg ) {
			$id = (int) $reg->spot_id;
			if ( ! array_key_exists( $id, $spots ) ) {
				$spots[ $id ] = Repository::get_spot( $id );
			}
			if ( $spots[ $id ] && Time::end( $spots[ $id ], $reg->occurrence_date ) <= $now ) {
				$counts[ $spots[ $id ]->title ] = ( $counts[ $spots[ $id ]->title ] ?? 0 ) + 1;
			}
		}
		arsort( $counts );
		return $counts;
	}

	/** Plain-text block for the {stats} placeholder. */
	public static function stats_box( string $email ): string {
		$counts = self::participation( $email );
		$rule   = str_repeat( '─', 32 );
		if ( ! $counts ) {
			return $rule . "\n" . __( 'This is your first duty with us – welcome, and thank you for joining in!', 'duty-planner' ) . "\n" . $rule;
		}
		$lines = array( $rule, __( 'Your duties so far', 'duty-planner' ), '' );
		foreach ( $counts as $title => $count ) {
			/* translators: 1: duty title, 2: how often */
			$lines[] = sprintf( __( '• %1$s: %2$d×', 'duty-planner' ), $title, $count );
		}
		if ( count( $counts ) > 1 ) {
			/* translators: %d: total number of duties done */
			$lines[] = sprintf( __( 'Total: %d×', 'duty-planner' ), array_sum( $counts ) );
		}
		$lines[] = '';
		$lines[] = __( 'Thank you for your help – it really makes a difference!', 'duty-planner' );
		$lines[] = $rule;
		return implode( "\n", $lines );
	}

	/**
	 * @param string|string[] $to
	 */
	public static function send( $to, string $subject, string $body ): bool {
		$from_name  = Settings::get( 'from_name' );
		$from_email = Settings::get( 'from_email' );
		$name_cb    = static function () use ( $from_name ) {
			return $from_name;
		};
		$email_cb   = static function () use ( $from_email ) {
			return $from_email;
		};

		if ( $from_name ) {
			add_filter( 'wp_mail_from_name', $name_cb );
		}
		if ( $from_email ) {
			add_filter( 'wp_mail_from', $email_cb );
		}

		$sent = wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );

		remove_filter( 'wp_mail_from_name', $name_cb );
		remove_filter( 'wp_mail_from', $email_cb );
		return (bool) $sent;
	}
}
