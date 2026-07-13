<?php
/**
 * Admin view — Conversation detail.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

$waicb_session_id = isset( $_GET['session_id'] ) ? absint( $_GET['session_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$waicb_session    = WAICB_Database::get_session( $waicb_session_id );

if ( ! $waicb_session ) {
	echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Session introuvable.', 'chatwick' ) . '</p></div></div>';
	return;
}

$waicb_messages = WAICB_Database::get_all_messages( $waicb_session_id );
?>
<div class="wrap">
	<h1>
		<?php esc_html_e( 'Conversation', 'chatwick' ); ?>
		&nbsp;<small><code><?php echo esc_html( $waicb_session['session_key'] ); ?></code></small>
	</h1>

	<p>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'waicb-conversations' ), admin_url( 'admin.php' ) ) ); ?>">
			&larr; <?php esc_html_e( 'Retour à la liste', 'chatwick' ); ?>
		</a>
	</p>

	<table class="form-table" role="presentation" style="max-width:600px">
		<tr>
			<th><?php esc_html_e( 'Mode', 'chatwick' ); ?></th>
			<td><?php echo esc_html( $waicb_session['mode'] ); ?></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Créée le', 'chatwick' ); ?></th>
			<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $waicb_session['created_at'] ) ); ?></td>
		</tr>
	</table>

	<div class="waicb-conversation-view">
		<?php if ( empty( $waicb_messages ) ) : ?>
			<p><?php esc_html_e( 'Aucun message.', 'chatwick' ); ?></p>
		<?php else : ?>
			<?php foreach ( $waicb_messages as $waicb_msg ) : ?>
				<?php if ( 'system' === $waicb_msg['role'] ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="waicb-msg waicb-msg--<?php echo esc_attr( $waicb_msg['role'] ); ?>">
					<div class="waicb-msg__meta">
						<strong><?php echo 'user' === $waicb_msg['role'] ? esc_html__( 'Utilisateur', 'chatwick' ) : esc_html__( 'Assistant', 'chatwick' ); ?></strong>
						<span><?php echo esc_html( mysql2date( 'H:i:s', $waicb_msg['created_at'] ) ); ?></span>
					</div>
					<div class="waicb-msg__content"><?php echo nl2br( esc_html( $waicb_msg['content'] ) ); ?></div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div style="margin-top:20px;">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="waicb_delete_conversation">
			<input type="hidden" name="session_id" value="<?php echo esc_attr( $waicb_session_id ); ?>">
			<?php wp_nonce_field( 'waicb_delete_conversation' ); ?>
			<button type="submit" class="button button-secondary waicb-btn-danger"
			        onclick="return confirm('<?php esc_attr_e( 'Supprimer cette conversation ?', 'chatwick' ); ?>')">
				<?php esc_html_e( 'Supprimer cette conversation', 'chatwick' ); ?>
			</button>
		</form>
	</div>
</div>
