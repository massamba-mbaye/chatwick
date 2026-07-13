<?php
/**
 * Cryptography — AES-256-CBC encryption / decryption.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Crypto
 *
 * Encrypts and decrypts sensitive options (API key, system prompt)
 * using AES-256-CBC with a key derived from WordPress's AUTH_KEY.
 */
class WAICB_Crypto {

	/**
	 * Derive the 256-bit encryption key from AUTH_KEY (or wp_salt fallback).
	 *
	 * @return string 32-byte binary key.
	 */
	private static function get_key() {
		$source = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		return hash( 'sha256', $source, true );
	}

	/**
	 * Encrypt a plain-text string.
	 *
	 * Returns a base64-encoded string of the form: base64( iv . ciphertext ).
	 *
	 * @param string $plain Plain-text value to encrypt.
	 * @return string Encrypted, base64-encoded string; empty string on empty input.
	 */
	public static function encrypt( $plain ) {
		if ( '' === (string) $plain ) {
			return '';
		}

		$key       = self::get_key();
		$iv        = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a previously encrypted string.
	 *
	 * @param string $stored Base64-encoded encrypted value.
	 * @return string Decrypted plain-text; empty string on failure.
	 */
	public static function decrypt( $stored ) {
		if ( '' === (string) $stored ) {
			return '';
		}

		$key     = self::get_key();
		$decoded = base64_decode( $stored, true );

		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}

		$iv        = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );
		$plain     = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}
}
