<?php
/**
 * Orquestador de la capa de IA (Fase 11).
 *
 * Estrategia en cascada, de lo barato a lo caro:
 *
 *   1. ALIAS EXACTO  → si el texto coincide con un alias registrado,
 *                      se resuelve al instante, sin gastar un token.
 *   2. ALIAS PARCIAL → coincidencia por palabras clave del catálogo.
 *   3. IA            → solo si lo anterior falla y la IA está activa.
 *
 * Esto mantiene el coste bajo y hace que el sistema funcione igual si
 * la IA se cae o se queda sin crédito: degrada, no se rompe.
 *
 * La IA nunca decide compatibilidad. Su salida se valida siempre contra
 * zair_vehicle_engine: si nombra un vehículo que no existe en la base,
 * se descarta.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_AI_Manager
 */
class ZAIR_AI_Manager {

	/**
	 * Devuelve la API key.
	 * Prioriza la constante de wp-config.php sobre la base de datos:
	 * es el lugar más seguro para guardarla.
	 *
	 * @return string
	 */
	public static function get_api_key() {

		if ( defined( 'ZAIR_AI_API_KEY' ) && ZAIR_AI_API_KEY ) {
			return ZAIR_AI_API_KEY;
		}

		$settings = get_option( 'zair_settings', array() );

		return isset( $settings['ai_api_key'] ) ? (string) $settings['ai_api_key'] : '';
	}

	/**
	 * Modelo configurado.
	 *
	 * @return string
	 */
	public static function get_model() {
		$settings = get_option( 'zair_settings', array() );
		$modelo   = isset( $settings['ai_model'] ) ? trim( $settings['ai_model'] ) : '';
		return $modelo ? $modelo : ZAIR_AI_Claude::MODELO;
	}

	/**
	 * ¿Está la IA habilitada y configurada?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = get_option( 'zair_settings', array() );
		return ! empty( $settings['ai_enabled'] ) && '' !== self::get_api_key();
	}

	/**
	 * Instancia el proveedor configurado.
	 *
	 * @return ZAIR_AI_Provider|WP_Error
	 */
	public static function provider() {

		$settings = get_option( 'zair_settings', array() );
		$proveedor = isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'claude';

		switch ( $proveedor ) {
			case 'claude':
			default:
				return new ZAIR_AI_Claude();
		}
	}

	/**
	 * Interpreta una búsqueda con la cascada completa.
	 *
	 * @param string $texto Texto del usuario.
	 * @return array {
	 *     @type string      $metodo               alias|parcial|ia|ninguno
	 *     @type object|null $vehiculo             Fila de zair_vehicle_engine.
	 *     @type float       $confianza
	 *     @type bool        $necesita_desambiguar
	 *     @type string|null $pregunta
	 *     @type array       $opciones             Candidatos si hay ambigüedad.
	 * }
	 */
	public static function interpretar( $texto ) {

		$texto = trim( wp_strip_all_tags( (string) $texto ) );

		$vacio = array(
			'metodo'               => 'ninguno',
			'vehiculo'             => null,
			'confianza'            => 0.0,
			'necesita_desambiguar' => false,
			'pregunta'             => null,
			'opciones'             => array(),
		);

		if ( mb_strlen( $texto ) < 2 ) {
			return $vacio;
		}

		// --- Nivel 1: alias exacto ---
		$por_alias = self::match_alias( $texto );
		if ( $por_alias ) {
			return array(
				'metodo'               => 'alias',
				'vehiculo'             => $por_alias,
				'confianza'            => 1.0,
				'necesita_desambiguar' => false,
				'pregunta'             => null,
				'opciones'             => array(),
			);
		}

		// --- Nivel 2: coincidencia parcial contra el catálogo ---
		$parciales = self::match_parcial( $texto );

		if ( 1 === count( $parciales ) ) {
			return array(
				'metodo'               => 'parcial',
				'vehiculo'             => $parciales[0],
				'confianza'            => 0.7,
				'necesita_desambiguar' => false,
				'pregunta'             => null,
				'opciones'             => array(),
			);
		}

		if ( count( $parciales ) > 1 ) {
			return array(
				'metodo'               => 'parcial',
				'vehiculo'             => null,
				'confianza'            => 0.4,
				'necesita_desambiguar' => true,
				'pregunta'             => self::build_pregunta( $parciales ),
				'opciones'             => $parciales,
			);
		}

		// --- Nivel 3: IA ---
		if ( ! self::is_enabled() ) {
			return $vacio;
		}

		$cache_key = 'ai_' . md5( mb_strtolower( $texto ) );
		$cached    = ZAIR_Cache::get( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$provider = self::provider();
		$catalogo = self::get_catalogo();
		$salida   = $provider->interpretar_busqueda( $texto, $catalogo );

		if ( is_wp_error( $salida ) ) {
			self::log_error( $salida->get_error_message() );
			return $vacio;
		}

		$resultado = self::validar_salida_ia( $salida );

		// Caché de 12 h: las mismas búsquedas se repiten mucho.
		ZAIR_Cache::set( $cache_key, $resultado, 12 * HOUR_IN_SECONDS );

		return $resultado;
	}

	/**
	 * Valida la salida de la IA contra la base real.
	 * Si nombra un vehículo inexistente, se descarta por completo.
	 *
	 * @param array $salida Respuesta de la IA.
	 * @return array
	 */
	private static function validar_salida_ia( $salida ) {

		$base = array(
			'metodo'               => 'ia',
			'vehiculo'             => null,
			'confianza'            => isset( $salida['confianza'] ) ? (float) $salida['confianza'] : 0.0,
			'necesita_desambiguar' => ! empty( $salida['necesita_desambiguar'] ),
			'pregunta'             => isset( $salida['pregunta'] ) ? $salida['pregunta'] : null,
			'opciones'             => array(),
		);

		// Si la IA pide aclaración, se respeta: mejor preguntar que fallar.
		if ( $base['necesita_desambiguar'] ) {
			return $base;
		}

		$vehiculo = self::find_vehiculo(
			isset( $salida['marca'] ) ? $salida['marca'] : '',
			isset( $salida['modelo'] ) ? $salida['modelo'] : '',
			isset( $salida['cilindrada'] ) ? $salida['cilindrada'] : '',
			isset( $salida['codigo_motor'] ) ? $salida['codigo_motor'] : ''
		);

		// La IA nombró algo que no existe en la base: se descarta.
		if ( ! $vehiculo ) {
			$base['confianza'] = 0.0;
			return $base;
		}

		$base['vehiculo'] = $vehiculo;

		return $base;
	}

	/* ---------------------------------------------------------------------
	 * Resolución sin IA.
	 * ------------------------------------------------------------------ */

	/**
	 * Busca coincidencia exacta en la tabla de alias.
	 *
	 * @param string $texto Texto normalizado.
	 * @return object|null
	 */
	private static function match_alias( $texto ) {
		global $wpdb;

		$normalizado = mb_strtolower( trim( $texto ) );

		$t_alias   = ZAIR_Database::table( 'zair_aliases' );
		$t_vehicle = ZAIR_Database::table( 'zair_vehicle_engine' );

		return $wpdb->get_row(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT v.* FROM {$t_alias} a
				 INNER JOIN {$t_vehicle} v ON v.id = a.vehicle_engine_id
				 WHERE a.alias = %s AND a.estado = 1 AND v.estado = 1
				 ORDER BY a.confianza DESC LIMIT 1",
				$normalizado
			)
		);
	}

	/**
	 * Busca vehículos cuyo modelo o código de motor aparezca en el texto.
	 *
	 * @param string $texto Texto del usuario.
	 * @return array
	 */
	private static function match_parcial( $texto ) {

		$normalizado = ' ' . mb_strtolower( $texto ) . ' ';
		$catalogo    = self::get_catalogo();
		$encontrados = array();

		foreach ( $catalogo as $v ) {

			$modelo = mb_strtolower( $v->modelo );
			$motor  = mb_strtolower( $v->codigo_motor );

			$coincide = false;

			if ( $modelo && false !== mb_strpos( $normalizado, $modelo ) ) {
				$coincide = true;
			}
			if ( $motor && false !== mb_strpos( $normalizado, $motor ) ) {
				$coincide = true;
			}

			if ( ! $coincide ) {
				continue;
			}

			// Si además menciona la cilindrada, es una coincidencia única.
			if ( $v->cilindrada && false !== mb_strpos( $normalizado, mb_strtolower( $v->cilindrada ) ) ) {
				return array( $v );
			}

			$encontrados[] = $v;
		}

		return $encontrados;
	}

	/**
	 * Construye la pregunta de desambiguación a partir de los candidatos.
	 *
	 * @param array $opciones Vehículos candidatos.
	 * @return string
	 */
	private static function build_pregunta( $opciones ) {

		$etiquetas = array();

		foreach ( array_slice( $opciones, 0, 4 ) as $v ) {
			$etiquetas[] = trim( $v->modelo . ' ' . $v->version . ' ' . $v->cilindrada );
		}

		if ( count( $etiquetas ) < 2 ) {
			return '¿Nos confirmas la versión de tu vehículo?';
		}

		$ultimo = array_pop( $etiquetas );

		return '¿Tu vehículo es ' . implode( ', ', $etiquetas ) . ' o ' . $ultimo . '?';
	}

	/**
	 * Busca un vehículo concreto en la base.
	 *
	 * @param string $marca      Marca.
	 * @param string $modelo     Modelo.
	 * @param string $cilindrada Cilindrada.
	 * @param string $motor      Código de motor.
	 * @return object|null
	 */
	private static function find_vehiculo( $marca, $modelo, $cilindrada, $motor ) {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_vehicle_engine' );

		// El código de motor es el identificador más fiable.
		if ( $motor ) {
			$row = $wpdb->get_row(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
					"SELECT * FROM {$table} WHERE codigo_motor = %s AND estado = 1 LIMIT 1",
					$motor
				)
			);
			if ( $row ) {
				return $row;
			}
		}

		if ( ! $modelo ) {
			return null;
		}

		$sql    = "SELECT * FROM {$table} WHERE modelo = %s AND estado = 1 ";
		$params = array( $modelo );

		if ( $marca ) {
			$sql     .= ' AND marca = %s ';
			$params[] = $marca;
		}
		if ( $cilindrada ) {
			$sql     .= ' AND cilindrada = %s ';
			$params[] = $cilindrada;
		}

		$sql .= ' LIMIT 1';

		return $wpdb->get_row( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Catálogo de vehículos activos (cacheado).
	 *
	 * @return array
	 */
	public static function get_catalogo() {
		global $wpdb;

		$cached = ZAIR_Cache::get( 'catalogo_vehiculos' );
		if ( false !== $cached ) {
			return $cached;
		}

		$table = ZAIR_Database::table( 'zair_vehicle_engine' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE estado = 1 ORDER BY marca, modelo LIMIT 300" ); // phpcs:ignore WordPress.DB

		$rows = is_array( $rows ) ? $rows : array();

		ZAIR_Cache::set( 'catalogo_vehiculos', $rows, HOUR_IN_SECONDS );

		return $rows;
	}

	/**
	 * Guarda el último error de IA para mostrarlo en Configuración.
	 *
	 * @param string $mensaje Mensaje de error.
	 * @return void
	 */
	private static function log_error( $mensaje ) {
		update_option(
			'zair_ai_last_error',
			array(
				'mensaje' => $mensaje,
				'fecha'   => current_time( 'mysql' ),
			),
			false
		);
	}
}
