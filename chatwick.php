<?php
/**
 * Plugin Name:       Chatwick
 * Plugin URI:        https://chatwick.app/
 * Description:       Add an AI chatbot to any site, powered by the Chatwick Cloud service (prepaid credits — no AI key to manage).
 * Version:           1.7.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Massamba MBAYE
 * Author URI:        https://www.linkedin.com/in/massamba-mbaye/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chatwick
 * Domain Path:       /languages
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

// ── Constantes ────────────────────────────────────────────────────────────────
define( 'WAICB_VERSION', '1.7.0' );
define( 'WAICB_FILE', __FILE__ );
define( 'WAICB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAICB_URL', plugin_dir_url( __FILE__ ) );
define( 'WAICB_DB_VERSION', '1.0.0' );

// Service Cloud (Chatwick) — endpoint du proxy IA et tableau de bord client.
define( 'WAICB_CLOUD_URL', 'https://api.chatwick.app/api/chat.php' );
define( 'WAICB_CLOUD_STATUS_URL', 'https://api.chatwick.app/api/status.php' );
define( 'WAICB_CLOUD_DASHBOARD', 'https://chatwick.app/dashboard.php' );
define( 'WAICB_CLOUD_KB_SYNC_URL', 'https://api.chatwick.app/api/kb-sync.php' );

// ── Autoloader ────────────────────────────────────────────────────────────────
spl_autoload_register( function ( $class_name ) {
	// Only handle our own classes.
	if ( strpos( $class_name, 'WAICB_' ) !== 0 ) {
		return;
	}

	// Map class name → file path.
	$class_map = array(
		'WAICB_Plugin_Core'            => WAICB_DIR . 'includes/class-plugin-core.php',
		'WAICB_Database'               => WAICB_DIR . 'includes/class-database.php',
		'WAICB_Crypto'                 => WAICB_DIR . 'includes/class-crypto.php',
		'WAICB_Security'               => WAICB_DIR . 'includes/class-security.php',
		'WAICB_Session_Manager'        => WAICB_DIR . 'includes/class-session-manager.php',
		'WAICB_Api_Router'             => WAICB_DIR . 'includes/class-api-router.php',
		'WAICB_Cloud_Chat'             => WAICB_DIR . 'includes/class-cloud-chat.php',
		'WAICB_KB_Sync'                => WAICB_DIR . 'includes/class-kb-sync.php',
		'WAICB_Admin_KB'               => WAICB_DIR . 'includes/class-admin-kb.php',
		'WAICB_Rest_Api'               => WAICB_DIR . 'includes/class-rest-api.php',
		'WAICB_Admin_Settings'         => WAICB_DIR . 'includes/class-admin-settings.php',
		'WAICB_Admin_Conversations'    => WAICB_DIR . 'includes/class-admin-conversations.php',
		'WAICB_Admin_Logs'             => WAICB_DIR . 'includes/class-admin-logs.php',
		'WAICB_Chatbot_Widget'         => WAICB_DIR . 'public/class-chatbot-widget.php',
	);

	if ( isset( $class_map[ $class_name ] ) ) {
		require_once $class_map[ $class_name ];
	}
} );

// ── Activation ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, function () {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Chatwick requires PHP 7.4 or higher.', 'chatwick' ) );
	}

	if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'Chatwick requires WordPress 6.0 or higher.', 'chatwick' ) );
	}

	WAICB_Database::install();
} );

// ── Désactivation ─────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, function () {
	// Purge l'événement cron de synchro incrémentale (RAG) pour ne pas laisser
	// de tâche orpheline programmée.
	wp_clear_scheduled_hook( 'waicb_kb_process_queue' );
} );

// ── Chargement ────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
	WAICB_Plugin_Core::get_instance()->init();
} );