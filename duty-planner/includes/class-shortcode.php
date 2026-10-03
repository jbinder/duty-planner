<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * [duty_planner view="month|list" weeks="6" spots="1,2"]
 */
class Shortcode {

	public static function init() {
		add_shortcode( 'duty_planner', array( self::class, 'render' ) );
	}

	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'view'  => 'month',
				'weeks' => 6,
				'spots' => '',
			),
			$atts,
			'duty_planner'
		);

		$config = array(
			'view'  => in_array( $atts['view'], array( 'month', 'list' ), true ) ? $atts['view'] : 'month',
			'weeks' => max( 1, min( 12, (int) $atts['weeks'] ) ),
			'spots' => implode( ',', wp_parse_id_list( $atts['spots'] ) ),
		);

		self::enqueue();

		return sprintf(
			'<div class="dp-planner alignwide" data-config="%1$s"><p class="dp-loading">%2$s</p><noscript>%3$s</noscript></div>',
			esc_attr( wp_json_encode( $config ) ),
			esc_html__( 'Loading…', 'duty-planner' ),
			esc_html__( 'Please enable JavaScript to see the duty calendar.', 'duty-planner' )
		);
	}

	private static function enqueue() {
		static $done = false;
		wp_enqueue_style( 'duty-planner', DUTYPLAN_URL . 'assets/css/calendar.css', array(), DUTYPLAN_VERSION );
		wp_enqueue_script( 'duty-planner', DUTYPLAN_URL . 'assets/js/calendar.js', array(), DUTYPLAN_VERSION, true );
		if ( $done ) {
			return;
		}
		$done = true;

		$user = null;
		if ( is_user_logged_in() ) {
			$current = wp_get_current_user();
			$user    = array(
				'name'  => $current->display_name,
				'email' => $current->user_email,
			);
		}

		$data = array(
			'restUrl'     => esc_url_raw( rest_url( Rest_Controller::NS . '/' ) ),
			'restNonce'   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'locale'      => I18n::js_locale(),
			'startOfWeek' => (int) get_option( 'start_of_week', 1 ),
			'today'       => Time::today(),
			'user'        => $user,
			'i18n'        => array(
				'needs'        => __( 'Needs people', 'duty-planner' ),
				'open'         => __( 'Places free', 'duty-planner' ),
				'full'         => __( 'Fully booked', 'duty-planner' ),
				'skipped'      => __( 'Cancelled', 'duty-planner' ),
				'past'         => __( 'Over', 'duty-planner' ),
				'prev'         => __( 'Previous', 'duty-planner' ),
				'next'         => __( 'Next', 'duty-planner' ),
				'today'        => __( 'Today', 'duty-planner' ),
				'close'        => __( 'Close', 'duty-planner' ),
				'date'         => __( 'Date', 'duty-planner' ),
				'time'         => __( 'Time', 'duty-planner' ),
				'location'     => __( 'Location', 'duty-planner' ),
				'signedUp'     => __( 'Signed up', 'duty-planner' ),
				'nobody'       => __( 'Nobody has signed up yet.', 'duty-planner' ),
				/* translators: 1: people signed up, 2: maximum */
				'places'       => __( '%1$s of %2$s places taken', 'duty-planner' ),
				/* translators: %s: number of people still needed */
				'moreNeeded'   => __( '%s more needed', 'duty-planner' ),
				'placeLeft'    => __( '1 place left', 'duty-planner' ),
				/* translators: %s: number of free places */
				'placesLeft'   => __( '%s places left', 'duty-planner' ),
				/* translators: %s: minimum number of people */
				'minimum'      => __( 'Minimum: %s', 'duty-planner' ),
				'signUpTitle'  => __( 'Sign up for this duty', 'duty-planner' ),
				'name'         => __( 'Your name', 'duty-planner' ),
				'nameHelp'     => __( 'Shown publicly in the calendar.', 'duty-planner' ),
				'email'        => __( 'Your email', 'duty-planner' ),
				'emailHelp'    => __( 'Only used for your confirmation and reminders – never shown.', 'duty-planner' ),
				'submit'       => __( 'Sign up', 'duty-planner' ),
				'sending'      => __( 'Sending…', 'duty-planner' ),
				'restricted'   => __( 'Sign-up is limited to approved email addresses.', 'duty-planner' ),
				'fullNote'     => __( 'This duty is fully booked. Thank you to everyone who signed up!', 'duty-planner' ),
				'pastNote'     => __( 'This duty is over.', 'duty-planner' ),
				'skippedNote'  => __( 'This duty does not take place on this date.', 'duty-planner' ),
				'loading'      => __( 'Loading…', 'duty-planner' ),
				'loadError'    => __( 'The calendar could not be loaded. Please try again later.', 'duty-planner' ),
				'empty'        => __( 'No duties in this period.', 'duty-planner' ),
				'genericError' => __( 'Something went wrong. Please try again.', 'duty-planner' ),
				'monthView'    => __( 'Month', 'duty-planner' ),
				'listView'     => __( 'List', 'duty-planner' ),
			),
		);

		wp_add_inline_script( 'duty-planner', 'window.DutyPlanner = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
