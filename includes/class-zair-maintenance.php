<?php
/**
 * Mantenimiento y salud del sistema (Fase 12).
 *
 * - Cron diario: purga de eventos según la retención configurada y
 *   limpieza de transients caducados del plugin.
 * - Diagnóstico: comprobaciones que se muestran en el Dashboard para
 *   detectar problemas de configuración antes de que afecten a ventas.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Maintenance
 */
class ZAIR_Maintenance {

	/**
	 * Nombre del evento cron.
	 */
	const CRON_HOOK = 'zair_daily_maintenance';

	/**
	 * Registra los hooks.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, array( $this, 'run_daily' ) );

		// Solo se comprueba desde el panel: hacerlo en cada visita del
		// frontend supondría una lectura de opciones innecesaria.
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'schedule' ) );
		}
	}

	/**
	 * Programa el cron si no existe.
	 *
	 * @return void
	 */
	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Tareas diarias.
	 *
	 * @return void
	 */
	public function run_daily() {
		global $wpdb;

		$settings = get_option( 'zair_settings', array() );
		$meses    = isset( $settings['events_retention_months'] ) ? (int) $settings['events_retention_months'] : 0;

		$purgados = ZAIR_Events::purge_old( $meses );

		// Transients caducados del plugin (los de rate limiting se acumulan).
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_value < %d",
				'_transient_timeout_zair_%',
				time()
			)
		);

		update_option(
			'zair_last_maintenance',
			array(
				'fecha'    => current_time( 'mysql' ),
				'purgados' => $purgados,
			),
			false
		);
	}

	/**
	 * Diagnóstico del sistema para el Dashboard.
	 *
	 * @return array Lista de comprobaciones { titulo, estado, mensaje }.
	 */
	public static function diagnostics() {
		global $wpdb;

		$checks = array();

		// 1) Vehículos activos.
		$vehiculos = (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . ZAIR_Database::table( 'zair_vehicle_engine' ) . ' WHERE estado = 1' // phpcs:ignore WordPress.DB
		);
		$checks[] = array(
			'titulo'  => 'Vehículos / motores activos',
			'estado'  => $vehiculos > 0 ? 'ok' : 'error',
			'mensaje' => $vehiculos > 0
				? $vehiculos . ' registrados'
				: 'Sin vehículos activos: el recomendador no puede funcionar.',
		);

		// 2) Compatibilidades verificadas.
		$verificadas = (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . ZAIR_Database::table( 'zair_product_compatibility' ) . ' WHERE compatibilidad_verificada = 1 AND estado = 1' // phpcs:ignore WordPress.DB
		);
		$checks[] = array(
			'titulo'  => 'Compatibilidades verificadas',
			'estado'  => $verificadas > 0 ? 'ok' : 'error',
			'mensaje' => $verificadas > 0
				? $verificadas . ' listas para recomendar'
				: 'Ninguna compatibilidad verificada: no se mostrará nada en la web.',
		);

		// 3) Productos "motor" (permiten reconocer el vehículo en la ficha).
		$motores = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM " . ZAIR_Database::table( 'zair_product_compatibility' ) . " WHERE tipo_relacion = 'motor' AND compatibilidad_verificada = 1 AND estado = 1" // phpcs:ignore WordPress.DB
		);
		$checks[] = array(
			'titulo'  => 'Productos marcados como «Motor»',
			'estado'  => $motores > 0 ? 'ok' : 'warning',
			'mensaje' => $motores > 0
				? $motores . ' productos identifican a su vehículo'
				: 'Sin productos de tipo «Motor»: el sistema no reconocerá el vehículo desde la ficha.',
		);

		// 4) Compatibilidades apuntando a productos inexistentes.
		$huerfanas = self::count_orphans();
		$checks[]  = array(
			'titulo'  => 'Compatibilidades huérfanas',
			'estado'  => 0 === $huerfanas ? 'ok' : 'warning',
			'mensaje' => 0 === $huerfanas
				? 'Ninguna: todos los productos existen'
				: $huerfanas . ' apuntan a productos eliminados de WooCommerce.',
		);

		// 5) REST API accesible.
		$checks[] = array(
			'titulo'  => 'API REST',
			'estado'  => 'ok',
			'mensaje' => rest_url( 'zair/v1/recommendations' ),
		);

		// 6) Cron de mantenimiento.
		$next     = wp_next_scheduled( self::CRON_HOOK );
		$checks[] = array(
			'titulo'  => 'Mantenimiento automático',
			'estado'  => $next ? 'ok' : 'warning',
			'mensaje' => $next
				? 'Próxima ejecución: ' . date_i18n( 'd/m/Y H:i', $next )
				: 'No programado.',
		);

		// 7) Tamaño de la tabla de eventos.
		$eventos  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ZAIR_Database::table( 'zair_recommendation_events' ) ); // phpcs:ignore WordPress.DB
		$checks[] = array(
			'titulo'  => 'Eventos registrados',
			'estado'  => $eventos < 500000 ? 'ok' : 'warning',
			'mensaje' => number_format_i18n( $eventos ) . ' filas'
				. ( $eventos >= 500000 ? ' — considera activar la retención en Configuración.' : '' ),
		);

		// 8) API key de IA fuera de la base de datos.
		if ( ZAIR_AI_Manager::is_enabled() ) {
			$en_config = defined( 'ZAIR_AI_API_KEY' ) && ZAIR_AI_API_KEY;
			$checks[]  = array(
				'titulo'  => 'API key de IA',
				'estado'  => $en_config ? 'ok' : 'warning',
				'mensaje' => $en_config
					? 'Definida en wp-config.php (recomendado)'
					: 'Guardada en la base de datos. Es más seguro moverla a wp-config.php.',
			);
		}

		return $checks;
	}

	/**
	 * Cuenta compatibilidades cuyo producto ya no existe.
	 *
	 * @return int
	 */
	public static function count_orphans() {
		global $wpdb;

		$ids = $wpdb->get_col(
			'SELECT DISTINCT product_id FROM ' . ZAIR_Database::table( 'zair_product_compatibility' ) . ' LIMIT 2000' // phpcs:ignore WordPress.DB
		);

		$huerfanas = 0;

		foreach ( (array) $ids as $id ) {
			if ( ! wc_get_product( (int) $id ) ) {
				$huerfanas++;
			}
		}

		return $huerfanas;
	}
}
