/* Duty Planner – public calendar. No dependencies. */
( function () {
	'use strict';

	var cfg = window.DutyPlanner || {};
	var t = cfg.i18n || {};
	var ICONS = { needs: '!', open: '+', full: '✓', skipped: '×' };

	/* ------------------------------------------------------------ helpers */

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}
	function iso( d ) {
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	}
	function parseISO( s ) {
		var p = s.split( '-' );
		return new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] );
	}
	function addDays( d, n ) {
		return new Date( d.getFullYear(), d.getMonth(), d.getDate() + n );
	}
	function fmt( s ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return String( s ).replace( /%(\d)\$s|%s/g, function ( m, n ) {
			return n ? args[ n - 1 ] : args[ i++ ];
		} );
	}
	function dateFmt( opts ) {
		try {
			return new Intl.DateTimeFormat( cfg.locale || undefined, opts );
		} catch ( e ) {
			return new Intl.DateTimeFormat( undefined, opts );
		}
	}

	/** Tiny DOM builder: el('div', {class: 'x', onclick: fn}, [children | 'text']) */
	function el( tag, attrs, children ) {
		var node = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( key ) {
			var val = attrs[ key ];
			if ( val === null || val === undefined || val === false ) {
				return;
			}
			if ( key.indexOf( 'on' ) === 0 ) {
				node.addEventListener( key.slice( 2 ), val );
			} else if ( key === 'text' ) {
				node.textContent = val;
			} else {
				node.setAttribute( key, val === true ? '' : val );
			}
		} );
		( children || [] ).forEach( function ( c ) {
			if ( c === null || c === undefined || c === false ) {
				return;
			}
			node.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c );
		} );
		return node;
	}

	function api( path, params, options ) {
		var url = cfg.restUrl + path;
		var qs = params ? new URLSearchParams( params ).toString() : '';
		if ( qs ) {
			url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + qs;
		}
		options = options || {};
		options.credentials = 'same-origin';
		options.headers = options.headers || {};
		if ( cfg.restNonce ) {
			options.headers[ 'X-WP-Nonce' ] = cfg.restNonce;
		}
		return fetch( url, options ).then( function ( res ) {
			return res.json().catch( function () {
				return {};
			} ).then( function ( body ) {
				if ( ! res.ok ) {
					var err = new Error( body.message || t.genericError );
					err.body = body;
					throw err;
				}
				return body;
			} );
		} );
	}

	function stateOf( item ) {
		return item.skipped ? 'skipped' : item.status;
	}

	function stateLabel( item ) {
		return item.skipped ? t.skipped : t[ item.status ];
	}

	function hint( item ) {
		if ( item.skipped || item.status === 'full' ) {
			return '';
		}
		if ( item.status === 'needs' ) {
			return fmt( t.moreNeeded, item.min - item.count );
		}
		var left = item.max - item.count;
		return fmt( left === 1 ? t.placeLeft : t.placesLeft, left );
	}

	function icon( state ) {
		return el( 'span', { class: 'dp-icon', 'aria-hidden': 'true', text: ICONS[ state ] } );
	}

	// With a single place, "0/1" or "1/1" only confuses – the status already says it.
	function showCount( item ) {
		return ! item.skipped && +item.max !== 1;
	}

	function fillBar( item ) {
		if ( ! showCount( item ) ) {
			return null;
		}
		var pct = item.max ? Math.min( 100, ( item.count / item.max ) * 100 ) : 0;
		var bar = el( 'span', { class: 'dp-fill-bar' } );
		bar.style.width = pct + '%';
		var track = el( 'span', { class: 'dp-fill-track' }, [ bar ] );
		if ( item.min > 0 && item.min < item.max ) {
			var marker = el( 'span', { class: 'dp-fill-min', title: fmt( t.minimum, item.min ) } );
			marker.style.left = ( item.min / item.max ) * 100 + '%';
			track.appendChild( marker );
		}
		return el( 'div', { class: 'dp-fill' }, [
			track,
			el( 'span', { class: 'dp-fill-text', text: fmt( t.places, item.count, item.max ) } ),
		] );
	}

	/* ------------------------------------------------------------ planner */

	function Planner( root ) {
		var conf = {};
		try {
			conf = JSON.parse( root.getAttribute( 'data-config' ) || '{}' );
		} catch ( e ) {}

		this.root = root;
		this.view = conf.view === 'list' ? 'list' : 'month';
		this.weeks = conf.weeks || 6;
		this.spots = conf.spots || '';
		this.startOfWeek = typeof cfg.startOfWeek === 'number' ? cfg.startOfWeek : 1;
		this.today = parseISO( cfg.today || iso( new Date() ) );
		this.month = new Date( this.today.getFullYear(), this.today.getMonth(), 1 );
		this.listStart = this.today;
		this.items = [];
		this.nonce = '';
		this.contact = cfg.user ? { name: cfg.user.name, email: cfg.user.email } : { name: '', email: '' };
		this.current = null;

		this.build();
		this.load();
	}

	Planner.prototype.build = function () {
		var self = this;
		this.root.textContent = '';
		this.root.classList.add( 'dp-ready' );

		this.title = el( 'h3', { class: 'dp-title', 'aria-live': 'polite' } );
		this.viewButtons = {
			month: el( 'button', { type: 'button', class: 'dp-btn', text: t.monthView, onclick: function () {
				self.setView( 'month' );
			} } ),
			list: el( 'button', { type: 'button', class: 'dp-btn', text: t.listView, onclick: function () {
				self.setView( 'list' );
			} } ),
		};

		var header = el( 'div', { class: 'dp-header' }, [
			el( 'div', { class: 'dp-nav' }, [
				el( 'button', { type: 'button', class: 'dp-btn dp-btn-icon', 'aria-label': t.prev, text: '‹', onclick: function () {
					self.shift( -1 );
				} } ),
				el( 'button', { type: 'button', class: 'dp-btn', text: t.today, onclick: function () {
					self.goToday();
				} } ),
				el( 'button', { type: 'button', class: 'dp-btn dp-btn-icon', 'aria-label': t.next, text: '›', onclick: function () {
					self.shift( 1 );
				} } ),
			] ),
			this.title,
			el( 'div', { class: 'dp-views', role: 'group' }, [ this.viewButtons.month, this.viewButtons.list ] ),
		] );

		var legend = el( 'ul', { class: 'dp-legend' }, [ 'needs', 'open', 'full' ].map( function ( s ) {
			return el( 'li', { class: 'dp-state-' + s }, [ icon( s ), t[ s ] ] );
		} ) );

		this.body = el( 'div', { class: 'dp-body', 'aria-busy': 'true' } );
		this.dialog = el( 'dialog', { class: 'dp-dialog', 'aria-labelledby': 'dp-dialog-title' } );
		this.dialog.addEventListener( 'click', function ( e ) {
			if ( e.target === self.dialog ) {
				self.closeDialog(); // Backdrop click.
			}
		} );
		this.dialog.addEventListener( 'close', function () {
			self.current = null;
		} );

		this.root.appendChild( header );
		this.root.appendChild( legend );
		this.root.appendChild( this.body );
		document.body.appendChild( this.dialog );
	};

	Planner.prototype.range = function () {
		if ( this.view === 'list' ) {
			return { from: this.listStart, to: addDays( this.listStart, this.weeks * 7 - 1 ) };
		}
		var first = this.month;
		var last = new Date( first.getFullYear(), first.getMonth() + 1, 0 );
		var lead = ( first.getDay() - this.startOfWeek + 7 ) % 7;
		var trail = ( this.startOfWeek + 6 - last.getDay() + 7 ) % 7;
		return { from: addDays( first, -lead ), to: addDays( last, trail ) };
	};

	Planner.prototype.setView = function ( view ) {
		if ( view === this.view ) {
			return;
		}
		this.view = view;
		this.load();
	};

	Planner.prototype.shift = function ( dir ) {
		if ( this.view === 'list' ) {
			this.listStart = addDays( this.listStart, dir * this.weeks * 7 );
		} else {
			this.month = new Date( this.month.getFullYear(), this.month.getMonth() + dir, 1 );
		}
		this.load();
	};

	Planner.prototype.goToday = function () {
		this.month = new Date( this.today.getFullYear(), this.today.getMonth(), 1 );
		this.listStart = this.today;
		this.load();
	};

	Planner.prototype.load = function () {
		var self = this;
		var r = this.range();
		var token = ( this.loadToken = {} );

		Object.keys( this.viewButtons ).forEach( function ( v ) {
			self.viewButtons[ v ].setAttribute( 'aria-pressed', v === self.view ? 'true' : 'false' );
		} );
		this.title.textContent = this.view === 'list'
			? dateFmt( { day: 'numeric', month: 'short' } ).format( r.from ) + ' – ' + dateFmt( { day: 'numeric', month: 'short', year: 'numeric' } ).format( r.to )
			: dateFmt( { month: 'long', year: 'numeric' } ).format( this.month );
		this.body.setAttribute( 'aria-busy', 'true' );
		this.body.classList.add( 'dp-is-loading' );

		return api( 'occurrences', { from: iso( r.from ), to: iso( r.to ), spots: this.spots } )
			.then( function ( data ) {
				if ( token !== self.loadToken ) {
					return;
				}
				self.items = data.occurrences || [];
				self.nonce = data.nonce || self.nonce;
				self.render();
			} )
			.catch( function () {
				if ( token !== self.loadToken ) {
					return;
				}
				self.body.textContent = '';
				self.body.appendChild( el( 'p', { class: 'dp-error', text: t.loadError } ) );
			} )
			.then( function () {
				if ( token === self.loadToken ) {
					self.body.setAttribute( 'aria-busy', 'false' );
					self.body.classList.remove( 'dp-is-loading' );
				}
			} );
	};

	Planner.prototype.byDate = function () {
		var map = {};
		this.items.forEach( function ( item ) {
			( map[ item.date ] = map[ item.date ] || [] ).push( item );
		} );
		return map;
	};

	Planner.prototype.render = function () {
		this.body.textContent = '';
		this.body.appendChild( this.view === 'list' ? this.renderList() : this.renderMonth() );
	};

	Planner.prototype.chip = function ( item ) {
		var self = this;
		var state = stateOf( item );
		var cls = 'dp-chip dp-state-' + state + ( item.past ? ' dp-past' : '' ) + ( item.all_day ? ' dp-allday' : '' );
		var label = [ item.title, item.date_label, item.time_label, stateLabel( item ), item.skipped ? '' : fmt( t.places, item.count, item.max ) ]
			.filter( Boolean ).join( ', ' );
		return el( 'button', { type: 'button', class: cls, 'aria-label': label, onclick: function () {
			self.openDialog( item );
		} }, [
			icon( state ),
			el( 'span', { class: 'dp-chip-time', text: item.start_label } ),
			el( 'span', { class: 'dp-chip-title', text: item.title } ),
			showCount( item ) ? el( 'span', { class: 'dp-chip-count', text: item.count + '/' + item.max } ) : null,
		] );
	};

	Planner.prototype.renderMonth = function () {
		var self = this;
		var r = this.range();
		var map = this.byDate();
		var todayIso = iso( this.today );
		var weekdayFmt = dateFmt( { weekday: 'short' } );
		var longFmt = dateFmt( { weekday: 'long', day: 'numeric', month: 'long' } );
		var sunday = new Date( 2024, 0, 7 ); // A known Sunday.

		var head = el( 'div', { class: 'dp-weekdays', 'aria-hidden': 'true' } );
		for ( var i = 0; i < 7; i++ ) {
			head.appendChild( el( 'div', { text: weekdayFmt.format( addDays( sunday, ( this.startOfWeek + i ) % 7 ) ) } ) );
		}

		var grid = el( 'div', { class: 'dp-grid' } );
		var any = false;
		for ( var d = r.from; d <= r.to; d = addDays( d, 1 ) ) {
			var key = iso( d );
			var items = map[ key ] || [];
			var cls = 'dp-day';
			if ( d.getMonth() !== this.month.getMonth() ) {
				cls += ' dp-outside';
			}
			if ( key === todayIso ) {
				cls += ' dp-today';
			}
			if ( key < todayIso ) {
				cls += ' dp-day-past';
			}
			if ( items.length ) {
				cls += ' dp-has-items';
				any = true;
			}
			grid.appendChild( el( 'div', { class: cls }, [
				el( 'div', { class: 'dp-daylabel' }, [
					el( 'span', { class: 'dp-daynum', 'aria-hidden': 'true', text: String( d.getDate() ) } ),
					el( 'span', { class: 'dp-daylong', text: longFmt.format( d ) } ),
				] ),
				el( 'div', { class: 'dp-chips' }, items.map( function ( item ) {
					return self.chip( item );
				} ) ),
			] ) );
		}

		var current = this.month.getFullYear() === this.today.getFullYear() && this.month.getMonth() === this.today.getMonth();
		return el( 'div', { class: 'dp-month' + ( any ? '' : ' dp-month-empty' ) + ( current ? ' dp-month-current' : '' ) }, [
			head,
			grid,
			any ? null : el( 'p', { class: 'dp-empty', text: t.empty } ),
		] );
	};

	Planner.prototype.renderList = function () {
		var self = this;
		var map = this.byDate();
		var dates = Object.keys( map ).sort();
		if ( ! dates.length ) {
			return el( 'p', { class: 'dp-empty', text: t.empty } );
		}
		return el( 'div', { class: 'dp-list' }, dates.map( function ( date ) {
			return el( 'section', { class: 'dp-list-day' }, [
				el( 'h4', { class: 'dp-list-date', text: map[ date ][ 0 ].date_label } ),
				el( 'div', { class: 'dp-cards' }, map[ date ].map( function ( item ) {
					return self.card( item );
				} ) ),
			] );
		} ) );
	};

	Planner.prototype.card = function ( item ) {
		var self = this;
		var state = stateOf( item );
		var open = function () {
			self.openDialog( item );
		};
		return el( 'article', { class: 'dp-card dp-state-' + state + ( item.past ? ' dp-past' : '' ) }, [
			el( 'div', { class: 'dp-card-main' }, [
				el( 'div', { class: 'dp-card-status' }, [ icon( state ), stateLabel( item ), hint( item ) ? el( 'span', { class: 'dp-hint', text: '· ' + hint( item ) } ) : null ] ),
				el( 'h5', { class: 'dp-card-title' }, [ el( 'button', { type: 'button', class: 'dp-linkbtn', text: item.title, onclick: open } ) ] ),
				el( 'div', { class: 'dp-card-meta', text: [ item.time_label, item.location ].filter( Boolean ).join( ' · ' ) } ),
				item.skipped ? null : fillBar( item ),
				item.people.length ? el( 'div', { class: 'dp-card-people', text: item.people.map( function ( p ) {
					return p.note ? p.name + ' (' + p.note + ')' : p.name;
				} ).join( ', ' ) } ) : null,
			] ),
			this.canRegister( item ) ? el( 'button', { type: 'button', class: 'dp-btn dp-btn-primary', text: t.submit, onclick: open } ) : null,
		] );
	};

	Planner.prototype.canRegister = function ( item ) {
		return ! item.past && ! item.skipped && item.status !== 'full';
	};

	/* ------------------------------------------------------------- dialog */

	Planner.prototype.openDialog = function ( item, message ) {
		var self = this;
		var state = stateOf( item );
		this.current = item;
		this.dialog.textContent = '';
		this.dialog.className = 'dp-dialog dp-state-' + state;

		var rows = [
			[ t.date, item.date_label ],
			[ t.time, item.time_label ],
			item.location ? [ t.location, item.location ] : null,
		].filter( Boolean );

		var people = item.people.length
			? el( 'ul', { class: 'dp-people' }, item.people.map( function ( p ) {
				return el( 'li', null, [
					p.name,
					p.note ? el( 'span', { class: 'dp-person-note', text: ' – ' + p.note } ) : null,
				] );
			} ) )
			: el( 'p', { class: 'dp-muted', text: t.nobody } );

		var note = null;
		if ( item.skipped ) {
			note = t.skippedNote;
		} else if ( item.past ) {
			note = t.pastNote;
		} else if ( item.status === 'full' ) {
			note = t.fullNote;
		}

		this.dialog.appendChild( el( 'div', { class: 'dp-dialog-inner' }, [
			el( 'button', { type: 'button', class: 'dp-close', 'aria-label': t.close, text: '×', onclick: function () {
				self.closeDialog();
			} } ),
			el( 'div', { class: 'dp-pill' }, [ icon( state ), stateLabel( item ), hint( item ) ? el( 'span', { class: 'dp-hint', text: '· ' + hint( item ) } ) : null ] ),
			el( 'h3', { id: 'dp-dialog-title', class: 'dp-dialog-title', text: item.title } ),
			el( 'dl', { class: 'dp-details' }, rows.reduce( function ( acc, row ) {
				acc.push( el( 'dt', { text: row[ 0 ] } ), el( 'dd', { text: row[ 1 ] } ) );
				return acc;
			}, [] ) ),
			item.description ? el( 'p', { class: 'dp-description', text: item.description } ) : null,
			item.skipped ? null : fillBar( item ),
			item.skipped ? null : el( 'h4', { class: 'dp-subtitle', text: t.signedUp } ),
			item.skipped ? null : people,
			message ? el( 'p', { class: 'dp-message dp-message-success', role: 'status', text: message } ) : null,
			note ? el( 'p', { class: 'dp-note', text: note } ) : null,
			this.canRegister( item ) ? this.form( item ) : null,
		] ) );

		if ( ! this.dialog.open ) {
			if ( typeof this.dialog.showModal === 'function' ) {
				this.dialog.showModal();
			} else {
				this.dialog.setAttribute( 'open', '' );
			}
		}
	};

	Planner.prototype.closeDialog = function () {
		if ( typeof this.dialog.close === 'function' ) {
			this.dialog.close();
		} else {
			this.dialog.removeAttribute( 'open' );
		}
		this.current = null;
	};

	Planner.prototype.form = function ( item ) {
		var self = this;
		var uid = 'dp-' + item.spot_id + '-' + item.date;
		var name = el( 'input', { type: 'text', id: uid + '-name', name: 'name', required: true, maxlength: '60', autocomplete: 'name', value: this.contact.name } );
		var email = el( 'input', { type: 'email', id: uid + '-email', name: 'email', required: true, autocomplete: 'email', value: this.contact.email } );
		var noteInput = item.note_label ? el( 'input', { type: 'text', id: uid + '-note', name: 'note', maxlength: '200', autocomplete: 'off' } ) : null;
		var honeypot = el( 'input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off' } );
		var submit = el( 'button', { type: 'submit', class: 'dp-btn dp-btn-primary', text: t.submit } );
		var msg = el( 'p', { class: 'dp-message', role: 'alert', hidden: true } );

		var form = el( 'form', { class: 'dp-form', novalidate: false }, [
			el( 'h4', { class: 'dp-subtitle', text: t.signUpTitle } ),
			item.restricted ? el( 'p', { class: 'dp-note dp-note-lock' }, [ el( 'span', { 'aria-hidden': 'true', text: '🔒 ' } ), t.restricted ] ) : null,
			el( 'div', { class: 'dp-field' }, [
				el( 'label', { for: uid + '-name', text: t.name } ),
				name,
				el( 'small', { text: t.nameHelp } ),
			] ),
			el( 'div', { class: 'dp-field' }, [
				el( 'label', { for: uid + '-email', text: t.email } ),
				email,
				el( 'small', { text: t.emailHelp } ),
			] ),
			noteInput ? el( 'div', { class: 'dp-field' }, [
				el( 'label', { for: uid + '-note' }, [ item.note_label + ' ', el( 'span', { class: 'dp-optional', text: t.optional } ) ] ),
				noteInput,
				el( 'small', { text: t.noteHelp } ),
			] ) : null,
			el( 'div', { class: 'dp-hp', 'aria-hidden': 'true' }, [ el( 'label', { text: 'Website' } ), honeypot ] ),
			msg,
			el( 'div', { class: 'dp-actions' }, [ submit ] ),
		] );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! form.reportValidity() ) {
				return;
			}
			self.contact = { name: name.value.trim(), email: email.value.trim() };
			submit.disabled = true;
			submit.textContent = t.sending;
			msg.hidden = true;

			api( 'register', null, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					spot_id: item.spot_id,
					date: item.date,
					name: self.contact.name,
					email: self.contact.email,
					note: noteInput ? noteInput.value.trim() : '',
					website: honeypot.value,
					nonce: self.nonce,
				} ),
			} ).then( function ( data ) {
				var updated = data.occurrence || item;
				self.items = self.items.map( function ( it ) {
					return it.spot_id === updated.spot_id && it.date === updated.date ? updated : it;
				} );
				self.render();
				self.openDialog( updated, data.message );
			} ).catch( function ( err ) {
				msg.textContent = err.message || t.genericError;
				msg.className = 'dp-message dp-message-error';
				msg.hidden = false;
				submit.disabled = false;
				submit.textContent = t.submit;
				// Someone else may have taken the last place – refresh counts in the background.
				if ( err.body && err.body.code === 'dutyplan_full' ) {
					self.load();
				}
			} );
		} );

		return form;
	};

	/* --------------------------------------------------------------- boot */

	function boot() {
		document.querySelectorAll( '.dp-planner:not(.dp-ready)' ).forEach( function ( root ) {
			new Planner( root );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
