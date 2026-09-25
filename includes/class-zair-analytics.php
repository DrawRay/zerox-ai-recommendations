<?php
/**
 * Analítica del embudo (Fase 9).
 *
 * Calcula todas las métricas del proyecto a partir de la base propia,
 * no de GA4. Esta es la fuente de verdad para el análisis del proyecto:
 * GA4 (Fase 10) será solo un espejo anónimo.
 *
 * Fórmulas implementadas:
 *   CTR                    = clics / impresiones × 100
 *   Conversión de leads    = leads con venta / total leads × 100
 *   Aceptación de recomend.= compras de recomendados / clics × 100
 *   Tasa carrito→compra    = compras / add_cart × 100
 *   Abandono de carrito    = 100 − tasa carrito→compra
 *   Apertura de formulario = lead_open / impresiones × 100
 *   Envío de formulario    = leads / lead_open × 100
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Analytics
 */
class ZAIR_Analytics {

	/**
	 * Rangos de fecha predefinidos.
	 *
	 * @var array<string,string>
	 */
	const RANGOS = array(
		'7'    => 'Últimos 7 días',
		'30'   => 'Últimos 30 días',
		'90'   => 'Últimos 90 días',
		'mes'  => 'Este mes',
		'todo' => 'Desde el inicio',
	);

	/**
	 * Traduce un rango en fechas concretas.
	 *
	 * @param string $rango Clave del rango.
	 * @return array{desde:string, hasta:string, etiqueta:string}
	 */
	public static function resolve_range( $rango ) {

		$hasta = current_time( 'Y-m-d' );

		switch ( $rango ) {
			case '7':
				$desde = gmdate( 'Y-m-d', strtotime( $hasta . ' -6 days' ) );
				break;

			case '90':
				$desde = gmdate( 'Y-m-d', strtotime( $hasta . ' -89 days' ) );
				break;

			case 'mes':
				$desde = current_time( 'Y-m' ) . '-01';
				break;

			case 'todo':
				$desde = '';
				$hasta = '';
				break;

			case '30':
			default:
				$rango = '30';
				$desde = gmdate( 'Y-m-d', strtotime( $hasta . ' -29 days' ) );
				break;
		}

		return array(
			'desde'    => $desde,
			'hasta'    => $hasta,
			'etiqueta' => isset( self::RANGOS[ $rango ] ) ? self::RANGOS[ $rango ] : self::RANGOS['30'],
		);
	}

	/**
	 * Calcula el informe completo del embudo.
	 *
	 * @param string $desde Fecha inicial (Y-m-d) o vacío.
	 * @param string $hasta Fecha final (Y-m-d) o vacío.
	 * @return array
	 */
	public static function get_report( $desde = '', $hasta = '' ) {

		$eventos  = ZAIR_Events::totals( $desde, $hasta );
		$sesiones = ZAIR_Events::sessions_with_recommendations( $desde, $hasta );
		$leads    = self::lead_stats( $desde, $hasta );
		$ventas   = ZAIR_WooCommerce::attributed_sales( $desde, $hasta );

		$impresiones = (int) $eventos['impression'];
		$clics       = (int) $eventos['click'];
		$carritos    = (int) $eventos['add_cart'];
		$compras     = (int) $eventos['purchase'];
		$lead_open   = (int) $eventos['lead_open'];
		$lead_submit = (int) $eventos['lead_submit'];

		return array(
			'eventos'     => $eventos,
			'sesiones'    => $sesiones,
			'leads'       => $leads,
			'ventas'      => $ventas,
			'impresiones' => $impresiones,
			'clics'       => $clics,
			'carritos'    => $carritos,
			'compras'     => $compras,
			'lead_open'   => $lead_open,
			'lead_submit' => $lead_submit,
			'metricas'    => array(
				'ctr'                => self::ratio( $clics, $impresiones ),
				'conversion_leads'   => self::ratio( $leads['con_venta'], $leads['total'] ),
				'aceptacion'         => self::ratio( $compras, $clics ),
				'carrito_a_compra'   => self::ratio( $compras, $carritos ),
				'abandono_carrito'   => $carritos > 0 ? round( 100 - self::ratio( $compras, $carritos ), 1 ) : 0,
				'apertura_form'      => self::ratio( $lead_open, $impresiones ),
				// Se compara con los envíos registrados como evento, no con
				// el total de la tabla de leads: esa tabla incluye también
				// las cotizaciones enviadas desde la ficha de producto, que
				// no pasan por el formulario del carrusel, y la proporción
				// salía por encima del 100 %.
				'envio_form'         => self::ratio( min( $lead_submit, $lead_open ), $lead_open ),
				'recs_por_sesion'    => $sesiones > 0 ? round( $impresiones / $sesiones, 1 ) : 0,
				'clics_por_sesion'   => $sesiones > 0 ? round( $clics / $sesiones, 2 ) : 0,
			),
		);
	}

	/**
	 * Porcentaje seguro (evita división por cero).
	 *
	 * @param int|float $parte Numerador.
	 * @param int|float $total Denominador.
	 * @return float
	 */
	public static function ratio( $parte, $total ) {
		if ( $total <= 0 ) {
			return 0.0;
		}
		return round( ( $parte / $total ) * 100, 1 );
	}

	/**
	 * Estadísticas de leads en el rango.
	 *
	 * @param string $desde Fecha inicial.
	 * @param string $hasta Fecha final.
	 * @return array{total:int, con_venta:int, monto:float, por_estado:array}
	 */
	public static function lead_stats( $desde = '', $hasta = '' ) {
		global $wpdb;

		$table  = ZAIR_Database::table( 'zair_leads' );
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

		$sql = "SELECT
					COUNT(*) AS total,
					SUM( CASE WHEN venta_generada = 1 THEN 1 ELSE 0 END ) AS con_venta,
					COALESCE( SUM( monto_venta ), 0 ) AS monto
				FROM {$table}" . $where;

		$row = $params
			? $wpdb->get_row( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		$sql_estado = "SELECT estado, COUNT(*) AS total FROM {$table}" . $where . ' GROUP BY estado';
		$rows       = $params
			? $wpdb->get_results( $wpdb->prepare( $sql_estado, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_results( $sql_estado ); // phpcs:ignore WordPress.DB.PreparedSQL

		$por_estado = array_fill_keys( array_keys( ZAIR_Leads::ESTADOS ), 0 );
		foreach ( (array) $rows as $r ) {
			if ( isset( $por_estado[ $r->estado ] ) ) {
				$por_estado[ $r->estado ] = (int) $r->total;
			}
		}

		return array(
			'total'      => $row ? (int) $row->total : 0,
			'con_venta'  => $row ? (int) $row->con_venta : 0,
			'monto'      => $row ? (float) $row->monto : 0.0,
			'por_estado' => $por_estado,
		);
	}

	/**
	 * Precisión de compatibilidad: proporción de relaciones verificadas
	 * sobre el total registrado. Indicador de calidad de la base.
	 *
	 * @return array{total:int, verificadas:int, porcentaje:float}
	 */
	public static function compatibility_precision() {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_product_compatibility' );

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB
			"SELECT COUNT(*) AS total,
					SUM( CASE WHEN compatibilidad_verificada = 1 AND estado = 1 THEN 1 ELSE 0 END ) AS verificadas
			 FROM {$table}"
		);

		$total       = $row ? (int) $row->total : 0;
		$verificadas = $row ? (int) $row->verificadas : 0;

		return array(
			'total'       => $total,
			'verificadas' => $verificadas,
			'porcentaje'  => self::ratio( $verificadas, $total ),
		);
	}

	/**
	 * Reparto de solicitudes por vendedor.
	 *
	 * Lee la tabla del plugin de cotizaciones si está instalado. Permite
	 * comprobar que el reparto automático está siendo equitativo y ver
	 * cuántas solicitudes atiende cada persona.
	 *
	 * @param string $desde Fecha inicial.
	 * @param string $hasta Fecha final.
	 * @return array
	 */
	public static function sellers_stats( $desde = '', $hasta = '' ) {
		global $wpdb;

		$tabla = $wpdb->prefix . 'zqp_quotes';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) ) !== $tabla ) {
			return array();
		}

		$where  = " WHERE vendedor_nombre <> '' ";
		$params = array();

		if ( $desde ) {
			$where   .= ' AND created_at >= %s ';
			$params[] = $desde . ' 00:00:00';
		}
		if ( $hasta ) {
			$where   .= ' AND created_at <= %s ';
			$params[] = $hasta . ' 23:59:59';
		}

		$sql = "SELECT vendedor_nombre,
					COUNT(*) AS total,
					SUM( CASE WHEN estado = 'contactado' THEN 1 ELSE 0 END ) AS contactados,
					SUM( CASE WHEN estado = 'cotizado' THEN 1 ELSE 0 END ) AS cotizados,
					SUM( CASE WHEN estado = 'venta' THEN 1 ELSE 0 END ) AS ventas
				FROM {$tabla}" . $where . '
				GROUP BY vendedor_nombre
				ORDER BY total DESC';

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Totales de solicitudes de cotización en el periodo.
	 *
	 * @param string $desde Fecha inicial.
	 * @param string $hasta Fecha final.
	 * @return array{total:int, por_estado:array, sin_asignar:int}
	 */
	public static function quotes_stats( $desde = '', $hasta = '' ) {
		global $wpdb;

		$tabla = $wpdb->prefix . 'zqp_quotes';

		$vacio = array(
			'total'       => 0,
			'por_estado'  => array(),
			'sin_asignar' => 0,
		);

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) ) !== $tabla ) {
			return $vacio;
		}

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

		$sql_total = "SELECT COUNT(*) FROM {$tabla}" . $where;
		$total     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $sql_total, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_var( $sql_total ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$sql_estado = "SELECT estado, COUNT(*) AS total FROM {$tabla}" . $where . ' GROUP BY estado';
		$rows       = $params
			? $wpdb->get_results( $wpdb->prepare( $sql_estado, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_results( $sql_estado ); // phpcs:ignore WordPress.DB.PreparedSQL

		$por_estado = array();
		foreach ( (array) $rows as $r ) {
			$por_estado[ $r->estado ] = (int) $r->total;
		}

		$sql_sin = "SELECT COUNT(*) FROM {$tabla}" . $where . " AND vendedor_nombre = '' ";
		$sin     = (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $sql_sin, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_var( $sql_sin ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return array(
			'total'       => $total,
			'por_estado'  => $por_estado,
			'sin_asignar' => $sin,
		);
	}

	/**
	 * Serie diaria de impresiones y clics (para la tabla de evolución).
	 *
	 * @param string $desde Fecha inicial.
	 * @param string $hasta Fecha final.
	 * @param int    $limit Máximo de días.
	 * @return array
	 */
	public static function daily_series( $desde = '', $hasta = '', $limit = 31 ) {
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

		$sql = "SELECT DATE( created_at ) AS dia,
					SUM( CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END ) AS impresiones,
					SUM( CASE WHEN event_type = 'click' THEN 1 ELSE 0 END ) AS clics,
					SUM( CASE WHEN event_type = 'add_cart' THEN 1 ELSE 0 END ) AS carritos,
					SUM( CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END ) AS compras
				FROM {$table}" . $where . '
				GROUP BY DATE( created_at )
				ORDER BY dia DESC
				LIMIT %d';

		$params[] = absint( $limit );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return is_array( $rows ) ? $rows : array();
	}
}
