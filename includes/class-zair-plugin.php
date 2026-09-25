<?php
/**
 * Orquestador central del plugin.
 *
 * Único punto que instancia clases y registra hooks. Los módulos se
 * incorporan aquí a medida que avanza cada fase:
 *
 *  Fase 2  ✔ Base de datos + admin base.
 *  Fase 3    ZAIR_Admin::vehicles (CRUD Motores/Vehículos).
 *  Fase 4    ZAIR_Admin::compatibility + buscador AJAX de productos.
 *  Fase 5    ZAIR_Recommender + ZAIR_Ranking + ZAIR_Cache.
 *  Fase 6    ZAIR_Public (carrusel) + ZAIR_Rest.
 *  Fase 7    ZAIR_Leads.
 *  Fase 8    ZAIR_Events + ZAIR_Session + atribución de compra.
 *  Fase 9    Dashboard con métricas + export CSV.
 *  Fase 10   ZAIR_GA4.
 *  Fase 11   includes/ai/ (proveedor IA vía API, server-side).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Plugin
 */
final class ZAIR_Plugin {

	/**
	 * Instancia única.
	 *
	 * @var ZAIR_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Devuelve la instancia única.
	 *
	 * @return ZAIR_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado (patrón singleton).
	 */
	private function __construct() {}

	/**
	 * Registra los módulos activos según el contexto.
	 *
	 * @return void
	 */
	public function run() {

		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			// Migraciones de esquema pendientes (idempotente).
			ZAIR_Database::maybe_upgrade();

			require_once ZAIR_PLUGIN_DIR . 'admin/class-zair-admin.php';
			new ZAIR_Admin();
		}

		// Núcleo del recomendador: necesario tanto en admin como en frontend.
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-cache.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-ranking.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-recommender.php';

		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-session.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-leads.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-events.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-ga4.php';

		// Capa de IA (Fase 11). Siempre server-side: la API key nunca
		// llega al navegador.
		require_once ZAIR_PLUGIN_DIR . 'includes/ai/interface-zair-ai-provider.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/ai/class-zair-ai-claude.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/ai/class-zair-ai-manager.php';

		// Mantenimiento automático (Fase 12).
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-maintenance.php';
		new ZAIR_Maintenance();

		// Mantenimiento: cron diario de purga y limpieza (Fase 12).
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-maintenance.php';
		new ZAIR_Maintenance();

		// Atribución de ventas: debe correr también en el admin,
		// porque un pedido puede completarse desde el panel.
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-woocommerce.php';
		new ZAIR_WooCommerce();

		// API REST (Fases 6-7): se registra siempre, la sirve WordPress.
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-rest.php';
		new ZAIR_Rest();

		// Frontend: solo fuera del admin.
		if ( ! is_admin() ) {
			require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-render.php';
			require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-public.php';
			new ZAIR_Public();
			new ZAIR_GA4();
		}

		/*
		 * Fase 12: optimización, seguridad y pruebas.
		 */
	}

	/**
	 * Carga las traducciones.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'zerox-ai-recommendations',
			false,
			dirname( ZAIR_PLUGIN_BASENAME ) . '/languages'
		);
	}
}
