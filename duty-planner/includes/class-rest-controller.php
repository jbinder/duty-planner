<?php
namespace DutyPlanner;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Public REST API used by the calendar.
 */
class Rest_Controller {

	const NS             = 'dutyplan/v1';
	const MAX_RANGE_DAYS = 92;
	const NONCE_ACTION   = 'dutyplan_register';

	public static function init() {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	public static function routes() {
		$date = array(
			'required'          => true,
			'type'              => 'string',
			'validate_callback' => static function ( $value ) {
				return Time::is_date( $value );
			},
		);

		register_rest_route(
			self::NS,
			'/occurrences',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_occurrences' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'from'  => $date,
					'to'    => $date,
					'spots' => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'register' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'spot_id' => array( 'required' => true, 'type' => 'integer' ),
					'date'    => $date,
					'name'    => array( 'required' => true, 'type' => 'string' ),
					'email'   => array( 'required' => true, 'type' => 'string' ),
					'note'    => array( 'type' => 'string', 'default' => '' ),
					'nonce'   => array( 'required' => true, 'type' => 'string' ),
					'website' => array( 'type' => 'string', 'default' => '' ), // Honeypot.
				),
			)
		);
	}

	/** Strip everything not meant for the public (emails, registration IDs). */
	public static function public_item( array $o ): array {
		$o['people'] = array_map(
			static function ( $p ) {
				return array(
					'name' => $p['name'],
					'note' => $p['note'],
				);
			},
			$o['people']
		);
		return $o;
	}

	public static function get_occurrences( WP_REST_Request $request ) {
		$from = $request['from'];
		$to   = $request['to'];
		if ( $to < $from ) {
			return new WP_Error( 'dutyplan_range', 'Invalid date range.', array( 'status' => 400 ) );
		}
		if ( Time::add_days( $from, self::MAX_RANGE_DAYS ) < $to ) {
			return new WP_Error( 'dutyplan_range', 'Date range too large.', array( 'status' => 400 ) );
		}

		$items    = Occurrences::between( $from, $to, wp_parse_id_list( $request['spots'] ) );
		$response = rest_ensure_response(
			array(
				'occurrences' => array_map( array( self::class, 'public_item' ), $items ),
				'nonce'       => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	public static function register( WP_REST_Request $request ) {
		if ( ! wp_verify_nonce( $request['nonce'], self::NONCE_ACTION ) ) {
			return new WP_Error( 'dutyplan_nonce', __( 'Your session has expired. Please reload the page and try again.', 'duty-planner' ), array( 'status' => 403 ) );
		}
		if ( '' !== trim( (string) $request['website'] ) ) {
			return new WP_Error( 'dutyplan_rejected', __( 'Your registration could not be accepted.', 'duty-planner' ), array( 'status' => 400 ) );
		}
		if ( self::rate_limited() ) {
			return new WP_Error( 'dutyplan_rate_limit', __( 'Too many attempts. Please wait a few minutes and try again.', 'duty-planner' ), array( 'status' => 429 ) );
		}

		$reg = Registration_Service::register(
			(int) $request['spot_id'],
			(string) $request['date'],
			(string) $request['name'],
			(string) $request['email'],
			array( 'note' => (string) $request['note'] )
		);
		if ( is_wp_error( $reg ) ) {
			return $reg;
		}

		$item = Occurrences::find( (int) $reg->spot_id, $reg->occurrence_date );
		return rest_ensure_response(
			array(
				'message'    => __( 'You are signed up! A confirmation email is on its way.', 'duty-planner' ),
				'occurrence' => $item ? self::public_item( $item ) : null,
			)
		);
	}

	/** Simple per-IP throttle on sign-up attempts. */
	private static function rate_limited(): bool {
		$limit  = (int) apply_filters( 'dutyplan_rate_limit', 15 );
		$window = 10 * MINUTE_IN_SECONDS;
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key    = 'dutyplan_rl_' . md5( $ip );
		$hits   = (int) get_transient( $key );
		if ( $limit > 0 && $hits >= $limit ) {
			return true;
		}
		set_transient( $key, $hits + 1, $window );
		return false;
	}
}
