<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks registrations into WordPress' personal data export/erase tools.
 */
class Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'register_eraser' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['duty-planner'] = array(
			'exporter_friendly_name' => __( 'Duty Planner registrations', 'duty-planner' ),
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['duty-planner'] = array(
			'eraser_friendly_name' => __( 'Duty Planner registrations', 'duty-planner' ),
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email, $page = 1 ) {
		$items = array();
		foreach ( Repository::registrations_by_email( $email ) as $reg ) {
			$spot    = Repository::get_spot( (int) $reg->spot_id );
			$items[] = array(
				'group_id'    => 'duty-planner',
				'group_label' => __( 'Duty registrations', 'duty-planner' ),
				'item_id'     => 'dutyplan-registration-' . $reg->id,
				'data'        => array(
					array( 'name' => __( 'Duty', 'duty-planner' ), 'value' => $spot ? $spot->title : '#' . $reg->spot_id ),
					array( 'name' => __( 'Date', 'duty-planner' ), 'value' => $reg->occurrence_date ),
					array( 'name' => __( 'Display name', 'duty-planner' ), 'value' => $reg->display_name ),
					array( 'name' => __( 'Email', 'duty-planner' ), 'value' => $reg->email ),
					array( 'name' => __( 'Registered at (UTC)', 'duty-planner' ), 'value' => $reg->created_at ),
				),
			);
		}
		return array( 'data' => $items, 'done' => true );
	}

	public static function erase( $email, $page = 1 ) {
		$removed = Repository::delete_registrations_by_email( $email );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
