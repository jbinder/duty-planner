<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Handles ?dutyplan_manage=<id>&key=<hmac> links from emails: a small page where people
 * change their name, note or phone number, cancel, or cancel to pick another date.
 *
 * GET only shows the page; every change needs a POST, so link scanners in mail clients
 * can't change or cancel registrations by accident. The secret key in the URL also
 * protects the form against CSRF.
 */
class Manage_Handler {

	const PARAM = 'dutyplan_manage';

	/** Query parameter of the links sent before 1.5, which only offered cancelling. */
	const LEGACY_PARAM = 'dutyplan_cancel';

	public static function init() {
		add_action( 'template_redirect', array( self::class, 'handle' ) );
	}

	public static function handle() {
		$param = isset( $_GET[ self::PARAM ] ) ? self::PARAM : ( isset( $_GET[ self::LEGACY_PARAM ] ) ? self::LEGACY_PARAM : '' );
		if ( '' === $param ) {
			return;
		}

		$id    = absint( $_GET[ $param ] );
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$reg   = $id ? Repository::get_registration( $id ) : null;
		$title = __( 'Your registration', 'duty-planner' );
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

		$spot = Repository::get_spot( (int) $reg->spot_id );
		if ( $spot && Time::end( $spot, $reg->occurrence_date ) <= Time::now() ) {
			self::page( $title, self::details( $spot, $reg ) . '<p>' . esc_html__( 'This duty is already over.', 'duty-planner' ) . '</p>' . $back );
		}

		$notice = '';
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$action = isset( $_POST['dutyplan_action'] ) ? sanitize_key( $_POST['dutyplan_action'] ) : '';

			if ( 'cancel' === $action || 'move' === $action ) {
				Registration_Service::cancel( $reg );
				if ( 'move' === $action ) {
					wp_safe_redirect( Settings::calendar_url() );
					exit;
				}
				self::page(
					__( 'Registration cancelled', 'duty-planner' ),
					self::details( $spot, $reg ) . '<p>' . esc_html(
						/* translators: %s: display name */
						sprintf( __( 'Thanks for letting us know, %s. You have been removed from this duty.', 'duty-planner' ), $reg->display_name )
					) . '</p>' . $back
				);
			}

			if ( 'save' === $action ) {
				$result = Registration_Service::update(
					$reg,
					isset( $_POST['name'] ) ? wp_unslash( (string) $_POST['name'] ) : '',
					isset( $_POST['note'] ) ? wp_unslash( (string) $_POST['note'] ) : '',
					isset( $_POST['phone'] ) ? wp_unslash( (string) $_POST['phone'] ) : ''
				);
				if ( is_wp_error( $result ) ) {
					$notice = '<p class="dp-manage-error" role="alert"><strong>' . esc_html( $result->get_error_message() ) . '</strong></p>';
				} else {
					$reg    = $result;
					$notice = '<p class="dp-manage-success" role="status"><strong>' . esc_html__( 'Your changes have been saved.', 'duty-planner' ) . '</strong></p>';
				}
			}
		}

		self::page( $title, $notice . self::details( $spot, $reg ) . self::form( $spot, $reg ) . $back );
	}

	private static function details( $spot, $reg ): string {
		if ( ! $spot ) {
			return '';
		}
		$time = $spot->all_day ? '' : '<br>' . esc_html( Time::format_time_range( $spot, $reg->occurrence_date ) );
		return sprintf(
			'<p><strong>%s</strong><br>%s%s%s</p>',
			esc_html( $spot->title ),
			esc_html( Time::format_long_date( $reg->occurrence_date ) ),
			$time,
			$spot->location ? '<br>' . esc_html( $spot->location ) : ''
		);
	}

	private static function form( $spot, $reg ): string {
		$note = '';
		if ( $spot && '' !== $spot->note_label ) {
			$note = sprintf(
				'<p><label for="dp-manage-note">%1$s <small>%2$s</small></label><br><input type="text" id="dp-manage-note" name="note" maxlength="%3$d" value="%4$s"></p>',
				esc_html( $spot->note_label ),
				esc_html__( '(optional)', 'duty-planner' ),
				Registration_Service::MAX_NOTE_LENGTH,
				esc_attr( $reg->note )
			);
		}

		$phone = '';
		if ( $spot && '' !== $spot->phone_field ) {
			$phone = sprintf(
				'<p><label for="dp-manage-phone">%1$s%2$s</label><br><input type="tel" id="dp-manage-phone" name="phone" autocomplete="tel" maxlength="%3$d" value="%4$s"%5$s></p>',
				esc_html__( 'Your phone number', 'duty-planner' ),
				'required' === $spot->phone_field ? '' : ' <small>' . esc_html__( '(optional)', 'duty-planner' ) . '</small>',
				Registration_Service::MAX_PHONE_LENGTH,
				esc_attr( $reg->phone ),
				'required' === $spot->phone_field ? ' required' : ''
			);
		}
		$shared = $spot && Contacts::enabled( $spot )
			? '<p><small>' . esc_html__( 'Your name, email address and phone number are shared with everyone on duty that day, so you can coordinate.', 'duty-planner' ) . '</small></p>'
			: '';

		return '<form method="post" class="dp-manage">'
			. '<h2>' . esc_html__( 'Change your details', 'duty-planner' ) . '</h2>'
			. sprintf(
				'<p><label for="dp-manage-name">%1$s</label><br><input type="text" id="dp-manage-name" name="name" required maxlength="%2$d" value="%3$s"></p>',
				esc_html__( 'Your name', 'duty-planner' ),
				Registration_Service::MAX_NAME_LENGTH,
				esc_attr( $reg->display_name )
			)
			. $phone
			. $note
			. $shared
			. '<p><small>' . esc_html(
				/* translators: %s: email address */
				sprintf( __( 'Registered with %s. To use a different email address, cancel and sign up again.', 'duty-planner' ), $reg->email )
			) . '</small></p>'
			. '<p><button type="submit" name="dutyplan_action" value="save" class="button button-primary">' . esc_html__( 'Save changes', 'duty-planner' ) . '</button></p>'
			. '<h2>' . esc_html__( 'Can\'t make it?', 'duty-planner' ) . '</h2>'
			. '<p>' . esc_html__( 'Cancel your registration so someone else can take over – or move to another date by cancelling and picking a new one in the calendar.', 'duty-planner' ) . '</p>'
			. '<p class="dp-manage-actions">'
			. '<button type="submit" name="dutyplan_action" value="cancel" class="button" formnovalidate>' . esc_html__( 'Cancel my registration', 'duty-planner' ) . '</button> '
			. '<button type="submit" name="dutyplan_action" value="move" class="button" formnovalidate>' . esc_html__( 'Cancel and pick another date', 'duty-planner' ) . '</button>'
			. '</p></form>';
	}

	private static function page( string $title, string $html, int $status = 200 ) {
		nocache_headers();
		$style = '<style>.dp-manage input[type=text],.dp-manage input[type=tel]{width:100%;max-width:28em;padding:6px 8px;font-size:1em;box-sizing:border-box}'
			. '.dp-manage h2{font-size:1.15em;margin:1.6em 0 .4em;border:0;padding:0}'
			. '.dp-manage-actions .button{margin:0 .4em .4em 0}'
			. '.dp-manage .button-primary{background:#2271b1;border-color:#2271b1;color:#fff}'
			. '.dp-manage .button-primary:hover,.dp-manage .button-primary:focus{background:#135e96;border-color:#135e96;color:#fff}'
			. '.dp-manage-success,.dp-manage-error{padding:8px 12px;border-left:4px solid #00a32a;background:#f0f6f1}'
			. '.dp-manage-error{border-color:#d63638;background:#fcf0f1}</style>';
		wp_die( $style . '<h1>' . esc_html( $title ) . '</h1>' . $html, esc_html( $title ), array( 'response' => $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
