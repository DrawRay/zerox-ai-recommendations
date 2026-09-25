<?php
/**
 * Panel administrativo.
 *
 * Fase 2: menú ZEROX AI, Dashboard de estado, Configuración.
 * Fase 3: módulo Motores/Vehículos (CRUD completo con búsqueda,
 *         filtros, paginación, activar/desactivar y borrado protegido).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Admin
 */
class ZAIR_Admin {

	/**
	 * Capacidad requerida para todo el panel.
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * Constructor: registra hooks del admin.
	 */
	public function __construct() {
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-vehicles.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-compatibility.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-aliases.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-cache.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-ranking.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-recommender.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-session.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-leads.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-events.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-analytics.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-ga4.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/ai/interface-zair-ai-provider.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/ai/class-zair-ai-claude.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/ai/class-zair-ai-manager.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-maintenance.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-autodetect.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-export.php';
		require_once ZAIR_PLUGIN_DIR . 'includes/class-zair-woocommerce.php';
		require_once ZAIR_PLUGIN_DIR . 'admin/class-zair-admin-ajax.php';

		new ZAIR_Admin_Ajax();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_init', array( $this, 'handle_vehicle_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_compat_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_alias_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_lead_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_ai_test' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
		add_action( 'admin_notices', array( $this, 'flash_notices' ) );
	}

	/**
	 * Menú principal y submenús.
	 *
	 * @return void
	 */
	public function register_menu() {

		add_menu_page(
			'ZEROX AI',
			'ZEROX AI',
			self::CAPABILITY,
			'zair-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-car',
			56
		);

		$submenus = array(
			array( 'Dashboard', 'zair-dashboard', 'render_dashboard' ),
			array( 'Motores / Vehículos', 'zair-vehicles', 'render_vehicles' ),
			array( 'Compatibilidades', 'zair-compatibility', 'render_compatibility' ),
			array( 'Autodetección', 'zair-autodetect', 'render_autodetect' ),
			array( 'Alias de búsqueda', 'zair-aliases', 'render_aliases' ),
			array( 'Recomendaciones', 'zair-recommendations', 'render_recommendations' ),
			array( 'Leads', 'zair-leads', 'render_leads' ),
			array( 'Analítica', 'zair-analytics', 'render_analytics' ),
			array( 'Configuración', 'zair-settings', 'render_settings' ),
		);

		foreach ( $submenus as $submenu ) {
			add_submenu_page(
				'zair-dashboard',
				$submenu[0],
				$submenu[0],
				self::CAPABILITY,
				$submenu[1],
				array( $this, $submenu[2] )
			);
		}
	}

	/**
	 * CSS del admin, solo en pantallas del plugin.
	 *
	 * @param string $hook Hook de la pantalla actual.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'zair-' ) ) {
			return;
		}
		wp_enqueue_style(
			'zair-admin',
			ZAIR_PLUGIN_URL . 'admin/css/zair-admin.css',
			array(),
			ZAIR_VERSION
		);

		// El buscador AJAX solo se necesita en el formulario de compatibilidades.
		if ( false !== strpos( $hook, 'zair-compatibility' ) ) {
			wp_enqueue_script(
				'zair-admin',
				ZAIR_PLUGIN_URL . 'admin/js/zair-admin.js',
				array(),
				ZAIR_VERSION,
				true
			);
			wp_localize_script(
				'zair-admin',
				'zairAdminData',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'zair_admin_ajax' ),
				)
			);
		}
	}

	/**
	 * Aviso de bienvenida tras la activación.
	 *
	 * @return void
	 */
	public function activation_notice() {
		if ( ! get_transient( 'zair_just_activated' ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		delete_transient( 'zair_just_activated' );
		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>Zerox AI Recommendations</strong> activado. <a href="%s">Ver estado del sistema</a>.</p></div>',
			esc_url( admin_url( 'admin.php?page=zair-dashboard' ) )
		);
	}

	/**
	 * Notificaciones tras redirecciones (patrón PRG).
	 *
	 * @return void
	 */
	public function flash_notices() {
		if ( empty( $_GET['zair_notice'] ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$key = sanitize_key( wp_unslash( $_GET['zair_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$map = array(
			'vehicle_saved'    => array( 'success', 'Vehículo/motor guardado correctamente.' ),
			'vehicle_deleted'  => array( 'success', 'Vehículo/motor eliminado.' ),
			'vehicle_enabled'  => array( 'success', 'Vehículo/motor activado.' ),
			'vehicle_disabled' => array( 'success', 'Vehículo/motor desactivado.' ),
			'vehicle_dup'      => array( 'error', 'Ya existe un vehículo/motor con esa misma combinación (marca, modelo, versión, cilindrada y código de motor).' ),
			'vehicle_required' => array( 'error', 'Marca y Modelo son obligatorios.' ),
			'vehicle_in_use'   => array( 'error', 'No se puede eliminar: tiene compatibilidades asociadas. Desactívalo en su lugar.' ),
			'vehicle_error'    => array( 'error', 'Ocurrió un error al procesar la operación.' ),
			'compat_saved'     => array( 'success', 'Compatibilidad guardada correctamente.' ),
			'compat_deleted'   => array( 'success', 'Compatibilidad eliminada.' ),
			'compat_updated'   => array( 'success', 'Compatibilidad actualizada.' ),
			'compat_dup'       => array( 'error', 'Ese producto ya está relacionado con ese vehículo/motor. Edita la relación existente.' ),
			'compat_required'  => array( 'error', 'Debes seleccionar un vehículo/motor y un producto de WooCommerce.' ),
			'compat_error'     => array( 'error', 'No se pudo guardar la compatibilidad.' ),
			'alias_saved'      => array( 'success', 'Alias añadido.' ),
			'alias_deleted'    => array( 'success', 'Alias eliminado.' ),
			'alias_updated'    => array( 'success', 'Alias actualizado.' ),
			'alias_dup'        => array( 'error', 'Ese alias ya existe para ese vehículo/motor.' ),
			'alias_error'      => array( 'error', 'No se pudo guardar el alias.' ),
			'lead_updated'     => array( 'success', 'Estado del lead actualizado.' ),
			'lead_error'       => array( 'error', 'No se pudo actualizar el lead.' ),
		);

		if ( isset( $map[ $key ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $map[ $key ][0] ),
				esc_html( $map[ $key ][1] )
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Acciones del módulo Motores/Vehículos (Fase 3).
	 * ------------------------------------------------------------------ */

	/**
	 * Procesa alta/edición (POST) y activar/desactivar/eliminar (GET),
	 * siempre con nonce + capability, y redirige (patrón PRG).
	 *
	 * @return void
	 */
	public function handle_vehicle_actions() {

		$base_url = admin_url( 'admin.php?page=zair-vehicles' );

		// --- Guardar (POST) ---
		if ( isset( $_POST['zair_vehicle_submit'] ) ) {

			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
			}
			check_admin_referer( 'zair_save_vehicle', 'zair_vehicle_nonce' );

			$id   = isset( $_POST['vehicle_id'] ) ? absint( $_POST['vehicle_id'] ) : 0;
			$data = ZAIR_Vehicles::sanitize( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( is_wp_error( $data ) ) {
				wp_safe_redirect( add_query_arg( 'zair_notice', 'vehicle_required', $base_url ) );
				exit;
			}

			$result = ZAIR_Vehicles::save( $data, $id );

			if ( is_wp_error( $result ) ) {
				$notice = ( 'zair_duplicate' === $result->get_error_code() ) ? 'vehicle_dup' : 'vehicle_error';
				wp_safe_redirect( add_query_arg( 'zair_notice', $notice, $base_url ) );
				exit;
			}

			wp_safe_redirect( add_query_arg( 'zair_notice', 'vehicle_saved', $base_url ) );
			exit;
		}

		// --- Acciones por GET (toggle / delete) ---
		if ( ! isset( $_GET['page'], $_GET['zair_action'], $_GET['vehicle_id'] ) ) {
			return;
		}
		if ( 'zair-vehicles' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
		}

		$action = sanitize_key( wp_unslash( $_GET['zair_action'] ) );
		$id     = absint( $_GET['vehicle_id'] );

		check_admin_referer( 'zair_vehicle_' . $action . '_' . $id );

		switch ( $action ) {
			case 'enable':
				ZAIR_Vehicles::set_estado( $id, 1 );
				$notice = 'vehicle_enabled';
				break;

			case 'disable':
				ZAIR_Vehicles::set_estado( $id, 0 );
				$notice = 'vehicle_disabled';
				break;

			case 'delete':
				$result = ZAIR_Vehicles::delete( $id );
				$notice = is_wp_error( $result ) ? 'vehicle_in_use' : 'vehicle_deleted';
				break;

			default:
				$notice = 'vehicle_error';
		}

		wp_safe_redirect( add_query_arg( 'zair_notice', $notice, $base_url ) );
		exit;
	}


	/* ---------------------------------------------------------------------
	 * Acciones del módulo Compatibilidades (Fase 4).
	 * ------------------------------------------------------------------ */

	/**
	 * Procesa alta/edición (POST) y verificar/activar/eliminar (GET).
	 *
	 * @return void
	 */
	public function handle_compat_actions() {

		$base_url = admin_url( 'admin.php?page=zair-compatibility' );

		// --- Guardar (POST) ---
		if ( isset( $_POST['zair_compat_submit'] ) ) {

			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
			}
			check_admin_referer( 'zair_save_compat', 'zair_compat_nonce' );

			$id   = isset( $_POST['compat_id'] ) ? absint( $_POST['compat_id'] ) : 0;
			$data = ZAIR_Compatibility::sanitize( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( is_wp_error( $data ) ) {
				wp_safe_redirect( add_query_arg( 'zair_notice', 'compat_required', $base_url ) );
				exit;
			}

			$result = ZAIR_Compatibility::save( $data, $id );

			if ( is_wp_error( $result ) ) {
				$notice = ( 'zair_duplicate' === $result->get_error_code() ) ? 'compat_dup' : 'compat_error';
				wp_safe_redirect( add_query_arg( 'zair_notice', $notice, $base_url ) );
				exit;
			}

			wp_safe_redirect( add_query_arg( 'zair_notice', 'compat_saved', $base_url ) );
			exit;
		}

		// --- Acciones por GET ---
		if ( ! isset( $_GET['page'], $_GET['zair_action'], $_GET['compat_id'] ) ) {
			return;
		}
		if ( 'zair-compatibility' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
		}

		$action = sanitize_key( wp_unslash( $_GET['zair_action'] ) );
		$id     = absint( $_GET['compat_id'] );

		check_admin_referer( 'zair_compat_' . $action . '_' . $id );

		switch ( $action ) {
			case 'verify':
				ZAIR_Compatibility::set_verificada( $id, 1 );
				$notice = 'compat_updated';
				break;

			case 'unverify':
				ZAIR_Compatibility::set_verificada( $id, 0 );
				$notice = 'compat_updated';
				break;

			case 'enable':
				ZAIR_Compatibility::set_estado( $id, 1 );
				$notice = 'compat_updated';
				break;

			case 'disable':
				ZAIR_Compatibility::set_estado( $id, 0 );
				$notice = 'compat_updated';
				break;

			case 'delete':
				ZAIR_Compatibility::delete( $id );
				$notice = 'compat_deleted';
				break;

			default:
				$notice = 'compat_error';
		}

		wp_safe_redirect( add_query_arg( 'zair_notice', $notice, $base_url ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Acciones del módulo Alias (Fase 4).
	 * ------------------------------------------------------------------ */

	/**
	 * Procesa alta (POST) y activar/desactivar/eliminar (GET) de alias.
	 *
	 * @return void
	 */
	public function handle_alias_actions() {

		$base_url = admin_url( 'admin.php?page=zair-aliases' );

		// --- Crear (POST) ---
		if ( isset( $_POST['zair_alias_submit'] ) ) {

			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
			}
			check_admin_referer( 'zair_save_alias', 'zair_alias_nonce' );

			$alias   = isset( $_POST['alias'] ) ? sanitize_text_field( wp_unslash( $_POST['alias'] ) ) : '';
			$vehicle = isset( $_POST['vehicle_engine_id'] ) ? absint( $_POST['vehicle_engine_id'] ) : 0;

			$result = ZAIR_Aliases::create( $alias, $vehicle );

			if ( is_wp_error( $result ) ) {
				$notice = ( 'zair_alias_dup' === $result->get_error_code() ) ? 'alias_dup' : 'alias_error';
			} else {
				$notice = 'alias_saved';
			}

			wp_safe_redirect( add_query_arg( 'zair_notice', $notice, $base_url ) );
			exit;
		}

		// --- Acciones por GET ---
		if ( ! isset( $_GET['page'], $_GET['zair_action'], $_GET['alias_id'] ) ) {
			return;
		}
		if ( 'zair-aliases' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
		}

		$action = sanitize_key( wp_unslash( $_GET['zair_action'] ) );
		$id     = absint( $_GET['alias_id'] );

		check_admin_referer( 'zair_alias_' . $action . '_' . $id );

		switch ( $action ) {
			case 'enable':
				ZAIR_Aliases::set_estado( $id, 1 );
				$notice = 'alias_updated';
				break;

			case 'disable':
				ZAIR_Aliases::set_estado( $id, 0 );
				$notice = 'alias_updated';
				break;

			case 'delete':
				ZAIR_Aliases::delete( $id );
				$notice = 'alias_deleted';
				break;

			default:
				$notice = 'alias_error';
		}

		wp_safe_redirect( add_query_arg( 'zair_notice', $notice, $base_url ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Acciones del módulo Leads (Fase 7).
	 * ------------------------------------------------------------------ */

	/**
	 * Actualiza el estado de un lead desde la bandeja.
	 *
	 * @return void
	 */
	public function handle_lead_actions() {

		if ( ! isset( $_POST['zair_lead_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
		}

		check_admin_referer( 'zair_lead_estado', 'zair_lead_nonce' );

		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		$estado  = isset( $_POST['estado'] ) ? sanitize_key( wp_unslash( $_POST['estado'] ) ) : '';

		$ok     = ZAIR_Leads::set_estado( $lead_id, $estado );
		$notice = $ok ? 'lead_updated' : 'lead_error';

		wp_safe_redirect( add_query_arg( 'zair_notice', $notice, admin_url( 'admin.php?page=zair-leads' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Prueba de conexión con la IA (Fase 11).
	 * ------------------------------------------------------------------ */

	/**
	 * Ejecuta una interpretación de prueba y guarda el resultado.
	 *
	 * @return void
	 */
	public function handle_ai_test() {

		if ( ! isset( $_POST['zair_ai_test'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
		}

		check_admin_referer( 'zair_ai_test', 'zair_ai_test_nonce' );

		$texto     = isset( $_POST['zair_ai_texto'] ) ? sanitize_text_field( wp_unslash( $_POST['zair_ai_texto'] ) ) : '';
		$resultado = ZAIR_AI_Manager::interpretar( $texto );

		$salida = array(
			'texto'  => $texto,
			'metodo' => $resultado['metodo'],
			'fecha'  => current_time( 'mysql' ),
		);

		if ( $resultado['necesita_desambiguar'] ) {
			$salida['estado']   = 'ambiguo';
			$salida['mensaje']  = $resultado['pregunta'];
		} elseif ( ! empty( $resultado['vehiculo'] ) ) {
			$v                 = $resultado['vehiculo'];
			$salida['estado']  = 'ok';
			$salida['mensaje'] = trim( $v->marca . ' ' . $v->modelo . ' ' . $v->version . ' ' . $v->cilindrada )
				. ( $v->codigo_motor ? ' · ' . $v->codigo_motor : '' );
			$salida['confianza'] = $resultado['confianza'];
		} else {
			$salida['estado']  = 'nada';
			$salida['mensaje'] = 'No se reconoció ningún vehículo del catálogo.';
		}

		set_transient( 'zair_ai_test_result', $salida, 120 );

		wp_safe_redirect( admin_url( 'admin.php?page=zair-settings#zair-ia' ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Exportación CSV (Fase 9).
	 * ------------------------------------------------------------------ */

	/**
	 * Atiende las descargas de CSV. Debe correr antes de imprimir HTML,
	 * porque envía cabeceras de descarga y termina la ejecución.
	 *
	 * @return void
	 */
	public function handle_export() {

		if ( ! isset( $_GET['zair_export'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para exportar datos.', 'zerox-ai-recommendations' ) );
		}

		$tipo = sanitize_key( wp_unslash( $_GET['zair_export'] ) );

		check_admin_referer( 'zair_export_' . $tipo );

		$rango = isset( $_GET['rango'] ) ? sanitize_key( wp_unslash( $_GET['rango'] ) ) : '30';
		$range = ZAIR_Analytics::resolve_range( $rango );

		switch ( $tipo ) {
			case 'leads':
				ZAIR_Export::leads(
					array(
						's'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
						'estado' => isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : '',
					)
				);
				break;

			case 'events':
				ZAIR_Export::events( $range['desde'], $range['hasta'] );
				break;

			case 'summary':
				ZAIR_Export::summary( $range['desde'], $range['hasta'], $range['etiqueta'] );
				break;
		}
	}

	/* ---------------------------------------------------------------------
	 * Pantallas.
	 * ------------------------------------------------------------------ */

	/**
	 * Dashboard de estado del sistema.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		$db_status = ZAIR_Database::get_status();
		require ZAIR_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Módulo Motores/Vehículos: enruta entre listado y formulario.
	 *
	 * @return void
	 */
	public function render_vehicles() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- solo lectura de vista.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list';

		if ( 'edit' === $view || 'new' === $view ) {
			$vehicle = null;
			if ( 'edit' === $view && isset( $_GET['vehicle_id'] ) ) {
				$vehicle = ZAIR_Vehicles::get( absint( $_GET['vehicle_id'] ) );
			}
			require ZAIR_PLUGIN_DIR . 'admin/views/vehicles-form.php';
			return;
		}

		$args = array(
			's'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'estado' => isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : '',
			'paged'  => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
		);
		// phpcs:enable

		$result = ZAIR_Vehicles::query( $args );

		require ZAIR_PLUGIN_DIR . 'admin/views/vehicles-list.php';
	}

	/**
	 * Módulo Compatibilidades: enruta entre listado y formulario.
	 *
	 * @return void
	 */
	public function render_compatibility() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$view     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'list';
		$vehicles = ZAIR_Vehicles::query(
			array(
				'estado'   => '1',
				'per_page' => 500,
			)
		);
		$vehicles = $vehicles['items'];

		if ( 'edit' === $view || 'new' === $view ) {
			$compat = null;
			if ( 'edit' === $view && isset( $_GET['compat_id'] ) ) {
				$compat = ZAIR_Compatibility::get( absint( $_GET['compat_id'] ) );
			}
			require ZAIR_PLUGIN_DIR . 'admin/views/compatibility-form.php';
			return;
		}

		$args = array(
			'vehicle_engine_id' => isset( $_GET['vehicle_engine_id'] ) ? absint( $_GET['vehicle_engine_id'] ) : 0,
			's'                 => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'tipo'              => isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( $_GET['tipo'] ) ) : '',
			'verificada'        => isset( $_GET['verificada'] ) ? sanitize_key( wp_unslash( $_GET['verificada'] ) ) : '',
			'paged'             => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
		);
		// phpcs:enable

		$result = ZAIR_Compatibility::query( $args );

		require ZAIR_PLUGIN_DIR . 'admin/views/compatibility-list.php';
	}

	/**
	 * Módulo Alias de búsqueda.
	 *
	 * @return void
	 */
	public function render_aliases() {

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$vehicles = ZAIR_Vehicles::query(
			array(
				'estado'   => '1',
				'per_page' => 500,
			)
		);
		$vehicles = $vehicles['items'];

		$aliases = ZAIR_Aliases::get_all( $search );

		require ZAIR_PLUGIN_DIR . 'admin/views/aliases.php';
	}

	/**
	 * Simulador del motor de recomendaciones (Fase 5).
	 *
	 * @return void
	 */
	public function render_recommendations() {

		$test_id      = isset( $_GET['test_id'] ) ? absint( $_GET['test_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$test_product = $test_id ? wc_get_product( $test_id ) : null;
		$result       = null;
		$weights      = ZAIR_Ranking::get_weights();

		if ( $test_product ) {
			$result = ZAIR_Recommender::get_recommendations(
				$test_id,
				array(
					'limit'         => 12,
					'with_desglose' => true,
				)
			);
		}

		require ZAIR_PLUGIN_DIR . 'admin/views/recommendations.php';
	}

	/**
	 * Bandeja de Leads (Fase 7).
	 *
	 * @return void
	 */
	public function render_leads() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$args = array(
			's'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'estado' => isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : '',
			'paged'  => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
		);
		// phpcs:enable

		$result = ZAIR_Leads::query( $args );
		$counts = ZAIR_Leads::count_by_estado();

		require ZAIR_PLUGIN_DIR . 'admin/views/leads.php';
	}

	/**
	 * Autodetección de compatibilidades por código de motor.
	 *
	 * @return void
	 */
	public function render_autodetect() {

		$resumen = null;

		if ( isset( $_POST['zair_autodetect_submit'] ) ) {

			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
			}

			check_admin_referer( 'zair_autodetect', 'zair_autodetect_nonce' );

			$codigos = isset( $_POST['zair_codigos'] ) && is_array( $_POST['zair_codigos'] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['zair_codigos'] ) )
				: array();

			$verificar = ! empty( $_POST['zair_verificar'] );

			if ( ! empty( $codigos ) ) {
				$resumen = ZAIR_Autodetect::apply( $codigos, $verificar );
			}
		}

		$grupos = ZAIR_Autodetect::scan();

		require ZAIR_PLUGIN_DIR . 'admin/views/autodetect.php';
	}

	/**
	 * Analítica del embudo (Fase 9).
	 *
	 * @return void
	 */
	public function render_analytics() {

		$rango = isset( $_GET['rango'] ) ? sanitize_key( wp_unslash( $_GET['rango'] ) ) : '30'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$range = ZAIR_Analytics::resolve_range( $rango );

		$report       = ZAIR_Analytics::get_report( $range['desde'], $range['hasta'] );
		$precision    = ZAIR_Analytics::compatibility_precision();
		$series       = ZAIR_Analytics::daily_series( $range['desde'], $range['hasta'] );
		$top          = ZAIR_Events::top_products( 10, $range['desde'], $range['hasta'] );
		$vendedores   = ZAIR_Analytics::sellers_stats( $range['desde'], $range['hasta'] );
		$cotizaciones = ZAIR_Analytics::quotes_stats( $range['desde'], $range['hasta'] );

		require ZAIR_PLUGIN_DIR . 'admin/views/analytics.php';
	}

	/**
	 * Pantalla de configuración.
	 *
	 * @return void
	 */
	public function render_settings() {
		$settings = get_option( 'zair_settings', array() );
		require ZAIR_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Marcador para pantallas de fases futuras.
	 *
	 * @return void
	 */
	public function render_placeholder() {
		$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$phases = array(
		);
		$info   = isset( $phases[ $page ] ) ? $phases[ $page ] : array( 'Módulo', 'PRÓXIMA FASE', '' );
		require ZAIR_PLUGIN_DIR . 'admin/views/placeholder.php';
	}

	/* ---------------------------------------------------------------------
	 * Guardado de configuración (con nonce + capability + sanitización).
	 * ------------------------------------------------------------------ */

	/**
	 * Procesa el formulario de Configuración.
	 *
	 * @return void
	 */
	public function handle_settings_save() {

		if ( ! isset( $_POST['zair_settings_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tienes permisos para esta acción.', 'zerox-ai-recommendations' ) );
		}

		check_admin_referer( 'zair_save_settings', 'zair_settings_nonce' );

		$settings = get_option( 'zair_settings', array() );

		$settings['delete_data_on_uninstall'] = isset( $_POST['zair_delete_data'] ) ? 1 : 0;
		$settings['max_recommendations']      = isset( $_POST['zair_max_recs'] )
			? min( 12, max( 1, absint( $_POST['zair_max_recs'] ) ) )
			: 4;
		$settings['section_title']            = isset( $_POST['zair_section_title'] )
			? sanitize_text_field( wp_unslash( $_POST['zair_section_title'] ) )
			: 'Complementa tu motor';
		$settings['section_subtitle']         = isset( $_POST['zair_section_subtitle'] )
			? sanitize_text_field( wp_unslash( $_POST['zair_section_subtitle'] ) )
			: '';
		$settings['cache_disabled']           = isset( $_POST['zair_cache_disabled'] ) ? 1 : 0;
		$settings['auto_insert']              = isset( $_POST['zair_auto_insert'] ) ? 1 : 0;
		$settings['show_buy']                 = isset( $_POST['zair_show_buy'] ) ? 1 : 0;
		$settings['show_quote']               = isset( $_POST['zair_show_quote'] ) ? 1 : 0;
		$settings['show_lead_form']           = isset( $_POST['zair_show_lead'] ) ? 1 : 0;
		$settings['show_subtitle']            = isset( $_POST['zair_show_subtitle'] ) ? 1 : 0;
		$settings['show_vehicle']             = isset( $_POST['zair_show_vehicle'] ) ? 1 : 0;
		$settings['show_badge']               = isset( $_POST['zair_show_badge'] ) ? 1 : 0;
		$settings['show_title_icon']          = isset( $_POST['zair_show_title_icon'] ) ? 1 : 0;
		$settings['quote_bar_button']         = isset( $_POST['zair_quote_bar_button'] ) ? 1 : 0;
		$settings['show_quote_bar']           = isset( $_POST['zair_show_quote_bar'] ) ? 1 : 0;
		$settings['show_more_button']         = isset( $_POST['zair_show_more_button'] ) ? 1 : 0;
		$settings['txt_more']                 = isset( $_POST['zair_txt_more'] )
			? sanitize_text_field( wp_unslash( $_POST['zair_txt_more'] ) )
			: 'Ver más';
		$settings['layout']                   = ( isset( $_POST['zair_layout'] ) && 'combo' === $_POST['zair_layout'] )
			? 'combo'
			: 'carrusel';
		$settings['quote_button_text']        = isset( $_POST['zair_quote_text'] )
			? sanitize_text_field( wp_unslash( $_POST['zair_quote_text'] ) )
			: 'COTIZAR SELECCIONADOS';
		$settings['ga4_enabled']              = isset( $_POST['zair_ga4_enabled'] ) ? 1 : 0;
		$settings['ga4_measurement_id']       = isset( $_POST['zair_ga4_id'] )
			? sanitize_text_field( wp_unslash( $_POST['zair_ga4_id'] ) )
			: '';

		// --- IA (Fase 11) ---
		$settings['events_retention_months'] = isset( $_POST['zair_retention'] )
			? min( 60, absint( $_POST['zair_retention'] ) )
			: 0;

		$settings['ai_enabled']  = isset( $_POST['zair_ai_enabled'] ) ? 1 : 0;
		$settings['ai_provider'] = 'claude';
		$settings['events_retention_months'] = isset( $_POST['zair_retention'] )
			? min( 60, absint( $_POST['zair_retention'] ) )
			: 0;
		$settings['ai_model']    = isset( $_POST['zair_ai_model'] )
			? sanitize_text_field( wp_unslash( $_POST['zair_ai_model'] ) )
			: '';

		// La API key solo se sobrescribe si el campo trae algo nuevo:
		// el formulario muestra un marcador, nunca la clave real.
		if ( isset( $_POST['zair_ai_key'] ) ) {
			$nueva_key = trim( sanitize_text_field( wp_unslash( $_POST['zair_ai_key'] ) ) );
			if ( '' !== $nueva_key && 0 !== strpos( $nueva_key, '•' ) ) {
				$settings['ai_api_key'] = $nueva_key;
			}
		}

		// Pesos del ranking (se normalizan a 100 al calcular el score).
		$weights = array();
		foreach ( array_keys( ZAIR_Ranking::DEFAULT_WEIGHTS ) as $weight_key ) {
			$field                 = 'zair_w_' . $weight_key;
			$weights[ $weight_key ] = isset( $_POST[ $field ] ) ? min( 100, max( 0, absint( $_POST[ $field ] ) ) ) : 0;
		}
		if ( array_sum( $weights ) > 0 ) {
			$settings['ranking_weights'] = $weights;
		}

		// Cualquier cambio de pesos invalida la caché de recomendaciones.
		ZAIR_Cache::flush();

		update_option( 'zair_settings', $settings, false );

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>';
			}
		);
	}
}
