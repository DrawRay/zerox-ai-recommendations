<?php
/**
 * Motor de ranking (Fase 5).
 *
 * Ordena candidatos que YA pasaron el filtro de compatibilidad.
 * Nunca decide compatibilidad: si un producto llegó aquí, es porque
 * tiene compatibilidad_verificada = 1 y estado = 1.
 *
 * Factores y pesos por defecto (editables en Configuración):
 *   - Relevancia comercial 45%  → nivel_prioridad (1-10) de la compatibilidad.
 *   - Tipo de relación     25%  → afinidad entre el tipo del producto origen
 *                                 y el del candidato.
 *   - Oferta vigente       15%  → el producto está rebajado en WooCommerce.
 *   - Rendimiento          15%  → histórico de clics/compras (Fase 8).
 *                                 Neutro (0.5) mientras no haya datos.
 *
 * El stock NO es un peso: es un filtro previo en ZAIR_Recommender.
 * Una oferta jamás compensa una incompatibilidad.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Ranking
 */
class ZAIR_Ranking {

	/**
	 * Pesos por defecto si no hay configuración guardada.
	 *
	 * @var array<string,int>
	 */
	const DEFAULT_WEIGHTS = array(
		'relevancia_comercial' => 45,
		'tipo_relacion'        => 25,
		'oferta_vigente'       => 15,
		'rendimiento'          => 15,
	);

	/**
	 * Afinidad entre tipos de relación.
	 * Un cliente que mira un motor busca sobre todo piezas del conjunto
	 * motriz; los complementarios pesan algo menos.
	 *
	 * @var array<string,float>
	 */
	const AFINIDAD_TIPO = array(
		'motor'          => 1.00,
		'distribucion'   => 0.95,
		'embrague'       => 0.95,
		'transmision'    => 0.85,
		'refrigeracion'  => 0.75,
		'electrico'      => 0.70,
		'frenos'         => 0.55,
		'suspension'     => 0.50,
		'complementario' => 0.45,
	);

	/**
	 * Devuelve los pesos configurados, normalizados a suma 100.
	 *
	 * @return array<string,float>
	 */
	public static function get_weights() {
		$settings = get_option( 'zair_settings', array() );
		$weights  = isset( $settings['ranking_weights'] ) && is_array( $settings['ranking_weights'] )
			? $settings['ranking_weights']
			: self::DEFAULT_WEIGHTS;

		$clean = array();
		foreach ( self::DEFAULT_WEIGHTS as $key => $default ) {
			$clean[ $key ] = isset( $weights[ $key ] ) ? max( 0, (float) $weights[ $key ] ) : (float) $default;
		}

		$sum = array_sum( $clean );
		if ( $sum <= 0 ) {
			return self::DEFAULT_WEIGHTS;
		}

		// Normalizar a 100 para que el score sea comparable siempre.
		foreach ( $clean as $key => $value ) {
			$clean[ $key ] = ( $value / $sum ) * 100;
		}

		return $clean;
	}

	/**
	 * Calcula el score de un candidato.
	 *
	 * @param array $candidate Datos del candidato (compatibilidad + producto).
	 * @return array{score:float, desglose:array<string,float>}
	 */
	public static function score( $candidate ) {

		$weights = self::get_weights();

		// 1) Relevancia comercial: nivel_prioridad 1-10 → 0..1.
		$prioridad  = isset( $candidate['nivel_prioridad'] ) ? (float) $candidate['nivel_prioridad'] : 5;
		$relevancia = min( 1, max( 0, ( $prioridad - 1 ) / 9 ) );

		// 2) Afinidad del tipo de relación.
		$tipo     = isset( $candidate['tipo_relacion'] ) ? $candidate['tipo_relacion'] : 'complementario';
		$afinidad = isset( self::AFINIDAD_TIPO[ $tipo ] ) ? self::AFINIDAD_TIPO[ $tipo ] : 0.45;

		// 3) Oferta vigente: binario.
		$oferta = ! empty( $candidate['en_oferta'] ) ? 1.0 : 0.0;

		// 4) Rendimiento histórico: 0.5 neutro hasta tener eventos (Fase 8).
		$rendimiento = isset( $candidate['rendimiento'] ) ? min( 1, max( 0, (float) $candidate['rendimiento'] ) ) : 0.5;

		$desglose = array(
			'relevancia_comercial' => $relevancia * $weights['relevancia_comercial'],
			'tipo_relacion'        => $afinidad * $weights['tipo_relacion'],
			'oferta_vigente'       => $oferta * $weights['oferta_vigente'],
			'rendimiento'          => $rendimiento * $weights['rendimiento'],
		);

		return array(
			'score'    => round( array_sum( $desglose ), 3 ),
			'desglose' => array_map(
				function ( $v ) {
					return round( $v, 2 );
				},
				$desglose
			),
		);
	}

	/**
	 * Ordena una lista de candidatos ya puntuados, de mayor a menor score.
	 * Desempate estable: prioridad, luego id de producto.
	 *
	 * @param array $candidates Lista de candidatos con clave 'score'.
	 * @return array
	 */
	public static function sort( $candidates ) {

		usort(
			$candidates,
			function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					$pa = isset( $a['nivel_prioridad'] ) ? (int) $a['nivel_prioridad'] : 0;
					$pb = isset( $b['nivel_prioridad'] ) ? (int) $b['nivel_prioridad'] : 0;
					if ( $pa === $pb ) {
						return ( (int) $a['product_id'] ) <=> ( (int) $b['product_id'] );
					}
					return $pb <=> $pa;
				}
				return ( $b['score'] < $a['score'] ) ? -1 : 1;
			}
		);

		return $candidates;
	}
}
