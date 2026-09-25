<?php
/**
 * Repositorio de Alias de búsqueda (Fase 4).
 *
 * Un alias es una forma alternativa en que el cliente puede referirse a
 * un vehículo/motor ("b12", "n300 1200", "motor n300"). Es la base del
 * reconocimiento sin IA y, más adelante (Fase 11), el diccionario que
 * la IA consultará antes de interpretar por su cuenta.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Aliases
 */
class ZAIR_Aliases {

	/**
	 * Listado completo con datos del vehículo asociado.
	 *
	 * @param string $s Búsqueda opcional.
	 * @return array
	 */
	public static function get_all( $s = '' ) {
		global $wpdb;

		$alias_table   = ZAIR_Database::table( 'zair_aliases' );
		$vehicle_table = ZAIR_Database::table( 'zair_vehicle_engine' );

		$sql    = "SELECT a.*, v.marca, v.modelo, v.version, v.cilindrada, v.codigo_motor
				   FROM {$alias_table} a
				   LEFT JOIN {$vehicle_table} v ON v.id = a.vehicle_engine_id";
		$params = array();

		if ( '' !== trim( $s ) ) {
			$like = '%' . $wpdb->esc_like( trim( $s ) ) . '%';
			$sql .= ' WHERE a.alias LIKE %s OR v.modelo LIKE %s OR v.codigo_motor LIKE %s';
			array_push( $params, $like, $like, $like );
		}

		$sql .= ' ORDER BY a.alias ASC LIMIT 500';

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Crea un alias (normalizado a minúsculas).
	 *
	 * @param string $alias             Texto del alias.
	 * @param int    $vehicle_engine_id Vehículo asociado.
	 * @param float  $confianza         0.00 a 1.00.
	 * @return int|WP_Error
	 */
	public static function create( $alias, $vehicle_engine_id, $confianza = 1.0 ) {
		global $wpdb;

		$alias             = mb_strtolower( trim( sanitize_text_field( $alias ) ) );
		$alias             = mb_substr( $alias, 0, 120 );
		$vehicle_engine_id = absint( $vehicle_engine_id );
		$confianza         = min( 1, max( 0, (float) $confianza ) );

		if ( '' === $alias ) {
			return new WP_Error( 'zair_alias_empty', 'El alias no puede estar vacío.' );
		}
		if ( $vehicle_engine_id <= 0 ) {
			return new WP_Error( 'zair_alias_vehicle', 'Debes seleccionar un vehículo/motor.' );
		}

		$table  = ZAIR_Database::table( 'zair_aliases' );
		$exists = (bool) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM {$table} WHERE alias = %s AND vehicle_engine_id = %d LIMIT 1",
				$alias,
				$vehicle_engine_id
			)
		);
		if ( $exists ) {
			return new WP_Error( 'zair_alias_dup', 'Ese alias ya existe para ese vehículo/motor.' );
		}

		$now = current_time( 'mysql' );
		if ( false === $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'alias'             => $alias,
				'vehicle_engine_id' => $vehicle_engine_id,
				'confianza'         => $confianza,
				'estado'            => 1,
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		) ) {
			return new WP_Error( 'zair_db', 'No se pudo crear el alias.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Cambia el estado de un alias.
	 *
	 * @param int $id     Id del alias.
	 * @param int $estado 1 o 0.
	 * @return bool
	 */
	public static function set_estado( $id, $estado ) {
		global $wpdb;
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ZAIR_Database::table( 'zair_aliases' ),
			array(
				'estado'     => $estado ? 1 : 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Elimina un alias.
	 *
	 * @param int $id Id del alias.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ZAIR_Database::table( 'zair_aliases' ),
			array( 'id' => absint( $id ) )
		);
	}
}
