<?php
/**
 * Repositorio de Vehículos / Motores (Fase 3).
 *
 * Capa de datos de la tabla zair_vehicle_engine:
 *  - Listado con búsqueda, filtro por estado y paginación.
 *  - Alta/edición con validación y control de duplicados.
 *  - Activar/desactivar.
 *  - Eliminación protegida: si existen compatibilidades asociadas,
 *    no se permite borrar (se sugiere desactivar).
 *
 * Todas las consultas usan prepared statements de $wpdb.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Vehicles
 */
class ZAIR_Vehicles {

	/**
	 * Valores permitidos para combustible.
	 *
	 * @var string[]
	 */
	const COMBUSTIBLES = array( '', 'Gasolina', 'Diésel', 'GLP', 'GNV', 'Dual' );

	/**
	 * Listado con filtros y paginación.
	 *
	 * @param array $args { s?:string, estado?:''|'0'|'1', paged?:int, per_page?:int }.
	 * @return array{items:array, total:int, pages:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$table    = ZAIR_Database::table( 'zair_vehicle_engine' );
		$s        = isset( $args['s'] ) ? trim( (string) $args['s'] ) : '';
		$estado   = isset( $args['estado'] ) ? (string) $args['estado'] : '';
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;

		$where  = ' WHERE 1=1 ';
		$params = array();

		if ( '' !== $s ) {
			$like   = '%' . $wpdb->esc_like( $s ) . '%';
			$where .= ' AND ( marca LIKE %s OR modelo LIKE %s OR version LIKE %s OR codigo_motor LIKE %s OR cilindrada LIKE %s ) ';
			array_push( $params, $like, $like, $like, $like, $like );
		}

		if ( '0' === $estado || '1' === $estado ) {
			$where   .= ' AND estado = %d ';
			$params[] = (int) $estado;
		}

		// Total.
		$sql_count = "SELECT COUNT(*) FROM {$table}" . $where;
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql_count, $params ) : $sql_count ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Ítems.
		$offset   = ( $paged - 1 ) * $per_page;
		$sql_list = "SELECT * FROM {$table}" . $where . ' ORDER BY marca ASC, modelo ASC, cilindrada ASC LIMIT %d OFFSET %d';
		$params2  = array_merge( $params, array( $per_page, $offset ) );
		$items    = $wpdb->get_results( $wpdb->prepare( $sql_list, $params2 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Obtiene un registro por id.
	 *
	 * @param int $id Id del vehículo/motor.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = ZAIR_Database::table( 'zair_vehicle_engine' );
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Número de compatibilidades asociadas a un vehículo/motor.
	 *
	 * @param int $id Id del vehículo/motor.
	 * @return int
	 */
	public static function compat_count( $id ) {
		global $wpdb;
		$table = ZAIR_Database::table( 'zair_product_compatibility' );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE vehicle_engine_id = %d", absint( $id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Sanitiza y valida los datos de un formulario de vehículo.
	 *
	 * @param array $raw Datos crudos ($_POST).
	 * @return array|WP_Error Datos limpios o error de validación.
	 */
	public static function sanitize( $raw ) {

		$data = array(
			'marca'        => isset( $raw['marca'] ) ? sanitize_text_field( wp_unslash( $raw['marca'] ) ) : '',
			'modelo'       => isset( $raw['modelo'] ) ? sanitize_text_field( wp_unslash( $raw['modelo'] ) ) : '',
			'version'      => isset( $raw['version'] ) ? sanitize_text_field( wp_unslash( $raw['version'] ) ) : '',
			'cilindrada'   => isset( $raw['cilindrada'] ) ? sanitize_text_field( wp_unslash( $raw['cilindrada'] ) ) : '',
			'codigo_motor' => isset( $raw['codigo_motor'] ) ? strtoupper( sanitize_text_field( wp_unslash( $raw['codigo_motor'] ) ) ) : '',
			'combustible'  => isset( $raw['combustible'] ) ? sanitize_text_field( wp_unslash( $raw['combustible'] ) ) : '',
			'estado'       => ! empty( $raw['estado'] ) ? 1 : 0,
		);

		if ( '' === $data['marca'] || '' === $data['modelo'] ) {
			return new WP_Error( 'zair_required', 'Marca y Modelo son obligatorios.' );
		}

		if ( ! in_array( $data['combustible'], self::COMBUSTIBLES, true ) ) {
			$data['combustible'] = '';
		}

		// Longitudes máximas coherentes con el esquema.
		$data['marca']        = mb_substr( $data['marca'], 0, 60 );
		$data['modelo']       = mb_substr( $data['modelo'], 0, 80 );
		$data['version']      = mb_substr( $data['version'], 0, 80 );
		$data['cilindrada']   = mb_substr( $data['cilindrada'], 0, 10 );
		$data['codigo_motor'] = mb_substr( $data['codigo_motor'], 0, 30 );

		return $data;
	}

	/**
	 * Comprueba si ya existe otra fila con la misma combinación única.
	 *
	 * @param array $data       Datos limpios.
	 * @param int   $exclude_id Id a excluir (edición).
	 * @return bool
	 */
	public static function exists_duplicate( $data, $exclude_id = 0 ) {
		global $wpdb;
		$table = ZAIR_Database::table( 'zair_vehicle_engine' );
		return (bool) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table}
				 WHERE marca = %s AND modelo = %s AND version = %s
				   AND cilindrada = %s AND codigo_motor = %s AND id != %d
				 LIMIT 1",
				$data['marca'],
				$data['modelo'],
				$data['version'],
				$data['cilindrada'],
				$data['codigo_motor'],
				absint( $exclude_id )
			)
		);
	}

	/**
	 * Crea o actualiza un vehículo/motor.
	 *
	 * @param array $data Datos limpios (de self::sanitize()).
	 * @param int   $id   0 para crear, id para actualizar.
	 * @return int|WP_Error Id del registro o error.
	 */
	public static function save( $data, $id = 0 ) {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_vehicle_engine' );
		$id    = absint( $id );

		if ( self::exists_duplicate( $data, $id ) ) {
			return new WP_Error( 'zair_duplicate', 'Ya existe un vehículo/motor con esa misma combinación (marca, modelo, versión, cilindrada y código de motor).' );
		}

		$now                = current_time( 'mysql' );
		$data['updated_at'] = $now;

		if ( $id > 0 ) {
			$result = $wpdb->update( $table, $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( false === $result ) {
				return new WP_Error( 'zair_db', 'No se pudo actualizar el registro.' );
			}
			return $id;
		}

		$data['created_at'] = $now;
		$result             = $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $result ) {
			return new WP_Error( 'zair_db', 'No se pudo crear el registro.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Cambia el estado (activo/inactivo).
	 *
	 * @param int $id     Id del vehículo/motor.
	 * @param int $estado 1 o 0.
	 * @return bool
	 */
	public static function set_estado( $id, $estado ) {
		global $wpdb;
		$table = ZAIR_Database::table( 'zair_vehicle_engine' );
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'estado'     => $estado ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Elimina un vehículo/motor si no tiene compatibilidades asociadas.
	 *
	 * @param int $id Id del vehículo/motor.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		$compat = self::compat_count( $id );
		if ( $compat > 0 ) {
			return new WP_Error(
				'zair_in_use',
				sprintf( 'No se puede eliminar: tiene %d compatibilidad(es) asociada(s). Desactívalo en su lugar, o elimina primero sus compatibilidades.', $compat )
			);
		}

		// Los alias asociados sí se eliminan (dependen del vehículo).
		$wpdb->delete( ZAIR_Database::table( 'zair_aliases' ), array( 'vehicle_engine_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( ZAIR_Database::table( 'zair_vehicle_engine' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return true;
	}
}
