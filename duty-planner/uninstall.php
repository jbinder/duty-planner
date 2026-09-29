<?php
/**
 * Removes plugin data on uninstall, if the admin opted in.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'dutyplan_tick' );

$dutyplan_settings = get_option( 'dutyplan_settings' );
if ( empty( $dutyplan_settings['delete_data'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'registrations', 'skips', 'spots' ) as $dutyplan_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dutyplan_{$dutyplan_table}" ); // phpcs:ignore WordPress.DB
}
delete_option( 'dutyplan_settings' );
delete_option( 'dutyplan_db_version' );
delete_option( 'dutyplan_last_alert_week' );
