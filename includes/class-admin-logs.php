<?php
/**
 * Admin Logs page controller.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Admin_Logs
 *
 * Registers the API Logs admin sub-page.
 */
class WAICB_Admin_Logs {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
	}

	/**
	 * Register the Logs sub-page.
	 *
	 * @return void
	 */
	public function add_submenu() {
		add_submenu_page(
			'waicb-settings',
			__( 'Logs API', 'chatwick' ),
			__( 'Logs API', 'chatwick' ),
			'manage_options',
			'waicb-logs',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require WAICB_DIR . 'admin/views/logs-page.php';
	}
}
