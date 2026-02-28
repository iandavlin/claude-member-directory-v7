/**
 * Member Directory — memdir.js
 *
 * Front-end interactions for the Member Directory profile page.
 *
 * Sections:
 *   1. Tab navigation  — show/hide ACF fields by tab group within a section
 *   2. Pill navigation — section switching (stub)
 *   3. Section save    — standard form submit; populates md_active_tab before POST
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
		var params        = new URLSearchParams( window.location.search );
		var activeSection = params.get( 'active_section' ) || '';
		var activeTab     = params.get( 'active_tab' )     || '';

		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var tabButtons = section.querySelectorAll( '.memdir-section-controls__tab-item' );

			if ( ! tabButtons.length ) {
				return;
			}

			// Default to the first tab, but restore the saved tab when URL
			// params point at this section and name a matching tab label.
			var defaultBtn = tabButtons[ 0 ];
			if ( activeSection && section.dataset.section === activeSection && activeTab ) {
				tabButtons.forEach( function ( btn ) {
					if ( btn.textContent.trim() === activeTab ) {
						defaultBtn = btn;
					}
				} );
			}

			activateTab( section, defaultBtn );

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
	// 3. Section save (standard form submit)
	//
	// The .memdir-section-save button submits the section's ACF form via
	// standard browser POST — no fetch/AJAX. Before submitting, it writes
	// the current active tab label into the form's hidden md_active_tab
	// input so PHP can include it in the post-save redirect URL.
	//
	// Flow:
	//   Save button click
	//     → read active tab label from the .is-active tab button
	//     → set form's input[name="md_active_tab"] to that label
	//     → form.submit()
	//
	//   PHP (AcfFormHelper::redirect_after_save) then redirects to:
	//     permalink?active_section={key}&active_tab={label}
	//   which initTabNav() and restoreStateFromUrl() use to restore state.
	// -----------------------------------------------------------------------

	function initSectionSave() {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var saveBtn = section.querySelector( '.memdir-section-save' );

			if ( ! saveBtn ) {
				return;
			}

			saveBtn.addEventListener( 'click', function () {
				var form = section.querySelector( 'form' );
				if ( ! form ) {
					return;
				}

				// Populate the hidden tab input before submitting so PHP knows
				// which tab to restore on the redirected page load.
				var activeTabBtn   = section.querySelector( '.memdir-section-controls__tab-item.is-active' );
				var activeTabLabel = activeTabBtn ? activeTabBtn.textContent.trim() : '';
				var tabInput       = form.querySelector( 'input[name="md_active_tab"]' );
				if ( tabInput ) {
					tabInput.value = activeTabLabel;
				}

				form.submit();
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// 4. State restoration from URL params
	//
	// After a successful save, the page reloads with:
	//   ?active_section={section_key}&active_tab={tab_label}
	//
	// initTabNav() already restores the correct tab for the saved section
	// (it reads URLSearchParams before iterating sections). This function
	// handles the pill side: activate the pill whose data-section matches
	// active_section so the nav reflects the just-saved section.
	// -----------------------------------------------------------------------

	function restoreStateFromUrl() {
		var params        = new URLSearchParams( window.location.search );
		var activeSection = params.get( 'active_section' ) || '';

		if ( ! activeSection ) {
			return;
		}

		// Activate the pill that matches the restored section.
		document.querySelectorAll( '.memdir-pill[data-section]' ).forEach( function ( pill ) {
			pill.classList.toggle( 'is-active', pill.dataset.section === activeSection );
		} );
	}

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		initTabNav();
		initPillNav();
		initSectionSave();
		restoreStateFromUrl();
	} );

}() );
