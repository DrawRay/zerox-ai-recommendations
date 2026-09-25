<?php
/**
 * Vista: panel de Compatibilidades (Fase 4).
 * Variables disponibles:
 *  $vehicles      array  Todos los vehículos activos (para el selector).
 *  $vehicle_id    int    Vehículo seleccionado (0 = ninguno).
 *  $vehicle       object|null Vehículo seleccionado.
 *  $rows          array  Compatibilidades del vehículo.
 *  $edit_row      object|null Fila en edición (o null).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_base_url = admin_url( 'admin.php?page=zair-compatibility' );
$zair_here_url = $vehicle_id ? add_query_arg( 'vehicle_id', $vehicle_id, $zair_base_url ) : $zair_base_url;
$zair_is_edit  = ( null !== $edit_row );

$zair_tipo_labels = array(
	'motor'          => 'Motor',
	'embrague'       => 'Embrague',
	'distribucion'   => 'Distribución',
	'transmision'    => 'Transmisión',
	'electrico'      => 'Eléctrico',
	'refrigeracion'  => 'Refrigeración',
	'suspension'     => 'Suspensión',
	'frenos'         => 'Frenos',
	'filtros'        => 'Filtros',
	'complementario' => 'Complementario',
);
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · Compatibilidades</h1>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin:14px 0;">
		<input type="hidden" name="page" value="zair-compatibility">
		<label for="zair-vehicle-select"><strong>Vehículo / motor:</strong></label>
		<select name="vehicle_id" id="zair-vehicle-select" onchange="this.form.submit()">
			<option value="0">— Selecciona un vehículo/motor —</option>
			<?php foreach ( $vehicles as $zair_veh ) : ?>
				<option value="<?php echo esc_attr( (int) $zair_veh->id ); ?>" <?php selected( $vehicle_id, (int) $zair_veh->id ); ?>>
					<?php
					echo esc_html(
						trim(
							$zair_veh->marca . ' ' . $zair_veh->modelo . ' ' . $zair_veh->version . ' ' .
							$zair_veh->cilindrada . ( $zair_veh->codigo_motor ? ' / ' . $zair_veh->codigo_motor : '' )
						)
					);
					?>
				</option>
			<?php endforeach; ?>
		</select>
		<noscript><button type="submit" class="button">Ver</button></noscript>
	</form>

	<?php if ( ! $vehicle_id || ! $vehicle ) : ?>

		<div class="zair-placeholder">
			<p>Selecciona un vehículo/motor para gestionar sus productos compatibles.</p>
			<p class="zair-meta">Si aún no registraste tus motores, hazlo primero en <a href="<?php echo esc_url( admin_url( 'admin.php?page=zair-vehicles' ) ); ?>">Motores / Vehículos</a>.</p>
		</div>

	<?php else : ?>

		<h2>
			<?php echo esc_html( trim( $vehicle->marca . ' ' . $vehicle->modelo . ' ' . $vehicle->version . ' ' . $vehicle->cilindrada ) ); ?>
			<?php if ( $vehicle->codigo_motor ) : ?>
				<code><?php echo esc_html( $vehicle->codigo_motor ); ?></code>
			<?php endif; ?>
		</h2>

		<!-- Formulario alta/edición -->
		<div class="zair-compat-form-box">
			<h3><?php echo $zair_is_edit ? 'Editar compatibilidad' : 'Agregar producto compatible'; ?></h3>

			<form method="post" action="<?php echo esc_url( $zair_here_url ); ?>">
				<?php wp_nonce_field( 'zair_save_compat', 'zair_compat_nonce' ); ?>
				<input type="hidden" name="vehicle_engine_id" value="<?php echo esc_attr( $vehicle_id ); ?>">
				<input type="hidden" name="compat_id" value="<?php echo esc_attr( $zair_is_edit ? (int) $edit_row->id : 0 ); ?>">

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="zair-product-search">Producto WooCommerce <span class="zair-req">*</span></label></th>
						<td>
							<?php
							$zair_selected_label = '';
							$zair_selected_id    = 0;
							if ( $zair_is_edit ) {
								$zair_selected_id = (int) $edit_row->product_id;
								$zair_p           = wc_get_product( $zair_selected_id );
								$zair_selected_label = $zair_p
									? $zair_p->get_name() . '  (SKU: ' . ( $zair_p->get_sku() ? $zair_p->get_sku() : '—' ) . ')'
									: 'Producto #' . $zair_selected_id . ' (ya no existe en WooCommerce)';
							}
							?>
							<div class="zair-search-wrap">
								<input type="text" id="zair-product-search" class="regular-text"
									placeholder="Escribe nombre o SKU (mín. 2 letras)..." autocomplete="off">
								<ul id="zair-product-results" class="zair-results" style="display:none;"></ul>
							</div>
							<input type="hidden" id="zair-product-id" name="product_id" value="<?php echo esc_attr( $zair_selected_id ); ?>">
							<p id="zair-product-selected" class="zair-selected" style="<?php echo $zair_selected_label ? '' : 'display:none;'; ?>">
								<?php echo esc_html( $zair_selected_label ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zair-tipo">Tipo de relación</label></th>
						<td>
							<select id="zair-tipo" name="tipo_relacion">
								<?php foreach ( $zair_tipo_labels as $zair_key => $zair_label ) : ?>
									<option value="<?php echo esc_attr( $zair_key ); ?>"
										<?php selected( $zair_is_edit ? $edit_row->tipo_relacion : 'complementario', $zair_key ); ?>>
										<?php echo esc_html( $zair_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zair-prioridad">Prioridad comercial (1–10)</label></th>
						<td>
							<input type="number" id="zair-prioridad" name="nivel_prioridad" min="1" max="10" style="width:80px;"
								value="<?php echo esc_attr( $zair_is_edit ? (int) $edit_row->nivel_prioridad : 5 ); ?>">
							<p class="description">10 = máxima relevancia comercial. Es el factor de mayor peso en el ranking.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Verificación</th>
						<td>
							<label>
								<input type="checkbox" name="compatibilidad_verificada" value="1"
									<?php checked( $zair_is_edit ? (int) $edit_row->compatibilidad_verificada : 0, 1 ); ?>>
								<strong>Compatibilidad verificada</strong>
							</label>
							<p class="description" style="color:#b32d2e;">Sin esta marca, el producto JAMÁS se recomendará. Márcala solo tras confirmar la compatibilidad real.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="zair-notas">Notas internas</label></th>
						<td>
							<textarea id="zair-notas" name="notas" rows="2" class="large-text"><?php echo esc_textarea( $zair_is_edit ? $edit_row->notas : '' ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">Estado</th>
						<td>
							<label>
								<input type="checkbox" name="estado" value="1"
									<?php checked( $zair_is_edit ? (int) $edit_row->estado : 1, 1 ); ?>>
								Activo
							</label>
						</td>
					</tr>
				</table>

				<p class="submit" style="margin-top:0;">
					<button type="submit" name="zair_compat_submit" value="1" class="button button-primary">
						<?php echo $zair_is_edit ? 'Actualizar compatibilidad' : 'Agregar compatibilidad'; ?>
					</button>
					<?php if ( $zair_is_edit ) : ?>
						<a href="<?php echo esc_url( $zair_here_url ); ?>" class="button">Cancelar edición</a>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<!-- Tabla de compatibilidades -->
		<h3>Productos relacionados (<?php echo esc_html( count( $rows ) ); ?>)</h3>
		<table class="widefat striped zair-table" style="max-width:1150px;">
			<thead>
				<tr>
					<th>Producto</th>
					<th>SKU</th>
					<th>Precio</th>
					<th>Stock</th>
					<th>Tipo</th>
					<th>Prioridad</th>
					<th>Verificada</th>
					<th>Estado</th>
					<th style="width:200px;">Acciones</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="9">Aún no hay productos relacionados con este vehículo/motor.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $zair_row ) : ?>
						<?php
						$zair_rid     = (int) $zair_row->id;
						$zair_product = wc_get_product( (int) $zair_row->product_id );

						$zair_action_url = function ( $action ) use ( $zair_rid, $zair_here_url ) {
							return wp_nonce_url(
								add_query_arg(
									array(
										'zair_action' => $action,
										'compat_id'   => $zair_rid,
									),
									$zair_here_url
								),
								'zair_compat_' . $action . '_' . $zair_rid
							);
						};
						?>
						<tr>
							<td>
								<?php if ( $zair_product ) : ?>
									<strong><?php echo esc_html( $zair_product->get_name() ); ?></strong>
								<?php else : ?>
									<span style="color:#b32d2e;">Producto #<?php echo esc_html( (int) $zair_row->product_id ); ?> eliminado de WooCommerce</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $zair_product && $zair_product->get_sku() ? $zair_product->get_sku() : '—' ); ?></td>
							<td><?php echo $zair_product ? wp_kses_post( wc_price( (float) $zair_product->get_price() ) ) : '—'; ?></td>
							<td>
								<?php if ( $zair_product ) : ?>
									<?php echo $zair_product->is_in_stock() ? '<span class="zair-badge zair-badge-ok">Sí</span>' : '<span class="zair-badge zair-badge-error">No</span>'; ?>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( isset( $zair_tipo_labels[ $zair_row->tipo_relacion ] ) ? $zair_tipo_labels[ $zair_row->tipo_relacion ] : $zair_row->tipo_relacion ); ?></td>
							<td><strong><?php echo esc_html( (int) $zair_row->nivel_prioridad ); ?></strong>/10</td>
							<td>
								<?php if ( $zair_row->compatibilidad_verificada ) : ?>
									<a href="<?php echo esc_url( $zair_action_url( 'unverify' ) ); ?>" class="zair-badge zair-badge-ok" title="Clic para quitar verificación">✓ Sí</a>
								<?php else : ?>
									<a href="<?php echo esc_url( $zair_action_url( 'verify' ) ); ?>" class="zair-badge zair-badge-error" title="Clic para verificar">✖ No</a>
								<?php endif; ?>
							</td>
							<td>
								<?php echo $zair_row->estado ? '<span class="zair-badge zair-badge-ok">Activo</span>' : '<span class="zair-badge zair-badge-error">Inactivo</span>'; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( 'edit_compat', $zair_rid, $zair_here_url ) ); ?>">Editar</a> |
								<a href="<?php echo esc_url( $zair_action_url( $zair_row->estado ? 'disable' : 'enable' ) ); ?>">
									<?php echo $zair_row->estado ? 'Desactivar' : 'Activar'; ?>
								</a> |
								<a href="<?php echo esc_url( $zair_action_url( 'delete' ) ); ?>" class="zair-delete"
									onclick="return confirm('¿Eliminar esta compatibilidad?');">Eliminar</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p class="zair-meta">
			El recomendador solo mostrará productos con <strong>Verificada = Sí</strong> y <strong>Estado = Activo</strong>.
			Precio y stock se leen en vivo desde WooCommerce.
		</p>

	<?php endif; ?>

</div>
