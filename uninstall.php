<?php
/**
 * Uninstall Chatwick.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package Chatwick
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop tables in FK-safe order.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aichat_logs" );      // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aichat_messages" );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aichat_sessions" );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove all plugin options.
$waicb_options = array(
	'waicb_provider',
	'waicb_cloud_key',
	'waicb_cloud_url',
	'waicb_instructions',
	'waicb_api_key',
	'waicb_claude_api_key',
	'waicb_claude_model',
	'waicb_mode',
	'waicb_model',
	'waicb_assistant_id',
	'waicb_system_prompt',
	'waicb_temperature',
	'waicb_max_tokens',
	'waicb_history_limit',
	'waicb_widget_position',
	'waicb_widget_title',
	'waicb_widget_color',
	'waicb_welcome_message',
	'waicb_cookie_days',
	'waicb_quick_replies',
	'waicb_bubble_icon',
	'waicb_display_mode',
	'waicb_display_pages',
	'waicb_enabled',
	'waicb_db_version',
);

foreach ( $waicb_options as $waicb_opt ) {
	delete_option( $waicb_opt );
}

// Remove the cached GitHub release lookup.
delete_transient( 'waicb_github_release' );

// Remove thread IDs stored by the Assistants API (pattern: waicb_thread_*).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery.slow_db_query_field_in
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'waicb_thread_%'" );