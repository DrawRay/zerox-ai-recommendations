<?php
/**
 * Vista: bandeja de Leads (Fase 7).
 * Variables: $result, $args, $counts.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_base = admin_url( 'admin.php?page=zair-leads' );
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · Leads</h1>
	<p class="zair-meta">
		Consultas de compatibilidad enviadas desde la web. Los datos personales viven solo aquí:
		nunca se envían a Google Analytics ni salen del servidor.
	</p>

	<table class="widefat striped zair-table" style="max-width:760px;">
		<tbody>
			<tr>
				<?php foreach ( ZAIR_Leads::ESTADOS as $zair_k => $zair_label ) : ?>
					<td style="text-align:center;">
						<strong style="font-size:1.4rem; display:block;"><?php echo esc_html( number_format_i18n( $counts[ $zair_k ] ) ); ?></strong>
						<span class="zair-product-meta" style="margin:0;"><?php echo esc_html( $zair_label ); ?></span>
					</td>
				<?php endforeach; ?>
			</tr>
		</tbody>
	</table>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="zair-leads">
		<p style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:18px 0 10px;">
			<input type="search" name="s" value="<?php echo esc_attr( $args['s'] ); ?>"
				placeholder="Buscar nombre, WhatsApp o código…">
			<select name="estado">
				<option value="">Todos los estados</option>
				<?php foreach ( ZAIR_Leads::ESTADOS as $zair_k => $zair_label ) : ?>
					<option value="<?php echo esc_attr( $zair_k ); ?>" <?php selected( $args['estado'], $zair_k ); ?>>
						<?php echo esc_html( $zair_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button">Filtrar</button>
			<a href="<?php echo esc_url( $zair_base ); ?>" class="button-link">Limpiar</a>
			<?php
			$zair_export = wp_nonce_url(
				add_query_arg(
					array(
						'page'        => 'zair-leads',
						'zair_export' => 'leads',
						's'           => $args['s'],
						'estado'      => $args['estado'],
					),
					admin_url( 'admin.php' )
				),
				'zair_export_leads'
			);
			?>
			<a href="<?php echo esc_url( $zair_export ); ?>" class="button button-primary">Exportar CSV</a>
		</p>
	</form>

	<table class="widefat striped zair-table" style="max-width:1250px;">
		<thead>
			<tr>
				<th>Fecha</th>
				<th>Código</th>
				<th>Nombre</th>
				<th>WhatsApp</th>
				<th>Producto consultado</th>
				<th>Vehículo</th>
				<th style="width:170px;">Estado</th>
				<th style="width:90px;">Venta</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr><td colspan="8">Todavía no hay leads. Aparecerán aquí en cuanto un cliente envíe una consulta de compatibilidad.</td></tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $zair_l ) : ?>
					<?php
					$zair_prod    = $zair_l->product_origin_id ? wc_get_product( $zair_l->product_origin_id ) : null;
					$zair_vehicle = $zair_l->vehicle_engine_id ? ZAIR_Vehicles::get( $zair_l->vehicle_engine_id ) : null;
					$zair_wa      = preg_replace( '/\D/', '', $zair_l->whatsapp );
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $zair_l->created_at ) ); ?></td>
						<td><code><?php echo esc_html( $zair_l->lead_code ); ?></code></td>
						<td><strong><?php echo esc_html( $zair_l->nombre ); ?></strong></td>
						<td>
							<a href="https://wa.me/<?php echo esc_attr( $zair_wa ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $zair_l->whatsapp ); ?>
							</a>
						</td>
						<td>
							<?php if ( $zair_prod ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $zair_prod->get_id() ) ); ?>" target="_blank">
									<?php echo esc_html( $zair_prod->get_name() ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo $zair_vehicle
								? esc_html( trim( $zair_vehicle->marca . ' ' . $zair_vehicle->modelo . ' ' . $zair_vehicle->cilindrada ) . ( $zair_vehicle->codigo_motor ? ' · ' . $zair_vehicle->codigo_motor : '' ) )
								: '—';
							?>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( $zair_base ); ?>" style="display:flex; gap:4px;">
								<?php wp_nonce_field( 'zair_lead_estado', 'zair_lead_nonce' ); ?>
								<input type="hidden" name="lead_id" value="<?php echo esc_attr( $zair_l->id ); ?>">
								<select name="estado" onchange="this.form.submit();">
									<?php foreach ( ZAIR_Leads::ESTADOS as $zair_k => $zair_label ) : ?>
										<option value="<?php echo esc_attr( $zair_k ); ?>" <?php selected( $zair_l->estado, $zair_k ); ?>>
											<?php echo esc_html( $zair_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<input type="hidden" name="zair_lead_submit" value="1">
							</form>
						</td>
						<td>
							<?php if ( $zair_l->venta_generada ) : ?>
								<span class="zair-badge zair-badge-ok">Sí</span>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $result['pages'] > 1 ) : ?>
		<div class="tablenav bottom"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => max( 1, $args['paged'] ),
						'total'     => $result['pages'],
						'prev_text' => '‹',
						'next_text' => '›',
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>

	<p class="zair-meta">
		Total: <?php echo esc_html( number_format_i18n( $result['total'] ) ); ?> lead(s).
		Haz clic en el número de WhatsApp para abrir la conversación directamente.
		El botón «Exportar CSV» respeta los filtros aplicados.
	</p>

</div>
