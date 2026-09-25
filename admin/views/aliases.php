<?php
/**
 * Vista: Alias de búsqueda (Fase 4).
 * Variables: $aliases (array), $vehicles (array), $search (string).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_base = admin_url( 'admin.php?page=zair-aliases' );
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · Alias de búsqueda</h1>
	<p class="zair-meta">
		Formas alternativas en que un cliente puede nombrar un vehículo o motor.
		Resuelven la búsqueda sin gastar una llamada a la IA, y en la Fase 11 serán el diccionario que la IA consulta primero.
	</p>

	<div class="zair-placeholder" style="max-width:820px;">
		<h2 style="margin-top:0;">Añadir alias</h2>
		<form method="post" action="<?php echo esc_url( $zair_base ); ?>">
			<?php wp_nonce_field( 'zair_save_alias', 'zair_alias_nonce' ); ?>
			<p style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap;">
				<input type="text" name="alias" class="regular-text" required maxlength="120"
					placeholder="b12, n300 1200, motor n300…">
				<select name="vehicle_engine_id" required>
					<option value="">— Vehículo/motor —</option>
					<?php foreach ( $vehicles as $zair_v ) : ?>
						<option value="<?php echo esc_attr( $zair_v->id ); ?>">
							<?php
							echo esc_html(
								trim( $zair_v->marca . ' ' . $zair_v->modelo . ' ' . $zair_v->version . ' ' . $zair_v->cilindrada )
								. ( $zair_v->codigo_motor ? ' · ' . $zair_v->codigo_motor : '' )
							);
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" name="zair_alias_submit" value="1" class="button button-primary">Añadir alias</button>
			</p>
			<p class="description">Se guardan en minúsculas. Escribe cómo lo diría un cliente real, no cómo se escribe en el catálogo.</p>
		</form>
	</div>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="zair-aliases">
		<p style="margin:18px 0 8px;">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar alias…">
			<button type="submit" class="button">Buscar</button>
		</p>
	</form>

	<table class="widefat striped zair-table" style="max-width:900px;">
		<thead>
			<tr>
				<th style="width:60px;">ID</th>
				<th>Alias</th>
				<th>Vehículo / Motor</th>
				<th style="width:100px;">Confianza</th>
				<th style="width:100px;">Estado</th>
				<th style="width:170px;">Acciones</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $aliases ) ) : ?>
				<tr><td colspan="6">No hay alias registrados todavía.</td></tr>
			<?php else : ?>
				<?php foreach ( $aliases as $zair_a ) : ?>
					<?php
					$zair_id     = (int) $zair_a->id;
					$zair_action = $zair_a->estado ? 'disable' : 'enable';

					$zair_toggle = wp_nonce_url(
						add_query_arg(
							array(
								'zair_action' => $zair_action,
								'alias_id'    => $zair_id,
							),
							$zair_base
						),
						'zair_alias_' . $zair_action . '_' . $zair_id
					);

					$zair_delete = wp_nonce_url(
						add_query_arg(
							array(
								'zair_action' => 'delete',
								'alias_id'    => $zair_id,
							),
							$zair_base
						),
						'zair_alias_delete_' . $zair_id
					);
					?>
					<tr>
						<td><?php echo esc_html( $zair_id ); ?></td>
						<td><code><?php echo esc_html( $zair_a->alias ); ?></code></td>
						<td>
							<?php
							echo esc_html(
								trim( $zair_a->marca . ' ' . $zair_a->modelo . ' ' . $zair_a->version . ' ' . $zair_a->cilindrada )
								. ( $zair_a->codigo_motor ? ' · ' . $zair_a->codigo_motor : '' )
							);
							?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (float) $zair_a->confianza, 2 ) ); ?></td>
						<td>
							<?php if ( $zair_a->estado ) : ?>
								<span class="zair-badge zair-badge-ok">Activo</span>
							<?php else : ?>
								<span class="zair-badge zair-badge-error">Inactivo</span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $zair_toggle ); ?>"><?php echo $zair_a->estado ? 'Desactivar' : 'Activar'; ?></a> |
							<a href="<?php echo esc_url( $zair_delete ); ?>" class="zair-delete"
								onclick="return confirm('¿Eliminar este alias?');">Eliminar</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

</div>
