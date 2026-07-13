<?php
/**
 * Database — table installation and all DB queries.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Database
 *
 * Handles CREATE TABLE on activation and all CRUD operations
 * for sessions, messages and API logs.
 */
class WAICB_Database {

	// ── Schema ────────────────────────────────────────────────────────────────

	/**
	 * Create (or upgrade) all plugin tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Sessions table.
		$sql_sessions = "CREATE TABLE {$wpdb->prefix}aichat_sessions (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_key VARCHAR(64)     NOT NULL,
			user_id     BIGINT UNSIGNED NULL,
			mode        ENUM('chat','assistant') NOT NULL DEFAULT 'chat',
			ip_hash     VARCHAR(64)     NULL,
			user_agent  VARCHAR(255)    NULL,
			created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY session_key (session_key)
		) $charset_collate;";

		// Messages table.
		$sql_messages = "CREATE TABLE {$wpdb->prefix}aichat_messages (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id BIGINT UNSIGNED NOT NULL,
			role       ENUM('user','assistant','system') NOT NULL,
			content    LONGTEXT        NOT NULL,
			created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id)
		) $charset_collate;";

		// Logs table.
		$sql_logs = "CREATE TABLE {$wpdb->prefix}aichat_logs (
			id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id        BIGINT UNSIGNED NOT NULL,
			model             VARCHAR(64)     NOT NULL,
			prompt_tokens     INT UNSIGNED    NOT NULL DEFAULT 0,
			completion_tokens INT UNSIGNED    NOT NULL DEFAULT 0,
			total_tokens      INT UNSIGNED    NOT NULL DEFAULT 0,
			cost_usd          DECIMAL(10,6)   NOT NULL DEFAULT 0.000000,
			created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id)
		) $charset_collate;";

		dbDelta( $sql_sessions );
		dbDelta( $sql_messages );
		dbDelta( $sql_logs );

		update_option( 'waicb_db_version', WAICB_DB_VERSION );
	}

	/**
	 * Ensure the schema is present and up to date.
	 *
	 * Runs cheaply on every admin load: it only triggers the (idempotent)
	 * installer when the stored DB version is behind OR the tables are missing.
	 * This guarantees tables exist after a plugin update — the activation hook
	 * does NOT fire on update, so relying on it alone leaves updated sites
	 * without tables if they were ever dropped.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$up_to_date = version_compare( get_option( 'waicb_db_version', '0' ), WAICB_DB_VERSION, '>=' );

		if ( $up_to_date && self::tables_exist() ) {
			return;
		}

		self::install();
	}

	/**
	 * Whether the plugin's tables exist.
	 *
	 * @return bool
	 */
	private static function tables_exist() {
		global $wpdb;

		$table = $wpdb->prefix . 'aichat_sessions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	// ── Sessions ──────────────────────────────────────────────────────────────

	/**
	 * Get or create a session row for the given session_key.
	 *
	 * @param string $session_key UUID v4.
	 * @param string $mode        'chat' or 'assistant'.
	 * @param string $ip_hash     SHA-256 of IP.
	 * @param string $user_agent  Raw user-agent string.
	 * @return int Session ID.
	 */
	public static function get_or_create_session( $session_key, $mode, $ip_hash, $user_agent ) {
		global $wpdb;

		// esc_sql() applied to table name so checkers recognise it as escaped.
		$table = esc_sql( $wpdb->prefix . 'aichat_sessions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_key = %s LIMIT 1", $session_key ) );

		if ( $existing ) {
			// Touch updated_at.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => (int) $existing ) );
			return (int) $existing;
		}

		$user_id = get_current_user_id() ?: null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'session_key' => $session_key,
				'user_id'     => $user_id,
				'mode'        => $mode,
				'ip_hash'     => $ip_hash,
				'user_agent'  => substr( $user_agent, 0, 255 ),
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get a single session row by its ID.
	 *
	 * @param int $session_id Session ID.
	 * @return array|null Associative row or null.
	 */
	public static function get_session( $session_id ) {
		global $wpdb;

		$table = esc_sql( $wpdb->prefix . 'aichat_sessions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $session_id ), ARRAY_A );
	}

	/**
	 * Get paginated list of sessions with their message count.
	 *
	 * @param int $per_page Number of rows per page.
	 * @param int $page     Current page (1-based).
	 * @return array {rows, total}.
	 */
	public static function get_sessions_paginated( $per_page = 20, $page = 1 ) {
		global $wpdb;

		$sessions_table = esc_sql( $wpdb->prefix . 'aichat_sessions' );
		$messages_table = esc_sql( $wpdb->prefix . 'aichat_messages' );
		$offset         = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_field_in
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, COUNT(m.id) AS message_count,
				        MAX(m.created_at) AS last_message_at
				 FROM {$sessions_table} s
				 LEFT JOIN {$messages_table} m ON m.session_id = s.id
				 GROUP BY s.id
				 ORDER BY s.updated_at DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sessions_table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_field_in

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Delete a session and cascade-delete its messages and logs.
	 *
	 * @param int $session_id Session ID.
	 * @return void
	 */
	public static function delete_session( $session_id ) {
		global $wpdb;

		$session_id = (int) $session_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'aichat_logs', array( 'session_id' => $session_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'aichat_messages', array( 'session_id' => $session_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'aichat_sessions', array( 'id' => $session_id ), array( '%d' ) );
	}

	/**
	 * Delete all sessions, messages, and logs.
	 *
	 * @return void
	 */
	public static function delete_all_sessions() {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aichat_logs" );     // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aichat_messages" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}aichat_sessions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// ── Messages ──────────────────────────────────────────────────────────────

	/**
	 * Save a message to the DB.
	 *
	 * @param int    $session_id Session ID.
	 * @param string $role       'user', 'assistant', or 'system'.
	 * @param string $content    Message content.
	 * @return int Inserted message ID.
	 */
	public static function save_message( $session_id, $role, $content ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'aichat_messages',
			array(
				'session_id' => (int) $session_id,
				'role'       => $role,
				'content'    => $content,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get the last N messages for a session.
	 *
	 * @param int $session_id Session ID.
	 * @param int $limit      Number of messages to retrieve.
	 * @return array Array of {role, content} associative arrays.
	 */
	public static function get_messages( $session_id, $limit = 20 ) {
		global $wpdb;

		$table = esc_sql( $wpdb->prefix . 'aichat_messages' );

		// Fetch the last $limit messages in ascending order.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_field_in
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM (
					SELECT role, content, created_at
					FROM {$table}
					WHERE session_id = %d
					ORDER BY id DESC
					LIMIT %d
				) sub ORDER BY created_at ASC",
				$session_id,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_field_in

		return $rows ? $rows : array();
	}

	/**
	 * Get all messages for a session (for conversation detail view).
	 *
	 * @param int $session_id Session ID.
	 * @return array Array of {role, content, created_at} associative arrays.
	 */
	public static function get_all_messages( $session_id ) {
		global $wpdb;

		$table = esc_sql( $wpdb->prefix . 'aichat_messages' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT role, content, created_at FROM {$table} WHERE session_id = %d ORDER BY id ASC", $session_id ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		return $rows ? $rows : array();
	}

	// ── Logs ──────────────────────────────────────────────────────────────────

	/**
	 * Insert an API log entry.
	 *
	 * @param int    $session_id        Session ID.
	 * @param string $model             Model name.
	 * @param int    $prompt_tokens     Prompt token count.
	 * @param int    $completion_tokens Completion token count.
	 * @param int    $total_tokens      Total token count.
	 * @param float  $cost_usd          Cost in USD.
	 * @return void
	 */
	public static function insert_log( $session_id, $model, $prompt_tokens, $completion_tokens, $total_tokens, $cost_usd ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'aichat_logs',
			array(
				'session_id'        => (int) $session_id,
				'model'             => $model,
				'prompt_tokens'     => (int) $prompt_tokens,
				'completion_tokens' => (int) $completion_tokens,
				'total_tokens'      => (int) $total_tokens,
				'cost_usd'          => (float) $cost_usd,
				'created_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%f', '%s' )
		);
	}

	/**
	 * Get paginated logs with session info.
	 *
	 * @param int $per_page Number of rows per page.
	 * @param int $page     Current page (1-based).
	 * @return array {rows, total, totals}.
	 */
	public static function get_logs_paginated( $per_page = 30, $page = 1 ) {
		global $wpdb;

		$logs_table     = esc_sql( $wpdb->prefix . 'aichat_logs' );
		$sessions_table = esc_sql( $wpdb->prefix . 'aichat_sessions' );
		$offset         = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_field_in
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, s.session_key
				 FROM {$logs_table} l
				 LEFT JOIN {$sessions_table} s ON s.id = l.session_id
				 ORDER BY l.id DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" );

		$totals = $wpdb->get_row(
			"SELECT SUM(prompt_tokens) AS prompt_tokens,
			        SUM(completion_tokens) AS completion_tokens,
			        SUM(total_tokens) AS total_tokens,
			        SUM(cost_usd) AS cost_usd
			 FROM {$logs_table}",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_field_in

		// get_row() returns null when the table is empty/missing — normalise so
		// callers can always read the keys without warnings.
		if ( ! is_array( $totals ) ) {
			$totals = array();
		}
		$totals = array_merge(
			array(
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
				'cost_usd'          => 0,
			),
			$totals
		);

		return array(
			'rows'   => $rows ? $rows : array(),
			'total'  => $total,
			'totals' => $totals,
		);
	}
}
