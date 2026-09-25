<?php
/**
 * Caché del motor de recomendaciones (Fase 5).
 *
 * Cachea SOLO la parte estable: qué productos son compatibles con qué
 * motor y con qué score base. El precio, la oferta y el stock NUNCA se
 * cachean: se leen en vivo de WooCommerce en cada render, de modo que un
 * cambio de precio se refleja al instante.
 *
 * La caché se invalida automáticamente al guardar compatibilidades
 * (ZAIR_Compatibility::flush_cache()).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Cache
 */
class ZAIR_Cache {

	/**
	 * Prefijo de los transients del recomendador.
	 */
	const PREFIX = 'zair_rec_';

	/**
	 * Duración por defecto en segundos (30 minutos).
	 */
	const TTL = 1800;

	/**
	 * Obtiene un valor cacheado.
	 *
	 * @param string $key Clave lógica.
	 * @return mixed|false
	 */
	public static function get( $key ) {
		if ( ! self::enabled() ) {
			return false;
		}
		return get_transient( self::PREFIX . md5( (string) $key ) );
	}

	/**
	 * Guarda un valor en caché.
	 *
	 * @param string $key   Clave lógica.
	 * @param mixed  $value Valor.
	 * @param int    $ttl   Segundos.
	 * @return void
	 */
	public static function set( $key, $value, $ttl = self::TTL ) {
		if ( ! self::enabled() ) {
			return;
		}
		set_transient( self::PREFIX . md5( (string) $key ), $value, (int) $ttl );
	}

	/**
	 * Indica si la caché está habilitada en Configuración.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = get_option( 'zair_settings', array() );
		return empty( $settings['cache_disabled'] );
	}

	/**
	 * Vacía toda la caché del recomendador.
	 *
	 * @return void
	 */
	public static function flush() {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '\_transient\_zair\_rec\_%'
			    OR option_name LIKE '\_transient\_timeout\_zair\_rec\_%'"
		);
	}
}
