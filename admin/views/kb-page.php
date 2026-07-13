<?php
/**
 * Vue admin — Base de connaissances (RAG).
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

$waicb_kb        = new WAICB_KB_Sync();
$waicb_available = $waicb_kb->available_post_types();
$waicb_selected  = $waicb_kb->synced_post_types();
$waicb_has_key   = '' !== (string) WAICB_Crypto::decrypt( get_option( 'waicb_cloud_key', '' ) );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Base de connaissances', 'chatwick' ); ?></h1>

	<p class="description" style="max-width:720px">
		<?php esc_html_e( "Indexez le contenu de votre site pour que l'assistant réponde à partir de vos pages, articles et produits. Le contenu est envoyé à Chatwick (qui le découpe et l'indexe) ; rien n'est stocké sur ce site.", 'chatwick' ); ?>
	</p>

	<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple avis de succès après une redirection déjà protégée par nonce. ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Types de contenu enregistrés.', 'chatwick' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $waicb_has_key ) : ?>
		<div class="notice notice-warning"><p>
			<?php esc_html_e( "Renseignez d'abord votre clé de compte Chatwick dans le menu « Réglages » : la synchronisation en a besoin.", 'chatwick' ); ?>
		</p></div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Contenu à indexer', 'chatwick' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="waicb_kb_save">
		<?php wp_nonce_field( 'waicb_kb_save' ); ?>
		<fieldset>
			<?php foreach ( $waicb_available as $waicb_slug => $waicb_obj ) : ?>
				<label style="display:inline-block;margin:0 18px 8px 0">
					<input type="checkbox" name="waicb_kb_post_types[]" value="<?php echo esc_attr( $waicb_slug ); ?>"
						<?php checked( in_array( $waicb_slug, $waicb_selected, true ) ); ?>>
					<?php echo esc_html( $waicb_obj->labels->name ); ?>
					<span class="description">(<?php echo esc_html( $waicb_slug ); ?>)</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p><button type="submit" class="button"><?php esc_html_e( 'Enregistrer la sélection', 'chatwick' ); ?></button></p>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Synchroniser maintenant', 'chatwick' ); ?></h2>
	<p class="description">
		<?php esc_html_e( "Parcourt le contenu sélectionné par lots et l'envoie à Chatwick. Vous pouvez relancer à tout moment : seul le contenu modifié est réindexé.", 'chatwick' ); ?>
	</p>

	<p>
		<button type="button" class="button button-primary" id="waicb-kb-sync"
			<?php disabled( ! $waicb_has_key ); ?>>
			<?php esc_html_e( 'Synchroniser maintenant', 'chatwick' ); ?>
		</button>
	</p>

	<div id="waicb-kb-progress" style="display:none;max-width:520px">
		<div style="background:#e2e4e7;border-radius:6px;overflow:hidden;height:14px">
			<div id="waicb-kb-bar" style="background:#2271b1;height:100%;width:0"></div>
		</div>
		<p id="waicb-kb-status" style="margin-top:8px"></p>
	</div>

	<div id="waicb-kb-summary" style="display:none;margin-top:10px"></div>

	<hr>

	<h2><?php esc_html_e( 'Synchronisation automatique', 'chatwick' ); ?></h2>
	<p class="description" style="max-width:720px">
		<?php esc_html_e( "Activée : dès qu'un contenu est publié, modifié, dépublié ou supprimé, la base est mise à jour automatiquement (en tâche de fond). La synchronisation manuelle ci-dessus ne sert donc qu'à la première indexation ou après un changement de sélection.", 'chatwick' ); ?>
	</p>
	<?php $waicb_pending = $waicb_kb->pending_count(); ?>
	<p>
		<span class="dashicons dashicons-update" style="vertical-align:text-bottom"></span>
		<?php if ( $waicb_pending > 0 ) : ?>
			<?php
			/* translators: %d = nombre de contenus en attente. */
			echo esc_html( sprintf( _n( '%d contenu en attente de synchronisation automatique.', '%d contenus en attente de synchronisation automatique.', $waicb_pending, 'chatwick' ), $waicb_pending ) );
			?>
			<span class="description"><?php esc_html_e( '(traité en tâche de fond dans la minute qui suit)', 'chatwick' ); ?></span>
		<?php else : ?>
			<?php esc_html_e( 'Aucun contenu en attente : la base est à jour.', 'chatwick' ); ?>
		<?php endif; ?>
	</p>
</div>
