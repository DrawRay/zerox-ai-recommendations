<?php
/**
 * Repositorio de Compatibilidades (Fase 4).
 *
 * Capa de datos de zair_product_compatibility: relaciona un vehículo/motor
 * con un producto de WooCommerce.
 *
 * REGLA CENTRAL DEL PROYECTO:
 * el recomendador solo mostrará filas con compatibilidad_verificada = 1
 * y estado = 1. Esta tabla es la única fuente de verdad de compatibilidad;
 * la IA (Fase 11) jamás escribirá aquí sin validación humana.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Compatibility
 */
class ZAIR_Compatibility {

	/**
	 * Tipos de relación permitidos.
	 * 'motor' identifica al producto que representa al propio motor:
	 * es lo que permite resolver "producto visto → vehículo/motor".
	 *
	 * @var array<string,string>
	 */
	const TIPOS_RELACION = array(
		'motor'          => 'Motor (producto principal)',
		'embrague'       => 'Embrague',
		'distribucion'   => 'Distribución',
		'transmision'    => 'Transmisión / Caja',
		'electrico'      => 'Eléctrico',
		'refrigeracion'  => 'Refrigeración',
		'frenos'         => 'Frenos',
		'suspension'     => 'Suspensión',
		'complementario' => 'Complementario',
	);

	/**
	 * Listado con filtros y paginación.
	 *
	 * @param array $args Filtros: vehicle_engine_id, s, tipo, verificada, paged, per_page.
	 * @return array{items:array, total:int, pages:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$t_compat  = ZAIR_Database::table( 'zair_product_compatibility' );
		$t_vehicle = ZAIR_Database::table( 'zair_vehicle_engine' );

		$vehicle_id = isset( $args['vehicle_engine_id'] ) ? absint( $args['vehicle_engine_id'] ) : 0;
		$s          = isset( $args['s'] ) ? trim( (string) $args['s'] ) : '';
		$tipo       = isset( $args['tipo'] ) ? (string) $args['tipo'] : '';
		$verificada = isset( $args['verificada'] ) ? (string) $args['verificada'] : '';
		$paged      = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page   = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;

		$where  = ' WHERE 1=1 ';
		$params = array();

		if ( $vehicle_id > 0 ) {
			$where   .= ' AND c.vehicle_engine_id = %d ';
			$params[] = $vehicle_id;
		}

		if ( '' !== $tipo && isset( self::TIPOS_RELACION[ $tipo ] ) ) {
			$where   .= ' AND c.tipo_relacion = %s ';
			$params[] = $tipo;
		}

		if ( '0' === $verificada || '1' === $verificada ) {
			$where   .= ' AND c.compatibilidad_verificada = %d ';
			$params[] = (int) $verificada;
		}

		if ( '' !== $s ) {
			$like   = '%' . $wpdb->esc_like( $s ) . '%';
			$where .= ' AND ( v.marca LIKE %s OR v.modelo LIKE %s OR v.codigo_motor LIKE %s ) ';
			array_push( $params, $like, $like, $like );
		}

		$join = " FROM {$t_compat} c LEFT JOIN {$t_vehicle} v ON v.id = c.vehicle_engine_id ";

		$sql_count = 'SELECT COUNT(*)' . $join . $where;
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql_count, $params ) : $sql_count ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$offset   = ( $paged - 1 ) * $per_page;
		$sql_list = 'SELECT c.*, v.marca, v.modelo, v.version, v.cilindrada, v.codigo_motor'
			. $join . $where
			. ' ORDER BY v.marca ASC, v.modelo ASC, c.nivel_prioridad DESC, c.id DESC LIMIT %d OFFSET %d';

		$params2 = array_merge( $params, array( $per_page, $offset ) );
		$items   = $wpdb->get_results( $wpdb->prepare( $sql_list, $params2 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Obtiene una compatibilidad por id.
	 *
	 * @param int $id Id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = ZAIR_Database::table( 'zair_product_compatibility' );
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Sanitiza y valida los datos del formulario.
	 *
	 * @param array $raw Datos crudos.
	 * @return array|WP_Error
	 */
	public static function sanitize( $raw ) {

		$data = array(
			'vehicle_engine_id'         => isset( $raw['vehicle_engine_id'] ) ? absint( $raw['vehicle_engine_id'] ) : 0,
			'product_id'                => isset( $raw['product_id'] ) ? absint( $raw['product_id'] ) : 0,
			'tipo_relacion'             => isset( $raw['tipo_relacion'] ) ? sanitize_key( wp_unslash( $raw['tipo_relacion'] ) ) : 'complementario',
			'nivel_prioridad'           => isset( $raw['nivel_prioridad'] ) ? absint( $raw['nivel_prioridad'] ) : 5,
			'compatibilidad_verificada' => ! empty( $raw['compatibilidad_verificada'] ) ? 1 : 0,
			'notas'                     => isset( $raw['notas'] ) ? sanitize_textarea_field( wp_unslash( $raw['notas'] ) ) : '',
			'estado'                    => ! empty( $raw['estado'] ) ? 1 : 0,
		);

		if ( $data['vehicle_engine_id'] < 1 ) {
			return new WP_Error( 'zair_required', 'Debes seleccionar un vehículo/motor.' );
		}

		if ( $data['product_id'] < 1 ) {
			return new WP_Error( 'zair_required', 'Debes seleccionar un producto de WooCommerce.' );
		}

		if ( ! wc_get_product( $data['product_id'] ) ) {
			return new WP_Error( 'zair_no_product', 'El producto seleccionado no existe en WooCommerce.' );
		}

		if ( ! ZAIR_Vehicles::get( $data['vehicle_engine_id'] ) ) {
			return new WP_Error( 'zair_no_vehicle', 'El vehículo/motor seleccionado no existe.' );
		}

		if ( ! isset( self::TIPOS_RELACION[ $data['tipo_relacion'] ] ) ) {
			$data['tipo_relacion'] = 'complementario';
		}

		$data['nivel_prioridad'] = min( 10, max( 1, $data['nivel_prioridad'] ) );

		return $data;
	}

	/**
	 * Comprueba duplicado (mismo vehículo + mismo producto).
	 *
	 * @param int $vehicle_id Id del vehículo.
	 * @param int $product_id Id del producto.
	 * @param int $exclude_id Id a excluir en edición.
	 * @return bool
	 */
	public static function exists_duplicate( $vehicle_id, $product_id, $exclude_id = 0 ) {
		global $wpdb;
		$table = ZAIR_Database::table( 'zair_product_compatibility' );
		return (bool) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table}
				 WHERE vehicle_engine_id = %d AND product_id = %d AND id != %d LIMIT 1",
				absint( $vehicle_id ),
				absint( $product_id ),
				absint( $exclude_id )
			)
		);
	}

	/**
	 * Crea o actualiza una compatibilidad.
	 *
	 * @param array $data Datos limpios.
	 * @param int   $id   0 para crear.
	 * @return int|WP_Error
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_product_compatibility' );
		$id    = absint( $id );

		if ( self::exists_duplicate( $data['vehicle_engine_id'], $data['product_id'], $id ) ) {
			return new WP_Error( 'zair_duplicate', 'Ese producto ya está relacionado con ese vehículo/motor.' );
		}

		$now                = current_time( 'mysql' );
		$data['updated_at'] = $now;

		if ( $id > 0 ) {
			$result = $wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::flush_cache();
			return ( false === $result ) ? new WP_Error( 'zair_db', 'No se pudo actualizar.' ) : $id;
		}

		$data['created_at'] = $now;
		$result             = $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::flush_cache();

		return ( false === $result ) ? new WP_Error( 'zair_db', 'No se pudo crear.' ) : (int) $wpdb->insert_id;
	}

	/**
	 * Cambia el estado activo/inactivo.
	 *
	 * @param int $id     Id.
	 * @param int $estado 1 o 0.
	 * @return bool
	 */
	public static function set_estado( $id, $estado ) {
		global $wpdb;
		$ok = false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ZAIR_Database::table( 'zair_product_compatibility' ),
			array(
				'estado'     => $estado ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
		self::flush_cache();
		return $ok;
	}

	/**
	 * Alterna el flag de compatibilidad verificada.
	 *
	 * @param int $id    Id.
	 * @param int $value 1 o 0.
	 * @return bool
	 */
	public static function set_verificada( $id, $value ) {
		global $wpdb;
		$ok = false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ZAIR_Database::table( 'zair_product_compatibility' ),
			array(
				'compatibilidad_verificada' => $value ? 1 : 0,
				'updated_at'                => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
		self::flush_cache();
		return $ok;
	}

	/**
	 * Elimina una compatibilidad.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$ok = (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ZAIR_Database::table( 'zair_product_compatibility' ),
			array( 'id' => absint( $id ) )
		);
		self::flush_cache();
		return $ok;
	}

	/**
	 * Datos de presentación de un producto WooCommerce.
	 * Punto único de lectura: precio y stock siempre en vivo.
	 *
	 * @param int $product_id Id del producto.
	 * @return array|null
	 */
	public static function get_product_info( $product_id ) {
		$product = wc_get_product( absint( $product_id ) );
		if ( ! $product ) {
			return null;
		}

		return array(
			'id'        => $product->get_id(),
			'nombre'    => $product->get_name(),
			'sku'       => $product->get_sku(),
			'precio'    => $product->get_price_html(),
			'en_stock'  => $product->is_in_stock(),
			'en_oferta' => $product->is_on_sale(),
			'publicado' => ( 'publish' === $product->get_status() ),
			'edit_url'  => get_edit_post_link( $product->get_id() ),
			'permalink' => $product->get_permalink(),
		);
	}

	/**
	 * Invalida la caché de recomendaciones (usada desde la Fase 5).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\_transient\_zair\_rec\_%'
			    OR option_name LIKE '\_transient\_timeout\_zair\_rec\_%'"
		);
	}
}
