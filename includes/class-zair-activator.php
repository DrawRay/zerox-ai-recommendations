<?php
/**
 * Rutinas de activación del plugin.
 *
 * Responsabilidades:
 *  - Crear las tablas.
 *  - Registrar opciones por defecto (pesos del ranking, límites, privacidad).
 *  - Insertar los datos semilla del MVP (Chevrolet N300 / N200 1.2 B12 + alias).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Activator
 */
class ZAIR_Activator {

	/**
	 * Se ejecuta al activar el plugin.
	 *
	 * @return void
	 */
	public static function activate() {

		ZAIR_Database::create_tables();
		self::set_default_options();
		self::seed_mvp_data();

		// Mantenimiento diario (purga de eventos y transients).
		if ( ! wp_next_scheduled( 'zair_daily_maintenance' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'zair_daily_maintenance' );
		}

		// Marca para mostrar aviso de bienvenida tras la activación.
		set_transient( 'zair_just_activated', 1, 60 );
	}

	/**
	 * Opciones por defecto. Solo se crean si no existen
	 * (una reactivación nunca pisa la configuración del usuario).
	 *
	 * @return void
	 */
	private static function set_default_options() {

		$defaults = array(
			// Motor de ranking (editable luego en Configuración).
			// El stock NO es un peso: es un filtro previo (sin stock => fuera).
			'ranking_weights'          => array(
				'relevancia_comercial' => 45, // nivel_prioridad de la compatibilidad.
				'tipo_relacion'        => 25,
				'oferta_vigente'       => 15,
				'rendimiento'          => 15, // histórico de clics/ventas; neutro al inicio.
			),
			// Frontend.
			'max_recommendations'      => 4,
			// Con el cotizador activo, la acción principal es cotizar;
			// el formulario propio se apaga para no duplicarlo.
			'show_quote'               => 1,
			'show_buy'                 => 0,
			'show_lead_form'           => 0,
			'section_title'            => 'Complementa tu motor',
			'section_subtitle'         => 'Repuestos compatibles y complementarios para tu vehículo',
			// GA4 (Fase 10). Vacío = detectar gtag existente.
			'ga4_measurement_id'       => '',
			// IA (Fase 11). La API key se guardará server-side, jamás en JS.
			'ai_provider'              => '',
			// Privacidad y datos.
			'delete_data_on_uninstall' => 0, // Protege los datos de la tesis.
			'consent_text_version'     => 'v1',
			'events_retention_months'  => 0, // 0 = no purgar (MVP).
		);

		if ( false === get_option( 'zair_settings' ) ) {
			add_option( 'zair_settings', $defaults, '', false );
		}
	}

	/**
	 * Datos semilla del MVP: Chevrolet N300 1.2 B12 y N200 1.2 B12,
	 * más alias de búsqueda frecuentes. Solo si la tabla está vacía.
	 *
	 * @return void
	 */
	private static function seed_mvp_data() {
		global $wpdb;

		$table_vehicle = ZAIR_Database::table( 'zair_vehicle_engine' );
		$table_alias   = ZAIR_Database::table( 'zair_aliases' );

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_vehicle}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $count > 0 ) {
			return;
		}

		$now = current_time( 'mysql' );

		$vehicles = array(
			array(
				'marca'        => 'Chevrolet',
				'modelo'       => 'N300',
				'version'      => '',
				'cilindrada'   => '1.2',
				'codigo_motor' => 'B12',
				'combustible'  => 'Gasolina',
				'estado'       => 1,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array(
				'marca'        => 'Chevrolet',
				'modelo'       => 'N200',
				'version'      => '',
				'cilindrada'   => '1.2',
				'codigo_motor' => 'B12',
				'combustible'  => 'Gasolina',
				'estado'       => 1,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
		);

		$ids = array();
		foreach ( $vehicles as $vehicle ) {
			$wpdb->insert( $table_vehicle, $vehicle ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$ids[ $vehicle['modelo'] ] = (int) $wpdb->insert_id;
		}

		// Alias iniciales (base de la futura interpretación por IA).
		$aliases = array(
			array( 'b12', 'N300' ),
			array( 'motor b12', 'N300' ),
			array( 'n300', 'N300' ),
			array( 'n300 1.2', 'N300' ),
			array( 'n300 1200', 'N300' ),
			array( 'motor n300', 'N300' ),
			array( 'n200', 'N200' ),
			array( 'n200 1.2', 'N200' ),
			array( 'motor n200', 'N200' ),
		);

		foreach ( $aliases as $row ) {
			if ( empty( $ids[ $row[1] ] ) ) {
				continue;
			}
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table_alias,
				array(
					'alias'             => $row[0],
					'vehicle_engine_id' => $ids[ $row[1] ],
					'confianza'         => 1.00,
					'estado'            => 1,
					'created_at'        => $now,
					'updated_at'        => $now,
				)
			);
		}
	}
}
