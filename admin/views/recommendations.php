<?php
/**
 * Vista: simulador del motor de recomendaciones (Fase 5).
 * Variables: $test_product (object|null), $result (array|null), $weights (array).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_base = admin_url( 'admin.php?page=zair-recommendations' );
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · Recomendaciones</h1>
	<p class="zair-meta">
		Simula qué vería un cliente en la ficha de un producto, con el score de cada recomendación desglosado.
		Nada de esto se muestra todavía en la web: el frontend llega en la Fase 6.
	</p>

	<div class="zair-placeholder" style="max-width:820px;">
		<h2 style="margin-top:0;">Probar un producto</h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="zair-recommendations">
			<p style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
				<label for="zair_test_id">ID del producto WooCommerce:</label>
				<input type="number" id="zair_test_id" name="test_id" style="width:130px;"
					value="<?php echo esc_attr( $test_product ? $test_product->get_id() : '' ); ?>" min="1">
				<button type="submit" class="button button-primary">Simular</button>
			</p>
			<p class="description">
				Encuentra el ID en Productos: aparece al pasar el cursor sobre el producto o en la URL de edición (<code>post=1234</code>).
			</p>
		</form>
	</div>

	<?php if ( $test_product ) : ?>

		<h2>Producto de origen</h2>
		<table class="widefat striped zair-table" style="max-width:820px;">
			<tbody>
				<tr>
					<th style="width:180px;">Producto</th>
					<td>
						<strong><?php echo esc_html( $test_product->get_name() ); ?></strong>
						(ID <?php echo esc_html( $test_product->get_id() ); ?>)
					</td>
				</tr>
				<tr>
					<th>Vehículo(s) detectado(s)</th>
					<td>
						<?php if ( empty( $result['vehicles'] ) ) : ?>
							<span class="zair-badge zair-badge-error">Ninguno</span>
							<p class="description" style="margin-top:6px;">
								Este producto no está relacionado con ningún vehículo/motor verificado y activo.
								Regístralo en <a href="<?php echo esc_url( admin_url( 'admin.php?page=zair-compatibility&view=new' ) ); ?>">Compatibilidades</a>
								con tipo <em>Motor (producto principal)</em> si se trata de un motor.
							</p>
						<?php else : ?>
							<?php foreach ( $result['vehicles'] as $zair_v ) : ?>
								<span class="zair-badge zair-badge-ok">
									<?php
									echo esc_html(
										trim( $zair_v->marca . ' ' . $zair_v->modelo . ' ' . $zair_v->version . ' ' . $zair_v->cilindrada )
										. ( $zair_v->codigo_motor ? ' · ' . $zair_v->codigo_motor : '' )
									);
									?>
								</span>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Compatibles disponibles</th>
					<td><?php echo esc_html( (int) $result['total_disponibles'] ); ?> (tras filtrar publicado y stock)</td>
				</tr>
			</tbody>
		</table>

		<h2>Recomendaciones que se mostrarían</h2>

		<?php if ( empty( $result['items'] ) ) : ?>
			<div class="zair-placeholder" style="max-width:820px;">
				<p>No hay recomendaciones para este producto.</p>
				<p class="description">
					Causas habituales: no hay compatibilidades verificadas para su motor, los productos compatibles están sin stock o despublicados,
					o el producto de origen no está vinculado a ningún vehículo.
				</p>
			</div>
		<?php else : ?>
			<table class="widefat striped zair-table" style="max-width:1200px;">
				<thead>
					<tr>
						<th style="width:50px;">#</th>
						<th>Producto</th>
						<th>Tipo</th>
						<th style="width:70px;">Prior.</th>
						<th>Precio</th>
						<th>Oferta</th>
						<th style="width:80px;">Score</th>
						<th>Desglose del score</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $result['items'] as $zair_i => $zair_item ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $zair_i + 1 ); ?></strong></td>
							<td>
								<?php echo esc_html( $zair_item['nombre'] ); ?><br>
								<span class="zair-product-meta">
									ID <?php echo esc_html( $zair_item['product_id'] ); ?> ·
									SKU <?php echo esc_html( $zair_item['sku'] ? $zair_item['sku'] : '—' ); ?> ·
									✓ Compatible con <?php echo esc_html( $zair_item['codigo_motor'] ); ?>
								</span>
							</td>
							<td>
								<?php
								echo esc_html(
									isset( ZAIR_Compatibility::TIPOS_RELACION[ $zair_item['tipo_relacion'] ] )
										? ZAIR_Compatibility::TIPOS_RELACION[ $zair_item['tipo_relacion'] ]
										: $zair_item['tipo_relacion']
								);
								?>
							</td>
							<td><?php echo esc_html( $zair_item['nivel_prioridad'] ); ?></td>
							<td><?php echo wp_kses_post( $zair_item['precio_html'] ); ?></td>
							<td>
								<?php if ( $zair_item['en_oferta'] ) : ?>
									<span class="zair-badge zair-badge-ok">Sí</span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( number_format_i18n( $zair_item['score'], 1 ) ); ?></strong></td>
							<td>
								<?php if ( ! empty( $zair_item['desglose'] ) ) : ?>
									<span class="zair-product-meta" style="margin-top:0;">
										Relevancia <?php echo esc_html( $zair_item['desglose']['relevancia_comercial'] ); ?> ·
										Tipo <?php echo esc_html( $zair_item['desglose']['tipo_relacion'] ); ?> ·
										Oferta <?php echo esc_html( $zair_item['desglose']['oferta_vigente'] ); ?> ·
										Rendim. <?php echo esc_html( $zair_item['desglose']['rendimiento'] ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php endif; ?>

	<h2>Pesos activos del ranking</h2>
	<table class="widefat striped zair-table" style="max-width:480px;">
		<tbody>
			<?php foreach ( $weights as $zair_k => $zair_w ) : ?>
				<tr>
					<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $zair_k ) ) ); ?></td>
					<td><strong><?php echo esc_html( number_format_i18n( $zair_w, 1 ) ); ?>%</strong></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="zair-meta">
		Ajústalos en <a href="<?php echo esc_url( admin_url( 'admin.php?page=zair-settings' ) ); ?>">Configuración</a>.
		El stock no aparece porque no es un peso: es un filtro previo, un producto sin stock nunca compite.
		El rendimiento histórico permanece neutro (0.5) hasta que existan eventos reales, a partir de la Fase 8.
	</p>

</div>
