<?php
/**
 * Admin view — API Logs.
 *
 * @package Chatwick
 */

defined( 'ABSPATH' ) || exit;

$waicb_per_page    = 30;
$waicb_paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$waicb_result      = WAICB_Database::get_logs_paginated( $waicb_per_page, $waicb_paged );
$waicb_logs        = $waicb_result['rows'];
$waicb_total       = $waicb_result['total'];
$waicb_total_pages = (int) ceil( $waicb_total / $waicb_per_page );
$waicb_totals      = $waicb_result['totals'];
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Logs API', 'chatwick' ); ?></h1>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Date', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Session', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Modèle', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Tokens prompt', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Tokens completion', 'chatwick' ); ?></th>
				<th><?php esc_html_e( 'Total tokens', 'chatwick' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $waicb_logs ) ) : ?>
			<tr>
				<td colspan="6"><?php esc_html_e( 'Aucun log.', 'chatwick' ); ?></td>
			</tr>
		<?php else : ?>
			<?php foreach ( $waicb_logs as $waicb_log ) : ?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $waicb_log['created_at'] ) ); ?></td>
					<td><code><?php echo esc_html( substr( $waicb_log['session_key'], 0, 13 ) . '…' ); ?></code></td>
					<td><?php echo esc_html( $waicb_log['model'] ); ?></td>
					<td><?php echo (int) $waicb_log['prompt_tokens']; ?></td>
					<td><?php echo (int) $waicb_log['completion_tokens']; ?></td>
					<td><?php echo (int) $waicb_log['total_tokens']; ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<th colspan="3"><strong><?php esc_html_e( 'Totaux', 'chatwick' ); ?></strong></th>
				<th><strong><?php echo (int) $waicb_totals['prompt_tokens']; ?></strong></th>
				<th><strong><?php echo (int) $waicb_totals['completion_tokens']; ?></strong></th>
				<th><strong><?php echo (int) $waicb_totals['total_tokens']; ?></strong></th>
			</tr>
		</tfoot>
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
