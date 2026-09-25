<?php
/**
 * Vista: Dashboard de estado del sistema (Fase 2).
 * Variables disponibles: $db_status (array de ZAIR_Database::get_status()).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_table_labels = array(
	'zair_vehicle_engine'        => 'Vehículos / Motores',
	'zair_product_compatibility' => 'Compatibilidades',
	'zair_aliases'               => 'Alias de búsqueda',
	'zair_recommendation_events' => 'Eventos de recomendación',
	'zair_leads'                 => 'Leads',
);

$zair_phases = array(
	array( '2', 'Estructura, instalación y tablas', true ),
	array( '3', 'Panel Motores / Vehículos', true ),
	array( '4', 'Panel Compatibilidades + Alias', true ),
	array( '5', 'Motor de recomendaciones (reglas + ranking)', true ),
	array( '6', 'Frontend "Complementa tu motor"', true ),
	array( '7', 'Formulario de captura de leads', true ),
	array( '8', 'Registro de eventos y atribución de ventas', true ),
	array( '9', 'Dashboard de métricas + exportación CSV', true ),
	array( '10', 'Integración GA4', true ),
	array( '11', 'IA generativa (interpretación de búsquedas)', true ),
	array( '12', 'Optimización, seguridad y pruebas', true ),
);
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · Estado del sistema</h1>
	<p class="zair-meta">
		Plugin v<?php echo esc_html( ZAIR_VERSION ); ?> ·
		Esquema BD v<?php echo esc_html( get_option( 'zair_db_version', '—' ) ); ?> ·
		WooCommerce <?php echo class_exists( 'WooCommerce' ) ? 'v' . esc_html( WC()->version ) : 'no detectado'; ?>
	</p>

	<h2>Tablas del plugin</h2>
	<table class="widefat striped zair-table">
		<thead>
			<tr>
				<th>Tabla</th>
				<th>Nombre en BD</th>
				<th>Estado</th>
				<th>Registros</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $db_status as $zair_table => $zair_info ) : ?>
				<tr>
					<td><?php echo esc_html( isset( $zair_table_labels[ $zair_table ] ) ? $zair_table_labels[ $zair_table ] : $zair_table ); ?></td>
					<td><code><?php echo esc_html( ZAIR_Database::table( $zair_table ) ); ?></code></td>
					<td>
						<?php if ( $zair_info['exists'] ) : ?>
							<span class="zair-badge zair-badge-ok">✔ Creada</span>
						<?php else : ?>
							<span class="zair-badge zair-badge-error">✖ No existe</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( number_format_i18n( $zair_info['rows'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Diagnóstico del sistema</h2>
	<?php
	// Blindaje: un fallo en el diagnóstico no debe dejar el panel inaccesible.
	$zair_checks = array();
	if ( class_exists( 'ZAIR_Maintenance' ) && method_exists( 'ZAIR_Maintenance', 'diagnostics' ) ) {
		try {
			$zair_checks = ZAIR_Maintenance::diagnostics();
		} catch ( Throwable $e ) {
			$zair_checks = array(
				array(
					'titulo'  => 'Diagnóstico',
					'estado'  => 'warning',
					'mensaje' => 'No se pudo completar: ' . $e->getMessage(),
				),
			);
		}
	}
	?>
	<table class="widefat striped zair-table" style="max-width:900px;">
		<tbody>
			<?php foreach ( $zair_checks as $zair_check ) : ?>
				<tr>
					<td style="width:280px;"><?php echo esc_html( $zair_check['titulo'] ); ?></td>
					<td style="width:110px;">
						<?php if ( 'ok' === $zair_check['estado'] ) : ?>
							<span class="zair-badge zair-badge-ok">✔ Correcto</span>
						<?php elseif ( 'warning' === $zair_check['estado'] ) : ?>
							<span class="zair-badge zair-badge-pending">Revisar</span>
						<?php else : ?>
							<span class="zair-badge zair-badge-error">✖ Atención</span>
						<?php endif; ?>
					</td>
					<td class="zair-product-meta" style="margin:0;"><?php echo esc_html( $zair_check['mensaje'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Embudo de recomendaciones</h2>
	<?php
	$zair_totals   = class_exists( 'ZAIR_Events' ) ? ZAIR_Events::totals() : array();
	$zair_sesiones = class_exists( 'ZAIR_Events' ) ? ZAIR_Events::sessions_with_recommendations() : 0;
	$zair_leads_n  = class_exists( 'ZAIR_Leads' ) ? array_sum( ZAIR_Leads::count_by_estado() ) : 0;

	// Valores por defecto para que ninguna clave ausente rompa la vista.
	$zair_totals = wp_parse_args(
		$zair_totals,
		array(
			'impression'  => 0,
			'click'       => 0,
			'add_cart'    => 0,
			'purchase'    => 0,
			'lead_open'   => 0,
			'lead_submit' => 0,
		)
	);

	$zair_ctr = $zair_totals['impression'] > 0
		? ( $zair_totals['click'] / $zair_totals['impression'] ) * 100
		: 0;

	$zair_aceptacion = $zair_totals['click'] > 0
		? ( $zair_totals['purchase'] / $zair_totals['click'] ) * 100
		: 0;

	$zair_funnel = array(
		array( 'Recomendaciones mostradas', $zair_totals['impression'] ),
		array( 'Clics en recomendaciones', $zair_totals['click'] ),
		array( 'Agregados al carrito', $zair_totals['add_cart'] ),
		array( 'Formularios abiertos', $zair_totals['lead_open'] ),
		array( 'Leads enviados', $zair_leads_n ),
		array( 'Compras de recomendados', $zair_totals['purchase'] ),
	);
	?>
	<table class="widefat striped zair-table" style="max-width:620px;">
		<tbody>
			<?php foreach ( $zair_funnel as $zair_step ) : ?>
				<tr>
					<td><?php echo esc_html( $zair_step[0] ); ?></td>
					<td style="text-align:right; width:120px;">
						<strong><?php echo esc_html( number_format_i18n( $zair_step[1] ) ); ?></strong>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<td>Sesiones con recomendaciones</td>
				<td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $zair_sesiones ) ); ?></strong></td>
			</tr>
			<tr>
				<td>CTR de recomendaciones</td>
				<td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $zair_ctr, 1 ) ); ?>%</strong></td>
			</tr>
			<tr>
				<td>Aceptación (compras / clics)</td>
				<td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $zair_aceptacion, 1 ) ); ?>%</strong></td>
			</tr>
		</tbody>
	</table>
	<p class="zair-meta">
		Métricas acumuladas desde la instalación. Consulta el desglose por periodo y las exportaciones en Analítica.
	</p>

	<h2>Hoja de ruta del desarrollo</h2>
	<table class="widefat striped zair-table">
		<thead>
			<tr>
				<th style="width:80px;">Fase</th>
				<th>Alcance</th>
				<th style="width:140px;">Estado</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $zair_phases as $zair_phase ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $zair_phase[0] ); ?></strong></td>
					<td><?php echo esc_html( $zair_phase[1] ); ?></td>
					<td>
						<?php if ( $zair_phase[2] ) : ?>
							<span class="zair-badge zair-badge-ok">Completada</span>
						<?php else : ?>
							<span class="zair-badge zair-badge-pending">Pendiente</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p class="zair-meta">
		Regla del recomendador: si <code>compatibilidad_verificada ≠ 1</code>, el producto no se muestra.
		Los datos personales de leads viven solo en esta base; GA4 recibirá únicamente eventos anónimos.
	</p>

</div>
