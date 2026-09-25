<?php
/**
 * Vista: Autodetección de compatibilidades (v1.2).
 * Variables: $grupos (array), $resumen (array|null).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_base = admin_url( 'admin.php?page=zair-autodetect' );
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · Autodetección</h1>
	<p class="zair-meta" style="max-width:820px;">
		Tu catálogo ya guarda el código de motor dentro de cada producto (por ejemplo <code>C14</code> o <code>4G15S</code>).
		Esta herramienta lo aprovecha: agrupa los productos que comparten código y propone las compatibilidades de una sola vez.
	</p>

	<?php if ( $resumen ) : ?>
		<div class="notice notice-success">
			<p>
				<strong>Importación completada.</strong>
				Vehículos creados: <?php echo esc_html( $resumen['vehiculos'] ); ?> ·
				Compatibilidades creadas: <?php echo esc_html( $resumen['compatibilidades'] ); ?> ·
				Omitidas (ya existían): <?php echo esc_html( $resumen['omitidas'] ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $grupos ) ) : ?>
		<div class="zair-placeholder" style="max-width:760px;">
			<p>No se detectaron códigos de motor en el catálogo.</p>
			<p class="description">
				El sistema busca códigos alfanuméricos cortos (letras + números) dentro de la descripción de cada producto.
				Comprueba que los productos tengan el código escrito en su descripción.
			</p>
		</div>
	<?php else : ?>

		<form method="post" action="<?php echo esc_url( $zair_base ); ?>">
			<?php wp_nonce_field( 'zair_autodetect', 'zair_autodetect_nonce' ); ?>

			<p style="display:flex; gap:14px; align-items:center; flex-wrap:wrap; margin:18px 0;">
				<button type="submit" name="zair_autodetect_submit" value="1" class="button button-primary">
					Importar seleccionados
				</button>
				<label>
					<input type="checkbox" name="zair_verificar" value="1">
					Marcar como <strong>verificadas</strong> al importar
				</label>
				<button type="button" class="button" id="zair-toggle-all">Seleccionar / deseleccionar todo</button>
			</p>

			<p class="description" style="max-width:820px; margin-bottom:14px;">
				Si no marcas la casilla de verificación, las compatibilidades se crean <em>sin verificar</em> y no se mostrarán
				al cliente hasta que las revises en Compatibilidades. Es la opción segura si quieres comprobarlas una por una.
				Márcala solo si confías en que el código de motor de tus productos es correcto.
			</p>

			<table class="widefat striped zair-table" style="max-width:1100px;">
				<thead>
					<tr>
						<th style="width:36px;"></th>
						<th style="width:110px;">Código</th>
						<th>Vehículo detectado</th>
						<th style="width:90px;">Productos</th>
						<th>Productos incluidos</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $grupos as $zair_codigo => $zair_g ) : ?>
						<?php
						$zair_existe = ZAIR_Vehicles::query(
							array(
								's'        => $zair_codigo,
								'per_page' => 1,
							)
						);
						$zair_ya     = $zair_existe['total'] > 0;
						?>
						<tr>
							<td>
								<input type="checkbox" class="zair-chk" name="zair_codigos[]"
									value="<?php echo esc_attr( $zair_codigo ); ?>">
							</td>
							<td>
								<code><?php echo esc_html( $zair_codigo ); ?></code>
								<?php if ( $zair_ya ) : ?>
									<br><span class="zair-badge zair-badge-ok" style="margin-top:4px;">Ya existe</span>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( trim( $zair_g['marca'] . ' ' . $zair_g['modelo'] ) ); ?></strong>
								<?php if ( $zair_g['cilindrada'] ) : ?>
									<span class="zair-product-meta" style="display:inline; margin:0;">· <?php echo esc_html( $zair_g['cilindrada'] ); ?></span>
								<?php endif; ?>
								<?php if ( ! $zair_g['marca'] ) : ?>
									<br><span class="zair-badge zair-badge-pending">Marca no detectada — revísala después</span>
								<?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( count( $zair_g['productos'] ) ); ?></strong></td>
							<td>
								<div class="zair-product-meta" style="margin:0; max-height:110px; overflow-y:auto;">
									<?php foreach ( $zair_g['productos'] as $zair_p ) : ?>
										<?php
										$zair_tipo_label = isset( ZAIR_Compatibility::TIPOS_RELACION[ $zair_p['tipo'] ] )
											? ZAIR_Compatibility::TIPOS_RELACION[ $zair_p['tipo'] ]
											: $zair_p['tipo'];
										?>
										• <?php echo esc_html( $zair_p['titulo'] ); ?>
										<em>(<?php echo esc_html( $zair_tipo_label ); ?>)</em><br>
									<?php endforeach; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>

		<script>
		( function () {
			var btn = document.getElementById( 'zair-toggle-all' );
			if ( ! btn ) { return; }
			btn.addEventListener( 'click', function () {
				var chks = document.querySelectorAll( '.zair-chk' );
				var todos = Array.prototype.every.call( chks, function ( c ) { return c.checked; } );
				Array.prototype.forEach.call( chks, function ( c ) { c.checked = ! todos; } );
			} );
		} )();
		</script>

	<?php endif; ?>

</div>
