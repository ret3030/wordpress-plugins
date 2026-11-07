/* global jQuery, wp, cgL10n */
( function ( $ ) {
	'use strict';

	var frames = {};

	function buildItem( slug, id, thumbUrl ) {
		var $li = $( '<li class="cg-item"></li>' ).attr( 'data-id', id );

		$( '<img>' ).attr( { src: thumbUrl, alt: '' } ).appendTo( $li );

		$( '<button type="button" class="cg-remove">&times;</button>' )
			.attr( 'aria-label', cgL10n.remove )
			.appendTo( $li );

		$( '<input type="hidden">' )
			.attr( {
				name: 'cg_galleries[' + slug + '][ids][]',
				value: id
			} )
			.appendTo( $li );

		return $li;
	}

	function makeSortable( $list ) {
		if ( $list.data( 'ui-sortable' ) ) {
			return;
		}
		$list.sortable( {
			items: '> li',
			cursor: 'move',
			placeholder: 'cg-item cg-placeholder',
			forcePlaceholderSize: true,
			tolerance: 'pointer'
		} );
	}

	$( function () {
		$( '.cg-items' ).each( function () {
			makeSortable( $( this ) );
		} );
	} );

	$( document ).on( 'click', '.cg-add', function ( e ) {
		e.preventDefault();

		var $list = $( this ).closest( '.cg-box' ).find( '.cg-items' );
		var slug = String( $list.data( 'slug' ) );

		if ( ! frames[ slug ] ) {
			frames[ slug ] = wp.media( {
				title: cgL10n.frameTitle,
				button: { text: cgL10n.frameButton },
				library: { type: 'image' },
				multiple: 'add'
			} );

			frames[ slug ].on( 'select', function () {
				frames[ slug ].state().get( 'selection' ).each( function ( attachment ) {
					var data = attachment.toJSON();

					// Duplicity přeskočíme.
					if ( $list.find( '[data-id="' + data.id + '"]' ).length ) {
						return;
					}

					var thumb = ( data.sizes && data.sizes.thumbnail )
						? data.sizes.thumbnail.url
						: data.url;

					$list.append( buildItem( slug, data.id, thumb ) );
				} );

				makeSortable( $list );
			} );
		}

		frames[ slug ].open();
	} );

	$( document ).on( 'click', '.cg-remove', function ( e ) {
		e.preventDefault();
		$( this ).closest( '.cg-item' ).remove();
	} );

	$( document ).on( 'click', '.cg-delete', function () {
		return window.confirm( cgL10n.confirm );
	} );
} )( jQuery );
