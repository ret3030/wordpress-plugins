( function ( $ ) {
	const config = window.velrionDpdPickup;
	const widgetOrigin = new URL( config.widgetUrl ).origin;
	let overlay = null;

	function deliveryCountry() {
		const toShipping = $( '#ship-to-different-address-checkbox' ).is( ':checked' );
		return $( toShipping ? '#shipping_country' : '#billing_country' ).val();
	}

	function openWidget( excludeProvider ) {
		const url = new URL( config.widgetUrl );
		url.searchParams.set( 'lang', config.lang );
		if ( deliveryCountry() ) {
			url.searchParams.set( 'countries', deliveryCountry() );
		}
		if ( excludeProvider ) {
			url.searchParams.set( 'excludeLockerProvider', excludeProvider );
		}

		overlay = $( '<div class="velrion-dpd-overlay"><iframe allow="geolocation"></iframe></div>' );
		overlay.find( 'iframe' ).attr( 'src', url.toString() );
		overlay.on( 'click', function ( event ) {
			if ( event.target === overlay[ 0 ] ) {
				closeWidget();
			}
		} );
		$( 'body' ).append( overlay );
	}

	function closeWidget() {
		if ( overlay ) {
			overlay.remove();
			overlay = null;
		}
	}

	// Tabulka dopravy se překresluje, text tlačítka se proto bere vždy ze skrytých polí.
	function render() {
		const label = $( '#velrion_dpd_point_label' ).val();
		$( '.velrion-dpd-selected' ).text( label || config.none );
		$( '.velrion-dpd-choose' ).text( label ? config.change : config.choose );
	}

	// Widget posílá vybrané místo jako { dpdWidget: { id, name, location: { address } } },
	// zavření křížkem jako { dpdWidget: { message: 'widgetClose' } }.
	window.addEventListener( 'message', function ( event ) {
		if ( event.origin !== widgetOrigin || ! event.data || ! event.data.dpdWidget ) {
			return;
		}
		const point = event.data.dpdWidget;
		if ( point.id ) {
			const address = point.location && point.location.address;
			const place = address ? ', ' + address.street + ', ' + address.zip + ' ' + address.city : '';
			$( '#velrion_dpd_point_id' ).val( point.id );
			$( '#velrion_dpd_point_label' ).val( point.name + place );
			render();
		}
		closeWidget();
	} );

	$( document.body ).on( 'click', '.velrion-dpd-choose', function ( event ) {
		event.preventDefault();
		openWidget( $( this ).data( 'exclude' ) );
	} );
	$( document.body ).on( 'updated_checkout', render );
	$( document ).on( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			closeWidget();
		}
	} );
} )( jQuery );
