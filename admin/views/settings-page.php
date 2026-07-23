<?php
/**
 * Admin view — Settings page (Cloud-only, guided onboarding).
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

$waicb_has_cloud_key   = '' !== get_option( 'waicb_cloud_key', '' );
$waicb_instructions    = get_option( 'waicb_instructions', '' );
$waicb_bubble_icon     = get_option( 'waicb_bubble_icon', '' );
$waicb_widget_position = get_option( 'waicb_widget_position', 'bottom-right' );
$waicb_bubble_effect   = get_option( 'waicb_bubble_effect', 'glow' );
$waicb_widget_title    = get_option( 'waicb_widget_title', 'Assistant IA' );
$waicb_widget_color    = get_option( 'waicb_widget_color', '#C49A2E' );
$waicb_welcome_message = get_option( 'waicb_welcome_message', __( 'Bonjour ! Comment puis-je vous aider ?', 'chatwick' ) );
$waicb_cookie_days     = (int) get_option( 'waicb_cookie_days', 90 );
$waicb_quick_replies   = get_option( 'waicb_quick_replies', '' );
$waicb_enabled         = (bool) get_option( 'waicb_enabled', true );

$waicb_display_mode  = get_option( 'waicb_display_mode', 'all' );
$waicb_display_pages = get_option( 'waicb_display_pages', array() );
if ( ! is_array( $waicb_display_pages ) ) {
	$waicb_display_pages = array();
}
$waicb_all_pages = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );

$waicb_saved   = isset( $_GET['saved'] ) && '1' === sanitize_key( $_GET['saved'] );     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$waicb_cleared = isset( $_GET['cleared'] ) && '1' === sanitize_key( $_GET['cleared'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── État de mise en route ────────────────────────────────────────────────────
$waicb_has_instr = '' !== trim( $waicb_instructions );
$waicb_is_live   = $waicb_has_cloud_key && $waicb_enabled;

$waicb_s1 = $waicb_has_cloud_key;   // compte créé (implicite si clé présente)
$waicb_s2 = $waicb_has_cloud_key;   // clé connectée
$waicb_s3 = $waicb_has_instr;       // assistant personnalisé
$waicb_s4 = $waicb_enabled;         // chatbot activé
$waicb_active = ! $waicb_s1 ? 1 : ( ! $waicb_s2 ? 2 : ( ! $waicb_s3 ? 3 : ( ! $waicb_s4 ? 4 : 0 ) ) );

/**
 * Classe d'une étape selon son état.
 */
$waicb_step_class = function ( $done, $n ) use ( $waicb_active ) {
	if ( $done ) {
		return 'waicb-step is-done';
	}
	return 'waicb-step' . ( $waicb_active === $n ? ' is-active' : '' );
};
?>
<div class="wrap waicb-settings-wrap">
	<h1><?php esc_html_e( 'Chatwick', 'chatwick' ); ?></h1>

	<?php if ( $waicb_saved ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Réglages enregistrés.', 'chatwick' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $waicb_cleared ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Toutes les conversations ont été supprimées.', 'chatwick' ); ?></p></div>
	<?php endif; ?>

	<!-- ── Bandeau de statut ─────────────────────────────────────── -->
	<?php
	if ( $waicb_is_live ) {
		$waicb_status_class = 'waicb-status--live';
		$waicb_status_ico   = '✓';
		$waicb_status_text  = '<strong>' . esc_html__( 'Chatbot en ligne.', 'chatwick' ) . '</strong> ' . esc_html__( 'Votre assistant répond à vos visiteurs.', 'chatwick' );
	} elseif ( $waicb_has_cloud_key ) {
		$waicb_status_class = 'waicb-status--connected';
		$waicb_status_ico   = '✓';
		$waicb_status_text  = '<strong>' . esc_html__( 'Connecté à Chatwick.', 'chatwick' ) . '</strong> ' . esc_html__( 'Activez le chatbot (étape 4) pour l\'afficher sur le site.', 'chatwick' );
	} else {
		$waicb_status_class = 'waicb-status--setup';
		$waicb_status_ico   = '!';
		$waicb_status_text  = '<strong>' . esc_html__( 'Configuration requise.', 'chatwick' ) . '</strong> ' . esc_html__( 'Connectez votre compte Chatwick pour activer le chatbot.', 'chatwick' );
	}
	?>
	<div class="waicb-status <?php echo esc_attr( $waicb_status_class ); ?>" id="waicb-status">
		<span class="waicb-status__ico" id="waicb-status-ico"><?php echo esc_html( $waicb_status_ico ); ?></span>
		<span id="waicb-status-text"><?php echo wp_kses( $waicb_status_text, array( 'strong' => array() ) ); ?></span>
		<?php if ( $waicb_has_cloud_key ) : ?>
			<span class="waicb-status__right">
				<?php esc_html_e( 'Crédits :', 'chatwick' ); ?>
				<strong id="waicb-credits">…</strong>
				&middot; <a href="<?php echo esc_url( WAICB_CLOUD_DASHBOARD ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Recharger', 'chatwick' ); ?></a>
			</span>
		<?php endif; ?>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="waicb_save_settings">
		<?php wp_nonce_field( 'waicb_settings_save' ); ?>

		<!-- ── Mise en route (guidée) ────────────────────────────── -->
		<div class="waicb-card">
			<h2><?php esc_html_e( 'Mise en route', 'chatwick' ); ?></h2>
			<p class="waicb-sub"><?php esc_html_e( 'Quatre étapes pour mettre votre assistant en ligne.', 'chatwick' ); ?></p>

			<!-- Étape 1 -->
			<div class="<?php echo esc_attr( $waicb_step_class( $waicb_s1, 1 ) ); ?>" id="waicb-s1">
				<div class="waicb-step__mark"><?php echo $waicb_s1 ? '✓' : '1'; ?></div>
				<div class="waicb-step__body">
					<h3><?php esc_html_e( 'Créez votre compte Chatwick', 'chatwick' ); ?><span class="waicb-gift"><?php esc_html_e( '25 conversations offertes', 'chatwick' ); ?></span></h3>
					<p><?php esc_html_e( 'Gratuit, sans carte bancaire. Vous obtiendrez une clé de compte à coller à l\'étape 2.', 'chatwick' ); ?></p>
					<a class="button waicb-btn-teal" href="<?php echo esc_url( WAICB_CLOUD_DASHBOARD . '?signup=1' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Créer un compte (gratuit)', 'chatwick' ); ?></a>
				</div>
			</div>

			<!-- Étape 2 -->
			<div class="<?php echo esc_attr( $waicb_step_class( $waicb_s2, 2 ) ); ?>" id="waicb-s2">
				<div class="waicb-step__mark"><?php echo $waicb_s2 ? '✓' : '2'; ?></div>
				<div class="waicb-step__body">
					<h3><?php esc_html_e( 'Connectez votre clé', 'chatwick' ); ?></h3>
					<p><?php esc_html_e( 'Collez la clé de compte récupérée sur Chatwick, puis testez la connexion.', 'chatwick' ); ?></p>
					<input type="password" id="waicb_cloud_key" name="waicb_cloud_key" class="regular-text"
					       value="<?php echo $waicb_has_cloud_key ? '****************' : ''; ?>"
					       autocomplete="new-password" placeholder="aica_live_...">
					<p>
						<button type="button" id="waicb-test-cloud" class="button button-secondary" data-provider="cloud"><?php esc_html_e( 'Tester la connexion', 'chatwick' ); ?></button>
						<span id="waicb-test-cloud-result" style="margin-left:10px;font-weight:600;"></span>
						<?php if ( $waicb_has_cloud_key ) : ?>
							<a class="button" href="<?php echo esc_url( WAICB_CLOUD_DASHBOARD ); ?>" target="_blank" rel="noopener" style="margin-left:6px;"><?php esc_html_e( 'Gérer mes crédits / Recharger', 'chatwick' ); ?></a>
						<?php endif; ?>
					</p>
					<p class="waicb-hint"><?php esc_html_e( 'La clé reste chiffrée. Aucune clé OpenAI ou Anthropic à fournir. Laissez vide pour conserver la valeur actuelle.', 'chatwick' ); ?></p>
				</div>
			</div>

			<!-- Étape 3 -->
			<div class="<?php echo esc_attr( $waicb_step_class( $waicb_s3, 3 ) ); ?>" id="waicb-s3">
				<div class="waicb-step__mark"><?php echo $waicb_s3 ? '✓' : '3'; ?></div>
				<div class="waicb-step__body">
					<h3><?php esc_html_e( 'Personnalisez l\'assistant', 'chatwick' ); ?></h3>
					<p><?php esc_html_e( 'Décrivez le rôle et le ton de votre bot. Modifiable à tout moment dans l\'onglet « Assistant ».', 'chatwick' ); ?></p>
					<button type="button" class="button button-secondary" id="waicb-go-assistant"><?php esc_html_e( 'Personnaliser le ton →', 'chatwick' ); ?></button>
				</div>
			</div>

			<!-- Étape 4 -->
			<div class="<?php echo esc_attr( $waicb_step_class( $waicb_s4, 4 ) ); ?>" id="waicb-s4">
				<div class="waicb-step__mark"><?php echo $waicb_s4 ? '✓' : '4'; ?></div>
				<div class="waicb-step__body">
					<h3><?php esc_html_e( 'Activez le chatbot', 'chatwick' ); ?></h3>
					<p><?php esc_html_e( 'Affichez la bulle de chat sur votre site. Choisissez les pages dans l\'onglet « Affichage ».', 'chatwick' ); ?></p>
					<label class="waicb-switch">
						<input type="checkbox" id="waicb_enabled" name="waicb_enabled" value="1" <?php checked( $waicb_enabled ); ?>>
						<span class="waicb-track"></span>
						<span><?php esc_html_e( 'Afficher le chatbot sur le site', 'chatwick' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<!-- ── Réglages (onglets) ────────────────────────────────── -->
		<div class="waicb-card">
			<div class="waicb-tabs">
				<button type="button" class="waicb-tab active" data-tab="assistant"><?php esc_html_e( 'Assistant', 'chatwick' ); ?></button>
				<button type="button" class="waicb-tab" data-tab="apparence"><?php esc_html_e( 'Apparence', 'chatwick' ); ?></button>
				<button type="button" class="waicb-tab" data-tab="affichage"><?php esc_html_e( 'Affichage', 'chatwick' ); ?></button>
				<button type="button" class="waicb-tab" data-tab="avance"><?php esc_html_e( 'Avancé', 'chatwick' ); ?></button>
			</div>

			<!-- Onglet Assistant -->
			<div class="waicb-pane active" data-pane="assistant">
				<div style="margin-bottom:16px;">
					<label for="waicb_instructions" style="display:block;font-weight:600;margin-bottom:6px;"><?php esc_html_e( 'Instructions de l\'assistant (persona)', 'chatwick' ); ?></label>
					<textarea id="waicb_instructions" name="waicb_instructions" rows="6" class="large-text" maxlength="2500"
					          placeholder="<?php esc_attr_e( 'Ex. : Tu es l\'assistant du site Exemple. Réponds en français, de façon concise et chaleureuse, à propos de nos services.', 'chatwick' ); ?>"><?php echo esc_textarea( $waicb_instructions ); ?></textarea>
					<p class="waicb-hint"><span id="waicb-instr-count">0</span> / 2500 · <?php esc_html_e( 'transmis à Chatwick à chaque message.', 'chatwick' ); ?></p>
				</div>
				<div>
					<label for="waicb_welcome_message" style="display:block;font-weight:600;margin-bottom:6px;"><?php esc_html_e( 'Message de bienvenue', 'chatwick' ); ?></label>
					<textarea id="waicb_welcome_message" name="waicb_welcome_message" rows="2" class="large-text"><?php echo esc_textarea( $waicb_welcome_message ); ?></textarea>
				</div>
			</div>

			<!-- Onglet Apparence -->
			<div class="waicb-pane" data-pane="apparence">
				<table class="form-table" role="presentation" style="margin-top:0;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Icône de la bulle', 'chatwick' ); ?></th>
						<td>
							<input type="hidden" id="waicb_bubble_icon" name="waicb_bubble_icon" value="<?php echo esc_attr( $waicb_bubble_icon ); ?>">
							<div id="waicb-icon-preview" style="margin-bottom:8px;<?php echo $waicb_bubble_icon ? '' : 'display:none;'; ?>">
								<img src="<?php echo esc_url( $waicb_bubble_icon ); ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:50%;border:2px solid #ddd;">
							</div>
							<button type="button" id="waicb-upload-icon" class="button button-secondary"><?php esc_html_e( 'Choisir une image', 'chatwick' ); ?></button>
							<button type="button" id="waicb-remove-icon" class="button" style="margin-left:6px;<?php echo $waicb_bubble_icon ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Supprimer', 'chatwick' ); ?></button>
							<p class="description"><?php esc_html_e( 'Remplace l\'icône par défaut dans la bulle flottante. Taille recommandée : 56×56 px.', 'chatwick' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="waicb_widget_title"><?php esc_html_e( 'Titre du widget', 'chatwick' ); ?></label></th>
						<td><input type="text" id="waicb_widget_title" name="waicb_widget_title" value="<?php echo esc_attr( $waicb_widget_title ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="waicb_widget_color"><?php esc_html_e( 'Couleur principale', 'chatwick' ); ?></label></th>
						<td><input type="color" id="waicb_widget_color" name="waicb_widget_color" value="<?php echo esc_attr( $waicb_widget_color ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="waicb_widget_position"><?php esc_html_e( 'Position', 'chatwick' ); ?></label></th>
						<td>
							<select id="waicb_widget_position" name="waicb_widget_position">
								<option value="bottom-right" <?php selected( $waicb_widget_position, 'bottom-right' ); ?>><?php esc_html_e( 'Bas droite', 'chatwick' ); ?></option>
								<option value="bottom-left" <?php selected( $waicb_widget_position, 'bottom-left' ); ?>><?php esc_html_e( 'Bas gauche', 'chatwick' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="waicb_bubble_effect"><?php esc_html_e( 'Effet de la bulle', 'chatwick' ); ?></label></th>
						<td>
							<select id="waicb_bubble_effect" name="waicb_bubble_effect">
								<option value="none" <?php selected( $waicb_bubble_effect, 'none' ); ?>><?php esc_html_e( 'Aucun', 'chatwick' ); ?></option>
								<option value="soft" <?php selected( $waicb_bubble_effect, 'soft' ); ?>><?php esc_html_e( 'Ombre douce', 'chatwick' ); ?></option>
								<option value="glow" <?php selected( $waicb_bubble_effect, 'glow' ); ?>><?php esc_html_e( 'Halo lumineux', 'chatwick' ); ?></option>
								<option value="pulse" <?php selected( $waicb_bubble_effect, 'pulse' ); ?>><?php esc_html_e( 'Halo + pulsation', 'chatwick' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Rend la bulle plus visible sur le site. Le halo reprend votre couleur principale.', 'chatwick' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Onglet Affichage -->
			<div class="waicb-pane" data-pane="affichage">
				<table class="form-table" role="presentation" style="margin-top:0;">
					<tr>
						<th scope="row"><?php esc_html_e( 'Où afficher le chatbot', 'chatwick' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:6px;"><input type="radio" name="waicb_display_mode" value="all" <?php checked( $waicb_display_mode, 'all' ); ?>> <?php esc_html_e( 'Sur toutes les pages', 'chatwick' ); ?></label>
							<label style="display:block;margin-bottom:6px;"><input type="radio" name="waicb_display_mode" value="specific" <?php checked( $waicb_display_mode, 'specific' ); ?>> <?php esc_html_e( 'Uniquement sur les pages sélectionnées', 'chatwick' ); ?></label>
							<label style="display:block;"><input type="radio" name="waicb_display_mode" value="exclude" <?php checked( $waicb_display_mode, 'exclude' ); ?>> <?php esc_html_e( 'Sur toutes les pages sauf les sélectionnées', 'chatwick' ); ?></label>
						</td>
					</tr>
					<tr id="waicb-display-pages-row" <?php echo 'all' === $waicb_display_mode ? 'style="display:none;"' : ''; ?>>
						<th scope="row"><?php esc_html_e( 'Pages', 'chatwick' ); ?></th>
						<td>
							<div style="max-height:200px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;padding:8px 12px;">
								<?php foreach ( $waicb_all_pages as $waicb_page ) : ?>
									<label style="display:block;margin-bottom:4px;">
										<input type="checkbox" name="waicb_display_pages[]" value="<?php echo esc_attr( $waicb_page->ID ); ?>"
										       <?php checked( in_array( (string) $waicb_page->ID, array_map( 'strval', $waicb_display_pages ), true ) ); ?>>
										<?php echo esc_html( $waicb_page->post_title ); ?>
									</label>
								<?php endforeach; ?>
								<?php if ( empty( $waicb_all_pages ) ) : ?>
									<em><?php esc_html_e( 'Aucune page trouvée.', 'chatwick' ); ?></em>
								<?php endif; ?>
							</div>
							<p class="description"><?php esc_html_e( 'Le shortcode [waicb_chatbot] fonctionne toujours indépendamment de cette règle.', 'chatwick' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Onglet Avancé -->
			<div class="waicb-pane" data-pane="avance">
				<table class="form-table" role="presentation" style="margin-top:0;">
					<tr>
						<th scope="row"><label for="waicb_quick_replies"><?php esc_html_e( 'Suggestions de questions', 'chatwick' ); ?></label></th>
						<td>
							<textarea id="waicb_quick_replies" name="waicb_quick_replies" rows="4" class="large-text"><?php echo esc_textarea( $waicb_quick_replies ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Une suggestion par ligne. Affichées comme boutons cliquables au démarrage du chat.', 'chatwick' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="waicb_cookie_days"><?php esc_html_e( 'Durée du cookie (jours)', 'chatwick' ); ?></label></th>
						<td><input type="number" id="waicb_cookie_days" name="waicb_cookie_days" value="<?php echo esc_attr( $waicb_cookie_days ); ?>" min="1" max="365" class="small-text"></td>
					</tr>
				</table>
			</div>
		</div>

		<?php submit_button( __( 'Enregistrer les réglages', 'chatwick' ) ); ?>
	</form>

	<!-- ── Zone de danger ────────────────────────────────────────── -->
	<div class="waicb-danger-zone">
		<h2><?php esc_html_e( 'Zone de danger', 'chatwick' ); ?></h2>
		<p><?php esc_html_e( 'Cette action supprime définitivement toutes les conversations et logs.', 'chatwick' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="waicb-clear-form">
			<input type="hidden" name="action" value="waicb_clear_conversations">
			<?php wp_nonce_field( 'waicb_clear_conversations' ); ?>
			<button type="submit" class="button waicb-btn-danger" id="waicb-clear-btn"><?php esc_html_e( 'Vider toutes les conversations', 'chatwick' ); ?></button>
		</form>
	</div>
</div>
