<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List of all spots on the "Spots" screen.
 */
class Spots_List_Table extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'spot',
				'plural'   => 'spots',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'title'      => __( 'Title', 'duty-planner' ),
			'recurrence' => __( 'Recurrence', 'duty-planner' ),
			'time'       => __( 'Time', 'duty-planner' ),
			'people'     => __( 'People (min–max)', 'duty-planner' ),
			'reminder'   => __( 'Reminder', 'duty-planner' ),
			'access'     => __( 'Sign-up', 'duty-planner' ),
		);
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = array_values( Repository::get_spots() );
	}

	public function no_items() {
		esc_html_e( 'No spots yet. Create your first one!', 'duty-planner' );
	}

	protected function column_title( $spot ) {
		$edit   = Admin::url( 'dutyplan-spot', array( 'id' => $spot->id ) );
		$delete = wp_nonce_url(
			add_query_arg( array( 'action' => 'dutyplan_delete_spot', 'id' => $spot->id ), admin_url( 'admin-post.php' ) ),
			'dutyplan_delete_spot'
		);
		$actions = array(
			'edit'     => sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html__( 'Edit', 'duty-planner' ) ),
			'schedule' => sprintf( '<a href="%s">%s</a>', esc_url( Admin::url( 'dutyplan', array( 'spot' => $spot->id ) ) ), esc_html__( 'Schedule', 'duty-planner' ) ),
			'delete'   => sprintf(
				'<a href="%s" class="dutyplan-confirm" data-confirm="%s">%s</a>',
				esc_url( $delete ),
				esc_attr__( 'Delete this spot including all registrations? This cannot be undone.', 'duty-planner' ),
				esc_html__( 'Delete', 'duty-planner' )
			),
		);
		$state = $spot->active ? '' : ' — <span class="post-state">' . esc_html__( 'Inactive', 'duty-planner' ) . '</span>';
		return sprintf( '<strong><a class="row-title" href="%s">%s</a>%s</strong>', esc_url( $edit ), esc_html( $spot->title ), $state )
			. $this->row_actions( $actions );
	}

	protected function column_recurrence( $spot ) {
		return esc_html( Admin::describe_recurrence( $spot ) );
	}

	protected function column_time( $spot ) {
		if ( $spot->all_day ) {
			return esc_html__( 'All day', 'duty-planner' );
		}
		/* translators: 1: start time, 2: duration in minutes */
		return esc_html( sprintf( __( '%1$s (%2$d min)', 'duty-planner' ), substr( $spot->start_time, 0, 5 ), $spot->duration_min ) );
	}

	protected function column_people( $spot ) {
		return esc_html( $spot->min_people . ' – ' . $spot->max_people );
	}

	protected function column_reminder( $spot ) {
		if ( ! $spot->reminder_offset_hours ) {
			return esc_html__( 'Off', 'duty-planner' );
		}
		/* translators: %d: hours */
		return esc_html( sprintf( _n( '%d hour before', '%d hours before', $spot->reminder_offset_hours, 'duty-planner' ), $spot->reminder_offset_hours ) );
	}

	protected function column_access( $spot ) {
		if ( '' !== trim( $spot->allowlist ) ) {
			return '<span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Own allowlist', 'duty-planner' );
		}
		if ( Allowlist::is_restricted( $spot ) ) {
			return '<span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Global allowlist', 'duty-planner' );
		}
		return esc_html__( 'Open', 'duty-planner' );
	}
}
