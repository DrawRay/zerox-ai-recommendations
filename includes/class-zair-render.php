<?php
/**
 * Renderizado de la sección en el servidor.
 *
 * Hasta ahora el contenedor se imprimía vacío y lo rellenaba JavaScript
 * tras pedir los datos por REST. Ese enfoque tiene una ventaja real —la
 * caché de página no congela precios ni stock— pero un punto débil: si
 * algo interrumpe el JavaScript de la página (otro plugin con un error,
 * la combinación de scripts de un plugin de caché, una extensión del
 * navegador), la sección se queda vacía aunque los datos existan.
 *
 * Esta clase genera el mismo HTML desde PHP, de modo que la sección
 * llega ya pintada y se ve aunque el JavaScript no llegue a ejecutarse.
 * El script sigue encargándose de lo interactivo: registrar eventos,
 * mover el carrusel y añadir al carrito sin recargar.
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZAIR_Render
 */
class ZAIR_Render {

	/**
	 * Genera el HTML completo de la sección.
	 *
	 * @param int $product_id Producto de la ficha.
	 * @return string Cadena vacía si no hay nada que mostrar.
	 */
	public static function seccion( $product_id ) {

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return '';
		}

		$ajustes = get_option( 'zair_settings', array() );
		$limite  = isset( $ajustes['max_recommendations'] ) ? (int) $ajustes['max_recommendations'] : 4;

		$datos = ZAIR_Recommender::get_recommendations( $product_id, array( 'limit' => $limite ) );

		if ( empty( $datos['items'] ) ) {
			return '';
		}

		$titulo  = isset( $ajustes['section_title'] ) ? $ajustes['section_title'] : 'Complementa tu motor';
		$sub     = ! empty( $ajustes['show_subtitle'] ) && isset( $ajustes['section_subtitle'] ) ? $ajustes['section_subtitle'] : '';
		$columnas = isset( $ajustes['cards_per_row'] ) ? (int) $ajustes['cards_per_row'] : 5;

		$vehiculo = '';
		if ( ! empty( $ajustes['show_vehicle'] ) && ! empty( $datos['vehicles'][0] ) ) {
			$v        = $datos['vehicles'][0];
			$vehiculo = trim( $v->marca . ' ' . $v->modelo . ' ' . $v->version . ' ' . $v->cilindrada );
			if ( $v->codigo_motor ) {
				$vehiculo .= ' / ' . $v->codigo_motor;
			}
		}

		$hay_mas = ( (int) $datos['total_disponibles'] > count( $datos['items'] ) );

		ob_start();
		?>
		<section class="zair-recommendations" id="zair-recommendations"
			data-product-id="<?php echo esc_attr( $product_id ); ?>"
			data-vehicle-id="<?php echo esc_attr( ! empty( $datos['vehicles'][0] ) ? (int) $datos['vehicles'][0]->id : 0 ); ?>"
			style="--zair-cols:<?php echo esc_attr( max( 3, min( 6, $columnas ) ) ); ?>;"
			aria-live="polite">

			<div class="zair-header">
				<h2 class="zair-title">
					<?php if ( ! empty( $ajustes['show_title_icon'] ) ) : ?>
						<svg class="zair-title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
							<circle cx="12" cy="12" r="3.2"></circle>
							<path d="M12 2.6v3M12 18.4v3M2.6 12h3M18.4 12h3M5.4 5.4l2.1 2.1M16.5 16.5l2.1 2.1M18.6 5.4l-2.1 2.1M7.5 16.5l-2.1 2.1"></path>
						</svg>
					<?php endif; ?>
					<?php echo esc_html( $titulo ); ?>
				</h2>

				<?php if ( $sub || $vehiculo ) : ?>
					<p class="zair-subtitle">
						<?php echo esc_html( $sub ); ?>
						<?php if ( $vehiculo ) : ?>
							<?php echo $sub ? '<br>' : ''; ?>
							<span class="zair-vehicle"><?php echo esc_html( $vehiculo ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="zair-carousel-wrap">
				<button type="button" class="zair-nav zair-nav-prev" aria-label="Anterior" hidden>‹</button>

				<ul class="zair-carousel" id="zair-carousel">
					<?php foreach ( $datos['items'] as $item ) : ?>
						<?php echo self::tarjeta( $item, $ajustes ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endforeach; ?>
				</ul>

				<button type="button" class="zair-nav zair-nav-next" aria-label="Siguiente" hidden>›</button>
			</div>

			<?php
			/*
			 * El carrusel ya se desliza, así que el botón de ampliar
			 * duplica una acción que el propio deslizamiento resuelve.
			 * Queda disponible por si se prefiere mostrar todo de golpe.
			 */
			if ( $hay_mas && ! empty( $ajustes['show_more_button'] ) ) :
				?>
				<div class="zair-footer">
					<button type="button" class="zair-more" id="zair-more">
						<?php echo esc_html( isset( $ajustes['txt_more'] ) && $ajustes['txt_more'] ? $ajustes['txt_more'] : 'Ver más' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php
			/*
			 * La barra de resumen solo tiene sentido si se quiere una
			 * confirmación visible en la propia sección. El formulario de
			 * cotización ya avisa de cuántos repuestos lleva la solicitud,
			 * así que por defecto no se muestra.
			 */
			$zair_ver_cotizar = ( ! isset( $ajustes['show_quote'] ) || ! empty( $ajustes['show_quote'] ) )
				&& class_exists( 'ZQP_Public' )
				&& ! empty( $ajustes['show_quote_bar'] );

			if ( $zair_ver_cotizar ) :
				?>
				<?php
				/*
				 * La barra solo informa de lo añadido. El envío se hace con
				 * el botón de cotización de la ficha, que ya recoge la
				 * selección: dos botones para la misma acción reparten la
				 * atención y confunden sobre cuál hay que pulsar.
				 */
				$zair_barra_cta = ! empty( $ajustes['quote_bar_button'] );
				?>
				<div class="zair-quote-bar" id="zair-quote-bar" hidden>
					<div class="zair-quote-info">
						<span class="zair-quote-count" id="zair-quote-count"></span>
						<button type="button" class="zair-quote-clear" id="zair-quote-clear">Vaciar</button>
					</div>

					<?php if ( $zair_barra_cta ) : ?>
						<button type="button" class="zair-quote-cta" id="zair-quote-cta">
							<?php echo esc_html( isset( $ajustes['txt_quote'] ) && $ajustes['txt_quote'] ? $ajustes['txt_quote'] : 'SOLICITAR COTIZACIÓN' ); ?>
						</button>
					<?php else : ?>
						<span class="zair-quote-hint">Pulsa «Solicitar cotización» para enviarlos</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Tarjeta individual.
	 *
	 * @param array $item    Datos del producto recomendado.
	 * @param array $ajustes Opciones del plugin.
	 * @return string
	 */
	private static function tarjeta( $item, $ajustes ) {

		$ver_cotizar = ( ! isset( $ajustes['show_quote'] ) || ! empty( $ajustes['show_quote'] ) )
			&& class_exists( 'ZQP_Public' );

		$ver_comprar = ! empty( $ajustes['show_buy'] ) && ! empty( $item['comprable'] );

		$imagen = $item['imagen'] ? $item['imagen'] : wc_placeholder_img_src( 'woocommerce_thumbnail' );

		ob_start();
		?>
		<li class="zair-card" data-product-id="<?php echo esc_attr( $item['product_id'] ); ?>"
			data-position="<?php echo esc_attr( $item['position'] ?? 0 ); ?>"
			data-score="<?php echo esc_attr( $item['score'] ); ?>">

			<a class="zair-card-media" href="<?php echo esc_url( $item['url'] ); ?>">
				<?php if ( ! empty( $item['en_oferta'] ) ) : ?>
					<span class="zair-flag-oferta">Oferta</span>
				<?php endif; ?>
				<img src="<?php echo esc_url( $imagen ); ?>" alt="<?php echo esc_attr( $item['nombre'] ); ?>" loading="lazy">
			</a>

			<div class="zair-card-body">
				<h3 class="zair-card-name">
					<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['nombre'] ); ?></a>
				</h3>

				<?php if ( ! empty( $ajustes['show_badge'] ) && ! empty( $item['codigo_motor'] ) ) : ?>
					<span class="zair-badge-compatible">✓ Compatible con <?php echo esc_html( $item['codigo_motor'] ); ?></span>
				<?php endif; ?>

				<?php if ( ! empty( $item['precio_html'] ) ) : ?>
					<div class="zair-card-price"><?php echo wp_kses_post( $item['precio_html'] ); ?></div>
				<?php endif; ?>

				<div class="zair-card-actions">
					<?php if ( $ver_cotizar ) : ?>
						<?php
						// Botón de acumulación: el cliente va sumando repuestos
						// y al final pide una sola cotización con todos.
						?>
						<button type="button" class="zair-add-quote"
							data-product-id="<?php echo esc_attr( $item['product_id'] ); ?>"
							data-nombre="<?php echo esc_attr( $item['nombre'] ); ?>"
							aria-pressed="false"
							title="Añadir a mi cotización">
							<span class="zair-add-icon" aria-hidden="true">+</span>
							<span class="zair-add-text">Añadir</span>
						</button>
					<?php endif; ?>

					<?php if ( $ver_comprar ) : ?>
						<button type="button" class="zair-btn zair-btn-primary zair-add"
							data-product-id="<?php echo esc_attr( $item['product_id'] ); ?>">
							<?php echo esc_html( isset( $ajustes['txt_cart'] ) && $ajustes['txt_cart'] ? $ajustes['txt_cart'] : 'COMPRAR' ); ?>
						</button>
					<?php elseif ( ! $ver_cotizar ) : ?>
						<a class="zair-btn" href="<?php echo esc_url( $item['url'] ); ?>">Ver producto</a>
					<?php endif; ?>
				</div>
			</div>
		</li>
		<?php
		return ob_get_clean();
	}
}
