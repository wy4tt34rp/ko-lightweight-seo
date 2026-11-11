( function () {
	'use strict';

	var form = document.querySelector( '[data-ko-lseo-alt-form]' );

	if ( ! form || ! window.sessionStorage ) {
		return;
	}

	var view = form.getAttribute( 'data-ko-lseo-view' ) || 'missing';
	var root = form.getAttribute( 'data-ko-lseo-draft-root' ) || 'ko-lseo-alt-drafts';
	var storageKey = root + ':' + window.location.host + ':' + view;
	var pendingKey = storageKey + ':pending';
	var status = document.getElementById( 'ko-lseo-draft-status' );
	var drafts = readStorage( storageKey, {} );
	var query = new URLSearchParams( window.location.search );

	/**
	 * Reads one JSON value without allowing storage restrictions to break the UI.
	 *
	 * @param {string} key Storage key.
	 * @param {*} fallback Value returned when no valid JSON is available.
	 * @return {*} Parsed value or the supplied fallback.
	 */
	function readStorage( key, fallback ) {
		try {
			var stored = window.sessionStorage.getItem( key );
			return stored ? JSON.parse( stored ) : fallback;
		} catch ( error ) {
			return fallback;
		}
	}

	/**
	 * Writes the current draft map when browser storage is available.
	 *
	 * @return {void}
	 */
	function saveDrafts() {
		try {
			window.sessionStorage.setItem( storageKey, JSON.stringify( drafts ) );
		} catch ( error ) {
			// The form remains fully usable as a normal page-local WordPress form.
		}
	}

	/**
	 * Records which drafts were submitted so only those are cleared on redirect.
	 *
	 * @param {string[]} ids Submitted attachment IDs.
	 * @return {void}
	 */
	function savePendingIds( ids ) {
		try {
			window.sessionStorage.setItem( pendingKey, JSON.stringify( { ids: ids } ) );
		} catch ( error ) {
			// A failed storage write does not block the server-side save.
		}
	}

	/**
	 * Updates the visible count of selections retained across pages.
	 *
	 * @return {void}
	 */
	function updateStatus() {
		if ( ! status ) {
			return;
		}

		var count = Object.keys( drafts ).length;
		status.textContent = count
			? count + ' unsaved selection' + ( 1 === count ? '' : 's' ) + ' retained across pages.'
			: 'No unsaved selections.';
	}

	/**
	 * Adds a hidden form value for a draft from another pagination page.
	 *
	 * @param {string} name Input name.
	 * @param {string} value Input value.
	 * @return {void}
	 */
	function appendHiddenInput( name, value ) {
		var input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = name;
		input.value = value;
		input.setAttribute( 'data-ko-lseo-generated', '1' );
		form.appendChild( input );
	}

	if ( 'clear' === query.get( 'ko_lseo_drafts' ) ) {
		var pending = readStorage( pendingKey, { ids: [] } );

		if ( pending.ids && pending.ids.length ) {
			pending.ids.forEach( function ( id ) {
				delete drafts[ String( id ) ];
			} );
			saveDrafts();
		}

		try {
			window.sessionStorage.removeItem( pendingKey );
		} catch ( error ) {
			// Nothing else is required when storage is unavailable.
		}
	}

	var altInputs = form.querySelectorAll( '[data-ko-lseo-alt]' );

	altInputs.forEach( function ( altInput ) {
		var id = altInput.getAttribute( 'data-ko-lseo-alt' );
		var checkbox = form.querySelector( '[data-ko-lseo-select="' + id + '"]' );
		var original = form.querySelector( '[data-ko-lseo-original="' + id + '"]' );
		var reviewState = form.querySelector( '[data-ko-lseo-review-state="' + id + '"]' );
		var row = form.querySelector( '[data-ko-lseo-row="' + id + '"]' );

		if ( drafts[ id ] ) {
			altInput.value = drafts[ id ].value;
			checkbox.checked = true;
			reviewState.value = 'selected';
			row.classList.add( 'ko-lseo-row-selected' );
		}

		altInput.addEventListener( 'input', function () {
			if ( '' === altInput.value.trim() ) {
				checkbox.checked = false;
				reviewState.value = 'excluded';
				delete drafts[ id ];
				row.classList.remove( 'ko-lseo-row-selected' );
			} else {
				checkbox.checked = true;
				reviewState.value = 'selected';
				drafts[ id ] = {
					value: altInput.value,
					original: original ? original.value : ''
				};
				row.classList.add( 'ko-lseo-row-selected' );
			}

			saveDrafts();
			updateStatus();
		} );

		checkbox.addEventListener( 'change', function () {
			if ( checkbox.checked ) {
				reviewState.value = 'selected';
				drafts[ id ] = {
					value: altInput.value,
					original: original ? original.value : ''
				};
				row.classList.add( 'ko-lseo-row-selected' );
			} else {
				reviewState.value = 'excluded';
				delete drafts[ id ];
				row.classList.remove( 'ko-lseo-row-selected' );
			}

			saveDrafts();
			updateStatus();
		} );
	} );

	var selectAll = form.querySelector( 'thead .check-column input[type="checkbox"]' );

	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			form.querySelectorAll( '[data-ko-lseo-select]' ).forEach( function ( checkbox ) {
				checkbox.checked = selectAll.checked;
				checkbox.dispatchEvent( new Event( 'change' ) );
			} );
		} );
	}

	form.addEventListener( 'submit', function ( event ) {
		form.querySelectorAll( '[data-ko-lseo-generated="1"]' ).forEach( function ( input ) {
			input.remove();
		} );

		var action = event.submitter ? event.submitter.value : '';
		var submittedIds = [];

		if ( 'save_alt' === action ) {
			Object.keys( drafts ).forEach( function ( id ) {
				var draft = drafts[ id ];
				var currentCheckbox = form.querySelector( '[data-ko-lseo-select="' + id + '"]' );

				if ( ! currentCheckbox ) {
					appendHiddenInput( 'ko_lseo_attachment_ids[]', id );
					appendHiddenInput( 'ko_lseo_alt[' + id + ']', draft.value || '' );
					appendHiddenInput( 'ko_lseo_original_alt[' + id + ']', draft.original || '' );
					appendHiddenInput( 'ko_lseo_review_state[' + id + ']', 'selected' );
				}

				submittedIds.push( id );
			} );
		} else {
			form.querySelectorAll( '[data-ko-lseo-select]:checked' ).forEach( function ( checkbox ) {
				submittedIds.push( checkbox.value );
			} );
		}

		savePendingIds( submittedIds );
	} );

	updateStatus();
}() );
