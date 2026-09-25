<?php
/**
 * Vista: listado de Motores / Vehículos (Fase 3).
 * Variables disponibles: $result (items|total|pages), $args (s|estado|paged).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_base_url = admin_url( 'admin.php?page=zair-vehicles' );
$zair_new_url  = add_query_arg( 'view', 'new', $zair_base_url );
?>
<div class="wrap zair-wrap">

	<h1 class="wp-heading-inline">ZEROX AI · Motores / Vehículos</h1>
	<a href="<?php echo esc_url( $zair_new_url ); ?>" class="page-title-action">Añadir nuevo</a>
	<hr class="wp-header-end">

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="zair-vehicles">
		<p class="search-box" style="float:none; display:flex; gap:8px; align-items:center; margin:12px 0;">
			<input type="search" name="s" value="<?php echo esc_attr( $args['s'] ); ?>"
				placeholder="Buscar marca, modelo, motor...">
			<select name="estado">
				<option value="" <?php selected( $args['estado'], '' ); ?>>Todos los estados</option>
				<option value="1" <?php selected( $args['estado'], '1' ); ?>>Activos</option>
				<option value="0" <?php selected( $args['estado'], '0' ); ?>>Inactivos</option>
			</select>
			<button type="submit" class="button">Filtrar</button>
			<?php if ( '' !== $args['s'] || '' !== $args['estado'] ) : ?>
				<a href="<?php echo esc_url( $zair_base_url ); ?>" class="button-link">Limpiar</a>
			<?php endif; ?>
		</p>
	</form>

	<table class="widefat striped zair-table" style="max-width:1100px;">
		<thead>
			<tr>
				<th style="width:50px;">ID</th>
				<th>Marca</th>
				<th>Modelo</th>
				<th>Versión</th>
				<th>Cilindrada</th>
				<th>Motor</th>
				<th>Combustible</th>
				<th>Compatib.</th>
				<th>Estado</th>
				<th style="width:220px;">Acciones</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr>
					<td colspan="10">No se encontraron vehículos/motores. <a href="<?php echo esc_url( $zair_new_url ); ?>">Añade el primero</a>.</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $zair_v ) : ?>
					<?php
					$zair_id       = (int) $zair_v->id;
					$zair_compat   = ZAIR_Vehicles::compat_count( $zair_id );
					$zair_edit_url = add_query_arg(
						array(
							'view'       => 'edit',
							'vehicle_id' => $zair_id,
						),
						$zair_base_url
					);

					$zair_toggle_action = $zair_v->estado ? 'disable' : 'enable';
					$zair_toggle_url    = wp_nonce_url(
						add_query_arg(
							array(
								'zair_action' => $zair_toggle_action,
								'vehicle_id'  => $zair_id,
							),
							$zair_base_url
						),
						'zair_vehicle_' . $zair_toggle_action . '_' . $zair_id
					);

					$zair_delete_url = wp_nonce_url(
						add_query_arg(
							array(
								'zair_action' => 'delete',
								'vehicle_id'  => $zair_id,
							),
							$zair_base_url
						),
						'zair_vehicle_delete_' . $zair_id
					);
					?>
					<tr>
						<td><?php echo esc_html( $zair_id ); ?></td>
						<td><strong><?php echo esc_html( $zair_v->marca ); ?></strong></td>
						<td><?php echo esc_html( $zair_v->modelo ); ?></td>
						<td><?php echo esc_html( $zair_v->version ); ?></td>
						<td><?php echo esc_html( $zair_v->cilindrada ); ?></td>
						<td><code><?php echo esc_html( $zair_v->codigo_motor ); ?></code></td>
						<td><?php echo esc_html( $zair_v->combustible ); ?></td>
						<td><?php echo esc_html( $zair_compat ); ?></td>
						<td>
							<?php if ( $zair_v->estado ) : ?>
								<span class="zair-badge zair-badge-ok">Activo</span>
							<?php else : ?>
								<span class="zair-badge zair-badge-error">Inactivo</span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $zair_edit_url ); ?>">Editar</a> |
							<a href="<?php echo esc_url( $zair_toggle_url ); ?>">
								<?php echo $zair_v->estado ? 'Desactivar' : 'Activar'; ?>
							</a> |
							<a href="<?php echo esc_url( $zair_delete_url ); ?>" class="zair-delete"
								onclick="return confirm('¿Eliminar definitivamente este vehículo/motor? Sus alias también se eliminarán.');">
								Eliminar
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $result['pages'] > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
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
			</div>
		</div>
	<?php endif; ?>

	<p class="zair-meta">
		Total: <?php echo esc_html( number_format_i18n( $result['total'] ) ); ?> vehículo(s)/motor(es).
		Los vehículos con compatibilidades asociadas no pueden eliminarse (solo desactivarse), para no romper el recomendador.
	</p>

</div>
