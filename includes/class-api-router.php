<?php
/**
 * API Router — dispatches to Chat or Assistant engine.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Api_Router
 *
 * Relaie chaque requête au service Cloud (Chatwick). Le site n'héberge aucune
 * clé OpenAI/Anthropic : le SaaS détient les clés, vérifie les crédits et appelle
 * l'IA. Les instructions (persona) du site sont transmises au proxy.
 */
class WAICB_Api_Router {

	/**
	 * Dispatch a chat request to the Cloud engine.
	 *
	 * @param string $message         Sanitised user message.
	 * @param int    $session_id      DB session ID (unused for Cloud; kept for signature stability).
	 * @param array  $history         Array of {role, content} history entries.
	 * @param string $conversation_id Visitor conversation key (session_key) for per-conversation billing.
	 * @return array {reply: string, usage: array, model: string}
	 * @throws RuntimeException If the API call fails.
	 */
	public static function dispatch( $message, $session_id, $history, $conversation_id = '' ) {
		$account_key = WAICB_Crypto::decrypt( get_option( 'waicb_cloud_key', '' ) );

		if ( '' === $account_key ) {
			throw new RuntimeException( esc_html__( 'Clé de compte Chatwick non configurée.', 'chatwick' ) );
		}

		$instructions = (string) get_option( 'waicb_instructions', '' );

		$engine = new WAICB_Cloud_Chat( WAICB_CLOUD_URL, $account_key, $instructions );
		return $engine->chat( $message, $history, $conversation_id );
	}
}
