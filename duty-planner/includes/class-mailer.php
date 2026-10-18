<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text emails built from editable templates with {placeholders}.
 */
class Mailer {

	/** Template keys that are sent to registrants (as opposed to admins). */
	const REGISTRANT_TEMPLATES = array( 'confirmation', 'reminder', 'cancellation', 'skip', 'overview' );

	public static function default_templates(): array {
		return array(
			'tpl_confirmation_subject' => __( 'You are signed up: {spot} on {date}', 'duty-planner' ),
			'tpl_confirmation_body'    => __(
				"Hi {name},\n\nthank you for signing up for \"{spot}\".\n\nDate: {date}\nTime: {time}\nLocation: {location}\n\nWe will send you a reminder beforehand.\nWant to change your details, or can't make it? Change or cancel your registration here, so someone else can take over:\n{manage_url}\n\nCalendar: {calendar_url}\n\n{stats}",
				'duty-planner'
			),
			'tpl_reminder_subject'     => __( 'Reminder: {spot} on {date}', 'duty-planner' ),
			'tpl_reminder_body'        => __(
				"Hi {name},\n\na friendly reminder that you are signed up for \"{spot}\".\n\nDate: {date}\nTime: {time}\nLocation: {location}\n\nCan't make it after all? Change or cancel your registration here:\n{manage_url}\n\nCalendar: {calendar_url}",
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
			'tpl_overview_subject'     => __( 'Your duties at {site}', 'duty-planner' ),
			'tpl_overview_body'        => __(
				"Hi {name},\n\nyou asked for an overview of your registrations. These are your upcoming duties:\n\n{list}\n\nUse the link below a duty to change or cancel that registration.\n\nCalendar: {calendar_url}\n\nIf you did not request this email, you can simply ignore it.",
				'duty-planner'
			),
			'tpl_alert_subject'        => __( '{count} duties still need people ({week})', 'duty-planner' ),
			'tpl_alert_body'           => __(
				"Hello,\n\nthe following duties for {week} have not reached their minimum number of people yet:\n\n{list}\n\nManage the schedule: {admin_url}\nCalendar: {calendar_url}",
				'duty-planner'
			),
			'tpl_list_subject'         => __( '{location}: plan for the next {weeks} weeks', 'duty-planner' ),
			'tpl_list_body'            => __(
				"Hello everyone,\n\nhere is the duty plan for {week}.\n\n{open}\n\n{covered}\n\nSign up or check the calendar: {calendar_url}\n\nThank you for keeping our building running!",
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
			'{manage_url}'   => __( 'Personal link to change or cancel the registration ({cancel_url} works too)', 'duty-planner' ),
			'{calendar_url}' => __( 'Link to the calendar page', 'duty-planner' ),
			'{site}'         => __( 'Site title', 'duty-planner' ),
			'{week}'         => __( 'Admin alert and mailing list: the period covered', 'duty-planner' ),
			'{count}'        => __( 'Admin alert only: number of understaffed duties', 'duty-planner' ),
			'{list}'         => __( 'Admin alert: list of understaffed duties. Overview of registrations: list of upcoming duties with their links', 'duty-planner' ),
			'{admin_url}'    => __( 'Admin alert only: link to the schedule screen', 'duty-planner' ),
			'{open}'         => __( 'Mailing list only: duties that still need people, with a call to jump in (or a note that everything is covered)', 'duty-planner' ),
			'{covered}'      => __( 'Mailing list only: covered duties with the first names (and notes) of the people, and a thank-you', 'duty-planner' ),
			'{status}'       => __( 'Mailing list only: how many duties still need people, for the subject', 'duty-planner' ),
			'{location}'     => __( 'Mailing list only: the location set above (or the site title)', 'duty-planner' ),
			'{weeks}'        => __( 'Mailing list only: number of weeks covered', 'duty-planner' ),
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
		// Empty placeholders (like {covered}) shouldn't leave a gap of blank lines behind.
		$body    = preg_replace( "/\n{3,}/", "\n\n", self::render( str_replace( "\r\n", "\n", $settings[ "tpl_{$key}_body" ] ), $vars ) );
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
			'manage_url' => Registration_Service::manage_url( $reg ),
			// Old name of the placeholder, still used by templates edited before 1.5.
			'cancel_url' => Registration_Service::manage_url( $reg ),
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
	 * Mails a person a list of their upcoming registrations, each with its management link.
	 * Sends nothing if there are none, so the caller can't tell which addresses are registered.
	 */
	public static function send_overview( string $email ): bool {
		$now   = Time::now();
		$lines = array();
		$name  = '';
		foreach ( Repository::registrations_by_email( $email ) as $reg ) {
			$spot = Repository::get_spot( (int) $reg->spot_id );
			if ( ! $spot || Time::end( $spot, $reg->occurrence_date ) <= $now ) {
				continue;
			}
			$name    = $reg->display_name;
			$time    = Time::format_time_range( $spot, $reg->occurrence_date );
			$lines[] = '• ' . $spot->title . "\n  " . Time::format_long_date( $reg->occurrence_date ) . ( $spot->all_day ? '' : ', ' . $time )
				. ( '' !== (string) $reg->note ? "\n  " . self::label( $spot->note_label ) . ' ' . $reg->note : '' )
				. "\n  " . Registration_Service::manage_url( $reg );
		}
		if ( ! $lines ) {
			return false;
		}
		return self::send_template(
			'overview',
			$email,
			array(
				'name' => $name,
				'list' => implode( "\n\n", $lines ),
			)
		);
	}

	/** "Label:" – unless the label already ends in punctuation, like "What will you bring?". */
	private static function label( string $label ): string {
		return preg_match( '/[?:!.]$/u', $label ) ? $label : $label . ':';
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
