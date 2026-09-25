<?php
/**
 * Endpoints REST del plugin (Fase 6).
 *
 * GET /wp-json/zair/v1/recommendations?product_id=123
 *
 * Es un endpoint de LECTURA PÚBLICA a propósito: solo devuelve datos que
 * ya son públicos en la tienda (nombre, precio, stock, imagen, URL). No
 * lleva nonce porque los nonces se rompen con la caché de página
 * (LiteSpeed, WP Rocket): un visitante cacheado recibiría un nonce
 * caducado y no vería recomendaciones. La protección es rate limiting
 * por IP y validación estricta de la entrada.
 *
 * Los endpoints de ESCRITURA (leads, eventos) sí llevarán nonce
 * y se añaden en las Fases 7 y 8.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Rest
 */
class ZAIR_Rest {

	/**
	 * Namespace de la API.
	 */
	const NS = 'zair/v1';

	/**
	 * Máximo de peticiones de lectura por IP y minuto.
	 */
	const RATE_LIMIT = 60;

	/**
	 * Máximo de escrituras (leads, interpretación) por IP y minuto.
	 * Más estricto: son operaciones que consumen recursos.
	 */
	const RATE_LIMIT_WRITE = 12;

	/**
	 * Registra los hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Define las rutas.
	 *
	 * @return void
	 */
	public function register_routes() {

		register_rest_route(
			self::NS,
			'/recommendations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_recommendations' ),
				'permission_callback' => array( $this, 'check_rate_limit' ),
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return absint( $value ) > 0;
						},
					),
					'limit'      => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/lead',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_lead' ),
				'permission_callback' => array( $this, 'check_rate_limit_write' ),
			)
		);

		register_rest_route(
			self::NS,
			'/event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'log_event' ),
				'permission_callback' => array( $this, 'check_rate_limit' ),
			)
		);

		register_rest_route(
			self::NS,
			'/interpret',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'interpret' ),
				'permission_callback' => array( $this, 'check_rate_limit_write' ),
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Interpreta una búsqueda en lenguaje natural (Fase 11).
	 *
	 * La IA se ejecuta siempre en el servidor: la API key nunca sale de
	 * PHP. La respuesta se valida contra la base de vehículos antes de
	 * devolverse, y las recomendaciones siguen saliendo únicamente de
	 * compatibilidades verificadas.
	 *
	 * @param WP_REST_Request $request Petición.
	 * @return WP_REST_Response
	 */
	public function interpret( $request ) {

		$texto = (string) $request->get_param( 'q' );
		$texto = mb_substr( trim( $texto ), 0, 200 );

		if ( mb_strlen( $texto ) < 2 ) {
			return new WP_REST_Response( array( 'encontrado' => false ), 200 );
		}

		$session_id = ZAIR_Session::get_id();

		ZAIR_Events::log(
			'search_detected',
			array( 'session_id' => $session_id )
		);

		$resultado = ZAIR_AI_Manager::interpretar( $texto );

		// Ambigüedad: se pregunta en lugar de adivinar.
		if ( $resultado['necesita_desambiguar'] ) {

			$opciones = array();
			foreach ( $resultado['opciones'] as $v ) {
				$opciones[] = array(
					'id'        => (int) $v->id,
					'etiqueta'  => trim( $v->marca . ' ' . $v->modelo . ' ' . $v->version . ' ' . $v->cilindrada ),
					'motor'     => $v->codigo_motor,
				);
			}

			return new WP_REST_Response(
				array(
					'encontrado' => false,
					'ambiguo'    => true,
					'pregunta'   => $resultado['pregunta'],
					'opciones'   => $opciones,
					'metodo'     => $resultado['metodo'],
				),
				200
			);
		}

		if ( empty( $resultado['vehiculo'] ) ) {
			return new WP_REST_Response(
				array(
					'encontrado' => false,
					'metodo'     => $resultado['metodo'],
				),
				200
			);
		}

		$vehiculo = $resultado['vehiculo'];

		ZAIR_Events::log(
			'compatibility_query',
			array(
				'session_id'        => $session_id,
				'vehicle_engine_id' => (int) $vehiculo->id,
			)
		);

		return new WP_REST_Response(
			array(
				'encontrado' => true,
				'metodo'     => $resultado['metodo'],
				'confianza'  => $resultado['confianza'],
				'vehiculo'   => array(
					'id'       => (int) $vehiculo->id,
					'etiqueta' => trim( $vehiculo->marca . ' ' . $vehiculo->modelo . ' ' . $vehiculo->version . ' ' . $vehiculo->cilindrada ),
					'motor'    => $vehiculo->codigo_motor,
				),
			),
			200
		);
	}

	/**
	 * Registra eventos anónimos del embudo (Fase 8).
	 *
	 * Sin nonce a propósito, igual que la lectura: solo escribe datos
	 * anónimos de comportamiento, y un nonce caducado por la caché
	 * dejaría el embudo sin datos. La protección es el rate limiting,
	 * la lista blanca de tipos y la validación del formato de sesión.
	 *
	 * @param WP_REST_Request $request Petición.
	 * @return WP_REST_Response
	 */
	public function log_event( $request ) {

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$tipo = isset( $params['event_type'] ) ? sanitize_key( $params['event_type'] ) : '';

		if ( ! in_array( $tipo, ZAIR_Events::TIPOS, true ) ) {
			return new WP_REST_Response( array( 'success' => false ), 400 );
		}

		$session_id = isset( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '';
		$common     = array(
			'session_id'        => $session_id,
			'product_origin_id' => isset( $params['product_origin_id'] ) ? absint( $params['product_origin_id'] ) : 0,
			'vehicle_engine_id' => isset( $params['vehicle_engine_id'] ) ? absint( $params['vehicle_engine_id'] ) : 0,
		);

		// Lote de impresiones (todas las tarjetas visibles de una vez).
		if ( ! empty( $params['items'] ) && is_array( $params['items'] ) ) {

			$items = array();
			foreach ( $params['items'] as $item ) {
				$items[] = array(
					'recommended_product_id' => isset( $item['id'] ) ? absint( $item['id'] ) : 0,
					'position'               => isset( $item['position'] ) ? absint( $item['position'] ) : null,
					'score'                  => isset( $item['score'] ) ? (float) $item['score'] : null,
				);
			}

			/*
			 * Evita contar dos veces la misma impresión cuando el visitante
			 * recarga la ficha, pero sin bloquear los productos que aparecen
			 * al expandir el carrusel: la comprobación es por producto.
			 */
			if ( 'impression' === $tipo && $session_id ) {

				$candidatos = wp_list_pluck( $items, 'recommended_product_id' );
				$nuevos     = ZAIR_Events::filter_new_impressions( $session_id, $candidatos );

				$items = array_values(
					array_filter(
						$items,
						function ( $item ) use ( $nuevos ) {
							return in_array( (int) $item['recommended_product_id'], $nuevos, true );
						}
					)
				);

				if ( empty( $items ) ) {
					return new WP_REST_Response(
						array(
							'success' => true,
							'skipped' => true,
						),
						200
					);
				}
			}

			$count = ZAIR_Events::log_batch( $tipo, $items, $common );

			return new WP_REST_Response( array( 'success' => true, 'logged' => $count ), 200 );
		}

		// Evento individual.
		$ok = ZAIR_Events::log(
			$tipo,
			array_merge(
				$common,
				array(
					'recommended_product_id' => isset( $params['recommended_product_id'] ) ? absint( $params['recommended_product_id'] ) : 0,
					'position'               => isset( $params['position'] ) ? absint( $params['position'] ) : null,
					'score'                  => isset( $params['score'] ) ? (float) $params['score'] : null,
				)
			)
		);

		return new WP_REST_Response( array( 'success' => (bool) $ok ), $ok ? 200 : 400 );
	}

	/**
	 * Crea un lead desde el formulario del frontend (Fase 7).
	 *
	 * Escritura: sí lleva nonce, honeypot y validación en servidor.
	 * El nonce se entrega al JS en el momento del render, no en el HTML
	 * cacheado, para que LiteSpeed no sirva uno caducado.
	 *
	 * @param WP_REST_Request $request Petición.
	 * @return WP_REST_Response
	 */
	public function create_lead( $request ) {

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		// 1) Nonce.
		$nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'zair_lead' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.',
				),
				403
			);
		}

		// 2) Honeypot: si viene relleno, es un bot. Respondemos "ok" sin guardar.
		if ( ! empty( $params['zair_website'] ) ) {
			return new WP_REST_Response( array( 'success' => true ), 200 );
		}

		// 3) Alta.
		$result = ZAIR_Leads::create(
			array(
				'nombre'                 => isset( $params['nombre'] ) ? $params['nombre'] : '',
				'whatsapp'               => isset( $params['whatsapp'] ) ? $params['whatsapp'] : '',
				'consentimiento'         => ! empty( $params['consentimiento'] ),
				'session_id'             => isset( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '',
				'product_origin_id'      => isset( $params['product_origin_id'] ) ? absint( $params['product_origin_id'] ) : 0,
				'recommended_product_id' => isset( $params['recommended_product_id'] ) ? absint( $params['recommended_product_id'] ) : 0,
				'vehicle_engine_id'      => isset( $params['vehicle_engine_id'] ) ? absint( $params['vehicle_engine_id'] ) : 0,
			)
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		ZAIR_Events::log(
			'lead_submit',
			array(
				'session_id'        => isset( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '',
				'product_origin_id' => isset( $params['product_origin_id'] ) ? absint( $params['product_origin_id'] ) : 0,
				'vehicle_engine_id' => isset( $params['vehicle_engine_id'] ) ? absint( $params['vehicle_engine_id'] ) : 0,
			)
		);

		return new WP_REST_Response(
			array(
				'success'   => true,
				'lead_code' => $result['lead_code'],
				'message'   => 'Recibimos tu consulta. Te escribiremos por WhatsApp muy pronto.',
			),
			201
		);
	}

	/**
	 * Rate limiting de lectura.
	 *
	 * @return bool|WP_Error
	 */
	public function check_rate_limit() {
		return self::throttle( 'r', self::RATE_LIMIT );
	}

	/**
	 * Rate limiting de escritura (más estricto).
	 *
	 * @return bool|WP_Error
	 */
	public function check_rate_limit_write() {
		return self::throttle( 'w', self::RATE_LIMIT_WRITE );
	}

	/**
	 * Contador por IP en ventana de un minuto.
	 *
	 * @param string $bucket Identificador del grupo.
	 * @param int    $limite Máximo permitido.
	 * @return bool|WP_Error
	 */
	private static function throttle( $bucket, $limite ) {

		$ip  = self::client_ip();
		$key = 'zair_rl_' . $bucket . '_' . md5( $ip );

		// Con caché de objetos persistente (Redis, Memcached, LiteSpeed)
		// el contador vive en memoria. Sin ella se usan transients, que
		// escriben en la base de datos: por eso el contador se guarda
		// solo cuando realmente hace falta.
		$persistente = wp_using_ext_object_cache();

		$count = $persistente
			? (int) wp_cache_get( $key, 'zair' )
			: (int) get_transient( $key );

		if ( $count >= $limite ) {
			return new WP_Error(
				'zair_rate_limited',
				'Demasiadas peticiones. Espera un momento.',
				array( 'status' => 429 )
			);
		}

		if ( $persistente ) {
			wp_cache_set( $key, $count + 1, 'zair', MINUTE_IN_SECONDS );
		} else {
			set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		}

		return true;
	}

	/**
	 * IP del cliente teniendo en cuenta proxys y CDN.
	 * Solo se usa para limitar peticiones; nunca se almacena.
	 *
	 * @return string
	 */
	private static function client_ip() {

		$candidatos = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $candidatos as $clave ) {
			if ( empty( $_SERVER[ $clave ] ) ) {
				continue;
			}

			$valor = sanitize_text_field( wp_unslash( $_SERVER[ $clave ] ) );
			$valor = trim( explode( ',', $valor )[0] );

			if ( filter_var( $valor, FILTER_VALIDATE_IP ) ) {
				return $valor;
			}
		}

		return 'unknown';
	}

	/**
	 * Devuelve las recomendaciones de un producto.
	 *
	 * @param WP_REST_Request $request Petición.
	 * @return WP_REST_Response
	 */
	public function get_recommendations( $request ) {

		$product_id = absint( $request->get_param( 'product_id' ) );
		$settings   = get_option( 'zair_settings', array() );

		$max   = isset( $settings['max_recommendations'] ) ? (int) $settings['max_recommendations'] : 4;
		$limit = $request->get_param( 'limit' ) ? absint( $request->get_param( 'limit' ) ) : $max;
		$limit = min( 24, max( 1, $limit ) );

		$result = ZAIR_Recommender::get_recommendations( $product_id, array( 'limit' => $limit ) );

		if ( empty( $result['items'] ) ) {
			return new WP_REST_Response(
				array(
					'has_recommendations' => false,
					'items'               => array(),
				),
				200
			);
		}

		// Texto del vehículo para el encabezado ("Chevrolet N300 1.2 / B12").
		$vehicle_label = '';
		$codigo_motor  = '';

		if ( ! empty( $result['vehicles'][0] ) ) {
			$v             = $result['vehicles'][0];
			$vehicle_label = trim( $v->marca . ' ' . $v->modelo . ' ' . $v->version . ' ' . $v->cilindrada );
			$codigo_motor  = $v->codigo_motor;
			if ( $codigo_motor ) {
				$vehicle_label .= ' / ' . $codigo_motor;
			}
		}

		$items = array();

		foreach ( $result['items'] as $position => $item ) {
			$items[] = array(
				'id'           => (int) $item['product_id'],
				'nombre'       => $item['nombre'],
				'sku'          => $item['sku'],
				'precio_html'  => $item['precio_html'],
				'en_oferta'    => (bool) $item['en_oferta'],
				'url'          => $item['url'],
				'imagen'       => $item['imagen'] ? $item['imagen'] : wc_placeholder_img_src( 'woocommerce_thumbnail' ),
				'comprable'    => (bool) $item['comprable'],
				'codigo_motor' => $item['codigo_motor'],
				'position'     => $position + 1,
				'score'        => (float) $item['score'],
			);
		}

		$response = new WP_REST_Response(
			array(
				'has_recommendations' => true,
				'titulo'              => isset( $settings['section_title'] ) ? $settings['section_title'] : 'Complementa tu motor',
				// El subtítulo y el vehículo detectado son opcionales: el código
				// de motor es información interna que no conviene exponer.
				'subtitulo'           => ! empty( $settings['show_subtitle'] ) && isset( $settings['section_subtitle'] )
					? $settings['section_subtitle']
					: '',
				'vehiculo'            => ! empty( $settings['show_vehicle'] ) ? $vehicle_label : '',
				'codigo_motor'        => $codigo_motor,
				'items'               => $items,
				'total_disponibles'   => (int) $result['total_disponibles'],
				'hay_mas'             => $result['total_disponibles'] > count( $items ),
				'product_origin_id'   => $product_id,
				'vehicle_engine_id'   => ! empty( $result['vehicles'][0] ) ? (int) $result['vehicles'][0]->id : 0,
				'lead_nonce'          => wp_create_nonce( 'zair_lead' ),
				'session_id'          => ZAIR_Session::get_id(),
			),
			200
		);

		// Caché de borde corta: alivia picos sin congelar precios.
		$response->header( 'Cache-Control', 'public, max-age=120' );

		return $response;
	}
}
