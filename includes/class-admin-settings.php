<?php
/**
 * Admin Settings page controller.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Admin_Settings
 *
 * Registers the Settings admin page and handles form submission.
 */
class WAICB_Admin_Settings {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_waicb_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_waicb_clear_conversations', array( $this, 'handle_clear' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add top-level menu and sub-pages.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Chatwick', 'chatwick' ),
			__( 'Chatwick', 'chatwick' ),
			'manage_options',
			'waicb-settings',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			80
		);
	}

	/**
	 * Enqueue admin CSS/JS on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'waicb' ) === false ) {
			return;
		}

		wp_enqueue_media(); // Required for the bubble icon media uploader.

		wp_enqueue_style(
			'waicb-admin',
			WAICB_URL . 'admin/assets/admin.css',
			array(),
			WAICB_VERSION
		);

		wp_enqueue_script(
			'waicb-admin',
			WAICB_URL . 'admin/assets/admin.js',
			array( 'jquery' ),
			WAICB_VERSION,
			true
		);

		wp_localize_script(
			'waicb-admin',
			'waicbAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'waicb_admin_nonce' ),
				'i18n'    => array(
					'testing'      => __( 'Test en cours…', 'chatwick' ),
					'confirmClear' => __( 'Êtes-vous sûr de vouloir supprimer toutes les conversations ?', 'chatwick' ),
					'connected'    => __( 'Connecté à Chatwick.', 'chatwick' ),
					'enableHint'   => __( 'Activez le chatbot (étape 4) pour l\'afficher sur le site.', 'chatwick' ),
					'creditsSuffix'      => __( 'conversations', 'chatwick' ),
					'creditsUnavailable' => __( 'indisponible', 'chatwick' ),
					'quotaPrefix'        => __( 'Quota du site :', 'chatwick' ),
					'quotaUnlimited'     => __( 'sans plafond', 'chatwick' ),
					/* translators: %s: number of days of estimated balance autonomy. */
					'autonomyLabel'      => __( 'Autonomie : ~%s j', 'chatwick' ),
					'useImage'           => __( 'Utiliser cette image', 'chatwick' ),
					'chooseIcon'         => __( 'Choisir l\'icône de la bulle', 'chatwick' ),
				),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require WAICB_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Handle settings form submission.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'chatwick' ) );
		}

		check_admin_referer( 'waicb_settings_save' );

		// Fournisseur : toujours « cloud » (service Chatwick).
		update_option( 'waicb_provider', 'cloud' );

		// Clé de compte Cloud — mise à jour uniquement si une nouvelle valeur est saisie.
		$submitted_cloud_key = isset( $_POST['waicb_cloud_key'] ) ? sanitize_text_field( wp_unslash( $_POST['waicb_cloud_key'] ) ) : '';
		if ( '' !== $submitted_cloud_key && strpos( $submitted_cloud_key, '****' ) === false ) {
			update_option( 'waicb_cloud_key', WAICB_Crypto::encrypt( $submitted_cloud_key ) );
		}

		// Instructions (persona) du site — texte simple, plafonné, transmis au SaaS.
		$instructions = isset( $_POST['waicb_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['waicb_instructions'] ) ) : '';
		if ( mb_strlen( $instructions ) > 2500 ) {
			$instructions = mb_substr( $instructions, 0, 2500 );
		}
		update_option( 'waicb_instructions', $instructions );

		// Widget options.
		$position = isset( $_POST['waicb_widget_position'] ) && 'bottom-left' === $_POST['waicb_widget_position'] ? 'bottom-left' : 'bottom-right';
		update_option( 'waicb_widget_position', $position );

		update_option( 'waicb_widget_title', sanitize_text_field( wp_unslash( isset( $_POST['waicb_widget_title'] ) ? $_POST['waicb_widget_title'] : 'Assistant IA' ) ) );
		update_option( 'waicb_welcome_message', sanitize_textarea_field( wp_unslash( isset( $_POST['waicb_welcome_message'] ) ? $_POST['waicb_welcome_message'] : '' ) ) );

		$color = isset( $_POST['waicb_widget_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['waicb_widget_color'] ) ) : '#C49A2E';
		update_option( 'waicb_widget_color', $color ? $color : '#C49A2E' );

		$bubble_effect = isset( $_POST['waicb_bubble_effect'] ) ? sanitize_key( wp_unslash( $_POST['waicb_bubble_effect'] ) ) : 'glow';
		if ( ! in_array( $bubble_effect, array( 'none', 'soft', 'glow', 'pulse' ), true ) ) {
			$bubble_effect = 'glow';
		}
		update_option( 'waicb_bubble_effect', $bubble_effect );

		$cookie_days = isset( $_POST['waicb_cookie_days'] ) ? (int) $_POST['waicb_cookie_days'] : 90;
		$cookie_days = max( 1, min( 365, $cookie_days ) );
		update_option( 'waicb_cookie_days', $cookie_days );

		$quick_replies = isset( $_POST['waicb_quick_replies'] ) ? sanitize_textarea_field( wp_unslash( $_POST['waicb_quick_replies'] ) ) : '';
		update_option( 'waicb_quick_replies', $quick_replies );

		$bubble_icon = isset( $_POST['waicb_bubble_icon'] ) ? esc_url_raw( wp_unslash( $_POST['waicb_bubble_icon'] ) ) : '';
		update_option( 'waicb_bubble_icon', $bubble_icon );

		// Repli « parler à un humain » à court de crédits.
		update_option( 'waicb_handoff_enabled', isset( $_POST['waicb_handoff_enabled'] ) ? 1 : 0 );

		// Numéro WhatsApp : on ne garde que les chiffres (format international sans +).
		$wa = isset( $_POST['waicb_whatsapp_number'] ) ? preg_replace( '/\D+/', '', wp_unslash( $_POST['waicb_whatsapp_number'] ) ) : '';
		update_option( 'waicb_whatsapp_number', $wa );

		$handoff_email = isset( $_POST['waicb_handoff_email'] ) ? sanitize_email( wp_unslash( $_POST['waicb_handoff_email'] ) ) : '';
		update_option( 'waicb_handoff_email', $handoff_email );

		update_option( 'waicb_handoff_message', sanitize_textarea_field( wp_unslash( isset( $_POST['waicb_handoff_message'] ) ? $_POST['waicb_handoff_message'] : '' ) ) );

		// Display rules.
		$display_mode = isset( $_POST['waicb_display_mode'] ) && in_array( wp_unslash( $_POST['waicb_display_mode'] ), array( 'all', 'specific', 'exclude' ), true )
			? sanitize_text_field( wp_unslash( $_POST['waicb_display_mode'] ) )
			: 'all';
		update_option( 'waicb_display_mode', $display_mode );

		$display_pages = isset( $_POST['waicb_display_pages'] ) && is_array( $_POST['waicb_display_pages'] )
			? array_map( 'absint', $_POST['waicb_display_pages'] )
			: array();
		update_option( 'waicb_display_pages', $display_pages );

		$enabled = isset( $_POST['waicb_enabled'] ) ? 1 : 0;
		update_option( 'waicb_enabled', $enabled );

		wp_safe_redirect( add_query_arg( array( 'page' => 'waicb-settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle "clear all conversations" form submission.
	 *
	 * @return void
	 */
	public function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'chatwick' ) );
		}

		check_admin_referer( 'waicb_clear_conversations' );

		WAICB_Database::delete_all_sessions();

		wp_safe_redirect( add_query_arg( array( 'page' => 'waicb-settings', 'cleared' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
