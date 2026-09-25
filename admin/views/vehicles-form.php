<?php
/**
 * Vista: formulario de alta/edición de Motores / Vehículos (Fase 3).
 * Variables disponibles: $vehicle (object|null).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_is_edit = ( $vehicle && ! empty( $vehicle->id ) );
$zair_title   = $zair_is_edit ? 'Editar vehículo/motor' : 'Nuevo vehículo/motor';
$zair_back    = admin_url( 'admin.php?page=zair-vehicles' );

$zair_val = function ( $field ) use ( $vehicle ) {
	return $vehicle && isset( $vehicle->{$field} ) ? $vehicle->{$field} : '';
};
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · <?php echo esc_html( $zair_title ); ?></h1>
	<p><a href="<?php echo esc_url( $zair_back ); ?>">← Volver al listado</a></p>

	<?php if ( $zair_is_edit && '' === $zair_val( 'marca' ) ) : ?>
		<div class="notice notice-error"><p>El registro solicitado no existe.</p></div>
	<?php else : ?>

	<form method="post" action="<?php echo esc_url( $zair_back ); ?>" class="zair-form">
		<?php wp_nonce_field( 'zair_save_vehicle', 'zair_vehicle_nonce' ); ?>
		<input type="hidden" name="vehicle_id" value="<?php echo esc_attr( $zair_is_edit ? (int) $vehicle->id : 0 ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="zair_marca">Marca <span class="zair-req">*</span></label></th>
				<td>
					<input type="text" id="zair_marca" name="marca" class="regular-text" required
						maxlength="60" value="<?php echo esc_attr( $zair_val( 'marca' ) ); ?>"
						placeholder="Chevrolet">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_modelo">Modelo <span class="zair-req">*</span></label></th>
				<td>
					<input type="text" id="zair_modelo" name="modelo" class="regular-text" required
						maxlength="80" value="<?php echo esc_attr( $zair_val( 'modelo' ) ); ?>"
						placeholder="N300">
					<p class="description">Un modelo por registro. Si N300 y N200 comparten motor, crea dos registros con el mismo código de motor.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_version">Versión</label></th>
				<td>
					<input type="text" id="zair_version" name="version" class="regular-text"
						maxlength="80" value="<?php echo esc_attr( $zair_val( 'version' ) ); ?>"
						placeholder="Work, Max, Cargo... (opcional)">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_cilindrada">Cilindrada</label></th>
				<td>
					<input type="text" id="zair_cilindrada" name="cilindrada" style="width:120px;"
						maxlength="10" value="<?php echo esc_attr( $zair_val( 'cilindrada' ) ); ?>"
						placeholder="1.2">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_codigo_motor">Código de motor</label></th>
				<td>
					<input type="text" id="zair_codigo_motor" name="codigo_motor" style="width:160px; text-transform:uppercase;"
						maxlength="30" value="<?php echo esc_attr( $zair_val( 'codigo_motor' ) ); ?>"
						placeholder="B12">
					<p class="description">Se guarda en mayúsculas. Es la clave que agrupa modelos que comparten motor.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_combustible">Combustible</label></th>
				<td>
					<select id="zair_combustible" name="combustible">
						<?php foreach ( ZAIR_Vehicles::COMBUSTIBLES as $zair_fuel ) : ?>
							<option value="<?php echo esc_attr( $zair_fuel ); ?>"
								<?php selected( $zair_val( 'combustible' ), $zair_fuel ); ?>>
								<?php echo '' === $zair_fuel ? '— Sin especificar —' : esc_html( $zair_fuel ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">Estado</th>
				<td>
					<label>
						<input type="checkbox" name="estado" value="1"
							<?php checked( $zair_is_edit ? (int) $vehicle->estado : 1, 1 ); ?>>
						Activo (disponible para compatibilidades y recomendaciones)
					</label>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="zair_vehicle_submit" value="1" class="button button-primary">
				<?php echo $zair_is_edit ? 'Actualizar' : 'Crear vehículo/motor'; ?>
			</button>
			<a href="<?php echo esc_url( $zair_back ); ?>" class="button">Cancelar</a>
		</p>
	</form>

	<?php endif; ?>

</div>
