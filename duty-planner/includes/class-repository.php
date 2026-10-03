<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Database access for spots, registrations and skipped dates.
 */
class Repository {

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'dutyplan_' . $name;
	}

	/* ---------------------------------------------------------------- Spots */

	public static function normalize_spot( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row->id                    = (int) $row->id;
		$row->weekdays              = array_values( array_filter( array_map( 'intval', explode( ',', (string) $row->weekdays ) ) ) );
		$row->interval_n            = max( 1, (int) $row->interval_n );
		$row->end_date              = ( $row->end_date && '0000-00-00' !== $row->end_date ) ? $row->end_date : null;
		$row->all_day               = (bool) $row->all_day;
		$row->duration_min          = null === $row->duration_min ? null : (int) $row->duration_min;
		$row->min_people            = (int) $row->min_people;
		$row->max_people            = (int) $row->max_people;
		$row->reminder_offset_hours = (int) $row->reminder_offset_hours;
		$row->allowlist             = (string) $row->allowlist;
		$row->description           = (string) $row->description;
		$row->note_label            = (string) ( $row->note_label ?? '' );
		$row->active                = (bool) $row->active;
		return $row;
	}

	/**
	 * @param int[] $ids Restrict to these IDs (empty = all).
	 * @return array<int,object> Keyed by spot ID.
	 */
	public static function get_spots( bool $active_only = false, array $ids = array() ): array {
		global $wpdb;
		$where = array( '1=1' );
		if ( $active_only ) {
			$where[] = 'active = 1';
		}
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( $ids ) {
			$where[] = 'id IN (' . implode( ',', $ids ) . ')';
		}
		$rows  = $wpdb->get_results( 'SELECT * FROM ' . self::table( 'spots' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY title ASC, id ASC' );
		$spots = array();
		foreach ( $rows as $row ) {
			$spots[ (int) $row->id ] = self::normalize_spot( $row );
		}
		return $spots;
	}

	public static function get_spot( int $id ) {
		global $wpdb;
		return self::normalize_spot(
			$wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'spots' ) . ' WHERE id = %d', $id ) )
		);
	}

	public static function save_spot( array $data, int $id = 0 ): int {
		global $wpdb;
		$data['weekdays'] = implode( ',', $data['weekdays'] ?? array() );
		if ( $id ) {
			$wpdb->update( self::table( 'spots' ), $data, array( 'id' => $id ) );
			return $id;
		}
		$data['created_at'] = current_time( 'mysql', true );
		$wpdb->insert( self::table( 'spots' ), $data );
		return (int) $wpdb->insert_id;
	}

	public static function delete_spot( int $id ): void {
		global $wpdb;
		$wpdb->delete( self::table( 'registrations' ), array( 'spot_id' => $id ) );
		$wpdb->delete( self::table( 'skips' ), array( 'spot_id' => $id ) );
		$wpdb->delete( self::table( 'spots' ), array( 'id' => $id ) );
	}

	/* ---------------------------------------------------------------- Skips */

	/** @return array<int,array<string,bool>> spot_id => [date => true] */
	public static function skips( array $spot_ids, string $from, string $to ): array {
		global $wpdb;
		$spot_ids = array_filter( array_map( 'intval', $spot_ids ) );
		if ( ! $spot_ids ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT spot_id, occurrence_date FROM ' . self::table( 'skips' ) . ' WHERE spot_id IN (' . implode( ',', $spot_ids ) . ') AND occurrence_date BETWEEN %s AND %s',
				$from,
				$to
			)
		);
		$out = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->spot_id ][ $row->occurrence_date ] = true;
		}
		return $out;
	}

	public static function is_skipped( int $spot_id, string $date ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( 'SELECT 1 FROM ' . self::table( 'skips' ) . ' WHERE spot_id = %d AND occurrence_date = %s', $spot_id, $date )
		);
	}

	public static function add_skip( int $spot_id, string $date ): void {
		global $wpdb;
		$wpdb->replace( self::table( 'skips' ), array( 'spot_id' => $spot_id, 'occurrence_date' => $date ) );
	}

	public static function remove_skip( int $spot_id, string $date ): void {
		global $wpdb;
		$wpdb->delete( self::table( 'skips' ), array( 'spot_id' => $spot_id, 'occurrence_date' => $date ) );
	}

	/* -------------------------------------------------------- Registrations */

	public static function registrations_in_range( string $from, string $to, array $spot_ids = array() ): array {
		global $wpdb;
		$sql      = 'SELECT * FROM ' . self::table( 'registrations' ) . ' WHERE occurrence_date BETWEEN %s AND %s';
		$spot_ids = array_filter( array_map( 'intval', $spot_ids ) );
		if ( $spot_ids ) {
			$sql .= ' AND spot_id IN (' . implode( ',', $spot_ids ) . ')';
		}
		return $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY created_at ASC, id ASC', $from, $to ) );
	}

	public static function registrations_for( int $spot_id, string $date ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( 'registrations' ) . ' WHERE spot_id = %d AND occurrence_date = %s ORDER BY created_at ASC, id ASC', $spot_id, $date )
		);
	}

	public static function count_for( int $spot_id, string $date ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'registrations' ) . ' WHERE spot_id = %d AND occurrence_date = %s', $spot_id, $date )
		);
	}

	public static function email_registered( int $spot_id, string $date, string $email ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare( 'SELECT 1 FROM ' . self::table( 'registrations' ) . ' WHERE spot_id = %d AND occurrence_date = %s AND email = %s', $spot_id, $date, $email )
		);
	}

	public static function insert_registration( int $spot_id, string $date, string $name, string $email, string $note = '' ): int {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table( 'registrations' ),
			array(
				'spot_id'         => $spot_id,
				'occurrence_date' => $date,
				'display_name'    => $name,
				'email'           => $email,
				'note'            => $note,
				'created_at'      => current_time( 'mysql', true ),
			)
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function get_registration( int $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'registrations' ) . ' WHERE id = %d', $id ) );
	}

	public static function delete_registration( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table( 'registrations' ), array( 'id' => $id ) );
	}

	/** Unsent reminders whose occurrence lies within the reminder window of their spot. */
	public static function pending_reminders( string $today ): array {
		global $wpdb;
		$r = self::table( 'registrations' );
		$s = self::table( 'spots' );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.* FROM {$r} r INNER JOIN {$s} s ON s.id = r.spot_id
				 WHERE r.reminder_sent_at IS NULL
				   AND s.active = 1 AND s.reminder_offset_hours > 0
				   AND r.occurrence_date >= %s
				   AND r.occurrence_date <= DATE_ADD(%s, INTERVAL (s.reminder_offset_hours DIV 24 + 1) DAY)",
				$today,
				$today
			)
		);
	}

	public static function mark_reminded( int $id ): void {
		global $wpdb;
		$wpdb->update( self::table( 'registrations' ), array( 'reminder_sent_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
	}

	public static function registrations_by_email( string $email ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM ' . self::table( 'registrations' ) . ' WHERE email = %s ORDER BY occurrence_date ASC', strtolower( $email ) )
		);
	}

	public static function delete_registrations_by_email( string $email ): int {
		global $wpdb;
		return (int) $wpdb->delete( self::table( 'registrations' ), array( 'email' => strtolower( $email ) ) );
	}
}
