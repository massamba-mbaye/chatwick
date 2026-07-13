<?php
/**
 * Knowledge base sync engine (RAG).
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WAICB_KB_Sync
 *
 * Énumère le contenu public du site (tous les post types + produits WooCommerce),
 * le normalise en documents { wp_id, type, title, url, text } et les pousse par
 * lots vers l'API de synchronisation du SaaS (…/api/kb-sync.php). Le SaaS gère
 * le découpage, l'embedding et le stockage. Aucune donnée n'est stockée côté site.
 *
 * Générique : aucun branchement par type de contenu — seul l'enrichissement
 * WooCommerce (prix, attributs, variations) est ajouté quand le produit s'y prête.
 */
class WAICB_KB_Sync {

	/** Taille d'un lot poussé au SaaS (le backend plafonne à 50). */
	const BATCH_SIZE = 10;

	/** Option de file d'attente pour la synchro incrémentale. */
	const QUEUE_OPTION = 'waicb_kb_queue';

	/** Hook cron qui vide la file. */
	const CRON_HOOK = 'waicb_kb_process_queue';

	/** Nombre max de documents traités par passage de cron (borne le temps). */
	const QUEUE_MAX_PER_RUN = 50;

	/** @var string[]|null Cache intra-requête des types synchronisés. */
	private $synced_types = null;

	/** @var array Garde-fou intra-requête : évite d'écrire l'option 2× pour un même (op,id). */
	private $enqueued = array();

	/**
	 * Post types indexables disponibles (publics, hors pièces jointes).
	 *
	 * @return array slug => WP_Post_Type
	 */
	public function available_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	/**
	 * Post types réellement synchronisés (sélection admin, sinon tous).
	 *
	 * @return string[]
	 */
	public function synced_post_types() {
		if ( null !== $this->synced_types ) {
			return $this->synced_types;
		}
		$available = array_keys( $this->available_post_types() );
		$saved     = get_option( 'waicb_kb_post_types', null );
		if ( is_array( $saved ) ) {
			$kept = array_values( array_intersect( $saved, $available ) );
			return $this->synced_types = ( $kept ? $kept : $available );
		}
		return $this->synced_types = $available;
	}

	/**
	 * Nombre total de contenus publiés pour les types donnés.
	 *
	 * @param string[] $types
	 * @return int
	 */
	public function count_items( $types ) {
		if ( ! $types ) {
			return 0;
		}
		$q = new WP_Query( array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		) );
		return (int) $q->found_posts;
	}

	/**
	 * Récupère et normalise un lot de contenus (page $page, $per par page).
	 *
	 * @param string[] $types
	 * @param int      $page 1-indexé.
	 * @param int      $per
	 * @return array { docs: array[], fetched: int } — « fetched » = contenus lus
	 *               sur la page (avant filtrage des textes vides), pour détecter la fin.
	 */
	public function collect_batch( $types, $page, $per ) {
		if ( ! $types ) {
			return array( 'docs' => array(), 'fetched' => 0 );
		}
		$q = new WP_Query( array(
			'post_type'           => $types,
			'post_status'         => 'publish',
			'posts_per_page'      => (int) $per,
			'paged'               => max( 1, (int) $page ),
			'orderby'             => 'ID',
			'order'               => 'ASC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );

		$docs = array();
		foreach ( $q->posts as $post ) {
			$doc = $this->normalize_post( $post );
			if ( '' !== $doc['text'] ) {
				$docs[] = $doc;
			}
		}
		return array( 'docs' => $docs, 'fetched' => count( $q->posts ) );
	}

	/**
	 * Normalise un contenu WordPress en document indexable.
	 *
	 * @param WP_Post $post
	 * @return array { wp_id, type, title, url, text }
	 */
	public function normalize_post( $post ) {
		$type = $post->post_type;

		// Corps : contenu sans shortcodes ni HTML + extrait.
		$body    = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$excerpt = has_excerpt( $post ) ? wp_strip_all_tags( get_the_excerpt( $post ) ) : '';

		$parts = array( $excerpt, $body );

		// Taxonomies publiques (catégories, étiquettes, attributs) → contexte.
		// get_the_terms lit le cache d'objets déjà amorcé par WP_Query (pas de requête N+1).
		$terms = array();
		foreach ( get_object_taxonomies( $type ) as $tax ) {
			$got = get_the_terms( $post, $tax );
			if ( is_array( $got ) ) {
				$terms = array_merge( $terms, wp_list_pluck( $got, 'name' ) );
			}
		}
		if ( $terms ) {
			$parts[] = implode( ', ', $terms );
		}

		// Enrichissement WooCommerce (prix, attributs, variations).
		if ( 'product' === $type && function_exists( 'wc_get_product' ) ) {
			$parts[] = $this->product_details( $post->ID );
		}

		$text = trim( implode( "\n", array_filter( array_map( 'trim', $parts ) ) ) );

		return array(
			'wp_id' => (int) $post->ID,
			'type'  => $type,
			'title' => get_the_title( $post ),
			'url'   => get_permalink( $post ),
			'text'  => $text,
		);
	}

	/**
	 * Détails produit WooCommerce en texte (prix, référence, stock, attributs,
	 * fourchette de prix des variations).
	 *
	 * @param int $product_id
	 * @return string
	 */
	private function product_details( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		$bits = array();

		$price = wp_strip_all_tags( $product->get_price_html() );
		if ( '' !== $price ) {
			$bits[] = 'Prix : ' . $price;
		}
		$sku = $product->get_sku();
		if ( '' !== (string) $sku ) {
			$bits[] = 'Référence : ' . $sku;
		}
		$bits[] = $product->is_in_stock() ? 'En stock.' : 'En rupture de stock.';

		// Attributs (taille, couleur…).
		$attrs = array();
		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! is_object( $attribute ) || ! method_exists( $attribute, 'get_name' ) ) {
				continue;
			}
			$label = wc_attribute_label( $attribute->get_name() );
			if ( $attribute->is_taxonomy() ) {
				$got    = get_the_terms( $product_id, $attribute->get_name() ); // cache d'objets amorcé
				$values = is_array( $got ) ? wp_list_pluck( $got, 'name' ) : array();
			} else {
				$values = (array) $attribute->get_options();
			}
			$values = array_filter( array_map( 'trim', $values ) );
			if ( $values ) {
				$attrs[] = $label . ' : ' . implode( ', ', $values );
			}
		}
		if ( $attrs ) {
			$bits[] = implode( ' ; ', $attrs );
		}

		// Variations : fourchette de prix (résumé, sans indexer chaque variante).
		if ( $product->is_type( 'variable' ) ) {
			$prices = $product->get_variation_prices( true );
			if ( ! empty( $prices['price'] ) ) {
				$min    = wp_strip_all_tags( wc_price( min( $prices['price'] ) ) );
				$max    = wp_strip_all_tags( wc_price( max( $prices['price'] ) ) );
				$range  = $min === $max ? $min : ( $min . ' à ' . $max );
				$bits[] = 'Disponible en plusieurs variantes (prix ' . $range . ').';
			}
		}

		return wp_strip_all_tags( implode( "\n", $bits ) );
	}

	/**
	 * Pousse un lot de documents vers l'API de synchronisation du SaaS.
	 *
	 * @param array[] $docs
	 * @return array Réponse décodée { ok, results, chunks_total, chunks_quota }.
	 * @throws RuntimeException Sur erreur HTTP/API ou clé manquante.
	 */
	public function push( array $docs ) {
		$key = WAICB_Crypto::decrypt( get_option( 'waicb_cloud_key', '' ) );
		if ( '' === (string) $key ) {
			throw new RuntimeException( esc_html__( 'Clé de compte Chatwick manquante (menu Réglages).', 'chatwick' ) );
		}
		if ( ! $docs ) {
			return array( 'ok' => true, 'results' => array(), 'chunks_total' => 0 );
		}

		$response = wp_remote_post(
			WAICB_CLOUD_KB_SYNC_URL,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'account_key' => $key,
					'site_url'    => home_url(),
					'documents'   => array_values( $docs ),
				) ),
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

		return $data;
	}

	// ── Synchronisation incrémentale (hooks + file d'attente + cron) ───────────

	/**
	 * Enregistre les hooks de synchro automatique. Appelé à chaque chargement
	 * (front, admin, REST, cron) car un contenu peut être modifié partout.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// transition_post_status : capte TOUT changement de statut, y compris la
		// publication planifiée (future → publish) que wp_insert_post ne déclenche pas.
		add_action( 'transition_post_status', array( $this, 'on_transition_status' ), 20, 3 );
		// Suppression définitive (ne passe pas par une transition de statut).
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );
		// WooCommerce : les MAJ de prix/stock via $product->save() écrivent des postmeta
		// sans passer par wp_insert_post — hook dédié pour ne pas les manquer.
		add_action( 'woocommerce_update_product', array( $this, 'on_wc_product' ), 20 );
		add_action( 'woocommerce_new_product', array( $this, 'on_wc_product' ), 20 );
		// Traitement différé de la file.
		add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );
	}

	/**
	 * Sur changement de statut : indexe si le contenu devient publié, retire s'il
	 * cesse de l'être. Règle générale — pas d'énumération de statuts.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 * @return void
	 */
	public function on_transition_status( $new_status, $old_status, $post ) {
		if ( in_array( $new_status, array( 'auto-draft', 'inherit' ), true ) ) {
			return; // brouillon d'éditeur ou révision : rien à indexer.
		}
		if ( ! in_array( $post->post_type, $this->synced_post_types(), true ) ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			$this->enqueue( 'sync', $post->ID, $post->post_type );
		} elseif ( 'publish' === $old_status ) {
			// Était publié, ne l'est plus → retrait de la base.
			$this->enqueue( 'delete', $post->ID, $post->post_type );
		}
	}

	/**
	 * Sur suppression définitive : retrait de la base.
	 *
	 * @param int $post_id
	 * @return void
	 */
	public function on_delete_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, $this->synced_post_types(), true ) ) {
			return;
		}
		$this->enqueue( 'delete', $post_id, $post->post_type );
	}

	/**
	 * Sur création/mise à jour d'un produit WooCommerce (capte les MAJ prix/stock
	 * qui ne passent pas par une transition de statut).
	 *
	 * @param int $product_id
	 * @return void
	 */
	public function on_wc_product( $product_id ) {
		if ( ! in_array( 'product', $this->synced_post_types(), true ) ) {
			return;
		}
		$this->enqueue( 'sync', (int) $product_id, 'product' );
	}

	/**
	 * Ajoute une opération à la file et programme le traitement différé.
	 * File = { sync: {id=>type}, delete: {id=>type} } ; un contenu n'est que dans
	 * l'une des deux (la dernière opération gagne).
	 *
	 * @param string $op 'sync' | 'delete'
	 * @param int    $post_id
	 * @param string $type
	 * @return void
	 */
	private function enqueue( $op, $post_id, $type ) {
		$post_id = (int) $post_id;
		$guard   = $op . ':' . $post_id;
		if ( isset( $this->enqueued[ $guard ] ) ) {
			return; // déjà mis en file dans cette requête (ex. produit : 2 hooks).
		}
		$this->enqueued[ $guard ] = true;

		$queue = get_option( self::QUEUE_OPTION, array( 'sync' => array(), 'delete' => array() ) );
		unset( $queue['sync'][ $post_id ], $queue['delete'][ $post_id ] );
		$queue[ $op ][ $post_id ] = $type;
		update_option( self::QUEUE_OPTION, $queue, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}

	/**
	 * Nombre d'éléments en attente de synchro automatique (file non encore vidée).
	 *
	 * @return int
	 */
	public function pending_count() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return count( (array) ( $queue['sync'] ?? array() ) ) + count( (array) ( $queue['delete'] ?? array() ) );
	}

	/**
	 * Document de suppression envoyé au SaaS.
	 *
	 * @param int|string $id
	 * @param string     $type
	 * @return array
	 */
	private function delete_doc( $id, $type ) {
		return array( 'wp_id' => (int) $id, 'type' => $type, 'deleted' => true );
	}

	/**
	 * Vide la file par lots bornés (cron). Pousse les documents modifiés/supprimés
	 * au SaaS ; en cas d'échec, conserve la file et reprogramme.
	 *
	 * @return void
	 */
	public function process_queue() {
		$queue  = get_option( self::QUEUE_OPTION, array( 'sync' => array(), 'delete' => array() ) );
		$delete = ! empty( $queue['delete'] ) ? $queue['delete'] : array();
		$sync   = ! empty( $queue['sync'] ) ? $queue['sync'] : array();
		if ( ! $delete && ! $sync ) {
			delete_option( self::QUEUE_OPTION );
			return;
		}

		// Lot borné : suppressions d'abord, puis mises à jour (tranches en tête de file).
		$del_slice  = array_slice( $delete, 0, self::QUEUE_MAX_PER_RUN, true );
		$sync_slice = array_slice( $sync, 0, self::QUEUE_MAX_PER_RUN - count( $del_slice ), true );

		$docs = array();
		foreach ( $del_slice as $id => $type ) {
			$docs[] = $this->delete_doc( $id, $type );
		}
		foreach ( $sync_slice as $id => $type ) {
			$post   = get_post( (int) $id );
			$docs[] = ( $post && 'publish' === $post->post_status )
				? $this->normalize_post( $post )
				: $this->delete_doc( $id, $type ); // disparu/dépublié entre-temps → retrait.
		}

		try {
			foreach ( array_chunk( $docs, self::BATCH_SIZE ) as $chunk ) {
				$this->push( $chunk );
			}
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'waicb kb queue : ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Journalise un échec de synchro uniquement en mode debug.
			}
			wp_schedule_single_event( time() + 300, self::CRON_HOOK );
			return;
		}

		$queue['delete'] = array_diff_key( $delete, $del_slice );
		$queue['sync']   = array_diff_key( $sync, $sync_slice );

		if ( empty( $queue['delete'] ) && empty( $queue['sync'] ) ) {
			delete_option( self::QUEUE_OPTION );
		} else {
			update_option( self::QUEUE_OPTION, $queue, false );
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}
}
