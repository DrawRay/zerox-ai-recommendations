<?php
/**
 * Plugin Name:          Zerox AI Recommendations
 * Plugin URI:           https://zeroxmotors.pe/
 * Description:          Sistema de recomendación de repuestos compatibles y complementarios (marca, modelo, cilindrada, código de motor) para ZEROXMOTORS. Base de compatibilidad verificada + motor de ranking + captura de leads + analítica.
 * Version:              2.1.1
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Author:               ZEROXMOTORS
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          zerox-ai-recommendations
 * Domain Path:          /languages
 * WC requires at least: 7.0
 *
 * @package Zerox_AI_Recommendations
 */

// Bloquear acceso directo.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Constantes globales del plugin.
 * ---------------------------------------------------------------------- */
define( 'ZAIR_VERSION', '2.1.1' );
define( 'ZAIR_DB_VERSION', '1.1.0' );
define( 'ZAIR_PLUGIN_FILE', __FILE__ );
define( 'ZAIR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZAIR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZAIR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/* -------------------------------------------------------------------------
 * Activación / desactivación.
 * Se registran fuera de plugins_loaded porque WordPress las ejecuta
 * en un contexto especial.
 * ---------------------------------------------------------------------- */
require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-database.php';
require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-activator.php';
require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-deactivator.php';

register_activation_hook( __FILE__, array( 'ZAIR_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZAIR_Deactivator', 'deactivate' ) );

/* -------------------------------------------------------------------------
 * Compatibilidad con HPOS (High-Performance Order Storage) de WooCommerce.
 * ---------------------------------------------------------------------- */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/* -------------------------------------------------------------------------
 * Arranque del plugin.
 * Se espera a plugins_loaded (prioridad 20) para garantizar que
 * WooCommerce ya esté cargado antes de decidir si arrancamos.
 * ---------------------------------------------------------------------- */
add_action( 'plugins_loaded', 'zair_bootstrap', 20 );

/**
 * Arranca el plugin solo si WooCommerce está activo.
 *
 * @return void
 */
function zair_bootstrap() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'zair_admin_notice_woocommerce_missing' );
		return;
	}

	require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-plugin.php';
	ZAIR_Plugin::instance()->run();
}

/**
 * Aviso en el admin cuando WooCommerce no está activo.
 *
 * @return void
 */
function zair_admin_notice_woocommerce_missing() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'Zerox AI Recommendations:', 'zerox-ai-recommendations' ),
		esc_html__( 'requiere que WooCommerce esté instalado y activo. El plugin permanecerá inactivo hasta entonces.', 'zerox-ai-recommendations' )
	);
}
