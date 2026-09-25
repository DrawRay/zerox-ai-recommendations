<?php
/**
 * Contrato para proveedores de IA (Fase 11).
 *
 * Cualquier proveedor se conecta implementando esta interfaz, SIEMPRE
 * desde PHP (server-side). La API key vive en wp-config.php o en la BD;
 * jamás se expone en JavaScript.
 *
 * LÍMITE INVIOLABLE: la IA solo INTERPRETA texto del usuario.
 * La compatibilidad la confirma exclusivamente zair_product_compatibility.
 * Ninguna respuesta de la IA puede añadir, alterar o saltarse una
 * compatibilidad verificada.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ZAIR_AI_Provider {

	/**
	 * Interpreta un texto de búsqueda y devuelve entidades detectadas.
	 *
	 * @param string $texto     Búsqueda del usuario ("busco cosas para mi n300").
	 * @param array  $contexto  Catálogo disponible para acotar la respuesta.
	 * @return array|WP_Error {
	 *     @type string|null $marca
	 *     @type string|null $modelo
	 *     @type string|null $cilindrada
	 *     @type string|null $codigo_motor
	 *     @type float       $confianza             0.0 a 1.0
	 *     @type bool        $necesita_desambiguar
	 *     @type string|null $pregunta
	 * }
	 */
	public function interpretar_busqueda( $texto, $contexto = array() );

	/**
	 * Genera una explicación breve de por qué se recomienda un producto.
	 *
	 * @param array $contexto Producto, vehículo y tipo de relación.
	 * @return string|WP_Error
	 */
	public function generar_explicacion( $contexto );

	/**
	 * Comprueba que las credenciales funcionan.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection();
}
