( function () {
	'use strict';

	// Confirmation prompts for destructive actions.
	document.addEventListener( 'click', function ( e ) {
		var el = e.target.closest( '.dutyplan-confirm' );
		if ( el && ! window.confirm( el.getAttribute( 'data-confirm' ) ) ) {
			e.preventDefault();
		}
	} );

	// Spot form: show only the fields relevant to the chosen frequency / all-day setting.
	var form = document.querySelector( '.dutyplan-spot-form' );
	if ( ! form ) {
		return;
	}

	function toggle( selector, show ) {
		form.querySelectorAll( selector ).forEach( function ( el ) {
			el.style.display = show ? '' : 'none';
			// Hidden fields must not block submission via browser validation.
			el.querySelectorAll( 'input, select, textarea' ).forEach( function ( input ) {
				input.disabled = ! show;
			} );
		} );
	}

	function update() {
		var checked = form.querySelector( 'input[name="frequency"]:checked' );
		var weekly = ! checked || checked.value === 'weekly';
		var allDay = form.querySelector( '#dp-all-day' ).checked;
		toggle( '.dutyplan-when-weekly', weekly );
		toggle( '.dutyplan-when-daily', ! weekly );
		toggle( '.dutyplan-timed', ! allDay );
	}

	form.addEventListener( 'change', update );
	update();
} )();
