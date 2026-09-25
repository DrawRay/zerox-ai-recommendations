<?php
/**
 * Frontend público: sección "Complementa tu motor" (Fase 6).
 *
 * Estrategia de carga:
 *  - En la ficha de producto se imprime únicamente un contenedor vacío.
 *  - El contenido se pide por REST y se pinta en el navegador.
 *
 * Esto es deliberado por dos razones: la ficha no se ralentiza (el HTML
 * de la página no espera al recomendador), y la caché de página
 * (LiteSpeed en zeroxmotors.pe) no congela precios ni stock, porque esos
 * datos se piden en vivo tras servirse el HTML cacheado.
 *
 * CSS y JS se cargan solo en fichas de producto, nunca en el resto del sitio.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Public
 */
class ZAIR_Public {

	/**
	 * Registra hooks del frontend.
	 */
	/**
	 * Evita que la sección se imprima dos veces si el tema usa el hook
	 * y además el shortcode.
	 *
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Resultado de has_vehicle() por producto, dentro de la misma petición.
	 *
	 * @var array<int,bool>
	 */
	private static $cache_vehiculo = array();

	public function __construct() {
		// Los hooks solo se registran en fichas de producto: en el resto
		// del sitio el plugin no ejecuta absolutamente nada.
		add_action( 'wp', array( $this, 'maybe_hook' ) );
	}

	/**
	 * Registra los hooks del frontend solo donde hacen falta.
	 *
	 * @return void
	 */
	public function maybe_hook() {

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Shortcode para temas con plantilla personalizada (Flatsome UX Builder,
		// Elementor, etc.), donde el hook estándar de WooCommerce no se ejecuta.
		add_shortcode( 'zair_recommendations', array( $this, 'shortcode' ) );

		// Inserción automática en el hook estándar, desactivable desde Configuración.
		$settings = get_option( 'zair_settings', array() );
		if ( ! isset( $settings['auto_insert'] ) || ! empty( $settings['auto_insert'] ) ) {
			add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_container' ), 25 );
		}
	}

	/**
	 * Shortcode [zair_recommendations].
	 *
	 * Permite colocar la sección donde el editor visual lo decida.
	 * Encola los assets aquí también, porque en algunos constructores
	 * la plantilla se resuelve después de wp_enqueue_scripts.
	 *
	 * @return string
	 */
	public function shortcode() {

		if ( self::$rendered ) {
			return '';
		}

		$product_id = get_the_ID();

		if ( ! $product_id ) {
			return '';
		}

		// El HTML se arma en el servidor: la sección llega pintada y se ve
		// aunque el JavaScript de la página no llegue a ejecutarse.
		$html = ZAIR_Render::seccion( $product_id );

		if ( '' === $html ) {
			return '';
		}

		$this->enqueue_assets( true );

		self::$rendered = true;

		return $html;
	}

	/**
	 * Carga CSS y JS solo en fichas de producto que tengan recomendaciones.
	 *
	 * @return void
	 */
	public function enqueue_assets( $force = false ) {

		if ( ! $force && ! $this->should_load() ) {
			return;
		}

		wp_enqueue_style(
			'zair-public',
			ZAIR_PLUGIN_URL . 'public/css/zair-public.css',
			array(),
			ZAIR_VERSION
		);

		wp_enqueue_script(
			'zair-public',
			ZAIR_PLUGIN_URL . 'public/js/zair-public.js',
			array(),
			ZAIR_VERSION,
			true
		);

		$zair_conf            = get_option( 'zair_settings', array() );
		$zair_ajustes_layout  = ( isset( $zair_conf['layout'] ) && 'combo' === $zair_conf['layout'] ) ? 'combo' : 'carrusel';
		$zair_texto_cotizar   = isset( $zair_conf['quote_button_text'] ) && $zair_conf['quote_button_text']
			? $zair_conf['quote_button_text']
			: 'COTIZAR SELECCIONADOS';

		wp_localize_script(
			'zair-public',
			'zairData',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'zair/v1/recommendations' ) ),
				'leadUrl'   => esc_url_raw( rest_url( 'zair/v1/lead' ) ),
				'eventUrl'  => esc_url_raw( rest_url( 'zair/v1/event' ) ),
				'productId' => (int) get_the_ID(),
				'cartUrl'   => wc_get_cart_url(),
				// Acciones visibles en cada tarjeta.
				'mostrarComprar' => ! empty( $zair_conf['show_buy'] ),
				'mostrarCotizar' => ! isset( $zair_conf['show_quote'] ) || ! empty( $zair_conf['show_quote'] ),
				'mostrarLead'    => ! empty( $zair_conf['show_lead_form'] ),
				'mostrarBadge'   => ! empty( $zair_conf['show_badge'] ),
				'mostrarIcono'   => ! empty( $zair_conf['show_title_icon'] ),
				'ga4'       => ZAIR_GA4::js_config(),
				'layout'    => $zair_ajustes_layout,
				// Tarjetas visibles a la vez en escritorio.
				'cols'      => isset( $zair_conf['cards_per_row'] ) ? (int) $zair_conf['cards_per_row'] : 5,
				'quoteText' => $zair_texto_cotizar,
				'hasQuote'  => class_exists( 'ZQP_Public' ) ? 1 : 0,
				'i18n'      => array(
					'comprar'    => 'COMPRAR',
					'cotizar'    => 'COTIZAR',
					'agregando'  => 'Agregando…',
					'agregado'   => 'Agregado ✓',
					'verCarrito' => 'Ver carrito',
					'verProducto'=> 'Ver producto',
					'verMas'     => 'Ver más compatibles',
					'cotizarSeleccion'   => 'COTIZAR SELECCIONADOS',
					'unoSeleccionado'    => '1 repuesto seleccionado',
					'variosSeleccionados'=> 'repuestos seleccionados',
					'compatible' => 'Compatible con',
					'oferta'     => 'Oferta',
					'error'      => 'No se pudo agregar. Inténtalo de nuevo.',
					'anterior'   => 'Anterior',
					'siguiente'  => 'Siguiente',
					// Captura de leads (Fase 7).
					'leadPregunta'       => '¿No estás seguro de la compatibilidad?',
					'leadBoton'          => 'Consultar compatibilidad',
					'leadNombre'         => 'Nombre',
					'leadWhatsapp'       => 'WhatsApp',
					'leadConsentimiento' => 'Autorizo el uso de mis datos para recibir atención sobre mi consulta.',
					'leadEnviar'         => 'Solicitar asesoría',
					'leadEnviando'       => 'Enviando…',
					'leadGracias'        => '¡Gracias!',
					'leadErrNombre'      => 'Escribe tu nombre.',
					'leadErrWhatsapp'    => 'Escribe un número de WhatsApp válido.',
					'leadErrConsent'     => 'Marca la autorización para poder contactarte.',
				),
			)
		);
	}

	/**
	 * ¿Estamos en una ficha de producto con recomendaciones posibles?
	 *
	 * @return bool
	 */
	private function should_load() {

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return false;
		}

		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return false;
		}

		return $this->has_vehicle( $product_id );
	}

	/**
	 * ¿El producto está vinculado a algún vehículo/motor verificado?
	 *
	 * @param int $product_id Id del producto.
	 * @return bool
	 */
	private function has_vehicle( $product_id ) {

		$product_id = (int) $product_id;

		// Memoización por petición: should_load() se consulta desde el
		// enqueue, desde el hook de render y desde el shortcode. Sin esto
		// la misma consulta se ejecutaría tres veces en cada carga.
		if ( isset( self::$cache_vehiculo[ $product_id ] ) ) {
			return self::$cache_vehiculo[ $product_id ];
		}

		$vehicles = ZAIR_Recommender::resolve_vehicles( $product_id );

		self::$cache_vehiculo[ $product_id ] = ! empty( $vehicles );

		return self::$cache_vehiculo[ $product_id ];
	}

	/**
	 * Imprime el contenedor que el JS rellenará.
	 *
	 * @return void
	 */
	public function render_container() {

		if ( self::$rendered ) {
			return;
		}

		$product_id = get_the_ID();

		if ( ! $product_id ) {
			return;
		}

		$html = ZAIR_Render::seccion( $product_id );

		if ( '' === $html ) {
			return;
		}

		self::$rendered = true;

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado en ZAIR_Render.
	}
}
