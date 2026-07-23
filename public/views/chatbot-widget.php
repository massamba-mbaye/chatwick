<?php
/**
 * Frontend view — Chatbot widget HTML.
 *
 * Variables available:
 *   $waicb_mode  'bubble' | 'shortcode'
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

$waicb_mode        = isset( $waicb_mode ) ? $waicb_mode : 'bubble';
$title             = get_option( 'waicb_widget_title', __( 'Assistant IA', 'chatwick' ) );
$waicb_color       = get_option( 'waicb_widget_color', '#C49A2E' );
$waicb_position    = get_option( 'waicb_widget_position', 'bottom-right' );
$waicb_bubble_icon = get_option( 'waicb_bubble_icon', '' );
$waicb_effect      = get_option( 'waicb_bubble_effect', 'glow' );

// Couleur principale en composantes R,G,B : permet de dériver le halo/l'ombre
// (rgba(var(--waicb-color-rgb), α)) tout en gardant la couleur exacte du client.
$waicb_hex = ltrim( (string) $waicb_color, '#' );
if ( 3 === strlen( $waicb_hex ) ) {
	$waicb_hex = $waicb_hex[0] . $waicb_hex[0] . $waicb_hex[1] . $waicb_hex[1] . $waicb_hex[2] . $waicb_hex[2];
}
$waicb_rgb = ( 6 === strlen( $waicb_hex ) && ctype_xdigit( $waicb_hex ) )
	? hexdec( substr( $waicb_hex, 0, 2 ) ) . ',' . hexdec( substr( $waicb_hex, 2, 2 ) ) . ',' . hexdec( substr( $waicb_hex, 4, 2 ) )
	: '196,154,46';
?>

<?php if ( 'bubble' === $waicb_mode ) : ?>
<!-- Floating bubble -->
<button id="waicb-bubble"
        class="waicb-bubble waicb-pos-<?php echo esc_attr( $waicb_position ); ?>"
        data-effect="<?php echo esc_attr( $waicb_effect ); ?>"
        style="--waicb-color:<?php echo esc_attr( $waicb_color ); ?>;--waicb-color-rgb:<?php echo esc_attr( $waicb_rgb ); ?>"
        aria-label="<?php
		/* translators: %s: chatbot widget title */
		echo esc_attr( sprintf( __( 'Open %s', 'chatwick' ), $title ) );
		?>"
        type="button">
	<?php if ( $waicb_bubble_icon ) : ?>
		<img src="<?php echo esc_url( $waicb_bubble_icon ); ?>" alt="" width="34" height="34"
		     style="border-radius:50%;object-fit:cover;" aria-hidden="true">
	<?php else : ?>
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="28" height="28" aria-hidden="true">
			<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
		</svg>
	<?php endif; ?>
</button>
<?php endif; ?>

<!-- Chat panel -->
<div id="waicb-panel"
     class="waicb-panel waicb-pos-<?php echo esc_attr( $waicb_position ); ?><?php echo 'shortcode' === $waicb_mode ? ' waicb-panel--inline waicb-panel--open' : ''; ?>"
     style="--waicb-color:<?php echo esc_attr( $waicb_color ); ?>"
     role="dialog"
     aria-label="<?php echo esc_attr( $title ); ?>"
     aria-modal="false"
     data-mode="<?php echo esc_attr( $waicb_mode ); ?>">

	<!-- ── Header ─────────────────────────────────────────────────── -->
	<div class="waicb-panel__header">
		<div class="waicb-panel__header-info">
			<div class="waicb-panel__header-avatar" aria-hidden="true">
				<?php if ( $waicb_bubble_icon ) : ?>
					<img src="<?php echo esc_url( $waicb_bubble_icon ); ?>" alt=""
					     style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
				<?php else : ?>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
						<rect x="3" y="11" width="18" height="10" rx="2"/>
						<path d="M9 11V8a3 3 0 0 1 6 0v3"/>
						<circle cx="9" cy="16" r="1" fill="currentColor" stroke="none"/>
						<circle cx="15" cy="16" r="1" fill="currentColor" stroke="none"/>
						<line x1="12" y1="3" x2="12" y2="5"/>
					</svg>
				<?php endif; ?>
			</div>
			<div class="waicb-panel__header-text">
				<div class="waicb-panel__title"><?php echo esc_html( $title ); ?></div>
				<div class="waicb-panel__subtitle">
					<span class="waicb-status-dot" aria-hidden="true"></span>
					<?php esc_html_e( 'En ligne', 'chatwick' ); ?>
				</div>
			</div>
		</div>
		<?php if ( 'bubble' === $waicb_mode ) : ?>
		<button class="waicb-panel__close" aria-label="<?php esc_attr_e( 'Fermer', 'chatwick' ); ?>" type="button">&times;</button>
		<?php endif; ?>
	</div>

	<!-- ── Messages area ──────────────────────────────────────────── -->
	<div class="waicb-panel__messages" id="waicb-messages" role="log" aria-live="polite" aria-atomic="false">
		<!-- Messages injected by JS -->
	</div>

	<!-- Scroll-to-bottom button -->
	<button id="waicb-scroll-btn" class="waicb-scroll-btn" type="button"
	        aria-label="<?php esc_attr_e( 'Défiler vers le bas', 'chatwick' ); ?>">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16" aria-hidden="true">
			<polyline points="6 9 12 15 18 9"/>
		</svg>
	</button>

	<!-- ── Quick replies ──────────────────────────────────────────── -->
	<div id="waicb-quick-replies" class="waicb-quick-replies" aria-label="<?php esc_attr_e( 'Suggestions', 'chatwick' ); ?>">
		<!-- Chips injected by JS from waicbConfig.quickReplies -->
	</div>

	<!-- ── Footer ─────────────────────────────────────────────────── -->
	<div class="waicb-panel__footer">
		<div class="waicb-input-wrap">
			<textarea id="waicb-input"
			          class="waicb-input"
			          rows="1"
			          placeholder="<?php esc_attr_e( 'Écrivez votre message…', 'chatwick' ); ?>"
			          aria-label="<?php esc_attr_e( 'Message', 'chatwick' ); ?>"
			          maxlength="4000"></textarea>
			<button id="waicb-clear-input" class="waicb-clear-input" type="button"
			        aria-label="<?php esc_attr_e( 'Effacer', 'chatwick' ); ?>"
			        tabindex="-1">&times;</button>
		</div>
		<button id="waicb-send" class="waicb-send-btn" type="button"
		        aria-label="<?php esc_attr_e( 'Envoyer', 'chatwick' ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20" aria-hidden="true">
				<line x1="22" y1="2" x2="11" y2="13"/>
				<polygon points="22 2 15 22 11 13 2 9 22 2"/>
			</svg>
		</button>
	</div>
	<div class="waicb-panel__footer-meta">
		<span id="waicb-char-counter" class="waicb-char-counter">0 / 4000</span>
	</div>

</div>
