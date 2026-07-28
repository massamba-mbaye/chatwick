<?php
/**
 * Admin view — Conversations list.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

$waicb_per_page    = 20;
$waicb_paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$waicb_result      = WAICB_Database::get_sessions_paginated( $waicb_per_page, $waicb_paged );
$waicb_sessions    = $waicb_result['rows'];
$waicb_total       = $waicb_result['total'];
$waicb_total_pages = (int) ceil( $waicb_total / $waicb_per_page );
$waicb_deleted       = isset( $_GET['deleted'] ) && '1' === sanitize_key( $_GET['deleted'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$waicb_has_cloud_key = '' !== get_option( 'waicb_cloud_key', '' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Conversations', 'chatwick' ); ?></h1>

	<?php if ( $waicb_has_cloud_key ) : ?>
		<div class="waicb-conv-summary" id="waicb-conv-summary">
			<span class="waicb-conv-summary__item">
				<?php esc_html_e( 'Crédits :', 'chatwick' ); ?>
				<strong id="waicb-credits">…</strong>
			</span>
			<span class="waicb-conv-summary__item" id="waicb-quota"></span>
			<span class="waicb-conv-summary__item" id="waicb-autonomy"></span>
			<a class="waicb-conv-summary__link" href="<?php echo esc_url( WAICB_CLOUD_DASHBOARD ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Recharger', 'chatwick' ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( $waicb_deleted ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Conversation supprimée.', 'chatwick' ); ?></p>
		</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Session', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Utilisateur', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Mode', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Messages', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Dernière activité', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'chatwick' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $waicb_sessions ) ) : ?>
			<tr>
				<td colspan="6"><?php esc_html_e( 'Aucune conversation.', 'chatwick' ); ?></td>
			</tr>
		<?php else : ?>
			<?php foreach ( $waicb_sessions as $waicb_session ) : ?>
				<tr>
					<td>
						<code><?php echo esc_html( substr( $waicb_session['session_key'], 0, 18 ) . '…' ); ?></code>
					</td>
					<td>
						<?php
						if ( ! empty( $waicb_session['user_id'] ) ) {
							$waicb_user = get_userdata( (int) $waicb_session['user_id'] );
							echo $waicb_user ? esc_html( $waicb_user->display_name ) : '#' . esc_html( $waicb_session['user_id'] );
						} else {
							esc_html_e( 'Anonyme', 'chatwick' );
						}
						?>
					</td>
					<td><?php echo esc_html( $waicb_session['mode'] ); ?></td>
					<td><?php echo (int) $waicb_session['message_count']; ?></td>
					<td>
						<?php
						$waicb_date = isset( $waicb_session['last_message_at'] ) ? $waicb_session['last_message_at'] : $waicb_session['updated_at'];
						echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $waicb_date ) );
						?>
					</td>
					<td>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'waicb-conversations', 'session_id' => (int) $waicb_session['id'] ), admin_url( 'admin.php' ) ) ); ?>">
							<?php esc_html_e( 'Voir', 'chatwick' ); ?>
						</a>
						&nbsp;|&nbsp;
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<input type="hidden" name="action" value="waicb_delete_conversation">
							<input type="hidden" name="session_id" value="<?php echo (int) $waicb_session['id']; ?>">
							<?php wp_nonce_field( 'waicb_delete_conversation' ); ?>
							<button type="submit" class="button-link waicb-delete-link"
							        onclick="return confirm('<?php esc_attr_e( 'Supprimer cette conversation ?', 'chatwick' ); ?>')">
								<?php esc_html_e( 'Supprimer', 'chatwick' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $waicb_total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $waicb_paged,
							'total'     => $waicb_total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
