<?php
/**
 * Vista: listado de Compatibilidades (Fase 4).
 * Variables: $result, $args, $vehicles.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_base = admin_url( 'admin.php?page=zair-compatibility' );
$zair_new  = add_query_arg( 'view', 'new', $zair_base );
?>
<div class="wrap zair-wrap">

	<h1 class="wp-heading-inline">ZEROX AI · Compatibilidades</h1>
	<a href="<?php echo esc_url( $zair_new ); ?>" class="page-title-action">Añadir compatibilidad</a>
	<hr class="wp-header-end">

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="zair-compatibility">
		<p style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:12px 0;">
			<select name="vehicle_engine_id">
				<option value="0">Todos los vehículos/motores</option>
				<?php foreach ( $vehicles as $zair_v ) : ?>
					<option value="<?php echo esc_attr( $zair_v->id ); ?>"
						<?php selected( $args['vehicle_engine_id'], (int) $zair_v->id ); ?>>
						<?php
						echo esc_html(
							trim( $zair_v->marca . ' ' . $zair_v->modelo . ' ' . $zair_v->version . ' ' . $zair_v->cilindrada )
							. ( $zair_v->codigo_motor ? ' · ' . $zair_v->codigo_motor : '' )
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="tipo">
				<option value="">Todos los tipos</option>
				<?php foreach ( ZAIR_Compatibility::TIPOS_RELACION as $zair_k => $zair_label ) : ?>
					<option value="<?php echo esc_attr( $zair_k ); ?>" <?php selected( $args['tipo'], $zair_k ); ?>>
						<?php echo esc_html( $zair_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="verificada">
				<option value="">Verificadas y no verificadas</option>
				<option value="1" <?php selected( $args['verificada'], '1' ); ?>>Solo verificadas</option>
				<option value="0" <?php selected( $args['verificada'], '0' ); ?>>Solo NO verificadas</option>
			</select>

			<button type="submit" class="button">Filtrar</button>
			<a href="<?php echo esc_url( $zair_base ); ?>" class="button-link">Limpiar</a>
		</p>
	</form>

	<table class="widefat striped zair-table" style="max-width:1200px;">
		<thead>
			<tr>
				<th>Vehículo / Motor</th>
				<th>Producto WooCommerce</th>
				<th>SKU</th>
				<th>Precio</th>
				<th>Stock</th>
				<th>Tipo</th>
				<th style="width:70px;">Prior.</th>
				<th>Verificada</th>
				<th>Activa</th>
				<th style="width:200px;">Acciones</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $result['items'] ) ) : ?>
				<tr>
					<td colspan="10">
						Aún no hay compatibilidades registradas.
						<a href="<?php echo esc_url( $zair_new ); ?>">Añade la primera</a>
						para que el recomendador tenga con qué trabajar.
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $result['items'] as $zair_c ) : ?>
					<?php
					$zair_id   = (int) $zair_c->id;
					$zair_prod = ZAIR_Compatibility::get_product_info( $zair_c->product_id );

					$zair_edit_url = add_query_arg(
						array(
							'view'      => 'edit',
							'compat_id' => $zair_id,
						),
						$zair_base
					);

					$zair_ver_action = $zair_c->compatibilidad_verificada ? 'unverify' : 'verify';
					$zair_ver_url    = wp_nonce_url(
						add_query_arg(
							array(
								'zair_action' => $zair_ver_action,
								'compat_id'   => $zair_id,
							),
							$zair_base
						),
						'zair_compat_' . $zair_ver_action . '_' . $zair_id
					);

					$zair_est_action = $zair_c->estado ? 'disable' : 'enable';
					$zair_est_url    = wp_nonce_url(
						add_query_arg(
							array(
								'zair_action' => $zair_est_action,
								'compat_id'   => $zair_id,
							),
							$zair_base
						),
						'zair_compat_' . $zair_est_action . '_' . $zair_id
					);

					$zair_del_url = wp_nonce_url(
						add_query_arg(
							array(
								'zair_action' => 'delete',
								'compat_id'   => $zair_id,
							),
							$zair_base
						),
						'zair_compat_delete_' . $zair_id
					);
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $zair_c->marca . ' ' . $zair_c->modelo ); ?></strong><br>
							<span class="zair-product-meta">
								<?php echo esc_html( trim( $zair_c->version . ' ' . $zair_c->cilindrada ) ); ?>
								<?php if ( $zair_c->codigo_motor ) : ?>
									· <code><?php echo esc_html( $zair_c->codigo_motor ); ?></code>
								<?php endif; ?>
							</span>
						</td>
						<td>
							<?php if ( $zair_prod ) : ?>
								<a href="<?php echo esc_url( $zair_prod['edit_url'] ); ?>" target="_blank">
									<?php echo esc_html( $zair_prod['nombre'] ); ?>
								</a>
								<?php if ( ! $zair_prod['publicado'] ) : ?>
									<span class="zair-badge zair-badge-error">No publicado</span>
								<?php endif; ?>
							<?php else : ?>
								<span class="zair-badge zair-badge-error">Producto eliminado (ID <?php echo esc_html( $zair_c->product_id ); ?>)</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $zair_prod ? $zair_prod['sku'] : '—' ); ?></td>
						<td><?php echo wp_kses_post( $zair_prod ? $zair_prod['precio'] : '—' ); ?></td>
						<td>
							<?php if ( $zair_prod && $zair_prod['en_stock'] ) : ?>
								<span class="zair-badge zair-badge-ok">Sí</span>
							<?php else : ?>
								<span class="zair-badge zair-badge-error">No</span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo esc_html(
								isset( ZAIR_Compatibility::TIPOS_RELACION[ $zair_c->tipo_relacion ] )
									? ZAIR_Compatibility::TIPOS_RELACION[ $zair_c->tipo_relacion ]
									: $zair_c->tipo_relacion
							);
							?>
						</td>
						<td><?php echo esc_html( $zair_c->nivel_prioridad ); ?></td>
						<td>
							<?php if ( $zair_c->compatibilidad_verificada ) : ?>
								<span class="zair-badge zair-badge-ok">✔ Sí</span>
							<?php else : ?>
								<span class="zair-badge zair-badge-pending">Pendiente</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $zair_c->estado ) : ?>
								<span class="zair-badge zair-badge-ok">Sí</span>
							<?php else : ?>
								<span class="zair-badge zair-badge-error">No</span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $zair_edit_url ); ?>">Editar</a> |
							<a href="<?php echo esc_url( $zair_ver_url ); ?>">
								<?php echo $zair_c->compatibilidad_verificada ? 'Quitar verif.' : 'Verificar'; ?>
							</a> |
							<a href="<?php echo esc_url( $zair_est_url ); ?>">
								<?php echo $zair_c->estado ? 'Desactivar' : 'Activar'; ?>
							</a> |
							<a href="<?php echo esc_url( $zair_del_url ); ?>" class="zair-delete"
								onclick="return confirm('¿Eliminar esta compatibilidad?');">Eliminar</a>
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
		Total: <?php echo esc_html( number_format_i18n( $result['total'] ) ); ?> compatibilidad(es).
		Solo las marcadas como <strong>verificadas</strong> y <strong>activas</strong> llegarán al recomendador.
		Marca como tipo <em>Motor</em> el producto que representa al motor: es lo que permite reconocer el vehículo desde la ficha de producto.
	</p>

</div>
