<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Limited HTML for duty descriptions (lists, emphasis, links).
 */
class Formatting {

	/** @return array<string,array> Tags and attributes allowed in descriptions. */
	public static function allowed_tags(): array {
		$tags = array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'h4'     => array(),
			'h5'     => array(),
			'blockquote' => array(),
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
		);
		return (array) apply_filters( 'dutyplan_description_allowed_tags', $tags );
	}

	/** Clean description HTML for storage. */
	public static function sanitize_description( string $html ): string {
		return trim( wp_kses( $html, self::allowed_tags() ) );
	}

	/**
	 * Description as safe HTML for display. Plain-text descriptions keep their
	 * line breaks; links open in a new tab, as the calendar is an app-like page.
	 */
	public static function description_html( string $text ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}
		$html = wpautop( wp_kses( $text, self::allowed_tags() ) );
		return preg_replace( '/<a\s(?![^>]*\btarget=)/i', '<a target="_blank" rel="noopener" ', $html );
	}
}
