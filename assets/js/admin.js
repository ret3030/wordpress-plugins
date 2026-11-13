/* Formulář odznaku: přepínání ikona/text, výchozí barva a popisek podle ikony, živý náhled. */
jQuery( function ( $ ) {
	var $form = $( '#post' );
	var $text = $form.find( '[name="badge_settings[text]"]' );
	var $bg = $form.find( '[name="badge_settings[bg_color]"]' );
	var $fg = $form.find( '[name="badge_settings[text_color]"]' );
	var presetLabels = $form.find( '.epxb-pick input' ).map( function () { return $( this ).data( 'label' ); } ).get();

	function field( name ) {
		return $form.find( '[name="badge_settings[' + name + ']"]' );
	}

	function render() {
		var type = field( 'type' ).filter( ':checked' ).val();
		var position = field( 'position' ).val();
		var $badge = $( '<span class="epxb-badge"></span>' )
			.addClass( 'epxb-' + field( 'size' ).val() + ' epxb-' + field( 'shape' ).val() + ' epxb-type-' + type )
			.css( { '--epxb-bg': $bg.val(), '--epxb-fg': $fg.val() } );

		if ( type === 'icon' ) {
			$badge.attr( 'data-label', $text.val() ).append( field( 'icon' ).filter( ':checked' ).closest( '.epxb-pick' ).find( 'svg' ).clone() );
		} else {
			$badge.text( $text.val() );
		}

		$( '.epxb-only-icon' ).toggle( type === 'icon' );
		$( '.epxb-only-text' ).toggle( type === 'text' );
		$( '.epxb-stage .epxb-corner' ).attr( 'class', 'epxb-corner epxb-' + position ).empty().append( $badge );
	}

	$( '.epxb-color' ).wpColorPicker( { change: function () { setTimeout( render, 0 ); }, clear: function () { setTimeout( render, 0 ); } } );

	// Při výběru ikony se doplní její barva a popisek, ale jen pokud popisek není vlastní.
	field( 'icon' ).on( 'change', function () {
		var label = $text.val();
		if ( label === '' || presetLabels.indexOf( label ) !== -1 ) {
			$text.val( $( this ).data( 'label' ) );
			$bg.wpColorPicker( 'color', $( this ).data( 'color' ) );
		}
		render();
	} );

	$form.on( 'input change', '[name^="badge_settings"]', render );
	render();
} );
