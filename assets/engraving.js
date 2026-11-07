/**
 * Epoxis gravírování: počítadlo znaků, živý náhled textu ve stylech
 * a povinné pole jen při zaškrtnutém gravírování.
 */
document.querySelectorAll( '.epx-engr' ).forEach( function ( box ) {
	var toggle = box.querySelector( '[name="epx_engr"]' );
	var input = box.querySelector( '[name="epx_engr_text"]' );
	var count = box.querySelector( '[data-epx-count]' );
	var samples = box.querySelectorAll( '[data-epx-sample]' );

	function update() {
		var text = input.value.trim();
		count.textContent = input.value.length;
		samples.forEach( function ( sample ) {
			sample.textContent = text || sample.dataset.placeholder;
		} );
	}

	function syncRequired() {
		input.required = toggle.checked;
		if ( toggle.checked ) {
			input.focus();
		}
	}

	input.addEventListener( 'input', update );
	toggle.addEventListener( 'change', syncRequired );
	input.required = toggle.checked;
	update();
} );
