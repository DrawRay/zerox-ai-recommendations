<?php
/**
 * Endpoints AJAX del panel administrativo (Fase 4).
 *
 * Único endpoint por ahora: buscador de productos de WooCommerce para
 * relacionarlos con un vehículo/motor.
 *
 * Seguridad: nonce + current_user_can() + sanitización de entrada.
 * Solo responde a usuarios logueados con permisos de tienda.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Admin_Ajax
 */
class ZAIR_Admin_Ajax {

	/**
	 * Registra los endpoints.
	 */
	public function __construct() {
		add_action( 'wp_ajax_zair_search_products', array( $this, 'search_products' ) );
	}

	/**
	 * Busca productos por nombre o SKU y devuelve JSON.
	 *
	 * @return void
	 */
	public function search_products() {

		check_ajax_referer( 'zair_admin_ajax', 'nonce' );

		if ( ! current_user_can( ZAIR_Admin::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( array() );
		}

		// Búsqueda nativa de WooCommerce (respeta nombre y SKU).
		$data_store = WC_Data_Store::load( 'product' );
		$ids        = $data_store->search_products( $term, '', false, false, 20 );

		$results = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || 'variation' === $product->get_type() ) {
				continue;
			}

			$results[] = array(
				'id'     => $product->get_id(),
				'nombre' => $product->get_name(),
				'sku'    => $product->get_sku() ? $product->get_sku() : '—',
				'precio' => wp_strip_all_tags( $product->get_price_html() ),
				'stock'  => $product->is_in_stock() ? 'En stock' : 'Sin stock',
			);
		}

		wp_send_json_success( $results );
	}
}
