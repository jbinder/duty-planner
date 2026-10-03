<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Global plugin settings, stored as one option array.
 */
class Settings {

	const OPTION = 'dutyplan_settings';

	public static function defaults(): array {
		return array(
			'language'               => '',
			'calendar_page_id'       => 0,
			'unlisted'               => 0,
			'from_name'              => '',
			'from_email'             => '',
			'default_reminder_hours' => 24,
			'allday_reference_time'  => '08:00',
			'alert_recipients'       => (string) get_option( 'admin_email' ),
			'alert_weekday'          => 5,
			'alert_time'             => '18:00',
			'alert_target'           => 'next',
			'allowlist'              => '',
			'delete_data'            => 0,
		) + Mailer::default_templates();
	}

	public static function all(): array {
		$saved    = get_option( self::OPTION, array() );
		$defaults = self::defaults();
		$all      = array_merge( $defaults, is_array( $saved ) ? $saved : array() );
		// Unchanged email templates follow the plugin language.
		foreach ( Mailer::default_templates() as $key => $default ) {
			if ( self::is_default_template( $key, (string) $all[ $key ] ) ) {
				$all[ $key ] = $default;
			}
		}
		return $all;
	}

	/** Whether a template text is empty or one of the shipped defaults (in any language). */
	public static function is_default_template( string $key, string $value ): bool {
		static $variants = null;
		if ( null === $variants ) {
			$variants = array();
			foreach ( array_keys( I18n::languages() ) as $locale ) {
				$templates = I18n::with_locale( $locale, array( Mailer::class, 'default_templates' ) );
				foreach ( $templates as $k => $text ) {
					$variants[ $k ][] = self::normalize( $text );
					// Defaults from before the {stats} placeholder existed.
					$variants[ $k ][] = self::normalize( str_replace( "\n\n{stats}", '', $text ) );
				}
			}
		}
		$value = self::normalize( $value );
		return '' === $value || in_array( $value, $variants[ $key ] ?? array(), true );
	}

	private static function normalize( string $text ): string {
		return trim( str_replace( "\r\n", "\n", $text ) );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function calendar_url(): string {
		$id = (int) self::get( 'calendar_page_id' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return (string) get_permalink( $id );
		}
		return home_url( '/' );
	}

	/** @return string[] */
	public static function alert_recipients(): array {
		return array_values( array_filter( preg_split( '/[\s,;]+/', (string) self::get( 'alert_recipients' ) ), 'is_email' ) );
	}

	public static function sanitize( $in ): array {
		$in  = is_array( $in ) ? $in : array(); // options.php already unslashed the input.
		$def = self::defaults();
		$out = array();

		$out['language']               = isset( I18n::languages()[ $in['language'] ?? '' ] ) ? $in['language'] : '';
		$out['calendar_page_id']       = absint( $in['calendar_page_id'] ?? 0 );
		$out['unlisted']               = empty( $in['unlisted'] ) ? 0 : 1;
		$out['from_name']              = sanitize_text_field( $in['from_name'] ?? '' );
		$from_email                    = sanitize_email( $in['from_email'] ?? '' );
		$out['from_email']             = is_email( $from_email ) ? $from_email : '';
		$out['default_reminder_hours'] = min( 720, absint( $in['default_reminder_hours'] ?? $def['default_reminder_hours'] ) );
		$out['allday_reference_time']  = self::sanitize_time( $in['allday_reference_time'] ?? '', $def['allday_reference_time'] );

		$recipients              = array_map( 'sanitize_email', preg_split( '/[\s,;]+/', (string) ( $in['alert_recipients'] ?? '' ) ) );
		$out['alert_recipients'] = implode( "\n", array_filter( $recipients, 'is_email' ) );
		$weekday                 = absint( $in['alert_weekday'] ?? 0 );
		$out['alert_weekday']    = ( $weekday >= 1 && $weekday <= 7 ) ? $weekday : $def['alert_weekday'];
		$out['alert_time']       = self::sanitize_time( $in['alert_time'] ?? '', $def['alert_time'] );
		$out['alert_target']     = in_array( $in['alert_target'] ?? '', array( 'next', 'current' ), true ) ? $in['alert_target'] : 'next';

		$out['allowlist']   = Allowlist::sanitize( (string) ( $in['allowlist'] ?? '' ) );
		$out['delete_data'] = empty( $in['delete_data'] ) ? 0 : 1;

		foreach ( array_keys( Mailer::default_templates() ) as $key ) {
			$value       = (string) ( $in[ $key ] ?? '' );
			$value       = substr( $key, -8 ) === '_subject' ? sanitize_text_field( $value ) : sanitize_textarea_field( $value );
			// Store defaults as empty, so they keep following the language setting.
			$out[ $key ] = self::is_default_template( $key, $value ) ? '' : $value;
		}

		return $out;
	}

	public static function sanitize_time( $value, string $fallback ): string {
		$value = substr( trim( (string) $value ), 0, 5 );
		return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
	}
}
