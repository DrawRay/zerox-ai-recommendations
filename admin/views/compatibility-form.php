<?php
/**
 * Vista: formulario de Compatibilidad (Fase 4).
 * Variables: $compat (object|null), $vehicles (array).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_is_edit = ( $compat && ! empty( $compat->id ) );
$zair_back    = admin_url( 'admin.php?page=zair-compatibility' );
$zair_prod    = $zair_is_edit ? ZAIR_Compatibility::get_product_info( $compat->product_id ) : null;
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · <?php echo $zair_is_edit ? 'Editar compatibilidad' : 'Nueva compatibilidad'; ?></h1>
	<p><a href="<?php echo esc_url( $zair_back ); ?>">← Volver al listado</a></p>

	<form method="post" action="<?php echo esc_url( $zair_back ); ?>" class="zair-form">
		<?php wp_nonce_field( 'zair_save_compat', 'zair_compat_nonce' ); ?>
		<input type="hidden" name="compat_id" value="<?php echo esc_attr( $zair_is_edit ? (int) $compat->id : 0 ); ?>">
		<input type="hidden" name="product_id" id="zair_product_id"
			value="<?php echo esc_attr( $zair_is_edit ? (int) $compat->product_id : 0 ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="zair_vehicle">Vehículo / Motor <span class="zair-req">*</span></label></th>
				<td>
					<select id="zair_vehicle" name="vehicle_engine_id" required class="regular-text">
						<option value="">— Selecciona —</option>
						<?php foreach ( $vehicles as $zair_v ) : ?>
							<option value="<?php echo esc_attr( $zair_v->id ); ?>"
								<?php selected( $zair_is_edit ? (int) $compat->vehicle_engine_id : 0, (int) $zair_v->id ); ?>>
								<?php
								echo esc_html(
									trim( $zair_v->marca . ' ' . $zair_v->modelo . ' ' . $zair_v->version . ' ' . $zair_v->cilindrada )
									. ( $zair_v->codigo_motor ? ' · ' . $zair_v->codigo_motor : '' )
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( empty( $vehicles ) ) : ?>
						<p class="description" style="color:#b32d2e;">
							No hay vehículos activos.
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=zair-vehicles&view=new' ) ); ?>">Crea uno primero</a>.
						</p>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="zair-product-search">Producto WooCommerce <span class="zair-req">*</span></label></th>
				<td>
					<div id="zair-product-selected">
						<?php if ( $zair_prod ) : ?>
							<div class="zair-selected-product">
								<strong><?php echo esc_html( $zair_prod['nombre'] ); ?></strong>
								<span class="zair-product-meta">ID: <?php echo esc_html( $zair_prod['id'] ); ?> · SKU: <?php echo esc_html( $zair_prod['sku'] ? $zair_prod['sku'] : '—' ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<input type="search" id="zair-product-search" class="regular-text"
						placeholder="Escribe el nombre o SKU del producto…" autocomplete="off">
					<div id="zair-product-results"></div>
					<p class="description">Busca entre los productos reales de tu tienda. Selecciona uno de la lista para vincularlo.</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="zair_tipo">Tipo de relación</label></th>
				<td>
					<select id="zair_tipo" name="tipo_relacion">
						<?php foreach ( ZAIR_Compatibility::TIPOS_RELACION as $zair_k => $zair_label ) : ?>
							<option value="<?php echo esc_attr( $zair_k ); ?>"
								<?php selected( $zair_is_edit ? $compat->tipo_relacion : 'complementario', $zair_k ); ?>>
								<?php echo esc_html( $zair_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						Usa <strong>Motor (producto principal)</strong> cuando el producto <em>sea</em> el motor.
						Así el sistema reconoce el vehículo al que pertenece la ficha que el cliente está viendo.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="zair_prioridad">Prioridad comercial</label></th>
				<td>
					<input type="number" id="zair_prioridad" name="nivel_prioridad" min="1" max="10" style="width:80px;"
						value="<?php echo esc_attr( $zair_is_edit ? (int) $compat->nivel_prioridad : 5 ); ?>">
					<p class="description">1 = baja, 10 = máxima. Es el factor de mayor peso en el ranking (45%).</p>
				</td>
			</tr>

			<tr>
				<th scope="row">Compatibilidad verificada</th>
				<td>
					<label>
						<input type="checkbox" name="compatibilidad_verificada" value="1"
							<?php checked( $zair_is_edit ? (int) $compat->compatibilidad_verificada : 0, 1 ); ?>>
						Confirmo que este producto es compatible con este vehículo/motor.
					</label>
					<p class="description" style="color:#b32d2e;">
						Sin esta confirmación el producto <strong>nunca</strong> se recomendará. Márcala solo si lo has comprobado.
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="zair_notas">Notas internas</label></th>
				<td>
					<textarea id="zair_notas" name="notas" rows="3" class="large-text"
						placeholder="Observaciones de instalación, equivalencias, año de fabricación…"><?php echo esc_textarea( $zair_is_edit ? $compat->notas : '' ); ?></textarea>
					<p class="description">Solo visibles en el panel; no se muestran al cliente.</p>
				</td>
			</tr>

			<tr>
				<th scope="row">Estado</th>
				<td>
					<label>
						<input type="checkbox" name="estado" value="1"
							<?php checked( $zair_is_edit ? (int) $compat->estado : 1, 1 ); ?>>
						Activa
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="zair_compat_submit" value="1" class="button button-primary">
				<?php echo $zair_is_edit ? 'Actualizar compatibilidad' : 'Crear compatibilidad'; ?>
			</button>
			<a href="<?php echo esc_url( $zair_back ); ?>" class="button">Cancelar</a>
		</p>
	</form>

</div>
