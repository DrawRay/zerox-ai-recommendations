<?php
/**
 * Autodetección de compatibilidades (v1.2).
 *
 * ZEROXMOTORS ya escribe el código de motor dentro de cada producto
 * (por ejemplo "C14" en el obturador y "MOTOR SEMI ARMADO C14" en el
 * motor). Esa convención interna es, de hecho, una base de compatibilidad
 * ya existente: si dos productos comparten código, son compatibles.
 *
 * Esta clase la aprovecha: escanea el catálogo, extrae el código de cada
 * producto, agrupa por código y propone las compatibilidades.
 *
 * IMPORTANTE: lo detectado se crea SIN verificar por defecto. El sistema
 * propone; la persona confirma. Así se respeta la regla del proyecto de
 * que ninguna compatibilidad llegue al cliente sin validación humana.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Autodetect
 */
class ZAIR_Autodetect {

	/**
	 * Marcas reconocidas en los títulos de producto.
	 *
	 * @var string[]
	 */
	const MARCAS = array(
		'CHEVROLET', 'CHANGAN', 'JINBEI', 'DFSK', 'FOTON', 'JAC', 'LIFAN',
		'HAFEI', 'DONGFENG', 'CHERY', 'SUZUKI', 'KIA', 'HYUNDAI', 'TOYOTA',
		'NISSAN', 'GREAT WALL', 'ZOTYE', 'SHINERAY', 'WULING', 'BAIC',
		'CHANGHE', 'KING LONG', 'MAXUS', 'GONOW', 'HAIMA', 'SOUEAST',
	);

	/**
	 * Palabras clave → tipo de relación.
	 * Se evalúan en orden: la primera coincidencia gana.
	 *
	 * @var array<string,string[]>
	 */
	const TIPOS = array(
		'motor'         => array( 'MOTOR', 'CULATA', 'BLOCK', 'CIGUEÑAL', 'CIGUENAL', 'PISTON', 'MULTIPLE', 'OBTURADOR', 'CARTER', 'BIELA', 'VALVULA', 'ARBOL DE LEVA' ),
		'embrague'      => array( 'EMBRAGUE', 'CLUTCH', 'COLLARIN', 'PRENSA', 'DISCO DE EMBRAGUE' ),
		'distribucion'  => array( 'DISTRIBUCION', 'DISTRIBUCIÓN', 'CADENA DE TIEMPO', 'CORREA DE TIEMPO', 'TENSOR' ),
		'transmision'   => array( 'CAJA DE CAMBIO', 'CAJA', 'TRANSMISION', 'TRANSMISIÓN', 'DIFERENCIAL', 'CORONA', 'CARDAN', 'HOMOCINETICA', 'PIÑON' ),
		'refrigeracion' => array( 'RADIADOR', 'BOMBA DE AGUA', 'TUBO DE AGUA', 'TERMOSTATO', 'VENTILADOR', 'MANGUERA', 'REFRIGERA' ),
		'electrico'     => array( 'ARRANCADOR', 'ALTERNADOR', 'BOBINA', 'SENSOR', 'BUJIA', 'BUJÍA', 'INYECTOR', 'CABLEADO', 'MODULO', 'MÓDULO', 'SERVO', 'MOTOR DE ARRANQUE' ),
		'frenos'        => array( 'FRENO', 'PASTILLA', 'ZAPATA', 'DISCO DE FRENO', 'BOMBIN' ),
		'suspension'    => array( 'AMORTIGUADOR', 'SUSPENSION', 'SUSPENSIÓN', 'CREMALLERA', 'ROTULA', 'RÓTULA', 'MUÑON', 'RESORTE', 'DIRECCION', 'DIRECCIÓN' ),
	);

	/**
	 * Escanea el catálogo y agrupa productos por código de motor.
	 *
	 * @param int $limit Máximo de productos a revisar.
	 * @return array Códigos detectados con sus productos.
	 */
	public static function scan( $limit = 3000 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT ID, post_title, post_content, post_excerpt
				 FROM {$wpdb->posts}
				 WHERE post_type = 'product' AND post_status = 'publish'
				 ORDER BY ID DESC LIMIT %d",
				absint( $limit )
			)
		);

		$grupos = array();

		foreach ( (array) $rows as $row ) {

			$codigo = self::extract_code( $row->post_content . ' ' . $row->post_excerpt );

			if ( ! $codigo ) {
				continue;
			}

			if ( ! isset( $grupos[ $codigo ] ) ) {
				$grupos[ $codigo ] = array(
					'codigo'    => $codigo,
					'productos' => array(),
					'motor'     => null,
					'marca'     => '',
					'modelo'    => '',
					'cilindrada'=> '',
				);
			}

			$titulo = $row->post_title;
			$tipo   = self::guess_tipo( $titulo );

			$producto = array(
				'id'     => (int) $row->ID,
				'titulo' => $titulo,
				'tipo'   => $tipo,
			);

			// El producto cuyo título empieza por MOTOR define marca/modelo.
			if ( 0 === stripos( $titulo, 'MOTOR' ) && ! $grupos[ $codigo ]['motor'] ) {
				$grupos[ $codigo ]['motor']      = $producto;
				$grupos[ $codigo ]['marca']      = self::guess_marca( $titulo );
				$grupos[ $codigo ]['modelo']     = self::guess_modelo( $titulo );
				$grupos[ $codigo ]['cilindrada'] = self::guess_cilindrada( $titulo . ' ' . $row->post_content );
			}

			$grupos[ $codigo ]['productos'][] = $producto;
		}

		// Completar datos faltantes con el primer producto del grupo.
		foreach ( $grupos as $codigo => $g ) {
			if ( '' === $g['marca'] && ! empty( $g['productos'][0] ) ) {
				$grupos[ $codigo ]['marca']      = self::guess_marca( $g['productos'][0]['titulo'] );
				$grupos[ $codigo ]['modelo']     = self::guess_modelo( $g['productos'][0]['titulo'] );
				$grupos[ $codigo ]['cilindrada'] = self::guess_cilindrada( $g['productos'][0]['titulo'] );
			}
		}

		// Solo interesan los grupos con al menos 2 productos.
		$grupos = array_filter(
			$grupos,
			function ( $g ) {
				return count( $g['productos'] ) >= 2;
			}
		);

		// Ordenar por cantidad de productos, de mayor a menor.
		uasort(
			$grupos,
			function ( $a, $b ) {
				return count( $b['productos'] ) <=> count( $a['productos'] );
			}
		);

		return $grupos;
	}

	/**
	 * Extrae el código de motor de un texto.
	 *
	 * Busca tokens alfanuméricos cortos que mezclen letras y números
	 * (C14, B12, 4G15S, JL474Q…), descartando falsos positivos comunes
	 * como cilindradas o medidas.
	 *
	 * @param string $texto Texto a analizar.
	 * @return string|null
	 */
	public static function extract_code( $texto ) {

		$texto = wp_strip_all_tags( (string) $texto );
		$texto = strtoupper( $texto );

		// Descartar unidades y palabras que no son códigos.
		$excluir = array( 'CC', 'HP', 'MM', 'KG', 'KM', 'RPM', 'V8', 'V6', '4X4', '4X2', '8X41', '12V', '24V' );

		if ( ! preg_match_all( '/\b[A-Z0-9]{2,9}\b/', $texto, $m ) ) {
			return null;
		}

		foreach ( $m[0] as $token ) {

			if ( in_array( $token, $excluir, true ) ) {
				continue;
			}

			// Debe contener al menos una letra y al menos un número.
			if ( ! preg_match( '/[A-Z]/', $token ) || ! preg_match( '/\d/', $token ) ) {
				continue;
			}

			// Descartar cilindradas escritas como 1200CC o 1.5CC.
			if ( preg_match( '/^\d{3,4}CC?$/', $token ) ) {
				continue;
			}

			// Descartar SKU largos con muchos dígitos seguidos.
			if ( preg_match( '/^\d{4,}/', $token ) ) {
				continue;
			}

			return $token;
		}

		return null;
	}

	/**
	 * Deduce el tipo de relación a partir del título del producto.
	 *
	 * @param string $titulo Título del producto.
	 * @return string
	 */
	public static function guess_tipo( $titulo ) {

		$t = strtoupper( wp_strip_all_tags( $titulo ) );

		foreach ( self::TIPOS as $tipo => $palabras ) {
			foreach ( $palabras as $palabra ) {
				if ( false !== strpos( $t, $palabra ) ) {
					return $tipo;
				}
			}
		}

		return 'complementario';
	}

	/**
	 * Deduce la marca del vehículo desde el título.
	 *
	 * @param string $titulo Título del producto.
	 * @return string
	 */
	public static function guess_marca( $titulo ) {

		$t = strtoupper( wp_strip_all_tags( $titulo ) );

		foreach ( self::MARCAS as $marca ) {
			if ( false !== strpos( $t, $marca ) ) {
				return ucwords( strtolower( $marca ) );
			}
		}

		return '';
	}

	/**
	 * Deduce el modelo: lo que viene después de la marca,
	 * sin la cilindrada ni las unidades.
	 *
	 * @param string $titulo Título del producto.
	 * @return string
	 */
	public static function guess_modelo( $titulo ) {

		$t = strtoupper( wp_strip_all_tags( $titulo ) );

		// Quitar la palabra inicial (MOTOR, RADIADOR, etc.) y la marca.
		foreach ( self::MARCAS as $marca ) {
			$pos = strpos( $t, $marca );
			if ( false !== $pos ) {
				$t = substr( $t, $pos + strlen( $marca ) );
				break;
			}
		}

		// Quitar cilindradas y unidades.
		$t = preg_replace( '/\d+\.?\d*\s*CC\b/', '', $t );
		$t = preg_replace( '/\b\d{3,4}\s*CC\b/', '', $t );
		$t = preg_replace( '/\b\d\.\d\b/', '', $t );
		$t = preg_replace( '/\s+/', ' ', $t );

		$modelo = trim( $t, " -/\t\n" );

		return $modelo ? ucwords( strtolower( mb_substr( $modelo, 0, 80 ) ) ) : '';
	}

	/**
	 * Deduce la cilindrada (formato 1.2, 1.5, 2.4).
	 *
	 * @param string $texto Texto a analizar.
	 * @return string
	 */
	public static function guess_cilindrada( $texto ) {

		$t = strtoupper( wp_strip_all_tags( $texto ) );

		// Formato 1.5 o 1.5CC.
		if ( preg_match( '/\b(\d\.\d)\s*(?:CC)?\b/', $t, $m ) ) {
			return $m[1];
		}

		// Formato 1200 CC → 1.2.
		if ( preg_match( '/\b(\d{3,4})\s*CC\b/', $t, $m ) ) {
			$valor = (int) $m[1];
			if ( $valor >= 600 && $valor <= 6000 ) {
				return number_format( $valor / 1000, 1, '.', '' );
			}
		}

		return '';
	}

	/**
	 * Aplica los grupos seleccionados: crea vehículos y compatibilidades.
	 *
	 * @param array $codigos    Códigos de motor a importar.
	 * @param bool  $verificar  Marcar las compatibilidades como verificadas.
	 * @return array Resumen { vehiculos, compatibilidades, omitidas }.
	 */
	public static function apply( $codigos, $verificar = false ) {
		global $wpdb;

		$grupos = self::scan();
		$codigos = array_map( 'strtoupper', (array) $codigos );

		$resumen = array(
			'vehiculos'        => 0,
			'compatibilidades' => 0,
			'omitidas'         => 0,
		);

		$t_vehicle = ZAIR_Database::table( 'zair_vehicle_engine' );
		$now       = current_time( 'mysql' );

		foreach ( $grupos as $codigo => $g ) {

			if ( ! in_array( $codigo, $codigos, true ) ) {
				continue;
			}

			// ¿Ya existe un vehículo con ese código de motor?
			$vehicle_id = (int) $wpdb->get_var(
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL
					"SELECT id FROM {$t_vehicle} WHERE codigo_motor = %s LIMIT 1",
					$codigo
				)
			);

			if ( ! $vehicle_id ) {

				$marca  = $g['marca'] ? $g['marca'] : 'Sin marca';
				$modelo = $g['modelo'] ? $g['modelo'] : $codigo;

				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$t_vehicle,
					array(
						'marca'        => $marca,
						'modelo'       => $modelo,
						'version'      => '',
						'cilindrada'   => $g['cilindrada'],
						'codigo_motor' => $codigo,
						'combustible'  => '',
						'estado'       => 1,
						'created_at'   => $now,
						'updated_at'   => $now,
					)
				);

				$vehicle_id = (int) $wpdb->insert_id;

				if ( $vehicle_id ) {
					$resumen['vehiculos']++;

					// Alias automático con el propio código de motor.
					$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
						ZAIR_Database::table( 'zair_aliases' ),
						array(
							'alias'             => strtolower( $codigo ),
							'vehicle_engine_id' => $vehicle_id,
							'confianza'         => 1.00,
							'estado'            => 1,
							'created_at'        => $now,
							'updated_at'        => $now,
						)
					);
				}
			}

			if ( ! $vehicle_id ) {
				continue;
			}

			foreach ( $g['productos'] as $producto ) {

				if ( ZAIR_Compatibility::exists_duplicate( $vehicle_id, $producto['id'] ) ) {
					$resumen['omitidas']++;
					continue;
				}

				$prioridad = ( 'motor' === $producto['tipo'] ) ? 10 : 5;

				$resultado = ZAIR_Compatibility::save(
					array(
						'vehicle_engine_id'         => $vehicle_id,
						'product_id'                => $producto['id'],
						'tipo_relacion'             => $producto['tipo'],
						'nivel_prioridad'           => $prioridad,
						'compatibilidad_verificada' => $verificar ? 1 : 0,
						'notas'                     => 'Detectada automáticamente por código ' . $codigo,
						'estado'                    => 1,
					)
				);

				if ( ! is_wp_error( $resultado ) ) {
					$resumen['compatibilidades']++;
				} else {
					$resumen['omitidas']++;
				}
			}
		}

		ZAIR_Cache::flush();

		return $resumen;
	}
}
