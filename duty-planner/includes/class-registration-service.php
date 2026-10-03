<?php
namespace DutyPlanner;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Registering for and cancelling occurrences.
 */
class Registration_Service {

	const MAX_NAME_LENGTH = 60;
	const MAX_NOTE_LENGTH = 200;

	/**
	 * @param array $opts bypass_allowlist (bool), notify (bool, send confirmation mail),
	 *                    note (string, only kept if the spot has a note field).
	 * @return object|WP_Error The registration row.
	 */
	public static function register( int $spot_id, string $date, string $name, string $email, array $opts = array() ) {
		global $wpdb;
		$opts  = wp_parse_args( $opts, array( 'bypass_allowlist' => false, 'notify' => true, 'note' => '' ) );
		$name  = trim( sanitize_text_field( $name ) );
		$email = strtolower( trim( sanitize_email( $email ) ) );

		if ( '' === $name ) {
			return new WP_Error( 'dutyplan_name', __( 'Please enter your name.', 'duty-planner' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $name ) > self::MAX_NAME_LENGTH ) {
			$name = mb_substr( $name, 0, self::MAX_NAME_LENGTH );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'dutyplan_email', __( 'Please enter a valid email address.', 'duty-planner' ), array( 'status' => 400 ) );
		}

		$spot = Repository::get_spot( $spot_id );
		if ( ! $spot || ! $spot->active ) {
			return new WP_Error( 'dutyplan_spot', __( 'This duty does not exist.', 'duty-planner' ), array( 'status' => 404 ) );
		}
		$note = '' === $spot->note_label ? '' : mb_substr( trim( sanitize_text_field( (string) $opts['note'] ) ), 0, self::MAX_NOTE_LENGTH );
		if ( ! Time::is_date( $date ) || ! Recurrence::occurs_on( $spot, $date ) ) {
			return new WP_Error( 'dutyplan_date', __( 'This duty does not take place on that date.', 'duty-planner' ), array( 'status' => 400 ) );
		}
		if ( Repository::is_skipped( $spot_id, $date ) ) {
			return new WP_Error( 'dutyplan_skipped', __( 'This duty has been cancelled for that date.', 'duty-planner' ), array( 'status' => 400 ) );
		}
		if ( Time::end( $spot, $date ) <= Time::now() ) {
			return new WP_Error( 'dutyplan_past', __( 'This duty is already over.', 'duty-planner' ), array( 'status' => 400 ) );
		}
		if ( ! $opts['bypass_allowlist'] && ! Allowlist::is_allowed( $spot, $email ) ) {
			return new WP_Error( 'dutyplan_not_allowed', __( 'This email address is not permitted to sign up for this duty.', 'duty-planner' ), array( 'status' => 403 ) );
		}

		// Serialize concurrent sign-ups for the same occurrence so max_people can't be exceeded.
		$lock = 'dutyplan_' . $spot_id . '_' . $date;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			return new WP_Error( 'dutyplan_busy', __( 'The server is busy. Please try again.', 'duty-planner' ), array( 'status' => 503 ) );
		}
		try {
			if ( Repository::email_registered( $spot_id, $date, $email ) ) {
				return new WP_Error( 'dutyplan_duplicate', __( 'This email address is already signed up for this duty.', 'duty-planner' ), array( 'status' => 409 ) );
			}
			if ( Repository::count_for( $spot_id, $date ) >= $spot->max_people ) {
				return new WP_Error( 'dutyplan_full', __( 'Sorry, this duty is already fully booked.', 'duty-planner' ), array( 'status' => 409 ) );
			}
			$id = Repository::insert_registration( $spot_id, $date, $name, $email, $note );
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}

		if ( ! $id ) {
			return new WP_Error( 'dutyplan_db', __( 'Your registration could not be saved. Please try again.', 'duty-planner' ), array( 'status' => 500 ) );
		}

		$reg = Repository::get_registration( $id );
		if ( $opts['notify'] ) {
			Mailer::send_registration_mail( 'confirmation', $spot, $reg );
		}
		do_action( 'dutyplan_registered', $reg, $spot );
		return $reg;
	}

	public static function cancel( $reg, bool $notify = true ): void {
		$spot = Repository::get_spot( (int) $reg->spot_id );
		Repository::delete_registration( (int) $reg->id );
		if ( $notify && $spot ) {
			Mailer::send_registration_mail( 'cancellation', $spot, $reg );
		}
		do_action( 'dutyplan_cancelled', $reg, $spot );
	}

	/**
	 * Cancellation keys are derived with an HMAC, so nothing secret has to be stored
	 * and the link can be regenerated for every reminder.
	 */
	public static function cancel_key( $reg ): string {
		return substr( hash_hmac( 'sha256', $reg->id . '|' . $reg->email . '|' . $reg->created_at, wp_salt( 'auth' ) ), 0, 32 );
	}

	public static function verify_key( $reg, string $key ): bool {
		return hash_equals( self::cancel_key( $reg ), $key );
	}

	public static function cancel_url( $reg ): string {
		return add_query_arg(
			array(
				'dutyplan_cancel' => (int) $reg->id,
				'key'             => self::cancel_key( $reg ),
			),
			home_url( '/' )
		);
	}
}
