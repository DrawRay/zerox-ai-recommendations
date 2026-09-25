<?php
/**
 * Rutinas de desactivación del plugin.
 *
 * REGLA: desactivar NUNCA borra datos. Solo limpia elementos temporales.
 * El borrado de datos ocurre únicamente en uninstall.php y solo si el
 * usuario lo autorizó expresamente en Configuración.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Deactivator
 */
class ZAIR_Deactivator {

	/**
	 * Se ejecuta al desactivar el plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {

		// Cron de mantenimiento (se usará desde la Fase 8).
		wp_clear_scheduled_hook( 'zair_daily_maintenance' );

		// Transients de caché de recomendaciones.
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\_transient\_zair\_%'
			    OR option_name LIKE '\_transient\_timeout\_zair\_%'"
		);
	}
}
