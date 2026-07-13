<?php
/**
 * Admin Conversations page controller.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Admin_Conversations
 *
 * Registers the Conversations admin page, handles pagination,
 * conversation detail view, and single-conversation deletion.
 */
class WAICB_Admin_Conversations {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_post_waicb_delete_conversation', array( $this, 'handle_delete' ) );
	}

	/**
	 * Register the Conversations sub-page.
	 *
	 * @return void
	 */
	public function add_submenu() {
		add_submenu_page(
			'waicb-settings',
			__( 'Conversations', 'chatwick' ),
			__( 'Conversations', 'chatwick' ),
			'manage_options',
			'waicb-conversations',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render conversations list or detail view.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, cast to int, capability already checked.
		$session_id = isset( $_GET['session_id'] ) ? (int) $_GET['session_id'] : 0;

		if ( $session_id > 0 ) {
			require WAICB_DIR . 'admin/views/conversation-detail.php';
		} else {
			require WAICB_DIR . 'admin/views/conversations-page.php';
		}
	}

	/**
	 * Handle single conversation deletion.
	 *
	 * @return void
	 */
	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'chatwick' ) );
		}

		check_admin_referer( 'waicb_delete_conversation' );

		$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;

		if ( $session_id > 0 ) {
			WAICB_Database::delete_session( $session_id );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'waicb-conversations', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
