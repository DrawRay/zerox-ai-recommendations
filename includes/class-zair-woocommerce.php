<?php
/**
 * Puente con WooCommerce: atribución de ventas (Fase 8).
 *
 * El problema que resuelve: saber si una compra vino de una recomendación
 * del carrusel. El session_id por sí solo no basta (el cliente pudo llegar
 * al producto por otro camino), así que marcamos el ítem en el carrito en
 * el momento del clic en "Agregar".
 *
 * Flujo:
 *   1. El JS envía zair_rec=1 al añadir al carrito.
 *   2. Se guarda meta en el cart item (_zair_rec, origen, sesión).
 *   3. La meta viaja del carrito al pedido.
 *   4. Al completarse el pedido se registra el evento 'purchase' y, si
 *      existe un lead con esa misma sesión, se marca la venta.
 *
 * Compatible con HPOS: los pedidos se leen siempre por el CRUD de
 * WooCommerce, nunca con consultas directas a wp_posts.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_WooCommerce
 */
class ZAIR_WooCommerce {

	/**
	 * Registra los hooks de WooCommerce.
	 */
	public function __construct() {
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );

		// Se engancha a ambos estados: no todas las tiendas pasan por "completed".
		add_action( 'woocommerce_order_status_processing', array( $this, 'attribute_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'attribute_order' ), 10, 2 );
	}

	/**
	 * Marca el ítem del carrito cuando viene del carrusel.
	 *
	 * @param array $cart_item_data Datos del ítem.
	 * @param int   $product_id     Producto añadido.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id ) {

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- lo envía el propio carrusel; solo marca origen, no altera precio ni cantidad.
		if ( empty( $_POST['zair_rec'] ) ) {
			return $cart_item_data;
		}

		$origin_id  = isset( $_POST['zair_origin'] ) ? absint( $_POST['zair_origin'] ) : 0;
		$session_id = ZAIR_Session::get_id();
		// phpcs:enable

		$cart_item_data['_zair_rec']        = 1;
		$cart_item_data['_zair_origin']     = $origin_id;
		$cart_item_data['_zair_session']    = $session_id;

		// Registrar el evento de carrito en el momento del clic.
		ZAIR_Events::log(
			'add_cart',
			array(
				'session_id'             => $session_id,
				'product_origin_id'      => $origin_id,
				'recommended_product_id' => absint( $product_id ),
			)
		);

		return $cart_item_data;
	}

	/**
	 * Traslada la marca del carrito a la línea del pedido.
	 *
	 * @param WC_Order_Item_Product $item          Línea del pedido.
	 * @param string                $cart_item_key Clave del ítem.
	 * @param array                 $values        Datos del ítem.
	 * @param WC_Order              $order         Pedido.
	 * @return void
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {

		if ( empty( $values['_zair_rec'] ) ) {
			return;
		}

		$item->add_meta_data( '_zair_rec', 1, true );
		$item->add_meta_data( '_zair_origin', isset( $values['_zair_origin'] ) ? absint( $values['_zair_origin'] ) : 0, true );
		$item->add_meta_data( '_zair_session', isset( $values['_zair_session'] ) ? $values['_zair_session'] : '', true );
	}

	/**
	 * Al confirmarse el pedido, registra las compras atribuidas
	 * y actualiza el lead correspondiente si existe.
	 *
	 * @param int      $order_id Id del pedido.
	 * @param WC_Order $order    Pedido.
	 * @return void
	 */
	public function attribute_order( $order_id, $order = null ) {

		$order = $order ? $order : wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Idempotencia: un pedido solo se atribuye una vez.
		if ( $order->get_meta( '_zair_attributed' ) ) {
			return;
		}

		$total_atribuido = 0.0;
		$session_id      = '';
		$hubo_recomendado = false;

		foreach ( $order->get_items() as $item ) {

			if ( ! $item->get_meta( '_zair_rec' ) ) {
				continue;
			}

			$hubo_recomendado = true;
			$item_session     = (string) $item->get_meta( '_zair_session' );

			if ( $item_session && ! $session_id ) {
				$session_id = $item_session;
			}

			$total_atribuido += (float) $item->get_total();

			ZAIR_Events::log(
				'purchase',
				array(
					'session_id'             => $item_session,
					'product_origin_id'      => absint( $item->get_meta( '_zair_origin' ) ),
					'recommended_product_id' => absint( $item->get_product_id() ),
				)
			);
		}

		if ( ! $hubo_recomendado ) {
			return;
		}

		$order->update_meta_data( '_zair_attributed', 1 );
		$order->update_meta_data( '_zair_attributed_total', $total_atribuido );
		$order->save();

		// Si esa misma sesión dejó un lead, se cierra el círculo.
		if ( $session_id ) {
			$this->link_lead_to_order( $session_id, $order, $total_atribuido );
		}
	}

	/**
	 * Marca como venta el lead más reciente de una sesión.
	 *
	 * @param string   $session_id Sesión anónima.
	 * @param WC_Order $order      Pedido.
	 * @param float    $total      Importe atribuido a recomendaciones.
	 * @return void
	 */
	private function link_lead_to_order( $session_id, $order, $total ) {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_leads' );

		$lead_id = $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT id FROM {$table}
				 WHERE session_id = %s AND venta_generada = 0
				 ORDER BY created_at DESC LIMIT 1",
				$session_id
			)
		);

		if ( ! $lead_id ) {
			return;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'estado'         => 'venta',
				'venta_generada' => 1,
				'order_id'       => $order->get_id(),
				'monto_venta'    => round( $total, 2 ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => absint( $lead_id ) )
		);
	}

	/**
	 * Suma de ventas atribuidas a recomendaciones en un rango.
	 * Usa consultas CRUD de WooCommerce (compatible con HPOS).
	 *
	 * @param string $desde Fecha inicial (Y-m-d).
	 * @param string $hasta Fecha final (Y-m-d).
	 * @return array{pedidos:int, total:float}
	 */
	public static function attributed_sales( $desde = '', $hasta = '' ) {

		$args = array(
			'limit'      => -1,
			'status'     => array( 'processing', 'completed' ),
			'meta_key'   => '_zair_attributed', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => 1, // phpcs:ignore WordPress.DB.SlowDBQuery
			'return'     => 'objects',
		);

		if ( $desde && $hasta ) {
			$args['date_created'] = $desde . '...' . $hasta;
		}

		$orders  = wc_get_orders( $args );
		$total   = 0.0;
		$pedidos = 0;

		foreach ( (array) $orders as $order ) {
			$pedidos++;
			$total += (float) $order->get_meta( '_zair_attributed_total' );
		}

		return array(
			'pedidos' => $pedidos,
			'total'   => round( $total, 2 ),
		);
	}
}
