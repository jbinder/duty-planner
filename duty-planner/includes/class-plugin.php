<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps all plugin components.
 */
class Plugin {

	public static function init() {
		load_plugin_textdomain( 'duty-planner', false, dirname( plugin_basename( DUTYPLAN_FILE ) ) . '/languages' );

		Installer::maybe_upgrade();
		Scheduler::init();
		Rest_Controller::init();
		Shortcode::init();
		Cancel_Handler::init();
		Privacy::init();
		Unlisted::init();

		if ( is_admin() ) {
			Admin::init();
		}
	}
}
