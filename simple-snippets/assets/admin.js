/* global jQuery, SSNIP, wp */
jQuery( function ( $ ) {
	'use strict';

	// Code editor (CodeMirror bundled with WordPress).
	var cm = null;
	if ( SSNIP.editor && wp.codeEditor ) {
		cm = wp.codeEditor.initialize( 'ssnip-code', SSNIP.editor ).codemirror;
		var setMode = function () {
			var js = $( 'input[name="ssnip[type]"]:checked' ).val() === 'js';
			var code = cm.getValue();
			// A JS snippet written with its own <script> tag is still HTML-ish.
			cm.setOption( 'mode', js && ! /<script[\s>]/i.test( code ) ? 'javascript' : 'htmlmixed' );
		};
		$( 'input[name="ssnip[type]"]' ).on( 'change', setMode );
		setMode();
	}

	// Show post type / item rules only in "Only where I choose" mode.
	$( 'input[name="ssnip[scope]"]' ).on( 'change', function () {
		$( '.ssnip-selected' ).prop( 'hidden', this.value !== 'selected' );
	} );

	// Content pickers.
	$( '.ssnip-picker' ).each( function () {
		var $picker = $( this ),
			field = $picker.data( 'field' ),
			$search = $picker.find( '.ssnip-search' ),
			$results = $picker.find( '.ssnip-results' ),
			$chips = $picker.find( '.ssnip-chips' ),
			timer = null,
			xhr = null;

		function has( id ) {
			return $chips.find( 'input[value="' + id + '"]' ).length > 0;
		}

		function add( item ) {
			if ( has( item.id ) ) {
				return;
			}
			var $li = $( '<li/>' );
			$( '<input type="hidden"/>' ).attr( 'name', 'ssnip[' + field + '][]' ).val( item.id ).appendTo( $li );
			$li.append( document.createTextNode( item.label + ' ' ) );
			$( '<button type="button" class="ssnip-remove">&times;</button>' ).attr( 'aria-label', SSNIP.i18n.remove ).appendTo( $li );
			$chips.append( $li );
		}

		$search.on( 'input', function () {
			var q = $.trim( $search.val() );
			clearTimeout( timer );
			if ( q.length < 2 && ! /^\d+$/.test( q ) ) {
				$results.empty().prop( 'hidden', true );
				return;
			}
			timer = setTimeout( function () {
				if ( xhr ) {
					xhr.abort();
				}
				xhr = $.get( SSNIP.ajaxUrl, { action: 'ssnip_search_posts', nonce: SSNIP.nonce, q: q } ).done( function ( res ) {
					$results.empty();
					var items = res && res.success ? res.data : [];
					if ( ! items.length ) {
						$( '<li class="ssnip-empty"/>' ).text( SSNIP.i18n.noResults ).appendTo( $results );
					}
					$.each( items, function ( i, item ) {
						$( '<li tabindex="0"/>' ).text( item.label ).data( 'item', item ).toggleClass( 'is-added', has( item.id ) ).appendTo( $results );
					} );
					$results.prop( 'hidden', false );
				} );
			}, 250 );
		} );

		$results.on( 'click keydown', 'li:not(.ssnip-empty)', function ( e ) {
			if ( e.type === 'keydown' && e.key !== 'Enter' ) {
				return;
			}
			e.preventDefault();
			add( $( this ).data( 'item' ) );
			$( this ).addClass( 'is-added' );
		} );

		// Don't submit the post form when pressing Enter in the search field.
		$search.on( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
			}
			if ( e.key === 'Escape' ) {
				$results.prop( 'hidden', true );
			}
		} );

		$( document ).on( 'click', function ( e ) {
			if ( ! $picker[ 0 ].contains( e.target ) ) {
				$results.prop( 'hidden', true );
			}
		} );

		$chips.on( 'click', '.ssnip-remove', function () {
			$( this ).closest( 'li' ).remove();
		} );
	} );
} );
