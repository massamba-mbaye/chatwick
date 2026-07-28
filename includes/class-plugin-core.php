<?php
/**
 * Plugin core — singleton bootstrap.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Plugin_Core
 *
 * Bootstraps all sub-systems: REST API, admin pages, and frontend widget.
 */
class WAICB_Plugin_Core {

	/** @var WAICB_Plugin_Core|null Singleton instance. */
	private static $instance = null;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Returns the singleton instance.
	 *
	 * @return WAICB_Plugin_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialise all plugin sub-systems.
	 *
	 * @return void
	 */
	public function init() {
		// REST API — always registered (needed by the frontend widget).
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Admin.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Frontend widget.
		$this->init_frontend();

		// RAG — synchro incrémentale de la base de connaissances (save/delete → file + cron).
		// Enregistré partout (front, admin, REST, cron) car un contenu peut être modifié n'importe où.
		( new WAICB_KB_Sync() )->register_hooks();
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$rest = new WAICB_Rest_Api();
		$rest->register_routes();
	}

	/**
	 * Initialise admin-only modules.
	 *
	 * @return void
	 */
	private function init_admin() {
		// Ensure the schema exists / is current (the activation hook does not
		// fire on plugin update, so self-heal missing tables here).
		add_action( 'admin_init', array( 'WAICB_Database', 'maybe_upgrade' ) );

		$settings      = new WAICB_Admin_Settings();
		$conversations = new WAICB_Admin_Conversations();
		$kb            = new WAICB_Admin_KB();

		$settings->init();
		$conversations->init();
		$kb->init();

		// AJAX handlers must be registered outside rest_api_init to fire on admin-ajax.php.
		$rest = new WAICB_Rest_Api();
		add_action( 'wp_ajax_waicb_test_api', array( $rest, 'handle_test_api' ) );
		add_action( 'wp_ajax_waicb_credits', array( $rest, 'handle_credits' ) );
	}

	/**
	 * Initialise frontend widget.
	 *
	 * @return void
	 */
	private function init_frontend() {
		$widget = new WAICB_Chatbot_Widget();
		$widget->init();
	}
}
