<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Handles ?dutyplan_cancel=<id>&key=<hmac> links from emails.
 *
 * GET only shows a confirmation button; the actual cancellation needs a POST,
 * so link scanners in mail clients can't cancel registrations by accident.
 */
class Cancel_Handler {

	public static function init() {
		add_action( 'template_redirect', array( self::class, 'handle' ) );
	}

	public static function handle() {
		if ( ! isset( $_GET['dutyplan_cancel'] ) ) {
			return;
		}

		$id    = absint( $_GET['dutyplan_cancel'] );
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$reg   = $id ? Repository::get_registration( $id ) : null;
		$title = __( 'Cancel registration', 'duty-planner' );
		$back  = sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( Settings::calendar_url() ),
			esc_html__( 'Back to the calendar', 'duty-planner' )
		);

		if ( ! $reg || ! Registration_Service::verify_key( $reg, $key ) ) {
			self::page(
				$title,
				'<p>' . esc_html__( 'This registration was not found. It may already have been cancelled.', 'duty-planner' ) . '</p>' . $back,
				404
			);
		}

		$spot    = Repository::get_spot( (int) $reg->spot_id );
		$details = '';
		if ( $spot ) {
			$details = sprintf(
				'<p><strong>%s</strong><br>%s<br>%s</p>',
				esc_html( $spot->title ),
				esc_html( Time::format_date( $reg->occurrence_date, 'l, ' . get_option( 'date_format' ) ) ),
				esc_html( Time::format_time_range( $spot, $reg->occurrence_date ) )
			);
			if ( Time::end( $spot, $reg->occurrence_date ) <= Time::now() ) {
				self::page( $title, $details . '<p>' . esc_html__( 'This duty is already over.', 'duty-planner' ) . '</p>' . $back );
			}
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! empty( $_POST['dutyplan_confirm'] ) ) {
			Registration_Service::cancel( $reg );
			self::page(
				__( 'Registration cancelled', 'duty-planner' ),
				$details . '<p>' . esc_html(
					/* translators: %s: display name */
					sprintf( __( 'Thanks for letting us know, %s. You have been removed from this duty.', 'duty-planner' ), $reg->display_name )
				) . '</p>' . $back
			);
		}

		$form = '<form method="post"><input type="hidden" name="dutyplan_confirm" value="1">'
			. '<p><button type="submit" class="button button-large">' . esc_html__( 'Yes, cancel my registration', 'duty-planner' ) . '</button></p></form>';

		self::page(
			$title,
			'<p>' . esc_html(
				/* translators: %s: display name */
				sprintf( __( 'Hi %s, do you want to cancel your registration for this duty?', 'duty-planner' ), $reg->display_name )
			) . '</p>' . $details . $form . $back
		);
	}

	private static function page( string $title, string $html, int $status = 200 ) {
		nocache_headers();
		wp_die( '<h1>' . esc_html( $title ) . '</h1>' . $html, esc_html( $title ), array( 'response' => $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
