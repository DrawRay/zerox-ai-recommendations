<?php
/**
 * Proveedor de IA: Anthropic Claude (Fase 11).
 *
 * Toda la comunicación ocurre server-side con wp_remote_post().
 * La API key nunca llega al navegador.
 *
 * El prompt entrega a la IA el catálogo de vehículos disponibles y le
 * exige elegir SOLO entre esas opciones. Si el texto es ambiguo debe
 * pedir aclaración en lugar de adivinar: es preferible una pregunta a
 * una compatibilidad equivocada.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_AI_Claude
 */
class ZAIR_AI_Claude implements ZAIR_AI_Provider {

	/**
	 * Endpoint de la API.
	 */
	const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * Versión de la API.
	 */
	const API_VERSION = '2023-06-01';

	/**
	 * Modelo por defecto.
	 */
	const MODELO = 'claude-sonnet-4-5';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Clave de API.
	 */
	public function __construct( $api_key = '' ) {
		$this->api_key = $api_key ? $api_key : ZAIR_AI_Manager::get_api_key();
	}

	/**
	 * Interpreta una búsqueda en lenguaje natural.
	 *
	 * @param string $texto    Texto del usuario.
	 * @param array  $contexto Vehículos disponibles.
	 * @return array|WP_Error
	 */
	public function interpretar_busqueda( $texto, $contexto = array() ) {

		$catalogo = $this->format_catalogo( $contexto );

		$system = 'Eres un asistente de una tienda de repuestos automotrices en Perú. '
			. "Tu única tarea es identificar a qué vehículo/motor del catálogo se refiere el cliente.\n\n"
			. "CATÁLOGO DISPONIBLE (son las únicas opciones válidas):\n" . $catalogo . "\n\n"
			. "REGLAS ESTRICTAS:\n"
			. "1. Solo puedes elegir vehículos que estén en el catálogo. Nunca inventes marcas, modelos ni códigos de motor.\n"
			. "2. Si el texto coincide con varias opciones del catálogo, NO elijas arbitrariamente: marca necesita_desambiguar como true y formula una pregunta corta y clara al cliente.\n"
			. "3. Si no reconoces ningún vehículo del catálogo, devuelve todos los campos en null con confianza 0.\n"
			. "4. La confianza refleja qué tan seguro estás: 1.0 si el cliente nombró el código de motor exacto, 0.7 si nombró modelo y cilindrada, 0.4 si solo nombró el modelo y hay varias versiones.\n"
			. "5. Responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional, sin explicaciones y sin bloques de código markdown.\n\n"
			. 'Formato exacto: {"marca":string|null,"modelo":string|null,"cilindrada":string|null,"codigo_motor":string|null,"confianza":number,"necesita_desambiguar":boolean,"pregunta":string|null}';

		$respuesta = $this->request(
			array(
				'model'      => ZAIR_AI_Manager::get_model(),
				'max_tokens' => 400,
				'system'     => $system,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'El cliente escribió: "' . $texto . '"',
					),
				),
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			return $respuesta;
		}

		return $this->parse_json( $respuesta );
	}

	/**
	 * Genera una explicación de por qué un producto encaja.
	 *
	 * @param array $contexto Datos del producto y vehículo.
	 * @return string|WP_Error
	 */
	public function generar_explicacion( $contexto ) {

		$producto = isset( $contexto['producto'] ) ? $contexto['producto'] : '';
		$vehiculo = isset( $contexto['vehiculo'] ) ? $contexto['vehiculo'] : '';
		$tipo     = isset( $contexto['tipo_relacion'] ) ? $contexto['tipo_relacion'] : '';

		$system = 'Eres un asesor de repuestos automotrices en Perú. '
			. 'Explica en UNA sola frase de máximo 18 palabras por qué este repuesto le sirve al cliente. '
			. 'Habla claro y directo, sin tecnicismos innecesarios y sin exagerar. '
			. 'No prometas compatibilidad tú mismo: la compatibilidad ya fue verificada por el taller. '
			. 'Responde solo con la frase, sin comillas ni preámbulos.';

		$respuesta = $this->request(
			array(
				'model'      => ZAIR_AI_Manager::get_model(),
				'max_tokens' => 120,
				'system'     => $system,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => sprintf(
							'Repuesto: %s. Vehículo: %s. Categoría: %s.',
							$producto,
							$vehiculo,
							$tipo
						),
					),
				),
			)
		);

		if ( is_wp_error( $respuesta ) ) {
			return $respuesta;
		}

		return trim( wp_strip_all_tags( $respuesta ) );
	}

	/**
	 * Verifica que la API responde correctamente.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {

		$respuesta = $this->request(
			array(
				'model'      => ZAIR_AI_Manager::get_model(),
				'max_tokens' => 20,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => 'Responde solo: OK',
					),
				),
			)
		);

		return is_wp_error( $respuesta ) ? $respuesta : true;
	}

	/* ---------------------------------------------------------------------
	 * Internos.
	 * ------------------------------------------------------------------ */

	/**
	 * Ejecuta la petición HTTP a la API.
	 *
	 * @param array $body Cuerpo de la petición.
	 * @return string|WP_Error Texto de la respuesta.
	 */
	private function request( $body ) {

		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'zair_ai_no_key', 'Falta configurar la API key de IA.' );
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'zair_ai_http', 'No se pudo conectar con el proveedor de IA: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $code ) {
			$detalle = '';
			$json    = json_decode( $raw, true );
			if ( isset( $json['error']['message'] ) ) {
				$detalle = ' — ' . $json['error']['message'];
			}
			return new WP_Error( 'zair_ai_status', 'La API respondió con código ' . $code . $detalle );
		}

		$json = json_decode( $raw, true );

		if ( empty( $json['content'] ) || ! is_array( $json['content'] ) ) {
			return new WP_Error( 'zair_ai_empty', 'La IA devolvió una respuesta vacía.' );
		}

		$texto = '';
		foreach ( $json['content'] as $bloque ) {
			if ( isset( $bloque['type'], $bloque['text'] ) && 'text' === $bloque['type'] ) {
				$texto .= $bloque['text'];
			}
		}

		return $texto;
	}

	/**
	 * Convierte el catálogo en texto para el prompt.
	 *
	 * @param array $vehiculos Filas de vehículos.
	 * @return string
	 */
	private function format_catalogo( $vehiculos ) {

		if ( empty( $vehiculos ) ) {
			return '(catálogo vacío)';
		}

		$lineas = array();

		foreach ( $vehiculos as $v ) {
			$lineas[] = sprintf(
				'- Marca: %s | Modelo: %s | Versión: %s | Cilindrada: %s | Motor: %s',
				$v->marca,
				$v->modelo,
				$v->version ? $v->version : '(única)',
				$v->cilindrada ? $v->cilindrada : '(sin dato)',
				$v->codigo_motor ? $v->codigo_motor : '(sin dato)'
			);
		}

		return implode( "\n", $lineas );
	}

	/**
	 * Extrae y valida el JSON de la respuesta.
	 *
	 * @param string $texto Respuesta de la IA.
	 * @return array|WP_Error
	 */
	private function parse_json( $texto ) {

		// La IA puede envolver el JSON en ```json a pesar de la instrucción.
		$limpio = trim( preg_replace( '/^```(?:json)?|```$/mi', '', trim( $texto ) ) );

		$datos = json_decode( $limpio, true );

		if ( ! is_array( $datos ) ) {
			return new WP_Error( 'zair_ai_json', 'No se pudo interpretar la respuesta de la IA.' );
		}

		return array(
			'marca'                => isset( $datos['marca'] ) ? sanitize_text_field( (string) $datos['marca'] ) : null,
			'modelo'               => isset( $datos['modelo'] ) ? sanitize_text_field( (string) $datos['modelo'] ) : null,
			'cilindrada'           => isset( $datos['cilindrada'] ) ? sanitize_text_field( (string) $datos['cilindrada'] ) : null,
			'codigo_motor'         => isset( $datos['codigo_motor'] ) ? strtoupper( sanitize_text_field( (string) $datos['codigo_motor'] ) ) : null,
			'confianza'            => isset( $datos['confianza'] ) ? min( 1, max( 0, (float) $datos['confianza'] ) ) : 0.0,
			'necesita_desambiguar' => ! empty( $datos['necesita_desambiguar'] ),
			'pregunta'             => isset( $datos['pregunta'] ) ? sanitize_text_field( (string) $datos['pregunta'] ) : null,
		);
	}
}
