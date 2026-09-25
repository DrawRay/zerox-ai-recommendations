<?php
/**
 * Integración con Google Analytics 4 (Fase 10).
 *
 * GA4 recibe ÚNICAMENTE datos anónimos de comportamiento y producto.
 * Nunca nombre, WhatsApp, correo, DNI ni dirección: esa información
 * permanece exclusivamente en la base de ZEROXMOTORS.
 *
 * Como salvaguarda, cada payload pasa por un filtro que elimina cualquier
 * clave sensible antes de salir del servidor, incluso si un cambio futuro
 * la incorporara por error.
 *
 * Modo de envío:
 *   - Si el sitio ya tiene gtag() (Site Kit, GTM, código manual), se usa.
 *   - Si no, se puede indicar un Measurement ID en Configuración y el
 *     plugin carga gtag.js por su cuenta.
 *
 * Eventos emitidos:
 *   zair_rec_impression, zair_rec_click, zair_rec_add_cart,
 *   zair_rec_purchase, zair_lead_open, zair_lead_submit,
 *   zair_compatibility_query, zair_search_detected
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_GA4
 */
class ZAIR_GA4 {

	/**
	 * Claves prohibidas: jamás salen hacia GA4.
	 *
	 * @var string[]
	 */
	const CLAVES_PROHIBIDAS = array(
		'nombre',
		'name',
		'whatsapp',
		'telefono',
		'phone',
		'email',
		'correo',
		'dni',
		'documento',
		'direccion',
		'address',
		'ip',
		'user_id',
		'lead_code',
	);

	/**
	 * Correspondencia evento interno → nombre en GA4.
	 *
	 * @var array<string,string>
	 */
	const MAPA_EVENTOS = array(
		'impression'          => 'zair_rec_impression',
		'click'               => 'zair_rec_click',
		'add_cart'            => 'zair_rec_add_cart',
		'purchase'            => 'zair_rec_purchase',
		'lead_open'           => 'zair_lead_open',
		'lead_submit'         => 'zair_lead_submit',
		'compatibility_query' => 'zair_compatibility_query',
		'search_detected'     => 'zair_search_detected',
	);

	/**
	 * Registra los hooks.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'maybe_load_gtag' ), 5 );
	}

	/**
	 * Indica si la integración está activa.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = get_option( 'zair_settings', array() );
		return ! empty( $settings['ga4_enabled'] );
	}

	/**
	 * Measurement ID configurado (puede estar vacío si ya existe gtag).
	 *
	 * @return string
	 */
	public static function measurement_id() {
		$settings = get_option( 'zair_settings', array() );
		$id       = isset( $settings['ga4_measurement_id'] ) ? trim( $settings['ga4_measurement_id'] ) : '';
		return preg_match( '/^G-[A-Z0-9]{4,}$/i', $id ) ? strtoupper( $id ) : '';
	}

	/**
	 * Carga gtag.js solo si el usuario indicó un Measurement ID.
	 * Si el sitio ya tiene GA4 por Site Kit o GTM, no se duplica nada:
	 * el JS del plugin reutiliza el gtag existente.
	 *
	 * @return void
	 */
	public function maybe_load_gtag() {

		if ( ! self::is_enabled() ) {
			return;
		}

		$id = self::measurement_id();

		if ( ! $id ) {
			return;
		}

		?>
		<!-- Zerox AI Recommendations: GA4 -->
		<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $id ); ?>"></script>
		<script>
			window.dataLayer = window.dataLayer || [];
			function gtag(){ dataLayer.push( arguments ); }
			gtag( 'js', new Date() );
			gtag( 'config', '<?php echo esc_js( $id ); ?>' );
		</script>
		<?php
	}

	/**
	 * Limpia un payload eliminando cualquier dato personal.
	 *
	 * @param array $params Parámetros a enviar.
	 * @return array
	 */
	public static function sanitize_payload( $params ) {

		$limpio = array();

		foreach ( (array) $params as $clave => $valor ) {

			$clave_norm = strtolower( (string) $clave );

			if ( in_array( $clave_norm, self::CLAVES_PROHIBIDAS, true ) ) {
				continue;
			}

			// Solo escalares: nada de objetos con estructura imprevista.
			if ( is_array( $valor ) || is_object( $valor ) ) {
				continue;
			}

			$limpio[ sanitize_key( $clave ) ] = is_numeric( $valor )
				? $valor + 0
				: sanitize_text_field( (string) $valor );
		}

		return $limpio;
	}

	/**
	 * Configuración que se pasa al JS del frontend.
	 *
	 * @return array
	 */
	public static function js_config() {
		return array(
			'enabled' => self::is_enabled(),
			'eventos' => self::MAPA_EVENTOS,
		);
	}
}
