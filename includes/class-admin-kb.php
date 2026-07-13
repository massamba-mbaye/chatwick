<?php
/**
 * Admin page — Base de connaissances (RAG).
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_Admin_KB
 *
 * Sous-page « Base de connaissances » : choix des types de contenu à indexer et
 * synchronisation par lots (AJAX) vers le SaaS. La progression est pilotée côté
 * navigateur : chaque appel traite un lot puis rend la main.
 */
class WAICB_Admin_KB {

	/** @var string Suffixe de hook de la sous-page (pour cibler l'enqueue). */
	private $hook = '';

	/**
	 * Enregistre les hooks admin.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_waicb_kb_save', array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_waicb_kb_sync', array( $this, 'ajax_sync' ) );
	}

	/**
	 * Sous-page sous le menu principal du plugin.
	 *
	 * @return void
	 */
	public function add_menu() {
		$this->hook = add_submenu_page(
			'waicb-settings',
			__( 'Base de connaissances', 'chatwick' ),
			__( 'Base de connaissances', 'chatwick' ),
			'manage_options',
			'waicb-kb',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Charge le script de synchronisation, uniquement sur la page « Base de connaissances ».
	 *
	 * @param string $hook Suffixe de la page admin courante.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_script(
			'waicb-kb-sync',
			WAICB_URL . 'admin/assets/kb-sync.js',
			array(),
			WAICB_VERSION,
			true
		);
		wp_localize_script(
			'waicb-kb-sync',
			'waicbKb',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'waicb_admin_nonce' ),
			)
		);
	}

	/**
	 * Rend la page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require WAICB_DIR . 'admin/views/kb-page.php';
	}

	/**
	 * Enregistre la sélection des types de contenu à indexer.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'chatwick' ) );
		}
		check_admin_referer( 'waicb_kb_save' );

		$types = isset( $_POST['waicb_kb_post_types'] ) && is_array( $_POST['waicb_kb_post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['waicb_kb_post_types'] ) )
			: array();
		update_option( 'waicb_kb_post_types', $types );

		wp_safe_redirect( add_query_arg( array( 'page' => 'waicb-kb', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Traite un lot de synchronisation et renvoie la progression.
	 *
	 * @return void
	 */
	public function ajax_sync() {
		check_ajax_referer( 'waicb_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'chatwick' ) ) );
		}

		$page  = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
		$sync  = new WAICB_KB_Sync();
		$types = $sync->synced_post_types();

		if ( ! $types ) {
			wp_send_json_success( array( 'done' => true, 'total' => 0, 'processed' => 0, 'chunks_total' => 0, 'results' => array() ) );
		}

		try {
			// Comptage une seule fois (page 1) ; renvoyé au client qui le répète ensuite.
			$total = ( 1 === $page ) ? $sync->count_items( $types ) : ( isset( $_POST['total'] ) ? absint( wp_unslash( $_POST['total'] ) ) : 0 );
			$batch = $sync->collect_batch( $types, $page, WAICB_KB_Sync::BATCH_SIZE );
			$res   = $sync->push( $batch['docs'] );
			$done  = $batch['fetched'] < WAICB_KB_Sync::BATCH_SIZE;

			wp_send_json_success( array(
				'page'         => $page,
				'total'        => $total,
				'processed'    => min( $total, $page * WAICB_KB_Sync::BATCH_SIZE ),
				'done'         => $done,
				'results'      => isset( $res['results'] ) ? $res['results'] : array(),
				'chunks_total' => isset( $res['chunks_total'] ) ? (int) $res['chunks_total'] : 0,
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
