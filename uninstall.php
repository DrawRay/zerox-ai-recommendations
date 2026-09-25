<?php
/**
 * Desinstalación del plugin.
 *
 * REGLA DE SEGURIDAD DE DATOS:
 * Solo borra tablas y opciones si el usuario activó expresamente
 * "Eliminar todos los datos al desinstalar" en ZEROX AI > Configuración.
 * Por defecto los datos se conservan (protege leads, eventos y
 * compatibilidades del proyecto/tesis).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_settings = get_option( 'zair_settings', array() );

if ( empty( $zair_settings['delete_data_on_uninstall'] ) ) {
	return; // Conservar todo.
}

global $wpdb;

$zair_tables = array(
	'zair_leads',
	'zair_recommendation_events',
	'zair_aliases',
	'zair_product_compatibility',
	'zair_vehicle_engine',
);

foreach ( $zair_tables as $zair_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$zair_table}" ); // phpcs:ignore WordPress.DB
}

delete_option( 'zair_settings' );
delete_option( 'zair_db_version' );
delete_option( 'zair_ai_last_error' );
delete_option( 'zair_last_maintenance' );
wp_clear_scheduled_hook( 'zair_daily_maintenance' );

// Transients residuales.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_zair\_%'
	    OR option_name LIKE '\_transient\_timeout\_zair\_%'"
); // phpcs:ignore WordPress.DB
