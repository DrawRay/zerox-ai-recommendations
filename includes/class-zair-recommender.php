<?php
/**
 * Motor de recomendaciones basado en reglas (Fase 5).
 *
 * Flujo:
 *   1. Producto visto → ¿a qué vehículo(s)/motor(es) pertenece?
 *      (se resuelve por la propia tabla de compatibilidad)
 *   2. Motor → candidatos con compatibilidad_verificada = 1 y estado = 1.
 *   3. Filtros duros: producto publicado, en stock, no es el propio origen.
 *   4. Datos en vivo de WooCommerce (precio, oferta, stock, imagen, URL).
 *   5. Ranking (ZAIR_Ranking) y corte al máximo configurado.
 *
 * REGLA INVIOLABLE: si compatibilidad_verificada != 1, el producto no
 * entra. Ninguna oferta, prioridad ni IA puede saltarse este filtro.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Recommender
 */
class ZAIR_Recommender {

	/**
	 * Resuelve a qué vehículos/motores pertenece un producto.
	 * Prioriza las relaciones de tipo 'motor' (el producto ES el motor);
	 * si no hay ninguna, usa cualquier relación verificada.
	 *
	 * @param int $product_id Id del producto WooCommerce.
	 * @return array Filas de vehículo (id, marca, modelo, version, cilindrada, codigo_motor).
	 */
	public static function resolve_vehicles( $product_id ) {
		global $wpdb;

		$product_id = absint( $product_id );
		if ( $product_id < 1 ) {
			return array();
		}

		// Esta consulta se ejecuta en cada visita a una ficha de producto,
		// así que se cachea: la relación producto-vehículo solo cambia
		// cuando se edita una compatibilidad, y ahí se invalida la caché.
		$cache_key = 'veh_' . $product_id;
		$cached    = ZAIR_Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$t_compat  = ZAIR_Database::table( 'zair_product_compatibility' );
		$t_vehicle = ZAIR_Database::table( 'zair_vehicle_engine' );

		$sql = "SELECT v.id, v.marca, v.modelo, v.version, v.cilindrada, v.codigo_motor,
					   c.tipo_relacion
				FROM {$t_compat} c
				INNER JOIN {$t_vehicle} v ON v.id = c.vehicle_engine_id
				WHERE c.product_id = %d
				  AND c.compatibilidad_verificada = 1
				  AND c.estado = 1
				  AND v.estado = 1
				ORDER BY ( c.tipo_relacion = 'motor' ) DESC, c.nivel_prioridad DESC";

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $product_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( empty( $rows ) ) {
			// También se cachea el resultado vacío: la mayoría de productos
			// no tienen vehículo asociado y no conviene repetir la consulta.
			ZAIR_Cache::set( $cache_key, array(), 6 * HOUR_IN_SECONDS );
			return array();
		}

		// Si el producto ES un motor, nos quedamos solo con esas relaciones:
		// son las que describen el vehículo del cliente con certeza.
		$motor_rows = array_filter(
			$rows,
			function ( $r ) {
				return 'motor' === $r->tipo_relacion;
			}
		);

		$resultado = ! empty( $motor_rows ) ? array_values( $motor_rows ) : $rows;

		ZAIR_Cache::set( $cache_key, $resultado, 6 * HOUR_IN_SECONDS );

		return $resultado;
	}

	/**
	 * Obtiene recomendaciones para un producto.
	 *
	 * @param int   $product_id Producto que el cliente está viendo.
	 * @param array $args       { limit?:int, include_out_of_stock?:bool, with_desglose?:bool }.
	 * @return array{vehicles:array, items:array, total_disponibles:int}
	 */
	public static function get_recommendations( $product_id, $args = array() ) {

		$product_id = absint( $product_id );
		$settings   = get_option( 'zair_settings', array() );

		$limit = isset( $args['limit'] )
			? absint( $args['limit'] )
			: ( isset( $settings['max_recommendations'] ) ? (int) $settings['max_recommendations'] : 4 );

		$include_oos   = ! empty( $args['include_out_of_stock'] );
		$with_desglose = ! empty( $args['with_desglose'] );

		$vehicles = self::resolve_vehicles( $product_id );

		if ( empty( $vehicles ) ) {
			return array(
				'vehicles'          => array(),
				'items'             => array(),
				'total_disponibles' => 0,
			);
		}

		$vehicle_ids = wp_list_pluck( $vehicles, 'id' );
		$candidates  = self::fetch_candidates( $vehicle_ids, $product_id );

		$scored = array();

		foreach ( $candidates as $row ) {

			$product = wc_get_product( $row->product_id );

			// Filtros duros sobre datos en vivo de WooCommerce.
			if ( ! $product || 'publish' !== $product->get_status() ) {
				continue;
			}
			if ( ! $include_oos && ! $product->is_in_stock() ) {
				continue;
			}

			$item = array(
				'product_id'        => (int) $row->product_id,
				'vehicle_engine_id' => (int) $row->vehicle_engine_id,
				'tipo_relacion'     => $row->tipo_relacion,
				'nivel_prioridad'   => (int) $row->nivel_prioridad,
				'codigo_motor'      => $row->codigo_motor,
				'nombre'            => $product->get_name(),
				'sku'               => $product->get_sku(),
				'precio_html'       => $product->get_price_html(),
				'precio'            => (float) $product->get_price(),
				'en_oferta'         => $product->is_on_sale(),
				'en_stock'          => $product->is_in_stock(),
				'url'               => $product->get_permalink(),
				'imagen'            => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ),
				'comprable'         => $product->is_purchasable() && $product->is_in_stock() && ! $product->is_type( 'variable' ),
				'rendimiento'       => self::get_performance( (int) $row->product_id ),
			);

			$ranking          = ZAIR_Ranking::score( $item );
			$item['score']    = $ranking['score'];
			if ( $with_desglose ) {
				$item['desglose'] = $ranking['desglose'];
			}

			$scored[] = $item;
		}

		$scored = ZAIR_Ranking::sort( $scored );
		$total  = count( $scored );

		return array(
			'vehicles'          => $vehicles,
			'items'             => $limit > 0 ? array_slice( $scored, 0, $limit ) : $scored,
			'total_disponibles' => $total,
		);
	}

	/**
	 * Consulta candidatos verificados para uno o varios vehículos,
	 * deduplicando por producto (se queda con la mayor prioridad).
	 *
	 * @param array $vehicle_ids Ids de vehículos.
	 * @param int   $exclude_id  Producto origen a excluir.
	 * @return array
	 */
	private static function fetch_candidates( $vehicle_ids, $exclude_id ) {
		global $wpdb;

		$vehicle_ids = array_map( 'absint', (array) $vehicle_ids );
		$vehicle_ids = array_filter( $vehicle_ids );

		if ( empty( $vehicle_ids ) ) {
			return array();
		}

		$cache_key = 'cands_' . implode( '-', $vehicle_ids ) . '_x' . absint( $exclude_id );
		$cached    = ZAIR_Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$t_compat  = ZAIR_Database::table( 'zair_product_compatibility' );
		$t_vehicle = ZAIR_Database::table( 'zair_vehicle_engine' );

		$placeholders = implode( ',', array_fill( 0, count( $vehicle_ids ), '%d' ) );

		$sql = "SELECT c.product_id, c.vehicle_engine_id, c.tipo_relacion,
					   MAX( c.nivel_prioridad ) AS nivel_prioridad, v.codigo_motor
				FROM {$t_compat} c
				INNER JOIN {$t_vehicle} v ON v.id = c.vehicle_engine_id
				WHERE c.vehicle_engine_id IN ( {$placeholders} )
				  AND c.compatibilidad_verificada = 1
				  AND c.estado = 1
				  AND v.estado = 1
				  AND c.product_id != %d
				GROUP BY c.product_id
				ORDER BY nivel_prioridad DESC";

		$params = array_merge( $vehicle_ids, array( absint( $exclude_id ) ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$rows = is_array( $rows ) ? $rows : array();

		ZAIR_Cache::set( $cache_key, $rows );

		return $rows;
	}

	/**
	 * Rendimiento histórico de un producto recomendado (0..1).
	 *
	 * Hasta la Fase 8 no hay eventos, así que devuelve 0.5 (neutro):
	 * ningún producto se ve favorecido o penalizado por falta de datos.
	 * A partir de la Fase 8 usará la tasa clic/impresión real.
	 *
	 * @param int $product_id Id del producto.
	 * @return float
	 */
	private static function get_performance( $product_id ) {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_recommendation_events' );

		$cache_key = 'perf_' . absint( $product_id );
		$cached    = ZAIR_Cache::get( $cache_key );
		if ( false !== $cached ) {
			return (float) $cached;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT
					SUM( CASE WHEN event_type = 'impression' THEN 1 ELSE 0 END ) AS impresiones,
					SUM( CASE WHEN event_type = 'click' THEN 1 ELSE 0 END ) AS clics
				 FROM {$table}
				 WHERE recommended_product_id = %d",
				absint( $product_id )
			)
		);

		$impresiones = $row ? (int) $row->impresiones : 0;
		$clics       = $row ? (int) $row->clics : 0;

		// Umbral mínimo: sin datos suficientes, valor neutro.
		if ( $impresiones < 20 ) {
			$perf = 0.5;
		} else {
			// CTR normalizado: un 20% de CTR se considera excelente (=1).
			$ctr  = $clics / $impresiones;
			$perf = min( 1, $ctr / 0.20 );
		}

		ZAIR_Cache::set( $cache_key, $perf, 3600 );

		return $perf;
	}
}
