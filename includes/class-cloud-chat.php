<?php
/**
 * Cloud (SaaS prépayé) chat engine.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Cloud_Chat
 *
 * Relaie la conversation vers un SaaS prépayé (ex. Chatwick) qui détient les
 * clés OpenAI/Anthropic côté serveur. Le site n'envoie qu'une clé de compte ;
 * le SaaS vérifie les crédits, appelle l'IA et décrémente 1 crédit par message.
 *
 * Utilise wp_remote_* (zéro dépendance), comme les autres moteurs.
 */
class WAICB_Cloud_Chat {

	/** @var string Endpoint du proxy IA (…/api/chat.php). */
	private $endpoint;

	/** @var string Clé de compte du SaaS. */
	private $account_key;

	/** @var string Instructions (persona) propres au site, transmises au SaaS. */
	private $instructions;

	/**
	 * Constructor.
	 *
	 * @param string $endpoint     URL du proxy (…/api/chat.php).
	 * @param string $account_key  Clé de compte du SaaS.
	 * @param string $instructions Instructions/persona du site (optionnel).
	 */
	public function __construct( $endpoint, $account_key, $instructions = '' ) {
		$this->endpoint     = $endpoint;
		$this->account_key  = $account_key;
		$this->instructions = $instructions;
	}

	/**
	 * Envoie un message au SaaS et renvoie la réponse de l'assistant.
	 *
	 * @param string $message User message (already sanitised).
	 * @param array  $history Array of {role, content} history rows from DB.
	 * @return array {reply: string, usage: array, model: string}
	 * @throws RuntimeException On HTTP or API errors.
	 */
	public function chat( $message, $history, $conversation_id = '' ) {
		$payload = array(
			'account_key'     => $this->account_key,
			'message'         => $message,
			'history'         => $this->normalize_history( $history ),
			'instructions'    => $this->instructions,
			'conversation_id' => $conversation_id,
			// Repli d'identité côté SaaS quand le cookie visiteur manque (navigation
			// privée, cookies bloqués) : IP visiteur hashée + salée, anonyme et stable.
			'ip_hash'         => WAICB_Security::hash_ip(),
			'site_url'        => home_url(),
			// Nom lisible du site : alimente le persona par défaut de l'assistant
			// côté SaaS (« l'assistant virtuel de <nom> ») quand aucune instruction
			// personnalisée n'est définie.
			'site_name'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new RuntimeException( esc_html( $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			$err = is_array( $data ) && isset( $data['error'] ) ? $data['error'] : 'HTTP ' . (int) $code;
			throw new RuntimeException( esc_html( $err ) );
		}

		if ( empty( $data['reply'] ) ) {
			throw new RuntimeException( esc_html__( 'Réponse Cloud inattendue.', 'chatwick' ) );
		}

		// Le SaaS facture au crédit (1 message), pas au token : usage non détaillé.
		return array(
			'reply' => $data['reply'],
			'usage' => array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
			),
			'model' => isset( $data['model'] ) && '' !== $data['model'] ? $data['model'] : 'cloud',
		);
	}

	/**
	 * Réduit l'historique DB à des entrées {role, content} valides (sans system).
	 *
	 * @param array $history Lignes d'historique.
	 * @return array
	 */
	private function normalize_history( $history ) {
		$out = array();
		foreach ( (array) $history as $row ) {
			if ( ! isset( $row['role'], $row['content'] ) || 'system' === $row['role'] ) {
				continue;
			}
			$out[] = array(
				'role'    => 'assistant' === $row['role'] ? 'assistant' : 'user',
				'content' => (string) $row['content'],
			);
		}
		return $out;
	}
}
