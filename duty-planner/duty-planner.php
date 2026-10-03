<?php
/**
 * Plugin Name:       Duty Planner
 * Description:       Plan recurring duties ("spots"), let people sign up, send reminders and alert admins about duties that still need people.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       duty-planner
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'DUTYPLAN_VERSION', '1.2.0' );
define( 'DUTYPLAN_FILE', __FILE__ );
define( 'DUTYPLAN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DUTYPLAN_URL', plugin_dir_url( __FILE__ ) );

foreach ( array(
	'settings',
	'i18n',
	'time',
	'recurrence',
	'repository',
	'allowlist',
	'occurrences',
	'mailer',
	'registration-service',
	'scheduler',
	'rest-controller',
	'shortcode',
	'cancel-handler',
	'privacy',
	'unlisted',
	'installer',
	'plugin',
) as $dutyplan_file ) {
	require_once DUTYPLAN_DIR . "includes/class-{$dutyplan_file}.php";
}

if ( is_admin() ) {
	require_once DUTYPLAN_DIR . 'admin/class-admin.php';
}

register_activation_hook( __FILE__, array( 'DutyPlanner\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DutyPlanner\\Installer', 'deactivate' ) );

// Everything that may translate strings runs on init (WP 6.7+ warns about earlier translation loading).
add_action( 'init', array( 'DutyPlanner\\Plugin', 'init' ), 0 );
