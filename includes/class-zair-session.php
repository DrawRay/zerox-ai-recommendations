<?php
/**
 * Identificador anónimo de sesión (Fase 7).
 *
 * Genera un código con formato ZX-R-A83F92 que permite enlazar
 * navegación → recomendación → clic → lead → carrito → compra.
 *
 * El identificador NO contiene ni deriva de nombre, WhatsApp, correo,
 * DNI ni dirección: es aleatorio. Se guarda en una cookie propia con
 * duración de 30 días, suficiente para atribuir una compra posterior
 * a la recomendación que la originó.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Session
 */
class ZAIR_Session {

	/**
	 * Nombre de la cookie.
	 */
	const COOKIE = 'zair_sid';

	/**
	 * Duración en segundos (30 días).
	 */
	const LIFETIME = 2592000;

	/**
	 * Longitud del sufijo hexadecimal.
	 */
	const SUFFIX_LEN = 6;

	/**
	 * Cache en memoria para la petición actual.
	 *
	 * @var string|null
	 */
	private static $current = null;

	/**
	 * Devuelve el id de sesión actual, creándolo si hace falta.
	 *
	 * @return string Formato ZX-R-XXXXXX.
	 */
	public static function get_id() {

		if ( null !== self::$current ) {
			return self::$current;
		}

		if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$candidate = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			if ( self::is_valid( $candidate ) ) {
				self::$current = $candidate;
				return self::$current;
			}
		}

		self::$current = self::generate();
		self::set_cookie( self::$current );

		return self::$current;
	}

	/**
	 * Valida el formato del identificador.
	 *
	 * @param string $id Identificador.
	 * @return bool
	 */
	public static function is_valid( $id ) {
		return (bool) preg_match( '/^ZX-R-[A-F0-9]{6}$/', (string) $id );
	}

	/**
	 * Genera un identificador aleatorio.
	 *
	 * @return string
	 */
	public static function generate() {
		return 'ZX-R-' . strtoupper( substr( bin2hex( random_bytes( 4 ) ), 0, self::SUFFIX_LEN ) );
	}

	/**
	 * Genera un código de lead (ZX-L-XXXXXX).
	 *
	 * @return string
	 */
	public static function generate_lead_code() {
		return 'ZX-L-' . strtoupper( substr( bin2hex( random_bytes( 4 ) ), 0, self::SUFFIX_LEN ) );
	}

	/**
	 * Escribe la cookie si aún no se enviaron las cabeceras.
	 *
	 * @param string $id Identificador.
	 * @return void
	 */
	private static function set_cookie( $id ) {

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			$id,
			array(
				'expires'  => time() + self::LIFETIME,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => false, // El JS del carrusel lo lee para enviar eventos.
				'samesite' => 'Lax',
			)
		);
	}
}
