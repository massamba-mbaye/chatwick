<?php
/**
 * Frontend chatbot widget — bubble + shortcode.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Chatbot_Widget
 *
 * Registers the [waicb_chatbot] shortcode and injects the floating bubble
 * on all public pages when the plugin is enabled.
 */
class WAICB_Chatbot_Widget {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! get_option( 'waicb_enabled', true ) ) {
			return;
		}

		// Shortcode is always registered regardless of display rules.
		add_shortcode( 'waicb_chatbot', array( $this, 'render_shortcode' ) );

		// Floating bubble respects display rules — check on wp action when query is ready.
		add_action( 'wp', array( $this, 'maybe_init_bubble' ) );
	}

	/**
	 * Register bubble hooks only if display rules allow it on the current page.
	 *
	 * @return void
	 */
	public function maybe_init_bubble() {
		$mode  = get_option( 'waicb_display_mode', 'all' );
		$pages = get_option( 'waicb_display_pages', array() );

		if ( 'specific' === $mode && ! empty( $pages ) ) {
			if ( ! is_page( $pages ) ) {
				return; // Not one of the allowed pages.
			}
		} elseif ( 'exclude' === $mode && ! empty( $pages ) ) {
			if ( is_page( $pages ) ) {
				return; // Explicitly excluded.
			}
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_bubble' ) );
	}

	/**
	 * Enqueue frontend CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'waicb-chatbot',
			WAICB_URL . 'public/assets/chatbot.css',
			array(),
			WAICB_VERSION
		);

		wp_enqueue_script(
			'waicb-chatbot',
			WAICB_URL . 'public/assets/chatbot.js',
			array(),
			WAICB_VERSION,
			true
		);

		wp_localize_script(
			'waicb-chatbot',
			'waicbConfig',
			array(
				'restUrl'        => rest_url( 'waicb/v1/chat' ),
				'nonce'          => wp_create_nonce( 'waicb_chat_nonce' ),
				'restNonce'      => wp_create_nonce( 'wp_rest' ),
				'position'       => get_option( 'waicb_widget_position', 'bottom-right' ),
				'color'          => get_option( 'waicb_widget_color', '#C49A2E' ),
				'title'          => get_option( 'waicb_widget_title', __( 'Assistant IA', 'chatwick' ) ),
				'welcomeMessage' => get_option( 'waicb_welcome_message', __( 'Bonjour ! Comment puis-je vous aider ?', 'chatwick' ) ),
				'cookieDays'     => (int) get_option( 'waicb_cookie_days', 90 ),
				'quickReplies'   => array_values( array_filter( array_map( 'trim', explode( "\n", get_option( 'waicb_quick_replies', '' ) ) ) ) ),
				'bubbleIcon'     => get_option( 'waicb_bubble_icon', '' ),
				'i18n'           => array(
					'placeholder'  => __( 'Écrivez votre message…', 'chatwick' ),
					'send'         => __( 'Envoyer', 'chatwick' ),
					'errorMessage' => __( 'Une erreur est survenue. Veuillez réessayer.', 'chatwick' ),
					'confirmReset' => __( 'Réinitialiser la conversation ? Les messages affichés seront effacés.', 'chatwick' ),
				),
			)
		);
	}

	/**
	 * Render the floating bubble HTML in the footer.
	 *
	 * @return void
	 */
	public function render_bubble() {
		// Pass mode=bubble so the template knows it's the floating bubble.
		$waicb_mode = 'bubble';
		require WAICB_DIR . 'public/views/chatbot-widget.php';
	}

	/**
	 * Render the inline widget via shortcode [waicb_chatbot].
	 *
	 * @param array $atts Shortcode attributes (currently unused).
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		ob_start();
		$waicb_mode = 'shortcode';
		require WAICB_DIR . 'public/views/chatbot-widget.php';
		return ob_get_clean();
	}
}
