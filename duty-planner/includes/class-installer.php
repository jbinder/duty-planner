<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Creates/updates database tables and handles (de)activation.
 */
class Installer {

	const DB_VERSION = '2';

	public static function activate() {
		self::create_tables();
		Scheduler::schedule();
	}

	public static function deactivate() {
		Scheduler::unschedule();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'dutyplan_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$spots   = Repository::table( 'spots' );
		$regs    = Repository::table( 'registrations' );
		$skips   = Repository::table( 'skips' );

		dbDelta(
			"CREATE TABLE {$spots} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  title varchar(190) NOT NULL DEFAULT '',
  description text NULL,
  location varchar(190) NOT NULL DEFAULT '',
  frequency varchar(10) NOT NULL DEFAULT 'weekly',
  weekdays varchar(20) NOT NULL DEFAULT '',
  interval_n smallint(5) unsigned NOT NULL DEFAULT 1,
  start_date date NOT NULL,
  end_date date NULL DEFAULT NULL,
  all_day tinyint(1) NOT NULL DEFAULT 0,
  start_time time NULL DEFAULT NULL,
  duration_min int(10) unsigned NULL DEFAULT NULL,
  min_people smallint(5) unsigned NOT NULL DEFAULT 1,
  max_people smallint(5) unsigned NOT NULL DEFAULT 1,
  reminder_offset_hours smallint(5) unsigned NOT NULL DEFAULT 24,
  allowlist text NULL,
  note_label varchar(190) NOT NULL DEFAULT '',
  active tinyint(1) NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$regs} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  spot_id bigint(20) unsigned NOT NULL,
  occurrence_date date NOT NULL,
  display_name varchar(100) NOT NULL DEFAULT '',
  email varchar(190) NOT NULL DEFAULT '',
  note varchar(500) NOT NULL DEFAULT '',
  reminder_sent_at datetime NULL DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY spot_date_email (spot_id,occurrence_date,email),
  KEY occurrence_date (occurrence_date),
  KEY email (email)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$skips} (
  spot_id bigint(20) unsigned NOT NULL,
  occurrence_date date NOT NULL,
  PRIMARY KEY  (spot_id,occurrence_date)
) {$charset};"
		);

		update_option( 'dutyplan_db_version', self::DB_VERSION );
	}
}
