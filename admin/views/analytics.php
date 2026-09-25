<?php
/**
 * Vista: Analítica del embudo (v1.5).
 *
 * Variables: $report, $range, $rango, $precision, $series, $top,
 *            $vendedores, $cotizaciones.
 *
 * Los gráficos se dibujan con CSS: una librería externa añadiría peso al
 * panel sin aportar nada que estas escalas no resuelvan.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_export_url = function ( $tipo ) use ( $rango ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'page'        => 'zair-analytics',
				'zair_export' => $tipo,
				'rango'       => $rango,
			),
			admin_url( 'admin.php' )
		),
		'zair_export_' . $tipo
	);
};

$zair_max_dia = 1;
foreach ( $series as $zair_d ) {
	$zair_max_dia = max( $zair_max_dia, (int) $zair_d->impresiones );
}

$zair_max_vend = 1;
foreach ( $vendedores as $zair_v ) {
	$zair_max_vend = max( $zair_max_vend, (int) $zair_v->total );
}
?>
<div class="wrap zair-wrap zair-analytics">

	<h1>ZEROX AI · Analítica</h1>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="zair-periodo">
		<input type="hidden" name="page" value="zair-analytics">
		<label for="zair_rango"><strong>Periodo</strong></label>
		<select id="zair_rango" name="rango" onchange="this.form.submit();">
			<?php foreach ( ZAIR_Analytics::RANGOS as $zair_k => $zair_label ) : ?>
				<option value="<?php echo esc_attr( $zair_k ); ?>" <?php selected( $rango, $zair_k ); ?>>
					<?php echo esc_html( $zair_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<span class="zair-fechas">
			<?php echo esc_html( $range['desde'] ? $range['desde'] . ' → ' . $range['hasta'] : 'todo el histórico' ); ?>
		</span>
	</form>

	<div class="zair-cards">
		<?php
		$zair_kpis = array(
			array(
				number_format_i18n( $report['impresiones'] ),
				'Recomendaciones mostradas',
				number_format_i18n( $report['sesiones'] ) . ' sesiones',
				'azul',
			),
			array(
				number_format_i18n( $report['metricas']['ctr'], 1 ) . '%',
				'CTR de recomendaciones',
				number_format_i18n( $report['clics'] ) . ' clics',
				'naranja',
			),
			array(
				number_format_i18n( $cotizaciones['total'] ),
				'Solicitudes de cotización',
				$cotizaciones['sin_asignar'] > 0
					? number_format_i18n( $cotizaciones['sin_asignar'] ) . ' sin asignar'
					: 'todas asignadas',
				'verde',
			),
			array(
				number_format_i18n( $precision['porcentaje'], 1 ) . '%',
				'Precisión de compatibilidad',
				$precision['verificadas'] . ' de ' . $precision['total'] . ' verificadas',
				'gris',
			),
		);

		foreach ( $zair_kpis as $zair_kpi ) :
			?>
			<div class="zair-card zair-card-<?php echo esc_attr( $zair_kpi[3] ); ?>">
				<span class="zair-card-valor"><?php echo esc_html( $zair_kpi[0] ); ?></span>
				<span class="zair-card-label"><?php echo esc_html( $zair_kpi[1] ); ?></span>
				<span class="zair-card-detalle"><?php echo esc_html( $zair_kpi[2] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="zair-cols">

		<div class="zair-panel">
			<h2>Embudo de conversión</h2>
			<?php
			$zair_pasos = array(
				array( 'Vieron recomendaciones', $report['impresiones'], null ),
				array( 'Hicieron clic', $report['clics'], $report['metricas']['ctr'] ),
				array( 'Agregaron al carrito', $report['carritos'], ZAIR_Analytics::ratio( $report['carritos'], $report['clics'] ) ),
				array( 'Abrieron el formulario', $report['lead_open'], ZAIR_Analytics::ratio( $report['lead_open'], $report['impresiones'] ) ),
				array( 'Enviaron sus datos', $report['lead_submit'], $report['metricas']['envio_form'] ),
				array( 'Compraron un recomendado', $report['compras'], ZAIR_Analytics::ratio( $report['compras'], $report['carritos'] ) ),
			);

			$zair_base_embudo = max( 1, (int) $report['impresiones'] );

			foreach ( $zair_pasos as $zair_paso ) :
				$zair_ancho = min( 100, ( $zair_paso[1] / $zair_base_embudo ) * 100 );
				$zair_ancho = $zair_paso[1] > 0 ? max( 4, $zair_ancho ) : 0;
				?>
				<div class="zair-funnel-row">
					<div class="zair-funnel-head">
						<span class="zair-funnel-label"><?php echo esc_html( $zair_paso[0] ); ?></span>
						<span class="zair-funnel-num"><?php echo esc_html( number_format_i18n( $zair_paso[1] ) ); ?></span>
					</div>
					<div class="zair-funnel-track">
						<div class="zair-funnel-bar" style="width:<?php echo esc_attr( $zair_ancho ); ?>%;"></div>
					</div>
					<span class="zair-funnel-pct">
						<?php echo null === $zair_paso[2] ? 'base' : esc_html( number_format_i18n( $zair_paso[2], 1 ) . '% del paso anterior' ); ?>
					</span>
				</div>
			<?php endforeach; ?>

			<p class="zair-nota">
				Las recomendaciones se cuentan cuando el carrusel llega a verse en pantalla, no al cargar la página:
				el CTR refleja así lo que el visitante tuvo realmente delante.
			</p>
		</div>

		<div class="zair-panel">
			<h2>Indicadores clave</h2>
			<table class="zair-kpi-table">
				<tbody>
					<?php
					$zair_ind = array(
						array( 'CTR de recomendaciones', $report['metricas']['ctr'] . '%', 'clics / mostradas' ),
						array( 'Aceptación de recomendaciones', $report['metricas']['aceptacion'] . '%', 'compras / clics' ),
						array( 'Carrito → compra', $report['metricas']['carrito_a_compra'] . '%', 'compras / carrito' ),
						array( 'Abandono de carrito', $report['metricas']['abandono_carrito'] . '%', 'complemento' ),
						array( 'Apertura de formulario', $report['metricas']['apertura_form'] . '%', 'aperturas / mostradas' ),
						array( 'Envío de formulario', $report['metricas']['envio_form'] . '%', 'envíos / aperturas' ),
						array( 'Conversión de leads', $report['metricas']['conversion_leads'] . '%', 'con venta / total' ),
						array( 'Recomendaciones por sesión', $report['metricas']['recs_por_sesion'], 'impresiones / sesiones' ),
					);
					foreach ( $zair_ind as $zair_row ) :
						?>
						<tr>
							<td class="zair-kpi-nombre"><?php echo esc_html( $zair_row[0] ); ?></td>
							<td class="zair-kpi-valor"><?php echo esc_html( $zair_row[1] ); ?></td>
							<td class="zair-kpi-formula"><?php echo esc_html( $zair_row[2] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	</div>

	<div class="zair-panel">
		<h2>Reparto de solicitudes por vendedor</h2>

		<?php if ( empty( $vendedores ) ) : ?>
			<p class="zair-nota">
				Sin datos de reparto en este periodo. Requiere el plugin de cotizaciones activo
				y al menos un vendedor configurado.
			</p>
		<?php else : ?>
			<table class="zair-tabla">
				<thead>
					<tr>
						<th>Vendedor</th>
						<th class="zair-th-bar">Solicitudes recibidas</th>
						<th>Total</th>
						<th>Contactadas</th>
						<th>Cotizadas</th>
						<th>Ventas</th>
						<th>Cierre</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $vendedores as $zair_v ) : ?>
						<?php
						$zair_ancho  = ( $zair_v->total / $zair_max_vend ) * 100;
						$zair_cierre = ZAIR_Analytics::ratio( $zair_v->ventas, $zair_v->total );
						?>
						<tr>
							<td class="zair-vend-nombre"><?php echo esc_html( $zair_v->vendedor_nombre ); ?></td>
							<td>
								<div class="zair-bar-track">
									<div class="zair-bar" style="width:<?php echo esc_attr( max( 3, $zair_ancho ) ); ?>%;"></div>
								</div>
							</td>
							<td class="zair-num"><strong><?php echo esc_html( number_format_i18n( $zair_v->total ) ); ?></strong></td>
							<td class="zair-num"><?php echo esc_html( number_format_i18n( $zair_v->contactados ) ); ?></td>
							<td class="zair-num"><?php echo esc_html( number_format_i18n( $zair_v->cotizados ) ); ?></td>
							<td class="zair-num"><?php echo esc_html( number_format_i18n( $zair_v->ventas ) ); ?></td>
							<td class="zair-num zair-destacado"><?php echo esc_html( number_format_i18n( $zair_cierre, 1 ) ); ?>%</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="zair-nota">
				Con el reparto por rotación las cantidades deben quedar prácticamente iguales.
				Una diferencia marcada indica que algún vendedor estuvo desactivado durante parte del periodo.
			</p>
		<?php endif; ?>
	</div>

	<div class="zair-panel">
		<h2>Evolución diaria</h2>

		<?php if ( empty( $series ) ) : ?>
			<p class="zair-nota">Sin actividad registrada en este periodo.</p>
		<?php else : ?>
			<div class="zair-chart">
				<?php
				$zair_dias = array_reverse( $series );
				foreach ( $zair_dias as $zair_d ) :
					$zair_h_imp = ( $zair_d->impresiones / $zair_max_dia ) * 100;
					$zair_h_cli = ( $zair_d->clics / $zair_max_dia ) * 100;
					?>
					<div class="zair-chart-col" title="<?php echo esc_attr( mysql2date( 'd/m/Y', $zair_d->dia ) . ': ' . $zair_d->impresiones . ' impresiones, ' . $zair_d->clics . ' clics' ); ?>">
						<div class="zair-chart-bars">
							<span class="zair-chart-bar zair-bar-imp" style="height:<?php echo esc_attr( max( 2, $zair_h_imp ) ); ?>%;"></span>
							<span class="zair-chart-bar zair-bar-cli" style="height:<?php echo esc_attr( $zair_d->clics > 0 ? max( 2, $zair_h_cli ) : 0 ); ?>%;"></span>
						</div>
						<span class="zair-chart-x"><?php echo esc_html( mysql2date( 'd/m', $zair_d->dia ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="zair-leyenda">
				<span><i class="zair-dot zair-dot-imp"></i> Impresiones</span>
				<span><i class="zair-dot zair-dot-cli"></i> Clics</span>
			</div>
		<?php endif; ?>
	</div>

	<div class="zair-panel">
		<h2>Productos recomendados con mejor rendimiento</h2>

		<?php if ( empty( $top ) ) : ?>
			<p class="zair-nota">Sin datos todavía en este periodo.</p>
		<?php else : ?>
			<?php
			$zair_max_prod = 1;
			foreach ( $top as $zair_t ) {
				$zair_max_prod = max( $zair_max_prod, (int) $zair_t->impresiones );
			}
			?>
			<table class="zair-tabla">
				<thead>
					<tr>
						<th>Producto</th>
						<th class="zair-th-bar">Impresiones</th>
						<th>Impr.</th>
						<th>Clics</th>
						<th>CTR</th>
						<th>Carrito</th>
						<th>Compras</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top as $zair_t ) : ?>
						<?php
						$zair_p   = wc_get_product( $zair_t->recommended_product_id );
						$zair_ctr = ZAIR_Analytics::ratio( $zair_t->clics, $zair_t->impresiones );
						$zair_w   = ( $zair_t->impresiones / $zair_max_prod ) * 100;
						?>
						<tr>
							<td>
								<?php if ( $zair_p ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $zair_p->get_id() ) ); ?>" target="_blank">
										<?php echo esc_html( $zair_p->get_name() ); ?>
									</a>
								<?php else : ?>
									ID <?php echo esc_html( $zair_t->recommended_product_id ); ?>
								<?php endif; ?>
							</td>
							<td>
								<div class="zair-bar-track">
									<div class="zair-bar zair-bar-alt" style="width:<?php echo esc_attr( max( 2, $zair_w ) ); ?>%;"></div>
								</div>
							</td>
							<td class="zair-num"><?php echo esc_html( number_format_i18n( $zair_t->impresiones ) ); ?></td>
							<td class="zair-num"><?php echo esc_html( number_format_i18n( $zair_t->clics ) ); ?></td>
							<td class="zair-num zair-destacado"><?php echo esc_html( number_format_i18n( $zair_ctr, 1 ) ); ?>%</td>
							<td class="zair-num"><?php echo esc_html( number_format_i18n( $zair_t->carritos ) ); ?></td>
							<td class="zair-num"><?php echo esc_html( number_format_i18n( $zair_t->compras ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="zair-cols">
		<div class="zair-panel">
			<h2>Ventas atribuidas</h2>
			<table class="zair-kpi-table">
				<tbody>
					<tr>
						<td class="zair-kpi-nombre">Pedidos con producto recomendado</td>
						<td class="zair-kpi-valor"><?php echo esc_html( number_format_i18n( $report['ventas']['pedidos'] ) ); ?></td>
					</tr>
					<tr>
						<td class="zair-kpi-nombre">Monto atribuido a recomendaciones</td>
						<td class="zair-kpi-valor"><?php echo wp_kses_post( wc_price( $report['ventas']['total'] ) ); ?></td>
					</tr>
					<tr>
						<td class="zair-kpi-nombre">Monto de ventas por leads</td>
						<td class="zair-kpi-valor"><?php echo wp_kses_post( wc_price( $report['leads']['monto'] ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<p class="zair-nota">
				La venta de ZEROXMOTORS se cierra por atención personalizada, de modo que estos importes solo
				recogen las operaciones completadas dentro de la plataforma.
			</p>
		</div>

		<div class="zair-panel">
			<h2>Exportar datos</h2>
			<p class="zair-export">
				<a href="<?php echo esc_url( $zair_export_url( 'summary' ) ); ?>" class="button button-primary">Resumen de métricas</a>
				<a href="<?php echo esc_url( $zair_export_url( 'events' ) ); ?>" class="button">Eventos del embudo</a>
				<a href="<?php echo esc_url( $zair_export_url( 'leads' ) ); ?>" class="button">Leads</a>
			</p>
			<p class="zair-nota">
				Archivos con separador «;» y codificación UTF-8: se abren en Excel con las columnas separadas.
				El de eventos contiene una fila por interacción, con su sesión anónima, posición en el carrusel
				y puntaje asignado: es el que necesitarás para el análisis estadístico.
			</p>
		</div>
	</div>

</div>
