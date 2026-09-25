<?php
/**
 * Vista: Configuración (Fase 2 - versión inicial).
 * Variables disponibles: $settings (array de zair_settings).
 *
 * @package Zerox_AI_Recommendations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zair_delete   = ! empty( $settings['delete_data_on_uninstall'] );
$zair_max      = isset( $settings['max_recommendations'] ) ? (int) $settings['max_recommendations'] : 4;
$zair_title    = isset( $settings['section_title'] ) ? $settings['section_title'] : 'Complementa tu motor';
$zair_subtitle = isset( $settings['section_subtitle'] ) ? $settings['section_subtitle'] : '';
$zair_weights  = isset( $settings['ranking_weights'] ) ? $settings['ranking_weights'] : array();
?>
<div class="wrap zair-wrap">

	<h1>ZEROX AI · Configuración</h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'zair_save_settings', 'zair_settings_nonce' ); ?>

		<h2>Sección "Complementa tu motor"</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="zair_section_title">Título de la sección</label></th>
				<td>
					<input type="text" class="regular-text" id="zair_section_title" name="zair_section_title"
						value="<?php echo esc_attr( $zair_title ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row">Icono del título</th>
				<td>
					<label>
						<input type="checkbox" name="zair_show_title_icon" value="1"
							<?php checked( ! empty( $settings['show_title_icon'] ) ); ?>>
						Mostrar el icono junto al título de la sección
					</label>
					<p class="description">
						Desactivado por defecto. Sin él, el título se alinea con el resto de encabezados de la ficha.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_section_subtitle">Subtítulo</label></th>
				<td>
					<input type="text" class="large-text" id="zair_section_subtitle" name="zair_section_subtitle"
						value="<?php echo esc_attr( $zair_subtitle ); ?>">
					<p style="margin-top:8px;">
						<label>
							<input type="checkbox" name="zair_show_subtitle" value="1"
								<?php checked( ! empty( $settings['show_subtitle'] ) ); ?>>
							Mostrar el subtítulo en la web
						</label>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Botón de ampliar</th>
				<td>
					<label>
						<input type="checkbox" name="zair_show_more_button" value="1"
							<?php checked( ! empty( $settings['show_more_button'] ) ); ?>>
						Mostrar un botón para cargar el resto de compatibles
					</label>
					<p style="margin-top:8px;">
						<label for="zair_txt_more" style="display:inline-block; width:150px;">Texto del botón</label>
						<input type="text" id="zair_txt_more" name="zair_txt_more" class="regular-text"
							value="<?php echo esc_attr( isset( $settings['txt_more'] ) ? $settings['txt_more'] : 'Ver más' ); ?>">
					</p>
					<p class="description" style="max-width:660px;">
						Desactivado por defecto: el carrusel ya se desliza, de modo que el botón repite una acción
						que el propio deslizamiento resuelve. Actívalo si prefieres que todos los compatibles se
						carguen de una vez.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Barra de repuestos añadidos</th>
				<td>
					<label style="display:block; margin-bottom:6px;">
						<input type="checkbox" name="zair_show_quote_bar" value="1"
							<?php checked( ! empty( $settings['show_quote_bar'] ) ); ?>>
						Mostrar el contador de repuestos añadidos bajo el carrusel
					</label>
					<label style="display:block;">
						<input type="checkbox" name="zair_quote_bar_button" value="1"
							<?php checked( ! empty( $settings['quote_bar_button'] ) ); ?>>
						Incluir también un botón de envío en esa barra
					</label>
					<p class="description" style="max-width:660px;">
						Desactivado por defecto. El botón «Solicitar cotización» de la ficha ya recoge todo lo añadido,
						así que la barra solo informa de cuántos repuestos lleva el cliente. Dos botones para la misma
						acción reparten la atención y hacen dudar sobre cuál pulsar.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Distintivo de compatibilidad</th>
				<td>
					<label>
						<input type="checkbox" name="zair_show_badge" value="1"
							<?php checked( ! empty( $settings['show_badge'] ) ); ?>>
						Mostrar «✓ Compatible con …» en cada tarjeta
					</label>
					<p class="description" style="max-width:660px;">
						Desactivado por defecto. El distintivo da confianza al cliente, pero también deja a la vista
						el código de motor con el que agrupas tu catálogo. Actívalo solo si prefieres esa señal
						de confianza por encima de reservarte ese dato.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Vehículo detectado</th>
				<td>
					<label>
						<input type="checkbox" name="zair_show_vehicle" value="1"
							<?php checked( ! empty( $settings['show_vehicle'] ) ); ?>>
						Mostrar la línea con marca, modelo y código de motor
					</label>
					<p class="description">
						Desactivado por defecto: el código de motor es información interna de taller.
						El badge «✓ Compatible con …» de cada tarjeta se sigue mostrando.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Acciones en cada tarjeta</th>
				<td>
					<label style="display:block; margin-bottom:8px;">
						<input type="checkbox" name="zair_show_quote" value="1"
							<?php checked( ! isset( $settings['show_quote'] ) || ! empty( $settings['show_quote'] ) ); ?>>
						Botón <strong>COTIZAR</strong> — abre el formulario de cotización con ese repuesto
					</label>
					<label style="display:block; margin-bottom:8px;">
						<input type="checkbox" name="zair_show_buy" value="1"
							<?php checked( ! empty( $settings['show_buy'] ) ); ?>>
						Botón <strong>COMPRAR</strong> — añade el repuesto al carrito
					</label>
					<p class="description" style="max-width:660px;">
						El botón de compra solo aparece en productos con precio y stock que WooCommerce permita
						adquirir directamente. En un catálogo que trabaja por cotización, dejarlo apagado evita
						ofrecer una vía de compra que luego no se puede completar.
					</p>
					<p class="description" style="max-width:660px;">
						«COTIZAR» requiere el plugin Zerox Cotizador New activo. Sin él, el botón lleva a la ficha del producto.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Formulario de consulta propio</th>
				<td>
					<label>
						<input type="checkbox" name="zair_show_lead" value="1"
							<?php checked( ! empty( $settings['show_lead_form'] ) ); ?>>
						Mostrar el bloque «¿No estás seguro de la compatibilidad?» bajo el carrusel
					</label>
					<p class="description" style="max-width:660px;">
						Desactivado por defecto: con el botón COTIZAR en cada tarjeta, este bloque duplica la misma
						función y dos formularios en la misma página compiten entre sí.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_cards_per_row">Tarjetas visibles</label></th>
				<td>
					<input type="number" id="zair_cards_per_row" name="zair_cards_per_row" min="3" max="6" style="width:80px;"
						value="<?php echo esc_attr( isset( $settings['cards_per_row'] ) ? (int) $settings['cards_per_row'] : 5 ); ?>">
					<p class="description" style="max-width:660px;">
						Cuántas tarjetas caben a la vez en pantallas de escritorio. A mayor número, tarjetas e imágenes
						más pequeñas y una sección más discreta. En móvil siempre se muestra algo más de una, para que
						se note que hay más al deslizar.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Dónde se muestra</th>
				<td>
					<label>
						<input type="checkbox" name="zair_auto_insert" value="1"
							<?php checked( ! isset( $settings['auto_insert'] ) || ! empty( $settings['auto_insert'] ) ); ?>>
						Insertar automáticamente debajo del resumen del producto
					</label>
					<p class="description">
						Funciona con plantillas estándar de WooCommerce. Si tu ficha usa una plantilla personalizada
						(Flatsome UX Builder, Elementor…), el hook automático no se ejecuta: desmarca esta casilla
						y coloca el shortcode <code>[zair_recommendations]</code> donde quieras que aparezca la sección.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Formato de presentación</th>
				<td>
					<?php $zair_layout = isset( $settings['layout'] ) ? $settings['layout'] : 'carrusel'; ?>
					<label style="display:block; margin-bottom:8px;">
						<input type="radio" name="zair_layout" value="carrusel" <?php checked( 'combo' !== $zair_layout ); ?>>
						<strong>Carrusel</strong> — tarjetas grandes con botón individual en cada producto
					</label>
					<label style="display:block;">
						<input type="radio" name="zair_layout" value="combo" <?php checked( 'combo' === $zair_layout ); ?>>
						<strong>Selección múltiple</strong> — tarjetas compactas con casilla; el cliente marca varios
						y pide una sola cotización de todo el conjunto
					</label>
					<p class="description" style="max-width:660px;">
						La selección múltiple encaja mejor con un negocio que vende por cotización: una consulta
						puede traer cuatro repuestos en lugar de uno, lo que eleva el importe medio sin trabajo
						comercial adicional. Requiere el plugin de cotizaciones activo.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_quote_text">Texto del botón de cotizar</label></th>
				<td>
					<input type="text" id="zair_quote_text" name="zair_quote_text" class="regular-text"
						value="<?php echo esc_attr( isset( $settings['quote_button_text'] ) ? $settings['quote_button_text'] : 'COTIZAR SELECCIONADOS' ); ?>">
					<p class="description">Solo se usa en el formato de selección múltiple.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_max_recs">Máximo de recomendaciones visibles</label></th>
				<td>
					<input type="number" min="1" max="12" id="zair_max_recs" name="zair_max_recs"
						value="<?php echo esc_attr( $zair_max ); ?>">
					<p class="description">El MVP muestra 4; el resto queda detrás de "Ver más compatibles".</p>
				</td>
			</tr>
		</table>

		<h2>Pesos del ranking</h2>
		<p class="description" style="max-width:640px;">
			Determinan el orden de las recomendaciones. No hace falta que sumen 100: el sistema los normaliza.
			El stock no figura aquí porque no es un peso sino un filtro previo — un producto sin stock nunca compite.
		</p>
		<table class="form-table" role="presentation">
			<?php
			$zair_weight_labels = array(
				'relevancia_comercial' => array( 'Relevancia comercial', 'Prioridad 1-10 que asignas a cada compatibilidad.' ),
				'tipo_relacion'        => array( 'Tipo de relación', 'Afinidad de la pieza con el motor (distribución y embrague pesan más que un complementario).' ),
				'oferta_vigente'       => array( 'Oferta vigente', 'El producto está rebajado en WooCommerce.' ),
				'rendimiento'          => array( 'Rendimiento histórico', 'CTR real del producto. Neutro hasta que existan eventos (Fase 8).' ),
			);
			foreach ( $zair_weight_labels as $zair_key => $zair_label ) :
				$zair_current = isset( $zair_weights[ $zair_key ] ) ? (int) $zair_weights[ $zair_key ] : 0;
				?>
				<tr>
					<th scope="row">
						<label for="zair_w_<?php echo esc_attr( $zair_key ); ?>"><?php echo esc_html( $zair_label[0] ); ?></label>
					</th>
					<td>
						<input type="number" min="0" max="100" style="width:80px;"
							id="zair_w_<?php echo esc_attr( $zair_key ); ?>"
							name="zair_w_<?php echo esc_attr( $zair_key ); ?>"
							value="<?php echo esc_attr( $zair_current ); ?>"> %
						<p class="description"><?php echo esc_html( $zair_label[1] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row">Caché</th>
				<td>
					<label>
						<input type="checkbox" name="zair_cache_disabled" value="1"
							<?php checked( ! empty( $settings['cache_disabled'] ) ); ?>>
						Desactivar la caché del recomendador
					</label>
					<p class="description">
						Útil mientras cargas compatibilidades y quieres ver los cambios al instante. Actívala de nuevo en producción.
						Los precios y el stock nunca se cachean: siempre se leen en vivo de WooCommerce.
					</p>
				</td>
			</tr>
		</table>

		<h2>Google Analytics 4</h2>
		<p class="description" style="max-width:640px;">
			GA4 recibe únicamente eventos anónimos de comportamiento y producto.
			Nombre, WhatsApp y cualquier dato personal se quedan siempre en esta base:
			el plugin filtra el envío antes de que salga del servidor.
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Enviar eventos a GA4</th>
				<td>
					<label>
						<input type="checkbox" name="zair_ga4_enabled" value="1"
							<?php checked( ! empty( $settings['ga4_enabled'] ) ); ?>>
						Activar la integración
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_ga4_id">Measurement ID</label></th>
				<td>
					<input type="text" id="zair_ga4_id" name="zair_ga4_id" class="regular-text"
						placeholder="G-XXXXXXXXXX"
						value="<?php echo esc_attr( isset( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '' ); ?>">
					<p class="description">
						Déjalo vacío si tu web ya carga GA4 con Site Kit, GTM o código en el tema: el plugin reutilizará ese gtag.
						Rellénalo solo si quieres que el plugin cargue GA4 por su cuenta.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Eventos que se envían</th>
				<td>
					<p class="description" style="max-width:620px;">
						<?php echo esc_html( implode( ', ', ZAIR_GA4::MAPA_EVENTOS ) ); ?>
					</p>
					<p class="description">
						Parámetros incluidos: identificador anónimo de sesión, ID de producto, ID de vehículo y posición en el carrusel.
						Regístralos como dimensiones personalizadas en GA4 si quieres segmentar por ellos.
					</p>
				</td>
			</tr>
		</table>

		<h2 id="zair-ia">Inteligencia artificial</h2>
		<p class="description" style="max-width:680px;">
			La IA solo interpreta lo que escribe el cliente («busco algo para mi n300») y lo traduce a un vehículo del catálogo.
			<strong>Nunca decide compatibilidad</strong>: eso lo sigue confirmando la tabla de compatibilidades verificadas.
			Si la IA nombra un vehículo que no existe en tu base, el sistema descarta su respuesta.
		</p>
		<p class="description" style="max-width:680px;">
			Antes de gastar una llamada, el sistema intenta resolver con los alias registrados y con coincidencias del catálogo.
			La IA entra solo cuando eso falla, así que el coste se mantiene bajo y el buscador sigue funcionando si la IA no está disponible.
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Activar IA</th>
				<td>
					<label>
						<input type="checkbox" name="zair_ai_enabled" value="1"
							<?php checked( ! empty( $settings['ai_enabled'] ) ); ?>>
						Usar IA para interpretar búsquedas
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_ai_key">API key</label></th>
				<td>
					<?php $zair_tiene_key = ! empty( $settings['ai_api_key'] ) || ( defined( 'ZAIR_AI_API_KEY' ) && ZAIR_AI_API_KEY ); ?>
					<input type="password" id="zair_ai_key" name="zair_ai_key" class="regular-text" autocomplete="new-password"
						placeholder="<?php echo $zair_tiene_key ? '•••••••••• (guardada)' : 'sk-ant-...'; ?>">
					<p class="description">
						<?php if ( defined( 'ZAIR_AI_API_KEY' ) && ZAIR_AI_API_KEY ) : ?>
							<strong>Definida en wp-config.php</strong> mediante la constante <code>ZAIR_AI_API_KEY</code>. Esa tiene prioridad sobre este campo.
						<?php else : ?>
							Recomendación: en lugar de guardarla aquí, añade a <code>wp-config.php</code> la línea
							<code>define( 'ZAIR_AI_API_KEY', 'tu-clave' );</code> — queda fuera de la base de datos y de los backups.
						<?php endif; ?>
						La clave se usa solo desde PHP; nunca se envía al navegador.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="zair_ai_model">Modelo</label></th>
				<td>
					<input type="text" id="zair_ai_model" name="zair_ai_model" class="regular-text"
						placeholder="claude-sonnet-4-5"
						value="<?php echo esc_attr( isset( $settings['ai_model'] ) ? $settings['ai_model'] : '' ); ?>">
					<p class="description">Déjalo vacío para usar el modelo por defecto.</p>
				</td>
			</tr>
		</table>

		<?php
		$zair_ai_error = get_option( 'zair_ai_last_error' );
		if ( ! empty( $zair_ai_error['mensaje'] ) ) :
			?>
			<div class="notice notice-warning inline" style="max-width:680px; margin:0 0 16px;">
				<p>
					<strong>Último error de IA:</strong> <?php echo esc_html( $zair_ai_error['mensaje'] ); ?>
					<span class="zair-product-meta">(<?php echo esc_html( mysql2date( 'd/m/Y H:i', $zair_ai_error['fecha'] ) ); ?>)</span>
				</p>
			</div>
		<?php endif; ?>

		<h2>Datos y desinstalación</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="zair_retention">Retención de eventos</label></th>
				<td>
					<input type="number" id="zair_retention" name="zair_retention" min="0" max="60" style="width:80px;"
						value="<?php echo esc_attr( isset( $settings['events_retention_months'] ) ? (int) $settings['events_retention_months'] : 0 ); ?>"> meses
					<p class="description">
						0 = conservar todo (recomendado mientras dure el proyecto).
						Con un valor mayor, un proceso diario elimina los eventos más antiguos.
						Exporta el CSV de eventos antes de activar la purga.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Borrado de datos</th>
				<td>
					<label>
						<input type="checkbox" name="zair_delete_data" value="1" <?php checked( $zair_delete ); ?>>
						Eliminar todas las tablas y opciones del plugin al desinstalarlo.
					</label>
					<p class="description" style="color:#b32d2e;">
						Desactivado por defecto para proteger los datos del proyecto (leads, eventos, compatibilidades).
						Actívalo solo si realmente deseas destruir toda la información.
					</p>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" name="zair_settings_submit" value="1" class="button button-primary">
				Guardar configuración
			</button>
		</p>
	</form>


	<h2>Probar la interpretación</h2>
	<?php $zair_test = get_transient( 'zair_ai_test_result' ); ?>
	<div class="zair-placeholder" style="max-width:680px;">
		<form method="post" action="">
			<?php wp_nonce_field( 'zair_ai_test', 'zair_ai_test_nonce' ); ?>
			<p style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
				<input type="text" name="zair_ai_texto" class="regular-text"
					placeholder="busco cosas para mi n300"
					value="<?php echo esc_attr( $zair_test ? $zair_test['texto'] : '' ); ?>">
				<button type="submit" name="zair_ai_test" value="1" class="button">Interpretar</button>
			</p>
			<p class="description">
				Guarda primero la configuración. La prueba recorre la misma cascada que usa la web:
				alias, coincidencia de catálogo y, si hace falta, IA.
			</p>
		</form>

		<?php if ( $zair_test ) : ?>
			<hr>
			<p>
				<strong>Método usado:</strong>
				<span class="zair-badge zair-badge-pending"><?php echo esc_html( strtoupper( $zair_test['metodo'] ) ); ?></span>
			</p>
			<p>
				<strong>Resultado:</strong>
				<?php if ( 'ok' === $zair_test['estado'] ) : ?>
					<span class="zair-badge zair-badge-ok">✔ <?php echo esc_html( $zair_test['mensaje'] ); ?></span>
					<span class="zair-product-meta">confianza <?php echo esc_html( number_format_i18n( $zair_test['confianza'], 2 ) ); ?></span>
				<?php elseif ( 'ambiguo' === $zair_test['estado'] ) : ?>
					<span class="zair-badge zair-badge-pending">Pregunta al cliente</span><br>
					<em><?php echo esc_html( $zair_test['mensaje'] ); ?></em>
				<?php else : ?>
					<span class="zair-badge zair-badge-error"><?php echo esc_html( $zair_test['mensaje'] ); ?></span>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>

</div>
