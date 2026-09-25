<?php
/**
 * Exportación de datos a CSV (Fase 9).
 *
 * Genera archivos compatibles con Excel en español: separador ';' y BOM
 * UTF-8 al inicio, para que las tildes se vean bien y las columnas no
 * queden todas en una celda al abrir el archivo con doble clic.
 *
 * Tres exportaciones:
 *   - leads:   datos de contacto y estado comercial.
 *   - eventos: registro anónimo del embudo (para análisis estadístico).
 *   - resumen: métricas calculadas del periodo.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Export
 */
class ZAIR_Export {

	/**
	 * Envía las cabeceras HTTP de descarga.
	 *
	 * @param string $filename Nombre del archivo.
	 * @return void
	 */
	private static function headers( $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
	}

	/**
	 * Abre la salida y escribe el BOM que Excel necesita.
	 *
	 * @return resource
	 */
	private static function open_output() {
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM UTF-8.
		return $out;
	}

	/**
	 * Escribe una fila con separador ';'.
	 *
	 * @param resource $out Recurso de salida.
	 * @param array    $row Valores.
	 * @return void
	 */
	private static function put( $out, $row ) {
		fputcsv( $out, $row, ';' );
	}

	/**
	 * Exporta los leads.
	 *
	 * @param array $args Filtros (s, estado).
	 * @return void Termina la ejecución.
	 */
	public static function leads( $args = array() ) {

		$args['per_page'] = 5000;
		$args['paged']    = 1;

		$result = ZAIR_Leads::query( $args );

		self::headers( 'zerox-ai-leads-' . current_time( 'Y-m-d' ) . '.csv' );
		$out = self::open_output();

		self::put(
			$out,
			array(
				'Codigo',
				'Fecha',
				'Nombre',
				'WhatsApp',
				'Producto consultado',
				'SKU',
				'Vehiculo',
				'Codigo motor',
				'Estado',
				'Venta generada',
				'Pedido',
				'Monto venta',
				'Consentimiento',
				'Fecha consentimiento',
				'Sesion',
			)
		);

		foreach ( $result['items'] as $lead ) {

			$producto = $lead->product_origin_id ? wc_get_product( $lead->product_origin_id ) : null;
			$vehiculo = $lead->vehicle_engine_id ? ZAIR_Vehicles::get( $lead->vehicle_engine_id ) : null;

			self::put(
				$out,
				array(
					$lead->lead_code,
					mysql2date( 'd/m/Y H:i', $lead->created_at ),
					$lead->nombre,
					// Prefijo ' para que Excel no convierta el número a notación científica.
					"'" . $lead->whatsapp,
					$producto ? $producto->get_name() : '',
					$producto ? $producto->get_sku() : '',
					$vehiculo ? trim( $vehiculo->marca . ' ' . $vehiculo->modelo . ' ' . $vehiculo->version . ' ' . $vehiculo->cilindrada ) : '',
					$vehiculo ? $vehiculo->codigo_motor : '',
					isset( ZAIR_Leads::ESTADOS[ $lead->estado ] ) ? ZAIR_Leads::ESTADOS[ $lead->estado ] : $lead->estado,
					$lead->venta_generada ? 'Si' : 'No',
					$lead->order_id ? $lead->order_id : '',
					$lead->monto_venta ? number_format( (float) $lead->monto_venta, 2, '.', '' ) : '',
					$lead->consentimiento ? 'Si' : 'No',
					$lead->consentimiento_fecha ? mysql2date( 'd/m/Y H:i', $lead->consentimiento_fecha ) : '',
					$lead->session_id,
				)
			);
		}

		fclose( $out );
		exit;
	}

	/**
	 * Exporta los eventos anónimos del embudo.
	 *
	 * @param string $desde Fecha inicial.
	 * @param string $hasta Fecha final.
	 * @return void Termina la ejecución.
	 */
	public static function events( $desde = '', $hasta = '' ) {
		global $wpdb;

		$table  = ZAIR_Database::table( 'zair_recommendation_events' );
		$where  = ' WHERE 1=1 ';
		$params = array();

		if ( $desde ) {
			$where   .= ' AND created_at >= %s ';
			$params[] = $desde . ' 00:00:00';
		}
		if ( $hasta ) {
			$where   .= ' AND created_at <= %s ';
			$params[] = $hasta . ' 23:59:59';
		}

		$sql  = "SELECT * FROM {$table}" . $where . ' ORDER BY created_at DESC LIMIT 50000';
		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		self::headers( 'zerox-ai-eventos-' . current_time( 'Y-m-d' ) . '.csv' );
		$out = self::open_output();

		self::put(
			$out,
			array(
				'ID',
				'Fecha',
				'Sesion',
				'Evento',
				'Producto origen',
				'Producto recomendado',
				'Vehiculo ID',
				'Posicion',
				'Score',
			)
		);

		foreach ( (array) $rows as $ev ) {
			self::put(
				$out,
				array(
					$ev->id,
					mysql2date( 'd/m/Y H:i:s', $ev->created_at ),
					$ev->session_id,
					$ev->event_type,
					$ev->product_origin_id,
					$ev->recommended_product_id,
					$ev->vehicle_engine_id,
					$ev->position,
					$ev->score,
				)
			);
		}

		fclose( $out );
		exit;
	}

	/**
	 * Exporta el resumen de métricas del periodo.
	 *
	 * @param string $desde    Fecha inicial.
	 * @param string $hasta    Fecha final.
	 * @param string $etiqueta Nombre del periodo.
	 * @return void Termina la ejecución.
	 */
	public static function summary( $desde, $hasta, $etiqueta ) {

		$r = ZAIR_Analytics::get_report( $desde, $hasta );
		$p = ZAIR_Analytics::compatibility_precision();

		self::headers( 'zerox-ai-resumen-' . current_time( 'Y-m-d' ) . '.csv' );
		$out = self::open_output();

		self::put( $out, array( 'Indicador', 'Valor' ) );
		self::put( $out, array( 'Periodo', $etiqueta ) );
		self::put( $out, array( 'Desde', $desde ? $desde : 'inicio' ) );
		self::put( $out, array( 'Hasta', $hasta ? $hasta : current_time( 'Y-m-d' ) ) );
		self::put( $out, array( '', '' ) );

		self::put( $out, array( 'Recomendaciones mostradas', $r['impresiones'] ) );
		self::put( $out, array( 'Clics en recomendaciones', $r['clics'] ) );
		self::put( $out, array( 'Agregados al carrito', $r['carritos'] ) );
		self::put( $out, array( 'Formularios abiertos', $r['lead_open'] ) );
		self::put( $out, array( 'Leads recibidos', $r['leads']['total'] ) );
		self::put( $out, array( 'Leads con venta', $r['leads']['con_venta'] ) );
		self::put( $out, array( 'Compras de productos recomendados', $r['compras'] ) );
		self::put( $out, array( 'Sesiones con recomendaciones', $r['sesiones'] ) );
		self::put( $out, array( '', '' ) );

		self::put( $out, array( 'CTR de recomendaciones (%)', $r['metricas']['ctr'] ) );
		self::put( $out, array( 'Conversion de leads (%)', $r['metricas']['conversion_leads'] ) );
		self::put( $out, array( 'Aceptacion de recomendaciones (%)', $r['metricas']['aceptacion'] ) );
		self::put( $out, array( 'Carrito a compra (%)', $r['metricas']['carrito_a_compra'] ) );
		self::put( $out, array( 'Abandono de carrito (%)', $r['metricas']['abandono_carrito'] ) );
		self::put( $out, array( 'Apertura de formulario (%)', $r['metricas']['apertura_form'] ) );
		self::put( $out, array( 'Envio de formulario (%)', $r['metricas']['envio_form'] ) );
		self::put( $out, array( 'Recomendaciones por sesion', $r['metricas']['recs_por_sesion'] ) );
		self::put( $out, array( '', '' ) );

		self::put( $out, array( 'Pedidos con recomendados', $r['ventas']['pedidos'] ) );
		self::put( $out, array( 'Monto atribuido a recomendaciones', number_format( $r['ventas']['total'], 2, '.', '' ) ) );
		self::put( $out, array( 'Monto de ventas por leads', number_format( $r['leads']['monto'], 2, '.', '' ) ) );
		self::put( $out, array( '', '' ) );

		self::put( $out, array( 'Compatibilidades registradas', $p['total'] ) );
		self::put( $out, array( 'Compatibilidades verificadas y activas', $p['verificadas'] ) );
		self::put( $out, array( 'Precision de compatibilidad (%)', $p['porcentaje'] ) );

		fclose( $out );
		exit;
	}
}
