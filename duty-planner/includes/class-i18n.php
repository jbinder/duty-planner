<?php
namespace DutyPlanner;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin language, independent of the WordPress language if configured.
 *
 * The plugin loads its own translation file for the chosen language and
 * formats dates with its own day/month names, so a German calendar works
 * even on a site whose WordPress language is English (and vice versa).
 */
class I18n {

	const DOMAIN = 'duty-planner';

	/** @var string Locale whose translations are currently loaded. */
	private static $loaded = 'en_US';

	/** @return array<string,string> Supported locale => native name. */
	public static function languages(): array {
		return array(
			'en_US' => 'English',
			'de_DE' => 'Deutsch',
		);
	}

	public static function init() {
		// Translations come only from this plugin's languages/ folder, so the
		// language setting is not overridden by language packs.
		add_filter( 'override_load_textdomain', array( self::class, 'block_foreign_files' ), 10, 3 );
		// WordPress drops plugin translations when it switches locale (e.g. for some core emails).
		add_action( 'change_locale', array( self::class, 'load' ) );
		self::$loaded = self::locale();
		self::load();
	}

	/** The configured locale, or the supported one closest to the site language. */
	public static function locale(): string {
		$saved  = get_option( Settings::OPTION, array() );
		$chosen = is_array( $saved ) ? (string) ( $saved['language'] ?? '' ) : '';
		if ( isset( self::languages()[ $chosen ] ) ) {
			return $chosen;
		}
		return self::closest( get_locale() );
	}

	/** Map any WordPress locale to a supported one (de_AT, de_DE_formal … → de_DE). */
	public static function closest( string $locale ): string {
		if ( isset( self::languages()[ $locale ] ) ) {
			return $locale;
		}
		$lang = strtolower( strtok( $locale, '_' ) );
		foreach ( array_keys( self::languages() ) as $supported ) {
			if ( strtolower( strtok( $supported, '_' ) ) === $lang ) {
				return $supported;
			}
		}
		return 'en_US';
	}

	/** BCP 47 tag for the browser (Intl date formatting in the calendar). */
	public static function js_locale(): string {
		return str_replace( '_', '-', self::$loaded );
	}

	/**
	 * Load the plugin's translation file for the configured language.
	 *
	 * The file is registered under WordPress' *current* locale: load_textdomain()
	 * makes the given locale the global one, so passing the plugin language
	 * would switch every other plugin and the theme to it, and the next
	 * translation load elsewhere would switch it back.
	 */
	public static function load() {
		unload_textdomain( self::DOMAIN );
		if ( 'en_US' === self::$loaded ) {
			return;
		}
		$current = class_exists( 'WP_Translation_Controller' )
			? \WP_Translation_Controller::get_instance()->get_locale()
			: determine_locale();
		load_textdomain( self::DOMAIN, self::file( self::$loaded ), $current );
	}

	private static function file( string $locale ): string {
		return DUTYPLAN_DIR . 'languages/' . self::DOMAIN . '-' . $locale . '.mo';
	}

	/**
	 * Run a callback with the plugin's strings in another language.
	 *
	 * Uses gettext filters instead of reloading translation files, so the
	 * loaded translations stay untouched.
	 *
	 * @return mixed
	 */
	public static function with_locale( string $locale, callable $callback ) {
		if ( $locale === self::$loaded ) {
			return $callback();
		}
		$mo = null;
		if ( 'en_US' !== $locale ) {
			if ( ! class_exists( 'MO' ) ) {
				require_once ABSPATH . WPINC . '/pomo/mo.php';
			}
			$mo = new \MO();
			if ( ! $mo->import_from_file( self::file( $locale ) ) ) {
				$mo = null;
			}
		}
		$plain   = static function ( $translation, $text, $domain ) use ( $mo ) {
			return self::DOMAIN === $domain ? ( $mo ? $mo->translate( $text ) : $text ) : $translation;
		};
		$context = static function ( $translation, $text, $ctx, $domain ) use ( $mo ) {
			return self::DOMAIN === $domain ? ( $mo ? $mo->translate( $text, $ctx ) : $text ) : $translation;
		};
		add_filter( 'gettext', $plain, PHP_INT_MAX, 3 );
		add_filter( 'gettext_with_context', $context, PHP_INT_MAX, 4 );
		try {
			return $callback();
		} finally {
			remove_filter( 'gettext', $plain, PHP_INT_MAX );
			remove_filter( 'gettext_with_context', $context, PHP_INT_MAX );
		}
	}

	public static function block_foreign_files( $override, $domain, $mofile ) {
		if ( self::DOMAIN === $domain && 0 !== strpos( wp_normalize_path( (string) $mofile ), wp_normalize_path( DUTYPLAN_DIR . 'languages/' ) ) ) {
			return true;
		}
		return $override;
	}

	/** Whether the plugin speaks the same language as WordPress (then the site's date/time formats fit). */
	private static function matches_site(): bool {
		return self::closest( get_locale() ) === self::$loaded;
	}

	public static function date_format(): string {
		/* translators: PHP date format for dates, see https://www.php.net/manual/datetime.format.php */
		return self::matches_site() ? (string) get_option( 'date_format' ) : _x( 'F j, Y', 'date format', 'duty-planner' );
	}

	public static function long_date_format(): string {
		/* translators: PHP date format for dates with weekday, see https://www.php.net/manual/datetime.format.php */
		return self::matches_site() ? 'l, ' . get_option( 'date_format' ) : _x( 'l, F j, Y', 'long date format', 'duty-planner' );
	}

	public static function time_format(): string {
		/* translators: PHP time format, see https://www.php.net/manual/datetime.format.php */
		return self::matches_site() ? (string) get_option( 'time_format' ) : _x( 'g:i a', 'time format', 'duty-planner' );
	}

	/**
	 * Like wp_date(), but with the plugin's day and month names.
	 */
	public static function date( string $format, int $timestamp ): string {
		$names = array(
			'l' => self::weekday( (int) wp_date( 'w', $timestamp ) ),
			'D' => self::weekday( (int) wp_date( 'w', $timestamp ), true ),
			'F' => self::month( (int) wp_date( 'n', $timestamp ) ),
			'M' => self::month( (int) wp_date( 'n', $timestamp ), true ),
		);
		$out = '';
		$len = strlen( $format );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $format[ $i ];
			if ( '\\' === $c ) {
				$out .= $c . ( $format[ ++$i ] ?? '' );
			} elseif ( isset( $names[ $c ] ) ) {
				$out .= addcslashes( $names[ $c ], 'A..Za..z\\' );
			} else {
				$out .= $c;
			}
		}
		return wp_date( $out, $timestamp );
	}

	/** @param int $day 0 = Sunday … 6 = Saturday. */
	public static function weekday( int $day, bool $short = false ): string {
		$long = array(
			__( 'Sunday', 'duty-planner' ),
			__( 'Monday', 'duty-planner' ),
			__( 'Tuesday', 'duty-planner' ),
			__( 'Wednesday', 'duty-planner' ),
			__( 'Thursday', 'duty-planner' ),
			__( 'Friday', 'duty-planner' ),
			__( 'Saturday', 'duty-planner' ),
		);
		$abbr = array(
			_x( 'Sun', 'weekday abbreviation', 'duty-planner' ),
			_x( 'Mon', 'weekday abbreviation', 'duty-planner' ),
			_x( 'Tue', 'weekday abbreviation', 'duty-planner' ),
			_x( 'Wed', 'weekday abbreviation', 'duty-planner' ),
			_x( 'Thu', 'weekday abbreviation', 'duty-planner' ),
			_x( 'Fri', 'weekday abbreviation', 'duty-planner' ),
			_x( 'Sat', 'weekday abbreviation', 'duty-planner' ),
		);
		return ( $short ? $abbr : $long )[ $day % 7 ];
	}

	/** @param int $month 1 = January … 12 = December. */
	public static function month( int $month, bool $short = false ): string {
		$long = array(
			1  => __( 'January', 'duty-planner' ),
			2  => __( 'February', 'duty-planner' ),
			3  => __( 'March', 'duty-planner' ),
			4  => __( 'April', 'duty-planner' ),
			5  => __( 'May', 'duty-planner' ),
			6  => __( 'June', 'duty-planner' ),
			7  => __( 'July', 'duty-planner' ),
			8  => __( 'August', 'duty-planner' ),
			9  => __( 'September', 'duty-planner' ),
			10 => __( 'October', 'duty-planner' ),
			11 => __( 'November', 'duty-planner' ),
			12 => __( 'December', 'duty-planner' ),
		);
		$abbr = array(
			1  => _x( 'Jan', 'month abbreviation', 'duty-planner' ),
			2  => _x( 'Feb', 'month abbreviation', 'duty-planner' ),
			3  => _x( 'Mar', 'month abbreviation', 'duty-planner' ),
			4  => _x( 'Apr', 'month abbreviation', 'duty-planner' ),
			5  => _x( 'May', 'month abbreviation', 'duty-planner' ),
			6  => _x( 'Jun', 'month abbreviation', 'duty-planner' ),
			7  => _x( 'Jul', 'month abbreviation', 'duty-planner' ),
			8  => _x( 'Aug', 'month abbreviation', 'duty-planner' ),
			9  => _x( 'Sep', 'month abbreviation', 'duty-planner' ),
			10 => _x( 'Oct', 'month abbreviation', 'duty-planner' ),
			11 => _x( 'Nov', 'month abbreviation', 'duty-planner' ),
			12 => _x( 'Dec', 'month abbreviation', 'duty-planner' ),
		);
		return ( $short ? $abbr : $long )[ $month ];
	}
}
