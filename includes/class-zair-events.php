<?php
/**
 * Registro de eventos del embudo (Fase 8).
 *
 * Guarda el comportamiento de forma ANÓNIMA: solo el identificador
 * ZX-R-XXXXXX, ids de producto y de vehículo. Ningún dato personal entra
 * en esta tabla, ni siquiera cuando el mismo visitante deja un lead.
 *
 * Eventos del embudo:
 *   impression → click → add_cart → purchase
 *   lead_open  → lead_submit
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Events
 */
class ZAIR_Events {

	/**
	 * Tipos de evento admitidos. Cualquier otro valor se descarta.
	 *
	 * @var string[]
	 */
	const TIPOS = array(
		'impression',
		'click',
		'add_cart',
		'purchase',
		'lead_open',
		'lead_submit',
		'compatibility_query',
		'search_detected',
	);

	/**
	 * Registra un evento.
	 *
	 * @param string $tipo Tipo de evento.
	 * @param array  $args Datos opcionales.
	 * @return bool
	 */
	public static function log( $tipo, $args = array() ) {
		global $wpdb;

		$tipo = sanitize_key( $tipo );

		if ( ! in_array( $tipo, self::TIPOS, true ) ) {
			return false;
		}

		$session_id = isset( $args['session_id'] ) && ZAIR_Session::is_valid( $args['session_id'] )
			? $args['session_id']
			: ZAIR_Session::get_id();

		$row = array(
			'session_id'             => $session_id,
			'product_origin_id'      => isset( $args['product_origin_id'] ) ? absint( $args['product_origin_id'] ) : null,
			'recommended_product_id' => isset( $args['recommended_product_id'] ) ? absint( $args['recommended_product_id'] ) : null,
			'vehicle_engine_id'      => isset( $args['vehicle_engine_id'] ) ? absint( $args['vehicle_engine_id'] ) : null,
			'event_type'             => $tipo,
			'position'               => isset( $args['position'] ) ? min( 255, absint( $args['position'] ) ) : null,
			'score'                  => isset( $args['score'] ) ? round( (float) $args['score'], 3 ) : null,
			'created_at'             => current_time( 'mysql' ),
		);

		$ok = $wpdb->insert( ZAIR_Database::table( 'zair_recommendation_events' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return false !== $ok;
	}

	/**
	 * Registra varios eventos de una vez (impresiones del carrusel).
	 *
	 * @param string $tipo   Tipo de evento.
	 * @param array  $items  Lista de arrays con datos por evento.
	 * @param array  $common Datos comunes a todos.
	 * @return int Número de eventos insertados.
	 */
	public static function log_batch( $tipo, $items, $common = array() ) {
		global $wpdb;

		$tipo = sanitize_key( $tipo );

		if ( ! in_array( $tipo, self::TIPOS, true ) ) {
			return 0;
		}

		$items = array_slice( (array) $items, 0, 24 ); // Tope defensivo.

		if ( empty( $items ) ) {
			return 0;
		}

		$session_id = isset( $common['session_id'] ) && ZAIR_Session::is_valid( $common['session_id'] )
			? $common['session_id']
			: ZAIR_Session::get_id();

		$origen  = isset( $common['product_origin_id'] ) ? absint( $common['product_origin_id'] ) : null;
		$vehicle = isset( $common['vehicle_engine_id'] ) ? absint( $common['vehicle_engine_id'] ) : null;
		$ahora   = current_time( 'mysql' );

		// Una sola sentencia para todas las impresiones del carrusel:
		// con cuatro tarjetas serían cuatro viajes a la base de datos.
		$valores  = array();
		$params   = array();

		foreach ( $items as $item ) {
			$valores[] = '(%s,%d,%d,%d,%s,%d,%f,%s)';
			array_push(
				$params,
				$session_id,
				$origen,
				isset( $item['recommended_product_id'] ) ? absint( $item['recommended_product_id'] ) : 0,
				$vehicle,
				$tipo,
				isset( $item['position'] ) ? min( 255, absint( $item['position'] ) ) : 0,
				isset( $item['score'] ) ? (float) $item['score'] : 0,
				$ahora
			);
		}

		$table = ZAIR_Database::table( 'zair_recommendation_events' );

		$sql = "INSERT INTO {$table}
				( session_id, product_origin_id, recommended_product_id, vehicle_engine_id,
				  event_type, position, score, created_at )
				VALUES " . implode( ',', $valores );

		$ok = $wpdb->query( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB

		return ( false === $ok ) ? 0 : (int) $ok;
	}

	/**
	 * Evita duplicar impresiones de la misma sesión y producto
	 * dentro de una ventana corta (el usuario recarga la página).
	 *
	 * @param string $session_id Sesión.
	 * @param int    $origin_id  Producto de origen.
	 * @return bool True si ya se registró hace poco.
	 */
	/**
	 * Filtra los productos cuya impresión ya se registró hace poco.
	 *
	 * La deduplicación se hace por producto y no por ficha: al pulsar
	 * «Ver más compatibles» aparecen productos nuevos que sí deben
	 * contarse, aunque el carrusel de esa página ya hubiera registrado
	 * los primeros. Una comprobación por ficha los descartaba, y esos
	 * productos acumulaban clics sin impresiones.
	 *
	 * @param string $session_id  Sesión anónima.
	 * @param array  $product_ids Ids candidatos.
	 * @return array Ids que aún no se registraron.
	 */
	public static function filter_new_impressions( $session_id, $product_ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', (array) $product_ids ) );

		if ( empty( $ids ) || ! $session_id ) {
			return $ids;
		}

		$table        = ZAIR_Database::table( 'zair_recommendation_events' );
		$marcadores   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$parametros   = array_merge( array( $session_id ), $ids, array( current_time( 'mysql' ) ) );

		$ya_vistos = $wpdb->get_col(
			$wpdb->prepare( // phpcs:ignore WordPress.DB
				"SELECT DISTINCT recommended_product_id
				 FROM {$table}
				 WHERE session_id = %s
				   AND event_type = 'impression'
				   AND recommended_product_id IN ( {$marcadores} )
				   AND created_at > DATE_SUB( %s, INTERVAL 30 MINUTE )",
				$parametros
			)
		);

		$ya_vistos = array_map( 'absint', (array) $ya_vistos );

		return array_values( array_diff( $ids, $ya_vistos ) );
	}

	public static function impression_recently_logged( $session_id, $origin_id ) {
		global $wpdb;

		// Marca en caché: evita consultar la tabla de eventos en cada
		// recarga de la misma ficha por el mismo visitante.
		$marca = 'imp_' . md5( $session_id . '_' . $origin_id );

		if ( false !== ZAIR_Cache::get( $marca ) ) {
			return true;
		}

		$table = ZAIR_Database::table( 'zair_recommendation_events' );

		$found = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT id FROM {$table}
				 WHERE session_id = %s
				   AND product_origin_id = %d
				   AND event_type = 'impression'
				   AND created_at > DATE_SUB( %s, INTERVAL 30 MINUTE )
				 LIMIT 1",
				$session_id,
				absint( $origin_id ),
				current_time( 'mysql' )
			)
		);

		if ( $found ) {
			ZAIR_Cache::set( $marca, 1, 30 * MINUTE_IN_SECONDS );
		}

		return (bool) $found;
	}

	/* ---------------------------------------------------------------------
	 * Consultas para métricas (usadas por el Dashboard, Fase 9).
	 * ------------------------------------------------------------------ */

	/**
	 * Totales por tipo de evento en un rango de fechas.
	 *
	 * @param string $desde Fecha inicial (Y-m-d).
	 * @param string $hasta Fecha final (Y-m-d).
	 * @return array<string,int>
	 */
	public static function totals( $desde = '', $hasta = '' ) {
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

		$sql  = "SELECT event_type, COUNT(*) AS total FROM {$table}" . $where . ' GROUP BY event_type';
		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL

		$totals = array_fill_keys( self::TIPOS, 0 );

		foreach ( (array) $rows as $row ) {
			if ( isset( $totals[ $row->event_type ] ) ) {
				$totals[ $row->event_type ] = (int) $row->total;
			}
		}

		return $totals;
	}

	/**
	 * Número de sesiones distintas que vieron recomendaciones.
	 *
	 * @param string $desde Fecha inicial.
	 * @param string $hasta Fecha final.
	 * @return int
	 */
	public static function sessions_with_recommendations( $desde = '', $hasta = '' ) {
		global $wpdb;

		$table  = ZAIR_Database::table( 'zair_recommendation_events' );
		$where  = " WHERE event_type = 'impression' ";
		$params = array();

		if ( $desde ) {
			$where   .= ' AND created_at >= %s ';
			$params[] = $desde . ' 00:00:00';
		}
		if ( $hasta ) {
			$where   .= ' AND created_at <= %s ';
			$params[] = $hasta . ' 23:59:59';
		}

		$sql = "SELECT COUNT( DISTINCT session_id ) FROM {$table}" . $where;

		return (int) ( $params
			? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Productos recomendados con mejor rendimiento.
	 *
	 * @param int    $limit Máximo de filas.
	 * @param string $desde Fecha inicial.
	 * @param string $hasta Fecha final.
	 * @return array
	 */
	public static function top_products( $limit = 10, $desde = '', $hasta = '' ) {
		global $wpdb;

		$table  = ZAIR_Database::table( 'zair_recommendation_events' );
		$where  = ' WHERE recommended_product_id IS NOT NULL ';
		$params = array();

		if ( $desde ) {
			$where   .= ' AND created_at >= %s ';
			$params[] = $desde . ' 00:00:00';
		}
		if ( $hasta ) {
			$where   .= ' AND created_at <= %s ';
			$params[] = $hasta . ' 23:59:59';
		}

		$sql = "SELECT recommended_product_id,
					SUM( CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END ) AS impresiones,
					SUM( CASE WHEN event_type = 'click' THEN 1 ELSE 0 END ) AS clics,
					SUM( CASE WHEN event_type = 'add_cart' THEN 1 ELSE 0 END ) AS carritos,
					SUM( CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END ) AS compras
				FROM {$table}" . $where . '
				GROUP BY recommended_product_id
				HAVING impresiones > 0
				ORDER BY impresiones DESC, clics DESC
				LIMIT %d';

		$params[] = absint( $limit );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Purga eventos anteriores a N meses (retención configurable).
	 *
	 * @param int $months Meses a conservar. 0 = no purgar.
	 * @return int Filas eliminadas.
	 */
	public static function purge_old( $months ) {
		global $wpdb;

		$months = absint( $months );
		if ( $months < 1 ) {
			return 0;
		}

		$table = ZAIR_Database::table( 'zair_recommendation_events' );

		return (int) $wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				"DELETE FROM {$table} WHERE created_at < DATE_SUB( %s, INTERVAL %d MONTH )",
				current_time( 'mysql' ),
				$months
			)
		);
	}
}
