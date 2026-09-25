<?php
/**
 * Gestión de leads (Fase 7).
 *
 * Los datos personales (nombre, WhatsApp) viven ÚNICAMENTE en esta tabla.
 * Nunca se envían a GA4 ni salen del servidor de ZEROXMOTORS.
 *
 * Se registra además la fecha y la versión del texto de consentimiento
 * aceptado, como respaldo frente a la Ley 29733 de Protección de Datos
 * Personales (Perú).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Leads
 */
class ZAIR_Leads {

	/**
	 * Estados posibles de un lead.
	 *
	 * @var array<string,string>
	 */
	const ESTADOS = array(
		'nuevo'         => 'Nuevo',
		'contactado'    => 'Contactado',
		'cotizacion'    => 'Cotización',
		'seguimiento'   => 'Seguimiento',
		'venta'         => 'Venta',
		'no_interesado' => 'No interesado',
	);

	/**
	 * Crea un lead a partir de datos ya validados.
	 *
	 * @param array $data Datos del formulario.
	 * @return array|WP_Error { lead_code }.
	 */
	public static function create( $data ) {
		global $wpdb;

		$nombre   = isset( $data['nombre'] ) ? sanitize_text_field( $data['nombre'] ) : '';
		$whatsapp = isset( $data['whatsapp'] ) ? self::sanitize_phone( $data['whatsapp'] ) : '';

		if ( mb_strlen( $nombre ) < 2 ) {
			return new WP_Error( 'zair_lead_nombre', 'Escribe tu nombre.' );
		}

		if ( mb_strlen( $whatsapp ) < 6 ) {
			return new WP_Error( 'zair_lead_whatsapp', 'Escribe un número de WhatsApp válido.' );
		}

		if ( empty( $data['consentimiento'] ) ) {
			return new WP_Error( 'zair_lead_consent', 'Necesitamos tu autorización para contactarte.' );
		}

		$session_id = isset( $data['session_id'] ) && ZAIR_Session::is_valid( $data['session_id'] )
			? $data['session_id']
			: ZAIR_Session::get_id();

		// Anti-flood: máximo 3 leads por sesión en 10 minutos.
		if ( self::recent_count( $session_id ) >= 3 ) {
			return new WP_Error( 'zair_lead_flood', 'Ya recibimos tu consulta. Te contactaremos en breve.' );
		}

		$settings = get_option( 'zair_settings', array() );
		$now      = current_time( 'mysql' );

		$row = array(
			'lead_code'              => ZAIR_Session::generate_lead_code(),
			'session_id'             => $session_id,
			'nombre'                 => mb_substr( $nombre, 0, 120 ),
			'whatsapp'               => mb_substr( $whatsapp, 0, 20 ),
			'product_origin_id'      => isset( $data['product_origin_id'] ) ? absint( $data['product_origin_id'] ) : null,
			'recommended_product_id' => isset( $data['recommended_product_id'] ) ? absint( $data['recommended_product_id'] ) : null,
			'vehicle_engine_id'      => isset( $data['vehicle_engine_id'] ) ? absint( $data['vehicle_engine_id'] ) : null,
			'estado'                 => 'nuevo',
			'venta_generada'         => 0,
			'consentimiento'         => 1,
			'consentimiento_fecha'   => $now,
			'consentimiento_texto'   => isset( $settings['consent_text_version'] ) ? $settings['consent_text_version'] : 'v1',
			'created_at'             => $now,
			'updated_at'             => $now,
		);

		$inserted = $wpdb->insert( ZAIR_Database::table( 'zair_leads' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( false === $inserted ) {
			return new WP_Error( 'zair_db', 'No se pudo registrar tu consulta. Inténtalo de nuevo.' );
		}

		$lead_id = (int) $wpdb->insert_id;

		/**
		 * Permite enganchar avisos (correo, webhook a n8n, WhatsApp API)
		 * cuando entra un lead nuevo.
		 *
		 * @param int   $lead_id Id del lead.
		 * @param array $row     Datos guardados.
		 */
		do_action( 'zair_lead_created', $lead_id, $row );

		return array(
			'lead_code' => $row['lead_code'],
			'lead_id'   => $lead_id,
		);
	}

	/**
	 * Normaliza un número de teléfono: deja dígitos y un + inicial.
	 *
	 * @param string $phone Número crudo.
	 * @return string
	 */
	public static function sanitize_phone( $phone ) {
		$phone = trim( (string) $phone );
		$plus  = ( 0 === strpos( $phone, '+' ) ) ? '+' : '';
		return $plus . preg_replace( '/\D/', '', $phone );
	}

	/**
	 * Cuenta leads recientes de una sesión (ventana de 10 minutos).
	 *
	 * @param string $session_id Id de sesión.
	 * @return int
	 */
	private static function recent_count( $session_id ) {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_leads' );

		return (int) $wpdb->get_var(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT COUNT(*) FROM {$table}
				 WHERE session_id = %s AND created_at > DATE_SUB( %s, INTERVAL 10 MINUTE )",
				$session_id,
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Listado de leads con filtros y paginación (panel admin).
	 *
	 * @param array $args Filtros: s, estado, paged, per_page.
	 * @return array{items:array, total:int, pages:int}
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$table    = ZAIR_Database::table( 'zair_leads' );
		$s        = isset( $args['s'] ) ? trim( (string) $args['s'] ) : '';
		$estado   = isset( $args['estado'] ) ? (string) $args['estado'] : '';
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 25;

		$where  = ' WHERE 1=1 ';
		$params = array();

		if ( '' !== $s ) {
			$like   = '%' . $wpdb->esc_like( $s ) . '%';
			$where .= ' AND ( nombre LIKE %s OR whatsapp LIKE %s OR lead_code LIKE %s ) ';
			array_push( $params, $like, $like, $like );
		}

		if ( '' !== $estado && isset( self::ESTADOS[ $estado ] ) ) {
			$where   .= ' AND estado = %s ';
			$params[] = $estado;
		}

		$sql_count = "SELECT COUNT(*) FROM {$table}" . $where;
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $sql_count, $params ) : $sql_count ); // phpcs:ignore WordPress.DB.PreparedSQL

		$offset  = ( $paged - 1 ) * $per_page;
		$sql     = "SELECT * FROM {$table}" . $where . ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$params2 = array_merge( $params, array( $per_page, $offset ) );
		$items   = $wpdb->get_results( $wpdb->prepare( $sql, $params2 ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Actualiza el estado de un lead.
	 *
	 * @param int    $id     Id del lead.
	 * @param string $estado Nuevo estado.
	 * @return bool
	 */
	public static function set_estado( $id, $estado ) {
		global $wpdb;

		if ( ! isset( self::ESTADOS[ $estado ] ) ) {
			return false;
		}

		$data = array(
			'estado'     => $estado,
			'updated_at' => current_time( 'mysql' ),
		);

		// Marcar venta también actualiza el flag de conversión.
		if ( 'venta' === $estado ) {
			$data['venta_generada'] = 1;
		}

		return false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ZAIR_Database::table( 'zair_leads' ),
			$data,
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Elimina un lead (derecho de supresión, Ley 29733).
	 *
	 * @param int $id Id del lead.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			ZAIR_Database::table( 'zair_leads' ),
			array( 'id' => absint( $id ) )
		);
	}

	/**
	 * Cuenta leads por estado (para el dashboard).
	 *
	 * @return array<string,int>
	 */
	public static function count_by_estado() {
		global $wpdb;

		$table = ZAIR_Database::table( 'zair_leads' );
		$rows  = $wpdb->get_results( "SELECT estado, COUNT(*) AS total FROM {$table} GROUP BY estado" ); // phpcs:ignore WordPress.DB

		$counts = array_fill_keys( array_keys( self::ESTADOS ), 0 );

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row->estado ] ) ) {
				$counts[ $row->estado ] = (int) $row->total;
			}
		}

		return $counts;
	}
}
