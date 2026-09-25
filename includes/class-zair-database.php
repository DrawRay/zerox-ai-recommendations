<?php
/**
 * Gestión de las tablas propias del plugin.
 *
 * Responsabilidades:
 *  - Definir el esquema de las 5 tablas zair_*.
 *  - Crearlas/actualizarlas con dbDelta().
 *  - Versionar el esquema (zair_db_version) para futuras migraciones.
 *  - Informar el estado de las tablas al Dashboard.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Database
 */
class ZAIR_Database {

	/**
	 * Sufijos de las tablas del plugin (sin prefijo de WordPress).
	 *
	 * @var string[]
	 */
	const TABLES = array(
		'zair_vehicle_engine',
		'zair_product_compatibility',
		'zair_aliases',
		'zair_recommendation_events',
		'zair_leads',
	);

	/**
	 * Devuelve el nombre completo (con prefijo) de una tabla del plugin.
	 *
	 * @param string $table Sufijo, p.ej. 'zair_leads'.
	 * @return string
	 */
	public static function table( $table ) {
		global $wpdb;
		return $wpdb->prefix . $table;
	}

	/**
	 * Crea o actualiza todas las tablas mediante dbDelta().
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$p               = $wpdb->prefix;

		/*
		 * 1) Vehículos / motores.
		 * Una fila por combinación real (marca+modelo+versión+cilindrada+motor).
		 * El agrupamiento N300/N200 se resuelve vía codigo_motor compartido (B12).
		 */
		$sql_vehicle = "CREATE TABLE {$p}zair_vehicle_engine (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			marca VARCHAR(60) NOT NULL,
			modelo VARCHAR(80) NOT NULL,
			version VARCHAR(80) NOT NULL DEFAULT '',
			cilindrada VARCHAR(10) NOT NULL DEFAULT '',
			codigo_motor VARCHAR(30) NOT NULL DEFAULT '',
			combustible VARCHAR(20) NOT NULL DEFAULT '',
			estado TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY vehiculo_unico (marca,modelo,version,cilindrada,codigo_motor),
			KEY codigo_motor (codigo_motor),
			KEY estado (estado)
		) {$charset_collate};";

		/*
		 * 2) Compatibilidad producto WooCommerce <-> vehículo/motor.
		 * nivel_prioridad (1-10) = relevancia comercial asignada por el admin.
		 * Regla dura del recomendador: compatibilidad_verificada = 1 o no se muestra.
		 */
		$sql_compat = "CREATE TABLE {$p}zair_product_compatibility (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			vehicle_engine_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			tipo_relacion VARCHAR(30) NOT NULL DEFAULT 'complementario',
			nivel_prioridad TINYINT UNSIGNED NOT NULL DEFAULT 5,
			compatibilidad_verificada TINYINT(1) NOT NULL DEFAULT 0,
			notas TEXT NULL,
			estado TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY vehiculo_producto (vehicle_engine_id,product_id),
			KEY product_id (product_id),
			KEY vehicle_engine_id (vehicle_engine_id),
			KEY estado_verificada (estado,compatibilidad_verificada)
		) {$charset_collate};";

		/*
		 * 3) Alias de búsqueda -> entidad normalizada.
		 * Base para la interpretación por IA (Fase 11).
		 */
		$sql_alias = "CREATE TABLE {$p}zair_aliases (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			alias VARCHAR(120) NOT NULL,
			vehicle_engine_id BIGINT UNSIGNED NOT NULL,
			confianza DECIMAL(3,2) NOT NULL DEFAULT 1.00,
			estado TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY alias_vehiculo (alias,vehicle_engine_id),
			KEY alias (alias),
			KEY vehicle_engine_id (vehicle_engine_id)
		) {$charset_collate};";

		/*
		 * 4) Eventos anónimos del embudo.
		 * session_id = ZX-R-XXXXXX (nunca datos personales).
		 */
		$sql_events = "CREATE TABLE {$p}zair_recommendation_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id CHAR(11) NOT NULL,
			product_origin_id BIGINT UNSIGNED NULL,
			recommended_product_id BIGINT UNSIGNED NULL,
			vehicle_engine_id BIGINT UNSIGNED NULL,
			event_type VARCHAR(30) NOT NULL,
			position TINYINT UNSIGNED NULL,
			score DECIMAL(6,3) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY event_fecha (event_type,created_at),
			KEY producto_evento (recommended_product_id,event_type),
			KEY created_at (created_at),
			KEY sesion_evento (session_id,event_type,created_at)
		) {$charset_collate};";

		/*
		 * 5) Leads (datos personales SOLO aquí, nunca en eventos ni GA4).
		 * Incluye fecha y versión del texto de consentimiento (Ley 29733).
		 */
		$sql_leads = "CREATE TABLE {$p}zair_leads (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_code CHAR(11) NOT NULL,
			session_id CHAR(11) NOT NULL,
			nombre VARCHAR(120) NOT NULL,
			whatsapp VARCHAR(20) NOT NULL,
			product_origin_id BIGINT UNSIGNED NULL,
			recommended_product_id BIGINT UNSIGNED NULL,
			vehicle_engine_id BIGINT UNSIGNED NULL,
			estado VARCHAR(20) NOT NULL DEFAULT 'nuevo',
			venta_generada TINYINT(1) NOT NULL DEFAULT 0,
			order_id BIGINT UNSIGNED NULL,
			monto_venta DECIMAL(10,2) NULL,
			consentimiento TINYINT(1) NOT NULL DEFAULT 0,
			consentimiento_fecha DATETIME NULL,
			consentimiento_texto VARCHAR(20) NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY lead_code (lead_code),
			KEY session_id (session_id),
			KEY estado (estado),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_vehicle );
		dbDelta( $sql_compat );
		dbDelta( $sql_alias );
		dbDelta( $sql_events );
		dbDelta( $sql_leads );

		update_option( 'zair_db_version', ZAIR_DB_VERSION );
	}

	/**
	 * Ejecuta la actualización del esquema si la versión guardada difiere.
	 * Se llama en cada carga del admin; dbDelta es idempotente.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'zair_db_version' ) !== ZAIR_DB_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Estado de las tablas para el Dashboard: existe + número de filas.
	 *
	 * @return array<string, array{exists:bool, rows:int}>
	 */
	public static function get_status() {
		global $wpdb;

		$status = array();

		foreach ( self::TABLES as $table ) {
			$full   = self::table( $table );
			$exists = ( $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $full )
			) === $full );

			$rows = 0;
			if ( $exists ) {
				// Nombre de tabla validado contra la lista blanca self::TABLES.
				$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$full}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			$status[ $table ] = array(
				'exists' => $exists,
				'rows'   => $rows,
			);
		}

		return $status;
	}
}
