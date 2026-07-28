<?php
/**
 * REST API — POST /wp-json/waicb/v1/chat endpoint.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Rest_Api
 *
 * Registers and handles the single REST endpoint used by the chatbot widget.
 */
class WAICB_Rest_Api {

	/** REST namespace. */
	const NAMESPACE = 'waicb/v1';

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle POST /wp-json/waicb/v1/chat.
	 *
	 * @param WP_REST_Request $request Incoming request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_chat( WP_REST_Request $request ) {
		// Plugin disabled globally.
		if ( ! get_option( 'waicb_enabled', true ) ) {
			return new WP_Error(
				'disabled',
				__( 'Le chatbot est désactivé.', 'chatwick' ),
				array( 'status' => 503 )
			);
		}

		try {
			$body    = $request->get_json_params();
			$nonce   = isset( $body['nonce'] ) ? sanitize_text_field( $body['nonce'] ) : '';
			$message = isset( $body['message'] ) ? $body['message'] : '';
			$sk_body = isset( $body['session_key'] ) ? $body['session_key'] : '';

			// 1. Verify nonce.
			WAICB_Security::verify_nonce( $nonce );

			// 2. Rate limit by IP.
			$ip_hash = WAICB_Security::hash_ip();
			WAICB_Security::check_rate_limit( $ip_hash );

			// 3. Verify session_key ownership: cookie must match body.
			$cookie_key  = WAICB_Session_Manager::get_cookie_key();
			$body_sk     = WAICB_Security::sanitize_session_key( $sk_body );

			if ( '' === $body_sk || $cookie_key !== $body_sk ) {
				return new WP_Error(
					'forbidden',
					__( 'Accès refusé.', 'chatwick' ),
					array( 'status' => 403 )
				);
			}

			// 4. Sanitise message.
			$clean_message = WAICB_Security::sanitize_message( $message );

			// 5. Get/create DB session.
			$session_id = WAICB_Session_Manager::get_or_create( $body_sk );

			// 6. Retrieve history.
			$limit   = (int) get_option( 'waicb_history_limit', 20 );
			$history = WAICB_Database::get_messages( $session_id, $limit );

			// Allow external filtering of the payload.
			$history = apply_filters( 'waicb_messages_payload', $history, $session_id );

			// 7. Dispatch to AI engine ($body_sk = visitor key, for per-conversation billing).
			$result = WAICB_Api_Router::dispatch( $clean_message, $session_id, $history, $body_sk );

			$reply = isset( $result['reply'] ) ? $result['reply'] : '';

			// Filter reply before saving / sending.
			$reply = apply_filters( 'waicb_before_send_response', $reply, $session_id );

			// 8. Save messages.
			WAICB_Database::save_message( $session_id, 'user', $clean_message );
			WAICB_Database::save_message( $session_id, 'assistant', $reply );

			// 9. Fire action hook.
			do_action( 'waicb_after_exchange_saved', $session_id, $clean_message, $reply );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => array(
						'reply'       => $reply,
						'session_key' => $body_sk,
					),
				)
			);

		} catch ( InvalidArgumentException $e ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array( 'message' => $e->getMessage() ),
				)
			);
		} catch ( RuntimeException $e ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'data'    => array( 'message' => $e->getMessage() ),
				)
			);
		}
	}

	/**
	 * AJAX handler — test the Chatwick Cloud connection from the admin.
	 *
	 * Sends an empty message: the proxy authenticates the account key first,
	 * then rejects the empty message (HTTP 400) *before* debiting a credit.
	 * So 200/400 ⇒ key valid, 401 ⇒ invalid key, 403 ⇒ suspended/domain.
	 *
	 * @return void
	 */
	public function handle_test_api() {
		check_ajax_referer( 'waicb_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'chatwick' ) ) );
			return;
		}

		$raw_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		// Un champ laissé sur le masque (« **** ») ne contient pas de nouvelle clé :
		// on teste alors la clé déjà enregistrée, comme le fait l'enregistrement des réglages.
		$is_mask     = false !== strpos( $raw_key, '****' );
		$account_key = ( '' !== $raw_key && ! $is_mask ) ? $raw_key : WAICB_Crypto::decrypt( get_option( 'waicb_cloud_key', '' ) );

		if ( '' === $account_key ) {
			wp_send_json_error( array( 'message' => __( 'Clé de compte manquante. Enregistrez d\'abord les réglages.', 'chatwick' ) ) );
			return;
		}

		$response = wp_remote_post(
			WAICB_CLOUD_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'account_key' => $account_key,
						'message'     => '',
						'site_url'    => home_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 200 (improbable avec message vide) ou 400 (« Message vide ») ⇒ la clé est valide.
		if ( 200 === $code || 400 === $code ) {
			wp_send_json_success( array( 'message' => __( 'Connexion réussie ✓ (clé valide)', 'chatwick' ) ) );
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$err  = is_array( $data ) && isset( $data['error'] ) ? $data['error'] : 'HTTP ' . (int) $code;
		wp_send_json_error( array( 'message' => $err ) );
	}

	/**
	 * AJAX handler — return the Chatwick credit balance for the dashboard.
	 *
	 * Calls the read-only status endpoint with the stored account key (which
	 * never leaves the server). Consumes no credit.
	 *
	 * @return void
	 */
	public function handle_credits() {
		check_ajax_referer( 'waicb_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'chatwick' ) ) );
			return;
		}

		$account_key = WAICB_Crypto::decrypt( get_option( 'waicb_cloud_key', '' ) );
		if ( '' === $account_key ) {
			wp_send_json_error( array( 'message' => __( 'Clé de compte non configurée.', 'chatwick' ) ) );
			return;
		}

		$response = wp_remote_post(
			WAICB_CLOUD_STATUS_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'account_key' => $account_key,
						'site_url'    => home_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $code && is_array( $data ) && isset( $data['credits_left'] ) ) {
			wp_send_json_success(
				array(
					'credits'       => (int) $data['credits_left'],
					'quota'         => isset( $data['quota'] ) && null !== $data['quota'] ? (int) $data['quota'] : null,
					'quota_used'    => isset( $data['quota_used'] ) ? (int) $data['quota_used'] : 0,
					'quota_pct'     => isset( $data['quota_pct'] ) ? (int) $data['quota_pct'] : 0,
					'autonomy_days' => isset( $data['autonomy_days'] ) && null !== $data['autonomy_days'] ? (int) $data['autonomy_days'] : null,
				)
			);
			return;
		}

		$err = is_array( $data ) && isset( $data['error'] ) ? $data['error'] : 'HTTP ' . (int) $code;
		wp_send_json_error( array( 'message' => $err ) );
	}
}
