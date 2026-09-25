/**
 * Zerox AI Recommendations - JS del panel admin (Fase 4).
 * Buscador AJAX de productos WooCommerce. Namespace propio: zairAdmin.
 */
( function () {
	'use strict';

	var zairAdmin = {

		init: function () {
			this.searchInput  = document.getElementById( 'zair-product-search' );
			this.resultsBox   = document.getElementById( 'zair-product-results' );
			this.hiddenId     = document.getElementById( 'zair_product_id' );
			this.selectedBox  = document.getElementById( 'zair-product-selected' );

			if ( ! this.searchInput ) {
				return;
			}

			this.timer = null;
			this.searchInput.addEventListener( 'input', this.onInput.bind( this ) );

			// Evitar que Enter envíe el formulario mientras se busca.
			this.searchInput.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key ) {
					e.preventDefault();
				}
			} );
		},

		onInput: function () {
			var self = this;
			clearTimeout( this.timer );
			var term = this.searchInput.value.trim();

			if ( term.length < 2 ) {
				this.resultsBox.innerHTML = '';
				return;
			}

			this.resultsBox.innerHTML = '<p class="zair-searching">Buscando productos…</p>';

			this.timer = setTimeout( function () {
				self.fetch( term );
			}, 350 );
		},

		fetch: function ( term ) {
			var self = this;
			var url  = zairAdminData.ajaxUrl +
				'?action=zair_search_products' +
				'&nonce=' + encodeURIComponent( zairAdminData.nonce ) +
				'&term=' + encodeURIComponent( term );

			fetch( url, { credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( ! json.success ) {
						self.resultsBox.innerHTML = '<p class="zair-searching">No se pudo buscar. Recarga la página e inténtalo de nuevo.</p>';
						return;
					}
					self.render( json.data );
				} )
				.catch( function () {
					self.resultsBox.innerHTML = '<p class="zair-searching">Error de conexión. Revisa tu red e inténtalo de nuevo.</p>';
				} );
		},

		render: function ( items ) {
			var self = this;

			if ( ! items.length ) {
				this.resultsBox.innerHTML = '<p class="zair-searching">Ningún producto coincide. Prueba con el SKU o parte del nombre.</p>';
				return;
			}

			var ul = document.createElement( 'ul' );
			ul.className = 'zair-product-results-list';

			items.forEach( function ( item ) {
				var li = document.createElement( 'li' );
				li.className = 'zair-product-result';
				li.setAttribute( 'tabindex', '0' );
				li.setAttribute( 'role', 'button' );

				li.innerHTML = '<strong>' + self.escape( item.nombre ) + '</strong>' +
					'<span class="zair-product-meta">SKU: ' + self.escape( item.sku ) +
					' · ' + self.escape( item.precio ) +
					' · ' + self.escape( item.stock ) + '</span>';

				var choose = function () { self.select( item ); };
				li.addEventListener( 'click', choose );
				li.addEventListener( 'keydown', function ( e ) {
					if ( 'Enter' === e.key || ' ' === e.key ) {
						e.preventDefault();
						choose();
					}
				} );

				ul.appendChild( li );
			} );

			this.resultsBox.innerHTML = '';
			this.resultsBox.appendChild( ul );
		},

		select: function ( item ) {
			this.hiddenId.value       = item.id;
			this.resultsBox.innerHTML = '';
			this.searchInput.value    = '';
			this.selectedBox.innerHTML =
				'<div class="zair-selected-product">' +
					'<strong>' + this.escape( item.nombre ) + '</strong>' +
					'<span class="zair-product-meta">ID: ' + item.id +
					' · SKU: ' + this.escape( item.sku ) + '</span>' +
				'</div>';
		},

		escape: function ( str ) {
			var div = document.createElement( 'div' );
			div.textContent = str === null || str === undefined ? '' : String( str );
			return div.innerHTML;
		}
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', zairAdmin.init.bind( zairAdmin ) );
	} else {
		zairAdmin.init();
	}
} )();
