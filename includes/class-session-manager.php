<?php
/**
 * Session manager — cookie UUID, DB session resolution.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Session_Manager
 *
 * Manages the visitor's UUID-based session stored in a cookie.
 * Sessions are persisted in the DB (not PHP native sessions).
 */
class WAICB_Session_Manager {

	/** Cookie name. */
	const COOKIE_NAME = 'waicb_session';

	/**
	 * Return the current session_key from the cookie.
	 *
	 * @return string UUID or empty string if absent / invalid.
	 */
	public static function get_cookie_key() {
		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return WAICB_Security::sanitize_session_key(
				sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) )
			);
		}
		return '';
	}

	/**
	 * Resolve (or create) the DB session for a given session_key.
	 *
	 * @param string $session_key UUID from the cookie / request body.
	 * @return int Session ID in the DB.
	 */
	public static function get_or_create( $session_key ) {
		$mode       = get_option( 'waicb_mode', 'chat' );
		$ip_hash    = WAICB_Security::hash_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		return WAICB_Database::get_or_create_session( $session_key, $mode, $ip_hash, $user_agent );
	}

	/**
	 * Set the session cookie (server-side).
	 *
	 * The cookie is HttpOnly, SameSite=Lax, and Secure when the site uses HTTPS.
	 *
	 * @param string $session_key UUID v4.
	 * @return void
	 */
	public static function set_cookie( $session_key ) {
		$days    = (int) get_option( 'waicb_cookie_days', 90 );
		$expires = time() + $days * DAY_IN_SECONDS;
		$secure  = is_ssl();

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				self::COOKIE_NAME,
				$session_key,
				array(
					'expires'  => $expires,
					'path'     => '/',
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		} else {
			// Fallback for PHP < 7.3 — SameSite via header trick.
			$samesite_flag = '; SameSite=Lax';
			setcookie(
				self::COOKIE_NAME,
				$session_key,
				$expires,
				'/' . $samesite_flag,
				'',
				$secure,
				true
			);
		}
	}
}
