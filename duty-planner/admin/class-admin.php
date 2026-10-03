<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens: Schedule, Spots, Add/Edit spot, Settings.
 */
class Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_filter( 'display_post_states', array( self::class, 'post_states' ), 10, 2 );

		foreach ( array( 'save_spot', 'delete_spot', 'add_registration', 'remove_registration', 'skip', 'unskip', 'test_alert' ) as $action ) {
			add_action( "admin_post_dutyplan_{$action}", array( self::class, "handle_{$action}" ) );
		}
	}

	public static function menu() {
		add_menu_page( __( 'Duty Planner', 'duty-planner' ), __( 'Duty Planner', 'duty-planner' ), self::CAP, 'dutyplan', array( self::class, 'page_schedule' ), 'dashicons-calendar-alt', 26 );
		add_submenu_page( 'dutyplan', __( 'Schedule', 'duty-planner' ), __( 'Schedule', 'duty-planner' ), self::CAP, 'dutyplan', array( self::class, 'page_schedule' ) );
		add_submenu_page( 'dutyplan', __( 'Spots', 'duty-planner' ), __( 'Spots', 'duty-planner' ), self::CAP, 'dutyplan-spots', array( self::class, 'page_spots' ) );
		add_submenu_page( 'dutyplan', __( 'Add spot', 'duty-planner' ), __( 'Add spot', 'duty-planner' ), self::CAP, 'dutyplan-spot', array( self::class, 'page_spot_edit' ) );
		add_submenu_page( 'dutyplan', __( 'Settings', 'duty-planner' ), __( 'Settings', 'duty-planner' ), self::CAP, 'dutyplan-settings', array( self::class, 'page_settings' ) );
	}

	public static function register_settings() {
		register_setting(
			'dutyplan_settings_group',
			Settings::OPTION,
			array( 'sanitize_callback' => array( Settings::class, 'sanitize' ) )
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'dutyplan' ) ) {
			return;
		}
		wp_enqueue_style( 'duty-planner-admin', DUTYPLAN_URL . 'assets/css/admin.css', array(), DUTYPLAN_VERSION );
		wp_enqueue_script( 'duty-planner-admin', DUTYPLAN_URL . 'assets/js/admin.js', array(), DUTYPLAN_VERSION, true );
	}

	/* ----------------------------------------------------------------- Pages */

	public static function page_schedule() {
		$from = isset( $_GET['from'] ) && Time::is_date( wp_unslash( $_GET['from'] ) ) ? wp_unslash( $_GET['from'] ) : Time::today();
		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 14;
		$days = in_array( $days, array( 7, 14, 28, 56 ), true ) ? $days : 14;
		$spot = isset( $_GET['spot'] ) ? absint( $_GET['spot'] ) : 0;
		$to   = Time::add_days( $from, $days - 1 );

		$items  = Occurrences::between( $from, $to, $spot ? array( $spot ) : array(), true );
		$spots  = Repository::get_spots();
		$counts = array( 'needs' => 0, 'open' => 0, 'full' => 0 );
		foreach ( $items as $o ) {
			if ( ! $o['skipped'] ) {
				$counts[ $o['status'] ]++;
			}
		}
		include DUTYPLAN_DIR . 'admin/views/schedule.php';
	}

	public static function page_spots() {
		require_once DUTYPLAN_DIR . 'admin/class-spots-list-table.php';
		$table = new Spots_List_Table();
		$table->prepare_items();
		include DUTYPLAN_DIR . 'admin/views/spots.php';
	}

	public static function page_spot_edit() {
		$id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$spot = $id ? Repository::get_spot( $id ) : null;
		if ( $id && ! $spot ) {
			wp_die( esc_html__( 'Spot not found.', 'duty-planner' ) );
		}

		$defaults = array(
			'title'                 => '',
			'description'           => '',
			'location'              => '',
			'frequency'             => 'weekly',
			'weekdays'              => array(),
			'interval_n'            => 1,
			'start_date'            => Time::today(),
			'end_date'              => '',
			'all_day'               => false,
			'start_time'            => '18:00',
			'duration_min'          => 60,
			'min_people'            => 1,
			'max_people'            => 2,
			'reminder_offset_hours' => (int) Settings::get( 'default_reminder_hours' ),
			'allowlist'             => '',
			'note_label'            => '',
			'active'                => true,
		);
		$values = $spot ? array_merge( $defaults, array_filter( (array) $spot, static function ( $v ) { return null !== $v; } ) ) : $defaults;

		// Refill the form after a validation error.
		$draft = get_transient( self::draft_key() );
		if ( is_array( $draft ) && (int) ( $draft['id'] ?? 0 ) === $id ) {
			$values = array_merge( $values, $draft['data'] );
			delete_transient( self::draft_key() );
		}
		$values['start_time'] = substr( (string) $values['start_time'], 0, 5 );

		include DUTYPLAN_DIR . 'admin/views/spot-edit.php';
	}

	public static function page_settings() {
		$settings = Settings::all();
		$window   = Scheduler::alert_window( Time::now() );
		include DUTYPLAN_DIR . 'admin/views/settings.php';
	}

	/* -------------------------------------------------------------- Handlers */

	public static function handle_save_spot() {
		self::verify( 'dutyplan_save_spot' );
		$in = wp_unslash( $_POST );
		$id = absint( $in['id'] ?? 0 );

		$all_day  = ! empty( $in['all_day'] );
		$weekdays = array_values( array_unique( array_filter( array_map( 'intval', (array) ( $in['weekdays'] ?? array() ) ), static function ( $d ) {
			return $d >= 1 && $d <= 7;
		} ) ) );
		sort( $weekdays );

		$data = array(
			'title'                 => sanitize_text_field( $in['title'] ?? '' ),
			'description'           => Formatting::sanitize_description( (string) ( $in['description'] ?? '' ) ),
			'location'              => sanitize_text_field( $in['location'] ?? '' ),
			'frequency'             => ( $in['frequency'] ?? '' ) === 'daily' ? 'daily' : 'weekly',
			'weekdays'              => $weekdays,
			'interval_n'            => max( 1, min( 52, absint( $in['interval_n'] ?? 1 ) ) ),
			'start_date'            => (string) ( $in['start_date'] ?? '' ),
			'end_date'              => (string) ( $in['end_date'] ?? '' ),
			'all_day'               => $all_day ? 1 : 0,
			'start_time'            => $all_day ? null : Settings::sanitize_time( $in['start_time'] ?? '', '' ),
			'duration_min'          => $all_day ? null : absint( $in['duration_min'] ?? 0 ),
			'min_people'            => absint( $in['min_people'] ?? 0 ),
			'max_people'            => absint( $in['max_people'] ?? 0 ),
			'reminder_offset_hours' => min( 720, absint( $in['reminder_offset_hours'] ?? 0 ) ),
			'allowlist'             => Allowlist::sanitize( (string) ( $in['allowlist'] ?? '' ) ),
			'note_label'            => mb_substr( sanitize_text_field( $in['note_label'] ?? '' ), 0, 190 ),
			'active'                => empty( $in['active'] ) ? 0 : 1,
		);

		$errors = array();
		if ( '' === $data['title'] ) {
			$errors[] = __( 'Please enter a title.', 'duty-planner' );
		}
		if ( ! Time::is_date( $data['start_date'] ) ) {
			$errors[] = __( 'Please enter a valid start date.', 'duty-planner' );
		}
		if ( '' !== $data['end_date'] && ( ! Time::is_date( $data['end_date'] ) || $data['end_date'] < $data['start_date'] ) ) {
			$errors[] = __( 'The end date must be a valid date after the start date.', 'duty-planner' );
		}
		if ( 'weekly' === $data['frequency'] && ! $weekdays ) {
			$errors[] = __( 'Please select at least one weekday.', 'duty-planner' );
		}
		if ( ! $all_day && ( '' === $data['start_time'] || $data['duration_min'] < 1 ) ) {
			$errors[] = __( 'Please enter a start time and a duration, or mark the spot as all-day.', 'duty-planner' );
		}
		if ( $data['max_people'] < 1 ) {
			$errors[] = __( 'The maximum number of people must be at least 1.', 'duty-planner' );
		}
		if ( $data['min_people'] > $data['max_people'] ) {
			$errors[] = __( 'The minimum cannot be larger than the maximum.', 'duty-planner' );
		}

		if ( $errors ) {
			set_transient( self::draft_key(), array( 'id' => $id, 'data' => $data ), 10 * MINUTE_IN_SECONDS );
			self::redirect( self::url( 'dutyplan-spot', $id ? array( 'id' => $id ) : array() ), 'error', implode( ' ', $errors ) );
		}

		$data['end_date']   = '' === $data['end_date'] ? null : $data['end_date'];
		$data['allowlist']  = '' === $data['allowlist'] ? null : $data['allowlist'];
		$data['start_time'] = $all_day ? null : $data['start_time'] . ':00';

		Repository::save_spot( $data, $id );
		self::redirect( self::url( 'dutyplan-spots' ), 'success', __( 'Spot saved.', 'duty-planner' ) );
	}

	public static function handle_delete_spot() {
		self::verify( 'dutyplan_delete_spot' );
		$id = absint( $_REQUEST['id'] ?? 0 );
		if ( $id ) {
			Repository::delete_spot( $id );
		}
		self::redirect( self::url( 'dutyplan-spots' ), 'success', __( 'Spot deleted.', 'duty-planner' ) );
	}

	public static function handle_add_registration() {
		self::verify( 'dutyplan_add_registration' );
		$in  = wp_unslash( $_POST );
		$reg = Registration_Service::register(
			absint( $in['spot_id'] ?? 0 ),
			(string) ( $in['date'] ?? '' ),
			(string) ( $in['name'] ?? '' ),
			(string) ( $in['email'] ?? '' ),
			array(
				'bypass_allowlist' => true,
				'notify'           => ! empty( $in['notify'] ),
				'note'             => (string) ( $in['note'] ?? '' ),
			)
		);
		if ( is_wp_error( $reg ) ) {
			self::redirect( self::back(), 'error', $reg->get_error_message() );
		}
		/* translators: %s: display name */
		self::redirect( self::back(), 'success', sprintf( __( '%s has been added.', 'duty-planner' ), $reg->display_name ) );
	}

	public static function handle_remove_registration() {
		self::verify( 'dutyplan_remove_registration' );
		$reg = Repository::get_registration( absint( $_POST['registration_id'] ?? 0 ) );
		if ( $reg ) {
			Registration_Service::cancel( $reg, ! empty( $_POST['notify'] ) );
			/* translators: %s: display name */
			self::redirect( self::back(), 'success', sprintf( __( '%s has been removed.', 'duty-planner' ), $reg->display_name ) );
		}
		self::redirect( self::back() );
	}

	public static function handle_skip() {
		self::verify( 'dutyplan_skip' );
		$spot = Repository::get_spot( absint( $_POST['spot_id'] ?? 0 ) );
		$date = (string) wp_unslash( $_POST['date'] ?? '' );
		if ( ! $spot || ! Time::is_date( $date ) ) {
			self::redirect( self::back() );
		}

		$regs = Repository::registrations_for( $spot->id, $date );
		foreach ( $regs as $reg ) {
			Mailer::send_registration_mail( 'skip', $spot, $reg );
			Repository::delete_registration( (int) $reg->id );
		}
		Repository::add_skip( $spot->id, $date );

		self::redirect(
			self::back(),
			'success',
			/* translators: %d: number of people notified */
			sprintf( _n( 'Date cancelled. %d person was notified.', 'Date cancelled. %d people were notified.', count( $regs ), 'duty-planner' ), count( $regs ) )
		);
	}

	public static function handle_unskip() {
		self::verify( 'dutyplan_unskip' );
		$date = (string) wp_unslash( $_POST['date'] ?? '' );
		if ( Time::is_date( $date ) ) {
			Repository::remove_skip( absint( $_POST['spot_id'] ?? 0 ), $date );
		}
		self::redirect( self::back(), 'success', __( 'Date restored.', 'duty-planner' ) );
	}

	public static function handle_test_alert() {
		self::verify( 'dutyplan_test_alert' );
		$back = self::url( 'dutyplan-settings' );
		if ( ! Settings::alert_recipients() ) {
			self::redirect( $back, 'error', __( 'Please configure at least one alert recipient first.', 'duty-planner' ) );
		}
		$window = Scheduler::alert_window( Time::now() );
		$count  = Scheduler::send_admin_alert( $window['from'], $window['to'] );
		$period = Time::format_date( $window['from'] ) . ' – ' . Time::format_date( $window['to'] );
		if ( $count ) {
			/* translators: 1: number of duties, 2: period */
			self::redirect( $back, 'success', sprintf( __( 'Alert sent, listing %1$d understaffed duties for %2$s.', 'duty-planner' ), $count, $period ) );
		}
		/* translators: %s: period */
		self::redirect( $back, 'success', sprintf( __( 'All duties for %s have reached their minimum – no alert needed.', 'duty-planner' ), $period ) );
	}

	/* --------------------------------------------------------------- Helpers */

	public static function url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/** Hidden fields for an admin-post form (no duplicate IDs, unlike wp_nonce_field). */
	public static function form_fields( string $action ): void {
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( $action ) );
		printf( '<input type="hidden" name="_wpnonce" value="%s">', esc_attr( wp_create_nonce( $action ) ) );
		printf( '<input type="hidden" name="_wp_http_referer" value="%s">', esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) );
	}

	public static function describe_recurrence( $spot ): string {
		$n = (int) $spot->interval_n;
		if ( 'daily' === $spot->frequency ) {
			/* translators: %d: interval in days */
			$text = 1 === $n ? __( 'Every day', 'duty-planner' ) : sprintf( __( 'Every %d days', 'duty-planner' ), $n );
		} else {
			$names = Time::weekday_names( true );
			$days  = implode( ', ', array_map( static function ( $d ) use ( $names ) {
				return $names[ $d ] ?? '';
			}, $spot->weekdays ) );
			/* translators: 1: weekdays, 2: interval in weeks */
			$text = 1 === $n ? sprintf( __( 'Weekly on %1$s', 'duty-planner' ), $days ) : sprintf( __( 'Every %2$d weeks on %1$s', 'duty-planner' ), $days, $n );
		}
		/* translators: %s: date */
		$text .= ' · ' . sprintf( __( 'from %s', 'duty-planner' ), Time::format_date( $spot->start_date ) );
		if ( $spot->end_date ) {
			/* translators: %s: date */
			$text .= ' ' . sprintf( __( 'until %s', 'duty-planner' ), Time::format_date( $spot->end_date ) );
		}
		return $text;
	}

	/**
	 * Where the public calendar lives – important once the page is unlisted,
	 * because then no menu on the site leads there.
	 */
	public static function calendar_link(): void {
		$id   = (int) Settings::get( 'calendar_page_id' );
		$page = $id ? get_post( $id ) : null;

		echo '<div class="dutyplan-calendar-link">';
		if ( ! $page || 'trash' === $page->post_status ) {
			printf(
				/* translators: 1: shortcode, 2: settings link */
				wp_kses_post( __( 'Public calendar: <strong>no page selected.</strong> Create a page with %1$s and choose it in the <a href="%2$s">settings</a>.', 'duty-planner' ) ),
				'<code>[duty_planner]</code>',
				esc_url( self::url( 'dutyplan-settings' ) )
			);
			echo '</div>';
			return;
		}

		$url = get_permalink( $page );
		echo '<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span> ';
		esc_html_e( 'Public calendar:', 'duty-planner' );
		printf( ' <a href="%1$s" target="_blank" rel="noopener"><code>%2$s</code></a>', esc_url( $url ), esc_html( $url ) );
		printf(
			' <button type="button" class="button button-small dutyplan-copy" data-copy="%1$s" data-done="%2$s">%3$s</button>',
			esc_attr( $url ),
			esc_attr__( 'Copied!', 'duty-planner' ),
			esc_html__( 'Copy link', 'duty-planner' )
		);

		$notes = array();
		if ( 'publish' !== $page->post_status ) {
			$notes[] = __( 'The page is not published yet, so visitors cannot open it.', 'duty-planner' );
		} elseif ( '' !== $page->post_password ) {
			$notes[] = __( 'The page is password protected.', 'duty-planner' );
		}
		if ( ! self::page_has_calendar( $page ) ) {
			$notes[] = __( 'The [duty_planner] shortcode was not found on this page. Add it there, e.g. with a Shortcode block, or in Elementor with the Shortcode widget.', 'duty-planner' );
		}
		if ( Settings::get( 'unlisted' ) ) {
			echo ' <span class="dutyplan-unlisted-tag">' . esc_html__( 'Unlisted – only reachable via this link', 'duty-planner' ) . '</span>';
		}
		foreach ( $notes as $note ) {
			echo '<br><span class="dutyplan-warning">' . esc_html( $note ) . '</span>';
		}
		echo '</div>';
	}

	/** Whether the page contains the shortcode – in the regular content or in Elementor's layout data. */
	public static function page_has_calendar( \WP_Post $page ): bool {
		$found = has_shortcode( $page->post_content, 'duty_planner' )
			|| false !== strpos( (string) get_post_meta( $page->ID, '_elementor_data', true ), '[duty_planner' );
		return (bool) apply_filters( 'dutyplan_page_has_calendar', $found, $page );
	}

	/** Label the calendar page in the Pages list, like WordPress does for the front page. */
	public static function post_states( $states, $post ) {
		if ( (int) $post->ID === (int) Settings::get( 'calendar_page_id' ) ) {
			$states['dutyplan'] = Settings::get( 'unlisted' )
				? __( 'Duty Planner calendar (unlisted)', 'duty-planner' )
				: __( 'Duty Planner calendar', 'duty-planner' );
		}
		return $states;
	}

	public static function status_badge( array $o ): string {
		if ( $o['skipped'] ) {
			return '<span class="dutyplan-badge dutyplan-badge-skipped">' . esc_html__( 'Cancelled', 'duty-planner' ) . '</span>';
		}
		$labels = array(
			'needs' => __( 'Needs people', 'duty-planner' ),
			'open'  => __( 'Places free', 'duty-planner' ),
			'full'  => __( 'Fully booked', 'duty-planner' ),
		);
		return sprintf( '<span class="dutyplan-badge dutyplan-badge-%1$s">%2$s</span>', esc_attr( $o['status'] ), esc_html( $labels[ $o['status'] ] ) );
	}

	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'dutyplan' ) ) {
			return;
		}
		$notice = get_transient( self::notice_key() );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( self::notice_key() );
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ),
			esc_html( $notice['text'] )
		);
	}

	private static function verify( string $action ): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'duty-planner' ), 403 );
		}
		check_admin_referer( $action );
	}

	private static function back(): string {
		return wp_get_referer() ?: self::url( 'dutyplan' );
	}

	/** @return never */
	private static function redirect( string $url, string $type = '', string $text = '' ) {
		if ( $text ) {
			set_transient( self::notice_key(), array( 'type' => $type, 'text' => $text ), MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( $url );
		exit;
	}

	private static function notice_key(): string {
		return 'dutyplan_notice_' . get_current_user_id();
	}

	private static function draft_key(): string {
		return 'dutyplan_draft_' . get_current_user_id();
	}
}
