/**
 * Member Directory — memdir.js
 *
 * Front-end interactions for the Member Directory profile page.
 *
 * Sections:
 *   1. Tab navigation  — show/hide ACF fields by tab group within a section
 *   2. Pill navigation — section switching (stub)
 *   3. Section save    — AJAX save for all fields in a section without reload
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
	// 3. Section save (AJAX)
	//
	// Each .memdir-section--edit wraps:
	//   .memdir-unsaved-banner  — shown when any field in the section changes.
	//   .memdir-section-save    — button that collects the section's ACF form
	//                             fields and POSTs them via fetch without a
	//                             full page reload.
	//
	// Flow:
	//   Any input/change inside .memdir-field-content
	//     → section.classList.add('has-unsaved')
	//     → banner.style.display = ''
	//
	//   Save button click
	//     → collect FormData from the section's .acf-form
	//     → POST action=md_save_section, nonce, post_id, acf[…] fields
	//     → success: redirect to pathname?active_section=…&active_tab=…
	//     → error:   show error state on button for 3 s
	// -----------------------------------------------------------------------

	function initSectionSave() {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var fieldContent = section.querySelector( '.memdir-field-content' );
			var banner       = section.querySelector( '.memdir-unsaved-banner' );
			var saveBtn      = section.querySelector( '.memdir-section-save' );

			if ( ! fieldContent || ! saveBtn ) {
				return;
			}

			// Show unsaved banner on any field change.
			fieldContent.addEventListener( 'input',  function () { markUnsaved( section, banner ); } );
			fieldContent.addEventListener( 'change', function () { markUnsaved( section, banner ); } );

			// Wire save button.
			saveBtn.addEventListener( 'click', function () {
				saveSection( section, saveBtn, banner );
			} );
		} );
	}

	/**
	 * Mark a section as having unsaved changes.
	 *
	 * @param {Element}      section The .memdir-section--edit wrapper.
	 * @param {Element|null} banner  The .memdir-unsaved-banner element, or null.
	 */
	function markUnsaved( section, banner ) {
		section.classList.add( 'has-unsaved' );
		if ( banner ) {
			banner.style.display = '';
		}
	}

	/**
	 * Collect field values from the section's ACF form and POST via fetch.
	 *
	 * @param {Element}      section The .memdir-section--edit wrapper.
	 * @param {Element}      saveBtn The .memdir-section-save button.
	 * @param {Element|null} banner  The .memdir-unsaved-banner element, or null.
	 */
	function saveSection( section, saveBtn, banner ) {
		var form   = section.querySelector( '.memdir-field-content .acf-form' );
		var postId = section.dataset.postId || '';

		if ( ! form || ! postId ) {
			return;
		}

		// Build form payload: all ACF inputs + our action/nonce/post_id.
		var formData = new FormData( form );
		formData.set( 'action',  'md_save_section' );
		formData.set( 'nonce',   ( window.mdAjax && window.mdAjax.nonce )   ? window.mdAjax.nonce   : '' );
		formData.set( 'post_id', postId );

		var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
			? window.mdAjax.ajaxurl
			: '/wp-admin/admin-ajax.php';

		// Saving state.
		saveBtn.classList.add( 'memdir-section-save--saving' );
		saveBtn.disabled = true;

		fetch( ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			body:        formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				saveBtn.classList.remove( 'memdir-section-save--saving' );
				saveBtn.disabled = false;

				if ( data.success ) {
					// Capture active tab label before redirecting so the
					// reloaded page can restore it via URL params.
					var sectionKey     = section.dataset.section || '';
					var activeTabBtn   = section.querySelector( '.memdir-section-controls__tab-item.is-active' );
					var activeTabLabel = activeTabBtn ? activeTabBtn.textContent.trim() : '';

					var reloadParams = new URLSearchParams();
					reloadParams.set( 'active_section', sectionKey );
					if ( activeTabLabel ) {
						reloadParams.set( 'active_tab', activeTabLabel );
					}

					window.location.href = window.location.pathname + '?' + reloadParams.toString();
				} else {
					// Error feedback (3 s).
					saveBtn.classList.add( 'memdir-section-save--error' );
					setTimeout( function () {
						saveBtn.classList.remove( 'memdir-section-save--error' );
					}, 3000 );
				}
			} )
			.catch( function () {
				// Network / parse error.
				saveBtn.classList.remove( 'memdir-section-save--saving' );
				saveBtn.disabled = false;
				saveBtn.classList.add( 'memdir-section-save--error' );
				setTimeout( function () {
					saveBtn.classList.remove( 'memdir-section-save--error' );
				}, 3000 );
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
