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
	//   Save button click  OR  Enter in any text input
	//     → collect FormData from the section's .acf-form
	//     → POST action=md_save_section, nonce, post_id, acf[…] fields
	//     → success: show 'Saved ✓' on button; update .memdir-header__title
	//               in place if field_md_profile_page_name was in the payload
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

			// Intercept Enter in text inputs — treat it as a save rather than
			// a native form submit. Textareas are excluded so Enter still adds
			// new lines there.
			fieldContent.addEventListener( 'keydown', function ( event ) {
				if ( event.key !== 'Enter' ) {
					return;
				}
				if ( event.target.tagName === 'TEXTAREA' ) {
					return;
				}
				event.preventDefault();
				saveSection( section, saveBtn, banner );
			} );

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
	 * Collect all field values from the section and POST via fetch.
	 *
	 * Iterates through all .acf-field[data-key] elements regardless of
	 * visibility (visible tabs vs hidden tabs), and collects input values
	 * from input, textarea, select elements within each field.
	 *
	 * @param {Element}      section The .memdir-section--edit wrapper.
	 * @param {Element}      saveBtn The .memdir-section-save button.
	 * @param {Element|null} banner  The .memdir-unsaved-banner element, or null.
	 */
	function saveSection( section, saveBtn, banner ) {
		var fieldContent = section.querySelector( '.memdir-field-content' );
		var postId = section.dataset.postId || '';

		if ( ! fieldContent || ! postId ) {
			return;
		}

		// Collect all .acf-field[data-key] elements, including those hidden by tab switcher.
		var acfFieldDivs = fieldContent.querySelectorAll( '.acf-field[data-key]' );
		var formData = new FormData();

		formData.set( 'action',  'md_save_section' );
		formData.set( 'nonce',   ( window.mdAjax && window.mdAjax.nonce )   ? window.mdAjax.nonce   : '' );
		formData.set( 'post_id', postId );

		// Iterate each field and collect its input values.
		acfFieldDivs.forEach( function ( fieldDiv ) {
			var fieldKey = fieldDiv.dataset.key || '';
			if ( ! fieldKey ) {
				return;
			}

			// Find all form controls within this field.
			var inputs = fieldDiv.querySelectorAll( 'input, textarea, select' );
			inputs.forEach( function ( input ) {
				// Skip unchecked checkboxes and radios — they shouldn't be submitted.
				if ( ( input.type === 'checkbox' || input.type === 'radio' ) && ! input.checked ) {
					return;
				}

				// Append using ACF's name convention acf[field_key].
				formData.append( 'acf[' + fieldKey + ']', input.value );
			} );
		} );

		var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
			? window.mdAjax.ajaxurl
			: '/wp-admin/admin-ajax.php';

		// Capture original label so we can restore it after the saved state.
		var originalBtnText = saveBtn.textContent;

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
					// Clear unsaved state.
					section.classList.remove( 'has-unsaved' );
					if ( banner ) {
						banner.style.display = 'none';
					}

					// Show 'Saved ✓' on button for 2 s, then restore original label.
					saveBtn.textContent = 'Saved ✓';
					saveBtn.classList.add( 'memdir-section-save--saved' );
					setTimeout( function () {
						saveBtn.classList.remove( 'memdir-section-save--saved' );
						saveBtn.textContent = originalBtnText;
					}, 2000 );

					// Update the page-name header title in place if that field
					// was included in this section's saved payload.
					var pageNameField = fieldContent.querySelector( '.acf-field[data-key="field_md_profile_page_name"]' );
					if ( pageNameField ) {
						var pageNameInput = pageNameField.querySelector( 'input' );
						if ( pageNameInput ) {
							var titleEl = document.querySelector( '.memdir-header__title' );
							if ( titleEl ) {
								titleEl.textContent = pageNameInput.value;
							}
						}
					}
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
