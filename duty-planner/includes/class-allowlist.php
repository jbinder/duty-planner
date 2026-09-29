<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Optional restriction of who may register.
 *
 * Entries are full addresses (anna@example.org) or whole domains (@example.org).
 * A spot's own list overrides the global list; no list at all means open registration.
 */
class Allowlist {

	/** @return string[] */
	public static function parse( ?string $text ): array {
		$entries = preg_split( '/[\s,;]+/', strtolower( (string) $text ) );
		return array_values( array_filter( array_map( 'trim', $entries ) ) );
	}

	public static function is_valid_entry( string $entry ): bool {
		if ( '@' === substr( $entry, 0, 1 ) ) {
			return (bool) preg_match( '/^@[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $entry );
		}
		return (bool) is_email( $entry );
	}

	public static function sanitize( string $text ): string {
		$valid = array_filter( self::parse( $text ), array( self::class, 'is_valid_entry' ) );
		return implode( "\n", array_unique( $valid ) );
	}

	/** @return string[] */
	public static function effective( $spot ): array {
		$own = self::parse( $spot->allowlist ?? '' );
		return $own ?: self::parse( Settings::get( 'allowlist' ) );
	}

	public static function is_restricted( $spot ): bool {
		return (bool) self::effective( $spot );
	}

	public static function is_allowed( $spot, string $email ): bool {
		$list = self::effective( $spot );
		if ( ! $list ) {
			return true;
		}
		$email = strtolower( trim( $email ) );
		$at    = strrpos( $email, '@' );
		if ( false === $at ) {
			return false;
		}
		return in_array( $email, $list, true ) || in_array( substr( $email, $at ), $list, true );
	}
}
