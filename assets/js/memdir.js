/**
 * Member Directory — memdir.js
 *
 * Front-end interactions for the Member Directory profile page.
 *
 * Sections:
 *   1. Tab navigation  — show/hide ACF fields by tab group within a section
 *   2. Pill navigation — section switching (stub)
 */

( function () {
	'use strict';

	// -----------------------------------------------------------------------
	// 1. Tab navigation
	//
	// Each .memdir-section--edit has a left-column tab list
	// (.memdir-section-controls__tabs) and a right-column ACF form
	// (.memdir-field-content). Tab buttons carry a data-field-keys attribute
	// (JSON array of ACF field keys) controlling which .acf-field elements
	// are visible.
	//
	// ACF renders each field as:
	//   <div class="acf-field" data-key="{field_key}">
	//
	// On page load: activate the first tab, hide all fields not in that group.
	// On tab click: activate the clicked tab, show only its fields.
	// -----------------------------------------------------------------------

	function initTabNav() {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var tabButtons = section.querySelectorAll( '.memdir-section-controls__tab-item' );

			if ( ! tabButtons.length ) {
				return;
			}

			// Activate the first tab on page load.
			activateTab( section, tabButtons[ 0 ] );

			// Wire up click handlers for subsequent tab switches.
			tabButtons.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					activateTab( section, btn );
				} );
			} );
		} );
	}

	/**
	 * Activate a tab button within a section: update active class on all tab
	 * buttons, then show only the ACF fields whose data-key appears in the
	 * activated button's data-field-keys JSON array.
	 *
	 * @param {Element} section   The .memdir-section--edit wrapper element.
	 * @param {Element} activeBtn The tab button being activated.
	 */
	function activateTab( section, activeBtn ) {
		var tabButtons = section.querySelectorAll( '.memdir-section-controls__tab-item' );
		var allFields  = section.querySelectorAll( '.memdir-field-content .acf-field[data-key]' );

		// Parse the field keys belonging to this tab.
		var fieldKeys = [];
		try {
			fieldKeys = JSON.parse( activeBtn.dataset.fieldKeys || '[]' );
		} catch ( e ) {
			fieldKeys = [];
		}

		// Toggle active class on all tab buttons.
		tabButtons.forEach( function ( btn ) {
			btn.classList.toggle( 'is-active', btn === activeBtn );
		} );

		// Show fields in this tab group; hide all others.
		allFields.forEach( function ( field ) {
			var key = field.dataset.key || '';
			field.style.display = fieldKeys.includes( key ) ? '' : 'none';
		} );
	}

	// -----------------------------------------------------------------------
	// 2. Pill navigation (stub)
	//
	// Full implementation: AJAX-save enabled state, show/hide sections in DOM,
	// update Viewing badge in header. For now: log to console.
	// -----------------------------------------------------------------------

	function initPillNav() {
		document.querySelectorAll( '.memdir-pill' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				console.log( 'pill clicked' );
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		initTabNav();
		initPillNav();
	} );

}() );
