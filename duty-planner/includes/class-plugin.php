<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps all plugin components.
 */
class Plugin {

	public static function init() {
		I18n::init();

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
