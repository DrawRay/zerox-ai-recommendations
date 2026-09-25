/**
 * Zerox AI Recommendations — frontend (Fase 6).
 *
 * Pide las recomendaciones por REST tras cargar la página y pinta el
 * carrusel "Complementa tu motor". Sin jQuery ni librerías de carrusel:
 * scroll-snap nativo.
 *
 * Namespace propio (zairPublic) para no colisionar con Flatsome ni con
 * otros plugins.
 */
( function () {
	'use strict';

	if ( typeof zairData === 'undefined' ) {
		return;
	}

	var zairPublic = {

		init: function () {
			// Se busca por id y, si no aparece, por clase: así el bloque se
			// encuentra aunque el tema o el editor alteren el id al copiar
			// el shortcode.
			this.container = document.getElementById( 'zair-recommendations' )
				|| document.querySelector( '.zair-recommendations' )
				|| document.querySelector( '[id^="zair-recom"]' );

			if ( ! this.container ) {
				console.warn( 'Zerox AI: no se encontró el contenedor de recomendaciones en la página.' );
				return;
			}

			if ( ! zairData.productId ) {
				console.warn( 'Zerox AI: no se pudo identificar el producto de la ficha.' );
				return;
			}

			this.expanded        = false;
			this.data            = null;
			this.impressionsSent = false;

			/*
			 * Si el servidor ya pintó las tarjetas, no hace falta pedirlas
			 * otra vez: basta con activar la interacción y el registro de
			 * eventos sobre el HTML que ya está en la página.
			 */
			if ( this.container.querySelector( '.zair-card' ) ) {
				this.adoptarHtml();
				return;
			}

			this.load();
		},

		/* --------------------------------------------------------------
		 * Cotización acumulada
		 * ----------------------------------------------------------- */

		/**
		 * Enlaza los botones «+» y la barra de resumen.
		 *
		 * La selección vive solo en memoria durante la visita: no se
		 * guarda nada en el navegador porque la cotización se envía en
		 * el momento y no tiene sentido conservarla entre páginas.
		 */
		bindCotizacion: function () {
			var self = this;

			this.cesta = this.cesta || {};

			this.container.querySelectorAll( '.zair-add-quote' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {

					var id     = parseInt( btn.getAttribute( 'data-product-id' ), 10 );
					var nombre = btn.getAttribute( 'data-nombre' ) || '';

					if ( self.cesta[ id ] ) {
						delete self.cesta[ id ];
						btn.classList.remove( 'is-added' );
						btn.setAttribute( 'aria-pressed', 'false' );
						btn.querySelector( '.zair-add-icon' ).textContent = '+';
						btn.querySelector( '.zair-add-text' ).textContent = 'Añadir';
					} else {
						self.cesta[ id ] = nombre;
						btn.classList.add( 'is-added' );
						btn.setAttribute( 'aria-pressed', 'true' );
						btn.querySelector( '.zair-add-icon' ).textContent = '✓';
						btn.querySelector( '.zair-add-text' ).textContent = 'Añadido';

						var card = btn.closest( '.zair-card' );
						self.track( {
							event_type: 'click',
							recommended_product_id: id,
							position: card ? parseInt( card.getAttribute( 'data-position' ), 10 ) : 0
						} );
					}

					self.actualizarBarra();
				} );
			} );

			var vaciar = document.getElementById( 'zair-quote-clear' );
			if ( vaciar ) {
				vaciar.addEventListener( 'click', function () {
					self.cesta = {};
					self.container.querySelectorAll( '.zair-add-quote' ).forEach( function ( b ) {
						b.classList.remove( 'is-added' );
						b.setAttribute( 'aria-pressed', 'false' );
						b.querySelector( '.zair-add-icon' ).textContent = '+';
						b.querySelector( '.zair-add-text' ).textContent = 'Añadir';
					} );
					self.actualizarBarra();
				} );
			}

			var cta = document.getElementById( 'zair-quote-cta' );
			if ( cta ) {
				cta.addEventListener( 'click', function () {
					self.abrirCotizacion();
				} );
			}
		},

		/**
		 * Tras redibujar el carrusel, vuelve a marcar lo ya añadido.
		 */
		restaurarSeleccion: function () {
			var self = this;

			this.cesta = this.cesta || {};

			this.container.querySelectorAll( '.zair-add-quote' ).forEach( function ( btn ) {
				var id = parseInt( btn.getAttribute( 'data-product-id' ), 10 );

				if ( self.cesta[ id ] ) {
					btn.classList.add( 'is-added' );
					btn.setAttribute( 'aria-pressed', 'true' );
					btn.querySelector( '.zair-add-icon' ).textContent = '✓';
					btn.querySelector( '.zair-add-text' ).textContent = 'Añadido';
				}
			} );

			this.actualizarBarra();
		},

		actualizarBarra: function () {
			var barra = document.getElementById( 'zair-quote-bar' );
			var texto = document.getElementById( 'zair-quote-count' );

			var ids = Object.keys( this.cesta || {} ).map( function ( x ) {
				return parseInt( x, 10 );
			} );

			/*
			 * La selección queda accesible de forma global para que el
			 * formulario de cotización la recoja se abra desde donde se
			 * abra: desde la barra del carrusel o desde el botón propio de
			 * la ficha de producto. Sin esto, pulsar el botón de la ficha
			 * enviaba la solicitud solo con el producto principal.
			 */
			window.zairQuoteItems = ids;

			if ( ! barra || ! texto ) {
				return;
			}

			var n = ids.length;

			barra.hidden      = ( 0 === n );
			texto.textContent = ( 1 === n )
				? '1 repuesto añadido'
				: n + ' repuestos añadidos';
		},

		/**
		 * Abre el formulario de cotización con todo lo acumulado.
		 */
		abrirCotizacion: function () {

			var ids = Object.keys( this.cesta || {} ).map( function ( x ) {
				return parseInt( x, 10 );
			} );

			if ( ! ids.length ) {
				return;
			}

			this.track( { event_type: 'lead_open' } );

			if ( window.zqpOpen ) {
				window.zqpOpen( { extraProducts: ids } );
				return;
			}

			// Sin el plugin de cotizaciones, se abre el botón de la ficha.
			var boton = document.querySelector( '[data-zqp-open]' );

			if ( boton ) {
				boton.click();
			}
		},

		/**
		 * Toma el HTML impreso por el servidor y lo deja operativo.
		 */
		adoptarHtml: function () {
			var self  = this;
			var items = [];

			this.container.querySelectorAll( '.zair-card' ).forEach( function ( card ) {
				items.push( {
					id: parseInt( card.getAttribute( 'data-product-id' ), 10 ),
					position: parseInt( card.getAttribute( 'data-position' ), 10 ) || 0,
					score: parseFloat( card.getAttribute( 'data-score' ) ) || 0
				} );
			} );

			// Datos mínimos para el registro de eventos y la captura de leads.
			this.data = {
				items: items,
				vehicle_engine_id: parseInt( this.container.getAttribute( 'data-vehicle-id' ), 10 ) || 0,
				session_id: '',
				lead_nonce: ''
			};

			this.bind();
			this.bindCotizacion();
			this.trackImpressions();

			// El identificador de sesión y el nonce del formulario llegan
			// con la primera petición al servidor, que además confirma
			// precios y stock del momento.
			this.sincronizar();
		},

		/**
		 * Pide los datos actuales para completar sesión y nonce.
		 */
		sincronizar: function () {
			var self = this;

			fetch( zairData.restUrl + '?product_id=' + encodeURIComponent( zairData.productId ), {
				credentials: 'same-origin'
			} )
				.then( function ( r ) { return r.ok ? r.json() : null; } )
				.then( function ( json ) {
					if ( ! json || ! json.has_recommendations ) {
						return;
					}
					self.data.session_id        = json.session_id;
					self.data.lead_nonce        = json.lead_nonce;
					self.data.vehicle_engine_id = json.vehicle_engine_id;
				} )
				.catch( function () {} );
		},

		/* --------------------------------------------------------------
		 * Datos
		 * ----------------------------------------------------------- */

		load: function ( limit ) {
			var self = this;
			var url  = zairData.restUrl +
				'?product_id=' + encodeURIComponent( zairData.productId ) +
				( limit ? '&limit=' + encodeURIComponent( limit ) : '' );

			fetch( url, { credentials: 'same-origin' } )
				.then( function ( r ) {
					return r.ok ? r.json() : null;
				} )
				.then( function ( json ) {

					if ( ! json ) {
						console.warn( 'Zerox AI: el servidor no devolvió datos. Revisa que la API REST esté accesible.' );
						return;
					}

					if ( ! json.has_recommendations || ! json.items.length ) {
						console.info( 'Zerox AI: este producto no tiene recomendaciones disponibles. Revisa sus compatibilidades verificadas, el stock y la publicación de los productos relacionados.' );
						return;
					}
					self.data = json;
					self.render( json );
				} )
				.catch( function ( error ) {
					// El cliente no ve ningún error: una recomendación que
					// falla no debe ensuciar la ficha. El aviso queda en la
					// consola para poder diagnosticarlo.
					console.warn( 'Zerox AI: falló la carga de recomendaciones.', error );
				} );
		},

		/* --------------------------------------------------------------
		 * Render
		 * ----------------------------------------------------------- */

		render: function ( data ) {

			var t = zairData.i18n;

			var icono = zairData.mostrarIcono
				? '<svg class="zair-title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">' +
					'<circle cx="12" cy="12" r="3.2"></circle>' +
					'<path d="M12 2.6v3M12 18.4v3M2.6 12h3M18.4 12h3M5.4 5.4l2.1 2.1M16.5 16.5l2.1 2.1M18.6 5.4l-2.1 2.1M7.5 16.5l-2.1 2.1"></path>' +
				'</svg>'
				: '';

			var html = '<div class="zair-header">' +
					'<h2 class="zair-title">' + icono + this.escape( data.titulo ) + '</h2>';

			if ( data.subtitulo || data.vehiculo ) {
				html += '<p class="zair-subtitle">' +
					this.escape( data.subtitulo );

				if ( data.vehiculo ) {
					html += ( data.subtitulo ? '<br>' : '' ) +
						'<span class="zair-vehicle">' + this.escape( data.vehiculo ) + '</span>';
				}

				html += '</p>';
			}

			html += '</div>';

			var esCombo = ( 'combo' === zairData.layout );

			html += '<div class="zair-carousel-wrap' + ( esCombo ? ' is-combo' : '' ) + '">' +
				'<button type="button" class="zair-nav zair-nav-prev" aria-label="' + this.escape( t.anterior ) + '" hidden>‹</button>' +
				'<ul class="zair-carousel' + ( esCombo ? ' zair-combo' : '' ) + '" id="zair-carousel">';

			data.items.forEach( function ( item ) {
				html += esCombo ? zairPublic.comboCardHtml( item ) : zairPublic.cardHtml( item );
			} );

			html += '</ul>' +
				'<button type="button" class="zair-nav zair-nav-next" aria-label="' + this.escape( t.siguiente ) + '" hidden>›</button>' +
				'</div>';

			// Barra de acción del modo selección múltiple.
			if ( esCombo ) {
				html += '<div class="zair-combo-bar" id="zair-combo-bar" hidden>' +
						'<span class="zair-combo-count" id="zair-combo-count"></span>' +
						'<button type="button" class="zair-btn zair-btn-primary zair-combo-btn" id="zair-combo-btn">' +
							this.escape( zairData.quoteText ) +
						'</button>' +
					'</div>';
			}

			if ( data.hay_mas && ! this.expanded ) {
				html += '<div class="zair-footer">' +
					'<button type="button" class="zair-more" id="zair-more">' + this.escape( t.verMas ) + '</button>' +
					'</div>';
			}

			// El formulario propio de consulta solo se imprime si está
			// activado: con el botón de cotización en cada tarjeta resulta
			// redundante, y dos formularios compiten entre sí.
			if ( zairData.mostrarLead ) {
				html += this.leadHtml();
			}

			this.container.innerHTML = html;

			// Por compatibilidad con instalaciones anteriores que aún
			// tuvieran el atributo en el HTML.
			this.container.removeAttribute( 'hidden' );

			// El número de tarjetas visibles se configura en el panel y
			// se aplica como variable CSS: el ancho lo calcula la hoja
			// de estilos, sin recalcular nada en cada redimensionado.
			this.container.style.setProperty( '--zair-cols', parseInt( zairData.cols, 10 ) || 5 );

			this.bind();
			this.bindCotizacion();
			this.restaurarSeleccion();
		},

		/**
		 * Tarjeta compacta con casilla, para el modo de selección múltiple.
		 * Toda la tarjeta actúa como etiqueta: en móvil acertar sobre una
		 * casilla de 16 px es incómodo.
		 */
		comboCardHtml: function ( item ) {
			var self = zairPublic;
			var t    = zairData.i18n;
			var id   = 'zair-chk-' + parseInt( item.id, 10 );

			var html = '<li class="zair-ccard" data-product-id="' + parseInt( item.id, 10 ) +
				'" data-position="' + parseInt( item.position, 10 ) + '">' +
				'<label class="zair-ccard-inner" for="' + id + '">' +
					'<input type="checkbox" class="zair-ccard-chk" id="' + id + '"' +
						' data-product-id="' + parseInt( item.id, 10 ) + '"' +
						' data-nombre="' + self.escapeAttr( item.nombre ) + '">' +
					'<span class="zair-ccard-media">';

			if ( item.en_oferta ) {
				html += '<span class="zair-ccard-flag">' + self.escape( t.oferta ) + '</span>';
			}

			html += '<img src="' + self.escapeAttr( item.imagen ) + '" alt="" loading="lazy">' +
					'</span>' +
					'<span class="zair-ccard-name">' + self.escape( item.nombre ) + '</span>';

			if ( item.codigo_motor ) {
				html += '<span class="zair-ccard-badge">✓ ' + self.escape( item.codigo_motor ) + '</span>';
			}

			html += '<span class="zair-ccard-price">' + item.precio_html + '</span>' +
				'</label>' +
				'<a class="zair-ccard-link" href="' + self.escapeAttr( item.url ) + '">' +
					self.escape( t.verProducto ) +
				'</a>' +
			'</li>';

			return html;
		},

		cardHtml: function ( item ) {
			var t    = zairData.i18n;
			var self = zairPublic;

			var html = '<li class="zair-card" data-product-id="' + parseInt( item.id, 10 ) +
				'" data-position="' + parseInt( item.position, 10 ) + '">';

			html += '<a class="zair-card-media" href="' + self.escapeAttr( item.url ) + '">';

			if ( item.en_oferta ) {
				html += '<span class="zair-flag-oferta">' + self.esc( t.oferta ) + '</span>';
			}

			html += '<img src="' + self.escapeAttr( item.imagen ) + '" alt="' +
				self.escapeAttr( item.nombre ) + '" loading="lazy"></a>';

			html += '<div class="zair-card-body">' +
				'<h3 class="zair-card-name"><a href="' + self.escapeAttr( item.url ) + '">' +
					self.esc( item.nombre ) + '</a></h3>';

			if ( item.codigo_motor && zairData.mostrarBadge ) {
				html += '<span class="zair-badge-compatible">✓ ' +
					self.esc( t.compatible ) + ' ' + self.esc( item.codigo_motor ) + '</span>';
			}

			html += '<div class="zair-card-price">' + item.precio_html + '</div>';

			html += '<div class="zair-card-actions">';

			// Comprar: solo si el producto se puede adquirir directamente.
			if ( zairData.mostrarComprar && item.comprable ) {
				html += '<button type="button" class="zair-btn zair-btn-primary zair-add" data-product-id="' +
					parseInt( item.id, 10 ) + '">' + self.esc( t.comprar ) + '</button>';
			}

			// Cotizar: abre el formulario con este repuesto.
			if ( zairData.mostrarCotizar ) {
				html += '<button type="button" class="zair-add-quote" data-product-id="' +
					parseInt( item.id, 10 ) + '" data-nombre="' + self.escapeAttr( item.nombre ) +
					'" aria-pressed="false" title="Añadir a mi cotización">' +
					'<span class="zair-add-icon" aria-hidden="true">+</span>' +
					'<span class="zair-add-text">Añadir</span>' +
				'</button>';
			}

			// Si no hay ninguna acción activa, al menos el enlace al producto.
			if ( ! zairData.mostrarCotizar && ( ! zairData.mostrarComprar || ! item.comprable ) ) {
				html += '<a class="zair-btn" href="' + self.escapeAttr( item.url ) + '">' +
					self.esc( t.verProducto ) + '</a>';
			}

			html += '</div></div></li>';

			return html;
		},

		/* --------------------------------------------------------------
		 * Interacción
		 * ----------------------------------------------------------- */

		bind: function () {
			var self     = this;
			var carousel = document.getElementById( 'zair-carousel' );

			// Botón "Ver más compatibles".
			var more = document.getElementById( 'zair-more' );
			if ( more ) {
				more.addEventListener( 'click', function () {
					self.expanded = true;
					// Los productos que aparecen al expandir no se habían
					// mostrado antes: sin esto quedaban con clics pero sin
					// impresiones, y su CTR salía en cero.
					self.impressionsSent = false;
					self.vistos          = self.vistos || {};
					self.load( 24 );
				} );
			}

			// Flechas de navegación.
			var prev = this.container.querySelector( '.zair-nav-prev' );
			var next = this.container.querySelector( '.zair-nav-next' );

			if ( carousel && prev && next ) {
				var step = function () {
					var card = carousel.querySelector( '.zair-card' );
					return card ? card.offsetWidth + 16 : 260;
				};

				prev.addEventListener( 'click', function () {
					carousel.scrollBy( { left: -step(), behavior: 'smooth' } );
				} );

				next.addEventListener( 'click', function () {
					carousel.scrollBy( { left: step(), behavior: 'smooth' } );
				} );

				var updateNav = function () {
					var overflow = carousel.scrollWidth > carousel.clientWidth + 4;
					prev.hidden  = ! overflow || carousel.scrollLeft <= 2;
					next.hidden  = ! overflow ||
						( carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 2 );
				};

				carousel.addEventListener( 'scroll', updateNav, { passive: true } );
				window.addEventListener( 'resize', updateNav );
				updateNav();
			}

			// Clic en imagen o nombre del producto recomendado.
			this.container.querySelectorAll( '.zair-card' ).forEach( function ( card ) {
				var links = card.querySelectorAll( 'a[href]' );
				links.forEach( function ( link ) {
					link.addEventListener( 'click', function () {
						self.track( {
							event_type: 'click',
							recommended_product_id: parseInt( card.getAttribute( 'data-product-id' ), 10 ),
							position: parseInt( card.getAttribute( 'data-position' ), 10 )
						} );
					} );
				} );
			} );

			// Botones de cotización.
			this.container.querySelectorAll( '.zair-quote' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var id     = parseInt( btn.getAttribute( 'data-product-id' ), 10 );
					var nombre = btn.getAttribute( 'data-product-name' );
					var fila   = btn.closest( '.zair-card' );

					self.track( {
						event_type: 'click',
						recommended_product_id: id,
						position: parseInt( fila.getAttribute( 'data-position' ), 10 )
					} );

					self.track( { event_type: 'lead_open' } );

					if ( window.zqpOpen ) {
						window.zqpOpen( { productId: id, productName: nombre } );
						return;
					}

					// Sin el plugin de cotizaciones, se lleva al producto.
					window.location.href = btn.closest( '.zair-card' ).querySelector( 'a' ).href;
				} );
			} );

			// Botones "Comprar".
			this.container.querySelectorAll( '.zair-add' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					self.addToCart( btn );
				} );
			} );

			this.bindCombo();
			if ( zairData.mostrarLead ) {
				this.bindLead();
			}

			this.trackImpressions();
		},

		/**
		 * Selección múltiple: la barra inferior aparece solo cuando hay
		 * algo marcado, para no ocupar espacio sin motivo.
		 */
		bindCombo: function () {
			var self = this;

			if ( 'combo' !== zairData.layout ) {
				return;
			}

			var barra  = document.getElementById( 'zair-combo-bar' );
			var cuenta = document.getElementById( 'zair-combo-count' );
			var boton  = document.getElementById( 'zair-combo-btn' );

			if ( ! barra || ! boton ) {
				return;
			}

			var casillas = this.container.querySelectorAll( '.zair-ccard-chk' );

			var refrescar = function () {
				var marcados = self.seleccionados();

				barra.hidden = ( 0 === marcados.length );

				cuenta.textContent = ( 1 === marcados.length )
					? '1 repuesto seleccionado'
					: marcados.length + ' repuestos seleccionados';

				Array.prototype.forEach.call( casillas, function ( c ) {
					c.closest( '.zair-ccard' ).classList.toggle( 'is-checked', c.checked );
				} );
			};

			Array.prototype.forEach.call( casillas, function ( c ) {
				c.addEventListener( 'change', function () {
					refrescar();

					if ( c.checked ) {
						self.track( {
							event_type: 'click',
							recommended_product_id: parseInt( c.getAttribute( 'data-product-id' ), 10 )
						} );
					}
				} );
			} );

			boton.addEventListener( 'click', function () {
				self.cotizarSeleccion();
			} );

			refrescar();
		},

		seleccionados: function () {
			var lista = [];

			this.container.querySelectorAll( '.zair-ccard-chk' ).forEach( function ( c ) {
				if ( c.checked ) {
					lista.push( {
						id: parseInt( c.getAttribute( 'data-product-id' ), 10 ),
						nombre: c.getAttribute( 'data-nombre' )
					} );
				}
			} );

			return lista;
		},

		/**
		 * Abre el formulario de cotización con los repuestos marcados.
		 *
		 * Si el plugin de cotizaciones está activo, se le pasan los
		 * productos y el cliente pide una sola cotización del conjunto.
		 * Si no lo está, se recurre al formulario de leads del propio
		 * carrusel para no dejar al visitante sin salida.
		 */
		cotizarSeleccion: function () {
			var marcados = this.seleccionados();

			if ( ! marcados.length ) {
				return;
			}

			this.track( {
				event_type: 'compatibility_query',
				recommended_product_id: marcados[0].id
			} );

			if ( window.zqp && typeof window.zqp.openWith === 'function' ) {
				window.zqp.openWith( {
					extras: marcados,
					origen: 'combo'
				} );
				return;
			}

			// Respaldo: desplegar el formulario propio.
			var toggle = document.getElementById( 'zair-lead-toggle' );

			if ( toggle && 'true' !== toggle.getAttribute( 'aria-expanded' ) ) {
				toggle.click();
				toggle.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
		},

		/**
		 * Añade al carrito usando el endpoint AJAX nativo de WooCommerce.
		 * Marca el item para poder atribuir la venta en la Fase 8.
		 */
		addToCart: function ( btn ) {
			var self      = this;
			var t         = zairData.i18n;
			var productId = parseInt( btn.getAttribute( 'data-product-id' ), 10 );

			if ( ! productId || btn.disabled ) {
				return;
			}

			this.track( {
				event_type: 'click',
				recommended_product_id: productId
			} );

			var original   = btn.textContent;
			btn.disabled   = true;
			btn.textContent = t.agregando;

			var body = new URLSearchParams();
			body.append( 'product_id', productId );
			body.append( 'quantity', 1 );
			body.append( 'zair_rec', '1' );
			body.append( 'zair_origin', zairData.productId );

			fetch( '/?wc-ajax=add_to_cart', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString()
			} )
				.then( function ( r ) {
					return r.ok ? r.json() : null;
				} )
				.then( function ( json ) {
					if ( json && json.error ) {
						throw new Error( 'wc error' );
					}

					btn.textContent = t.agregado;
					btn.classList.remove( 'zair-btn-primary' );
					btn.classList.add( 'zair-btn-added' );

					self.showCartLink( btn );

					// Refresca minicarrito/contadores del tema.
					document.body.dispatchEvent( new CustomEvent( 'wc_fragment_refresh' ) );
					if ( window.jQuery ) {
						window.jQuery( document.body ).trigger( 'wc_fragment_refresh' );
						window.jQuery( document.body ).trigger( 'added_to_cart' );
					}
				} )
				.catch( function () {
					btn.disabled    = false;
					btn.textContent = original;
					window.location.href = '/?add-to-cart=' + productId;
				} );
		},

		showCartLink: function ( btn ) {
			var actions = btn.parentNode;

			if ( actions.querySelector( '.zair-cart-link' ) ) {
				return;
			}

			var link = document.createElement( 'a' );
			link.className   = 'zair-cart-link';
			link.href        = zairData.cartUrl;
			link.textContent = zairData.i18n.verCarrito;
			actions.appendChild( link );
		},

		/* --------------------------------------------------------------
		 * Registro de eventos (Fase 8)
		 * ----------------------------------------------------------- */

		/**
		 * Envía un evento al backend. Usa sendBeacon cuando está
		 * disponible para no retrasar la navegación del usuario.
		 */
		track: function ( payload ) {
			if ( ! this.data ) {
				return;
			}

			payload.session_id        = this.data.session_id;
			payload.product_origin_id = zairData.productId;
			payload.vehicle_engine_id = this.data.vehicle_engine_id;

			// Espejo anónimo en GA4 (nunca datos personales).
			this.trackGA4( payload );

			var body = JSON.stringify( payload );

			if ( navigator.sendBeacon ) {
				try {
					navigator.sendBeacon( zairData.eventUrl, new Blob( [ body ], { type: 'application/json' } ) );
					return;
				} catch ( e ) {
					// Continúa con fetch.
				}
			}

			fetch( zairData.eventUrl, {
				method: 'POST',
				credentials: 'same-origin',
				keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body: body
			} ).catch( function () {} );
		},


		/**
		 * Emite el evento a GA4 si está habilitado y gtag existe.
		 * Solo viajan identificadores de producto, posición y motor:
		 * ningún dato personal, ni siquiera al enviar un lead.
		 */
		trackGA4: function ( payload ) {

			if ( ! zairData.ga4 || ! zairData.ga4.enabled ) {
				return;
			}

			var nombre = zairData.ga4.eventos[ payload.event_type ];

			if ( ! nombre || typeof window.gtag !== 'function' ) {
				return;
			}

			var params = {
				zair_session: payload.session_id,
				zair_origin_product: payload.product_origin_id,
				zair_vehicle: payload.vehicle_engine_id
			};

			if ( payload.recommended_product_id ) {
				params.zair_product = payload.recommended_product_id;
			}
			if ( payload.position ) {
				params.zair_position = payload.position;
			}
			if ( payload.items && payload.items.length ) {
				params.zair_items = payload.items.length;
			}

			try {
				window.gtag( 'event', nombre, params );
			} catch ( e ) {
				// GA4 no debe romper nunca la experiencia del usuario.
			}
		},

		/**
		 * Registra la impresión cuando el carrusel entra en pantalla.
		 * Si no se llega a ver, no cuenta como impresión: así el CTR
		 * refleja lo que el usuario realmente tuvo delante.
		 */
		trackImpressions: function () {
			var self = this;

			if ( ! this.data ) {
				return;
			}

			// Registro de los productos ya contados: al pulsar «Ver más»
			// aparecen productos nuevos que también deben contar como
			// impresión. Sin esto acumulaban clics sin impresiones y su
			// CTR salía en cero.
			this.vistos = this.vistos || {};

			var send = function () {

				var nuevos = self.data.items.filter( function ( item ) {
					return ! self.vistos[ item.id ];
				} );

				if ( ! nuevos.length ) {
					return;
				}

				nuevos.forEach( function ( item ) {
					self.vistos[ item.id ] = true;
				} );

				self.impressionsSent = true;

				self.track( {
					event_type: 'impression',
					items: nuevos.map( function ( item ) {
						return {
							id: item.id,
							position: item.position,
							score: item.score
						};
					} )
				} );
			};

			// Tras expandir, el carrusel ya está visible: se envían de una vez.
			if ( this.impressionsSent ) {
				send();
				return;
			}

			if ( ! ( 'IntersectionObserver' in window ) ) {
				send();
				return;
			}

			var observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						send();
						observer.disconnect();
					}
				} );
			}, { threshold: 0.35 } );

			observer.observe( this.container );
		},

		/* --------------------------------------------------------------
		 * Captura de leads (Fase 7)
		 * ----------------------------------------------------------- */

		/**
		 * Bloque colapsado. El formulario no aparece hasta que la persona
		 * muestra interés: primero recibe las recomendaciones.
		 */
		leadHtml: function () {
			var t = zairData.i18n;

			return '<div class="zair-lead">' +
					'<button type="button" class="zair-lead-toggle" id="zair-lead-toggle" aria-expanded="false" aria-controls="zair-lead-panel">' +
						'<span class="zair-lead-question">' + this.escape( t.leadPregunta ) + '</span>' +
						'<span class="zair-lead-cta">' + this.escape( t.leadBoton ) + '</span>' +
					'</button>' +
					'<div class="zair-lead-panel" id="zair-lead-panel" hidden>' +
						'<div class="zair-lead-inner">' +
							'<div class="zair-field">' +
								'<label for="zair-lead-nombre">' + this.escape( t.leadNombre ) + '</label>' +
								'<input type="text" id="zair-lead-nombre" autocomplete="name" maxlength="120" required>' +
							'</div>' +
							'<div class="zair-field">' +
								'<label for="zair-lead-whatsapp">' + this.escape( t.leadWhatsapp ) + '</label>' +
								'<input type="tel" id="zair-lead-whatsapp" autocomplete="tel" inputmode="tel" maxlength="20" placeholder="999 999 999" required>' +
							'</div>' +
							'<label class="zair-consent">' +
								'<input type="checkbox" id="zair-lead-consent">' +
								'<span>' + this.escape( t.leadConsentimiento ) + '</span>' +
							'</label>' +
							'<div class="zair-honeypot" aria-hidden="true">' +
								'<label for="zair-website">No rellenar</label>' +
								'<input type="text" id="zair-website" tabindex="-1" autocomplete="off">' +
							'</div>' +
							'<button type="button" class="zair-btn zair-btn-primary zair-lead-submit" id="zair-lead-submit">' +
								this.escape( t.leadEnviar ) +
							'</button>' +
							'<p class="zair-lead-msg" id="zair-lead-msg" role="status"></p>' +
						'</div>' +
					'</div>' +
				'</div>';
		},

		bindLead: function () {
			var self   = this;
			var toggle = document.getElementById( 'zair-lead-toggle' );
			var panel  = document.getElementById( 'zair-lead-panel' );
			var submit = document.getElementById( 'zair-lead-submit' );

			if ( ! toggle || ! panel ) {
				return;
			}

			toggle.addEventListener( 'click', function () {
				var open = 'true' === toggle.getAttribute( 'aria-expanded' );

				if ( open ) {
					panel.style.maxHeight = '0px';
					toggle.setAttribute( 'aria-expanded', 'false' );
					window.setTimeout( function () {
						panel.hidden = true;
					}, 260 );
					return;
				}

				panel.hidden = false;
				toggle.setAttribute( 'aria-expanded', 'true' );
				self.track( { event_type: 'lead_open' } );

				// Despliegue suave: se mide el contenido real.
				window.requestAnimationFrame( function () {
					panel.style.maxHeight = panel.scrollHeight + 'px';
					var nombre = document.getElementById( 'zair-lead-nombre' );
					if ( nombre ) {
						nombre.focus();
					}
				} );
			} );

			if ( submit ) {
				submit.addEventListener( 'click', function () {
					self.submitLead( submit );
				} );
			}
		},

		submitLead: function ( btn ) {
			var self     = this;
			var t        = zairData.i18n;
			var nombre   = document.getElementById( 'zair-lead-nombre' );
            var whatsapp = document.getElementById( 'zair-lead-whatsapp' );
			var consent  = document.getElementById( 'zair-lead-consent' );
			var website  = document.getElementById( 'zair-website' );
			var msg      = document.getElementById( 'zair-lead-msg' );

			msg.className   = 'zair-lead-msg';
			msg.textContent = '';

			// Validación en cliente (el servidor vuelve a validar todo).
			if ( ! nombre.value.trim() || nombre.value.trim().length < 2 ) {
				return self.leadError( msg, t.leadErrNombre, nombre );
			}
			if ( whatsapp.value.replace( /\D/g, '' ).length < 6 ) {
				return self.leadError( msg, t.leadErrWhatsapp, whatsapp );
			}
			if ( ! consent.checked ) {
				return self.leadError( msg, t.leadErrConsent, consent );
			}

			btn.disabled    = true;
			btn.textContent = t.leadEnviando;

			fetch( zairData.leadUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					nonce: self.data ? self.data.lead_nonce : '',
					session_id: self.data ? self.data.session_id : '',
					nombre: nombre.value,
					whatsapp: whatsapp.value,
					consentimiento: true,
					zair_website: website ? website.value : '',
					product_origin_id: zairData.productId,
					vehicle_engine_id: self.data ? self.data.vehicle_engine_id : 0
				} )
			} )
				.then( function ( r ) {
					return r.json().then( function ( json ) {
						return { ok: r.ok, json: json };
					} );
				} )
				.then( function ( res ) {
					if ( ! res.ok || ! res.json.success ) {
						btn.disabled    = false;
						btn.textContent = t.leadEnviar;
						self.leadError( msg, res.json.message || t.error );
						return;
					}
					self.track( { event_type: 'lead_submit' } );
					self.leadSuccess( res.json.message );
				} )
				.catch( function () {
					btn.disabled    = false;
					btn.textContent = t.leadEnviar;
					self.leadError( msg, t.error );
				} );
		},

		leadError: function ( msg, text, field ) {
			msg.className   = 'zair-lead-msg zair-lead-msg-error';
			msg.textContent = text;
			if ( field ) {
				field.focus();
			}
		},

		leadSuccess: function ( text ) {
			var panel = document.getElementById( 'zair-lead-panel' );
			var toggle = document.getElementById( 'zair-lead-toggle' );

			if ( toggle ) {
				toggle.hidden = true;
			}

			panel.innerHTML = '<div class="zair-lead-inner zair-lead-done">' +
				'<p class="zair-lead-done-title">' + this.escape( zairData.i18n.leadGracias ) + '</p>' +
				'<p class="zair-lead-msg zair-lead-msg-ok">' + this.escape( text ) + '</p>' +
				'</div>';
			panel.hidden          = false;
			panel.style.maxHeight = panel.scrollHeight + 'px';
		},

		/* --------------------------------------------------------------
		 * Utilidades
		 * ----------------------------------------------------------- */

		escape: function ( str ) {
			var div = document.createElement( 'div' );
			div.textContent = ( str === null || str === undefined ) ? '' : String( str );
			return div.innerHTML;
		},

		escapeAttr: function ( str ) {
			return this.escape( str ).replace( /"/g, '&quot;' );
		}
	};

	/*
	 * Arranque tolerante. Algunos constructores visuales y los plugins de
	 * caché inyectan el contenido después de DOMContentLoaded, con lo que
	 * el contenedor aún no existía cuando el script se ejecutaba y la
	 * sección quedaba vacía. Se reintenta durante unos segundos y se
	 * vuelve a probar al terminar de cargar la página.
	 */
	var intentos = 0;

	function arrancar() {

		if ( zairPublic.iniciado ) {
			return;
		}

		var hay = document.getElementById( 'zair-recommendations' )
			|| document.querySelector( '.zair-recommendations' )
			|| document.querySelector( '[id^="zair-recom"]' );

		if ( hay ) {
			zairPublic.iniciado = true;
			zairPublic.init();
			return;
		}

		intentos++;

		if ( intentos < 20 ) {
			window.setTimeout( arrancar, 250 );
		} else {
			console.warn( 'Zerox AI: el contenedor de recomendaciones no apareció en la página. Comprueba que el shortcode [zair_recommendations] esté colocado en la plantilla del producto.' );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', arrancar );
	} else {
		arrancar();
	}

	window.addEventListener( 'load', arrancar );

	// Disponible para diagnóstico desde la consola del navegador.
	window.zairPublic = zairPublic;
} )();
