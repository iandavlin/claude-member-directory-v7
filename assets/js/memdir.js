/**
 * Member Directory — memdir.js
 *
 * Front-end interactions for the Member Directory profile page.
 *
 * Sections:
 *   1. Tab navigation     — show/hide ACF fields by tab group within a section
 *   2. Pill navigation    — single-section / all-sections view switching
 *   3. Header swap        — show correct header variant based on active pill
 *   4. Section save       — AJAX save for all fields in a section without reload
 *   5. Right panel        — Primary Section AJAX save + pill DOM update
 *   6. Pill enable/disable — checkbox toggles section on/off + DOM reorder
 *   7. State restore      — sessionStorage + URL param restore on page load
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
	// 2. Pill navigation
	//
	// Clicking a .memdir-pill (but NOT its checkbox child) activates
	// single-section view:
	//   - The matching .memdir-section[data-section='{key}'] is shown.
	//   - All other .memdir-section elements are hidden.
	//   - .memdir-pill--active moves to the clicked pill.
	//
	// Clicking the All Sections pill (data-section="all") restores all
	// sections and marks that pill active.
	//
	// Checkbox clicks (.memdir-pill__checkbox) are intentionally ignored here
	// so the checkbox can handle enable/disable toggling independently.
	// -----------------------------------------------------------------------

	function initPillNav() {
		document.querySelectorAll( '.memdir-pill' ).forEach( function ( pill ) {
			pill.addEventListener( 'click', function ( e ) {
				// Checkbox clicks: let the checkbox handle its own change event;
				// don't switch sections.
				if ( e.target.tagName === 'INPUT' && e.target.type === 'checkbox' ) {
					return;
				}

				// Disabled pills are not navigable — only the checkbox can re-enable.
				if ( pill.classList.contains( 'memdir-pill--disabled' ) ) {
					return;
				}

				activatePill( pill.dataset.section || 'all' );
			} );
		} );
	}

	/**
	 * Activate a pill: update pill active classes, section visibility, header
	 * variant, and sessionStorage state.
	 *
	 * Called by pill click handlers, restoreState(), and restoreStateFromUrl()
	 * so all logic lives in one place.
	 *
	 * @param {string} sectionKey  data-section value of the pill to activate,
	 *                             or 'all' to show every section.
	 */
	function activatePill( sectionKey ) {
		// Guard: if the target section's pill is disabled, fall back to 'all'.
		// Prevents showing an empty content area when sessionStorage or a direct
		// pill click targets a section that PHP did not render (enabled === false).
		if ( sectionKey !== 'all' ) {
			var targetPill = document.querySelector( '.memdir-pill[data-section="' + sectionKey + '"]' );
			if ( targetPill && targetPill.classList.contains( 'memdir-pill--disabled' ) ) {
				activatePill( 'all' );
				return;
			}
		}

		// Move the active class to the matching pill; clear it from all others.
		document.querySelectorAll( '.memdir-pill' ).forEach( function ( p ) {
			p.classList.toggle( 'memdir-pill--active', p.dataset.section === sectionKey );
		} );

		// Show the matching section; hide everything else.
		// For 'all', respect the pill's disabled state so unchecked sections
		// stay hidden even when "All Sections" is clicked within the same load.
		document.querySelectorAll( '.memdir-section' ).forEach( function ( section ) {
			if ( sectionKey === 'all' ) {
				var sKey       = section.dataset.section || '';
				var pill       = document.querySelector( '.memdir-pill[data-section="' + sKey + '"]' );
				var isDisabled = pill && pill.classList.contains( 'memdir-pill--disabled' );
				section.style.display = isDisabled ? 'none' : '';
			} else {
				section.style.display =
					( section.dataset.section === sectionKey ) ? '' : 'none';
			}
		} );

		// Swap the header variant to match the active pill.
		swapHeader( sectionKey );

		// Persist the active pill to sessionStorage keyed by post ID so state
		// survives navigation within the same browser session.
		var nav    = document.querySelector( '.memdir-pills' );
		var postId = nav ? ( nav.dataset.postId || '' ) : '';
		if ( postId ) {
			try {
				sessionStorage.setItem( 'memdir_active_pill_' + postId, sectionKey );
			} catch ( e ) {
				// sessionStorage may be unavailable (private browsing, storage full).
			}
		}
	}

	// -----------------------------------------------------------------------
	// 3. Header swap
	//
	// Two header variants live in the DOM (.memdir-header-wrap[data-header]).
	// The correct one is shown based on the active pill and the primary section.
	//
	// Rule:
	//   Business primary → show profile header ONLY when Profile pill is active;
	//                       show business header for all other pills.
	//   Profile primary  → show business header ONLY when Business pill is active;
	//                       show profile header for all other pills.
	//
	// .memdir-sticky[data-primary-section] is the source of truth for the
	// primary section key.
	// -----------------------------------------------------------------------

	/**
	 * Show the correct header variant for the active pill.
	 *
	 * @param {string} sectionKey  The newly activated section key.
	 */
	function swapHeader( sectionKey ) {
		var sticky = document.querySelector( '.memdir-sticky' );
		if ( ! sticky ) {
			return;
		}

		var primarySection = sticky.dataset.primarySection || 'profile';
		var showBusiness;

		if ( primarySection === 'business' ) {
			// Business is primary: show the profile header only when the
			// Profile pill is active; all other pills show the business header.
			showBusiness = ( sectionKey !== 'profile' );
		} else {
			// Profile is primary (default): show the business header only when
			// the Business pill is active; all other pills show the profile header.
			showBusiness = ( sectionKey === 'business' );
		}

		var profileWrap  = sticky.querySelector( '.memdir-header-wrap[data-header="profile"]' );
		var businessWrap = sticky.querySelector( '.memdir-header-wrap[data-header="business"]' );

		if ( profileWrap )  { profileWrap.style.display  = showBusiness ? 'none' : ''; }
		if ( businessWrap ) { businessWrap.style.display = showBusiness ? '' : 'none'; }
	}

	// -----------------------------------------------------------------------
	// 4. Section save (AJAX)
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

					// Update header title in place when a name field was in the saved payload.
					// Each section targets its own header wrapper so both can live in the DOM.
					var pageNameField = fieldContent.querySelector( '.acf-field[data-key="field_md_profile_page_name"]' );
					if ( pageNameField ) {
						var pageNameInput = pageNameField.querySelector( 'input' );
						if ( pageNameInput ) {
							var titleEl = document.querySelector( '.memdir-header-wrap[data-header="profile"] .memdir-header__title' );
							if ( titleEl ) { titleEl.textContent = pageNameInput.value; }
						}
					}

					var businessNameField = fieldContent.querySelector( '.acf-field[data-key="field_md_business_name"]' );
					if ( businessNameField ) {
						var businessNameInput = businessNameField.querySelector( 'input' );
						if ( businessNameInput ) {
							var businessTitleEl = document.querySelector( '.memdir-header-wrap[data-header="business"] .memdir-header__title' );
							if ( businessTitleEl ) { businessTitleEl.textContent = businessNameInput.value; }
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
	// 5. Right panel controls
	//
	// PRIMARY SECTION buttons — clicking a .memdir-panel__primary-btn saves
	// the new primary section via AJAX, then:
	//   - Updates button active states in the panel.
	//   - Calls updatePrimarySection() to reorder pills in the nav.
	// -----------------------------------------------------------------------

	function initRightPanel() {
		var panel = document.querySelector( '.memdir-right-panel' );
		if ( ! panel ) {
			return;
		}

		panel.querySelectorAll( '.memdir-panel__primary-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var sectionKey = btn.dataset.sectionKey || '';
				var nav        = document.querySelector( '.memdir-pills' );
				var postId     = nav ? ( nav.dataset.postId || '' ) : '';

				if ( ! sectionKey || ! postId ) {
					return;
				}

				var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
					? window.mdAjax.ajaxurl
					: '/wp-admin/admin-ajax.php';
				var nonce = ( window.mdAjax && window.mdAjax.nonce )
					? window.mdAjax.nonce
					: '';

				var formData = new FormData();
				formData.set( 'action',      'memdir_ajax_save_primary_section' );
				formData.set( 'nonce',       nonce );
				formData.set( 'post_id',     postId );
				formData.set( 'section_key', sectionKey );

				fetch( ajaxUrl, {
					method:      'POST',
					credentials: 'same-origin',
					body:        formData,
				} )
					.then( function ( response ) { return response.json(); } )
					.then( function ( data ) {
						if ( ! data.success ) {
							console.error( 'MemberDirectory: primary section AJAX returned error', data );
							return;
						}

						// Move active state on the primary-section buttons.
						panel.querySelectorAll( '.memdir-panel__primary-btn' ).forEach( function ( b ) {
							b.classList.toggle( 'is-active', b.dataset.sectionKey === sectionKey );
						} );

						// Reorder pills and update checkbox visibility.
						updatePrimarySection( sectionKey );
					} )
					.catch( function ( err ) {
						console.error( 'MemberDirectory: primary section AJAX failed', err );
					} );
			} );
		} );

		// GLOBAL PMP buttons — clicking a .memdir-panel__global-btn saves the
		// profile-wide default visibility level via AJAX and toggles the active
		// highlight to the clicked button.
		panel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var pmp    = btn.dataset.pmp || '';
				var nav    = document.querySelector( '.memdir-pills' );
				var postId = nav ? ( nav.dataset.postId || '' ) : '';

				if ( ! pmp || ! postId ) {
					return;
				}

				var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
					? window.mdAjax.ajaxurl
					: '/wp-admin/admin-ajax.php';
				var nonce = ( window.mdAjax && window.mdAjax.nonce )
					? window.mdAjax.nonce
					: '';

				var formData = new FormData();
				formData.set( 'action',  'memdir_ajax_save_global_pmp' );
				formData.set( 'nonce',   nonce );
				formData.set( 'post_id', postId );
				formData.set( 'pmp',     pmp );

				fetch( ajaxUrl, {
					method:      'POST',
					credentials: 'same-origin',
					body:        formData,
				} )
					.then( function ( response ) { return response.json(); } )
					.then( function ( data ) {
						if ( ! data.success ) {
							console.error( 'MemberDirectory: global PMP AJAX returned error', data );
							return;
						}
						// Move active highlight to the clicked button.
						panel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( b ) {
							b.classList.toggle( 'memdir-panel__global-btn--active', b.dataset.pmp === pmp );
						} );
					} )
					.catch( function ( err ) {
						console.error( 'MemberDirectory: global PMP AJAX failed', err );
					} );
			} );
		} );
	}

	/**
	 * Update the pill nav DOM when the primary section changes.
	 *
	 * - Moves the new primary pill to immediately after the All Sections pill.
	 * - Removes the checkbox from the new primary pill (primary cannot be disabled).
	 * - Restores a checkbox on the old primary pill.
	 * - Updates data-primary-section on the nav and on .memdir-sticky.
	 *
	 * @param {string} newPrimaryKey  Section key of the new primary section.
	 */
	function updatePrimarySection( newPrimaryKey ) {
		var nav        = document.querySelector( '.memdir-pills' );
		var allPill    = nav ? nav.querySelector( '.memdir-pill--all' ) : null;
		var oldPrimaryKey = nav ? ( nav.dataset.primarySection || '' ) : '';

		if ( ! nav || ! allPill || oldPrimaryKey === newPrimaryKey ) {
			return;
		}

		var newPrimaryPill = nav.querySelector( '.memdir-pill[data-section="' + newPrimaryKey + '"]' );
		var oldPrimaryPill = oldPrimaryKey
			? nav.querySelector( '.memdir-pill[data-section="' + oldPrimaryKey + '"]' )
			: null;

		if ( ! newPrimaryPill ) {
			return;
		}

		// Remove checkbox from the new primary pill and mark it primary.
		var newCheckbox = newPrimaryPill.querySelector( '.memdir-pill__checkbox' );
		if ( newCheckbox ) {
			newCheckbox.remove();
		}
		newPrimaryPill.classList.add( 'memdir-pill--primary' );

		// Primary sections are always enabled. If the promoted pill was previously
		// disabled, clear that state, show its section, update the badge, and persist.
		if ( newPrimaryPill.classList.contains( 'memdir-pill--disabled' ) ) {
			newPrimaryPill.classList.remove( 'memdir-pill--disabled' );

			var newPrimarySection = document.querySelector(
				'.memdir-section[data-section="' + newPrimaryKey + '"]'
			);
			if ( newPrimarySection ) {
				newPrimarySection.style.display = '';
			}

			var postId = nav.dataset.postId || '';
			if ( postId ) {
				saveSectionEnabled( postId, newPrimaryKey, true );
			}

			updateAllSectionsBadge( nav );
		}

		// Restore checkbox on the old primary pill.
		if ( oldPrimaryPill && ! oldPrimaryPill.querySelector( '.memdir-pill__checkbox' ) ) {
			var checkbox             = document.createElement( 'input' );
			checkbox.type            = 'checkbox';
			checkbox.className       = 'memdir-pill__checkbox';
			checkbox.dataset.section = oldPrimaryKey;
			checkbox.checked         = true; // Enabled by default when demoted.
			oldPrimaryPill.insertBefore( checkbox, oldPrimaryPill.firstChild );
			oldPrimaryPill.classList.remove( 'memdir-pill--primary' );

			// Wire the new checkbox into the enable/disable handler.
			bindCheckbox( checkbox, nav );
		}

		// Move new primary pill to first position (right after All Sections pill).
		nav.insertBefore( newPrimaryPill, allPill.nextSibling );

		// Record the new primary on both the nav and the sticky wrapper so
		// swapHeader() reads the updated value immediately.
		nav.dataset.primarySection = newPrimaryKey;
		var sticky = document.querySelector( '.memdir-sticky' );
		if ( sticky ) {
			sticky.dataset.primarySection = newPrimaryKey;
		}

		// Navigate to the new primary so it becomes the active single-section view.
		activatePill( newPrimaryKey );
	}

	// -----------------------------------------------------------------------
	// 6. Pill enable/disable
	//
	// Checkboxes on non-primary pills toggle whether a section is shown.
	// Unchecking a checkbox:
	//   - Adds .memdir-pill--disabled to the pill.
	//   - Hides the matching .memdir-section[data-section] in the content area.
	//   - Moves the pill to the end (after enabled pills, before other disabled).
	//   - Saves via AJAX (memdir_ajax_save_section_enabled, enabled=0).
	//
	// Checking a checkbox:
	//   - Removes .memdir-pill--disabled.
	//   - Shows the matching section.
	//   - Moves the pill before the first disabled pill (end of enabled group).
	//   - Saves via AJAX (enabled=1).
	//
	// On page load: any pills that are already disabled are sorted to the end.
	// -----------------------------------------------------------------------

	function initPillCheckboxes() {
		var nav = document.querySelector( '.memdir-pills' );
		if ( ! nav ) {
			return;
		}

		// Sort any already-disabled pills to the end on page load.
		reorderPills( nav );
		updateAllSectionsBadge( nav );

		// Wire change events on all existing checkboxes.
		nav.querySelectorAll( '.memdir-pill__checkbox' ).forEach( function ( checkbox ) {
			bindCheckbox( checkbox, nav );
		} );
	}

	/**
	 * Attach the change handler to a single pill checkbox.
	 *
	 * Called on init and also when updatePrimarySection() injects a new
	 * checkbox onto the demoted primary pill.
	 *
	 * @param {HTMLInputElement} checkbox  The checkbox element.
	 * @param {Element}          nav       The .memdir-pills nav element.
	 */
	function bindCheckbox( checkbox, nav ) {
		checkbox.addEventListener( 'change', function () {
			var sectionKey = checkbox.dataset.section || '';
			var pill       = checkbox.closest( '.memdir-pill' );
			var postId     = nav.dataset.postId || '';
			var enabled    = checkbox.checked;

			if ( ! pill || ! sectionKey ) {
				return;
			}

			// Toggle disabled class on the pill.
			pill.classList.toggle( 'memdir-pill--disabled', ! enabled );

			if ( ! enabled ) {
				// Capture the closest enabled neighbour BEFORE reordering so the
				// DOM order still reflects position relative to the disabled pill.
				var closestKey = findClosestEnabledPill( pill, nav );

				// Navigate to the closest still-enabled pill (reorder+badge run below).
				activatePill( closestKey );
			} else {
				// Enabling: show the section only when in "all" view or its own
				// single-section view. activatePill() owns visibility otherwise.
				var sectionEl = document.querySelector(
					'.memdir-section[data-section="' + sectionKey + '"]'
				);
				if ( sectionEl ) {
					var activePillEl = document.querySelector( '.memdir-pill--active' );
					var activeKeyEl  = activePillEl ? ( activePillEl.dataset.section || 'all' ) : 'all';
					if ( activeKeyEl === 'all' || activeKeyEl === sectionKey ) {
						sectionEl.style.display = '';
					}
				}
			}

			// Reorder pills: disabled pills go to the end.
			reorderPills( nav );

			// Update the All Sections badge count.
			updateAllSectionsBadge( nav );

			// Persist via AJAX (fire-and-forget — UI is already updated).
			if ( postId ) {
				saveSectionEnabled( postId, sectionKey, enabled );
			}
		} );
	}

	/**
	 * Reorder pills so disabled pills appear after enabled pills.
	 *
	 * Order: All Sections → primary → enabled non-primary → disabled non-primary.
	 * Relative order within each group is preserved (DOM order at call time).
	 *
	 * @param {Element} nav  The .memdir-pills nav element.
	 */
	function reorderPills( nav ) {
		var all      = Array.from( nav.querySelectorAll( '.memdir-pill' ) );
		var allPill  = all.filter( function ( p ) {
			return p.classList.contains( 'memdir-pill--all' );
		} );
		var primary  = all.filter( function ( p ) {
			return p.classList.contains( 'memdir-pill--primary' );
		} );
		var enabled  = all.filter( function ( p ) {
			return ! p.classList.contains( 'memdir-pill--all' ) &&
			       ! p.classList.contains( 'memdir-pill--primary' ) &&
			       ! p.classList.contains( 'memdir-pill--disabled' );
		} );
		var disabled = all.filter( function ( p ) {
			return ! p.classList.contains( 'memdir-pill--all' ) &&
			       ! p.classList.contains( 'memdir-pill--primary' ) &&
			       p.classList.contains( 'memdir-pill--disabled' );
		} );

		allPill.concat( primary ).concat( enabled ).concat( disabled ).forEach( function ( p ) {
			nav.appendChild( p );
		} );
	}

	/**
	 * Update the count badge on the All Sections pill to reflect the number
	 * of currently enabled sections (primary + non-primary, excluding disabled).
	 *
	 * @param {Element} nav  The .memdir-pills nav element.
	 */
	function updateAllSectionsBadge( nav ) {
		var countEl = nav.querySelector( '.memdir-pill--all .memdir-pill__count' );
		if ( ! countEl ) {
			return;
		}

		var enabledCount = nav.querySelectorAll(
			'.memdir-pill:not(.memdir-pill--all):not(.memdir-pill--disabled)'
		).length;

		countEl.textContent = enabledCount + ' enabled';
	}

	/**
	 * Find the pill closest (by DOM order) to a just-disabled pill that is
	 * still enabled. Searches backwards first, then forwards. Returns the
	 * section key, or "all" if no enabled non-all pill remains.
	 *
	 * @param {Element} disabledPill  The pill that was just disabled.
	 * @param {Element} nav           The .memdir-pills nav element.
	 * @returns {string}
	 */
	function findClosestEnabledPill( disabledPill, nav ) {
		var allPills    = Array.from( nav.querySelectorAll( '.memdir-pill' ) );
		var disabledIdx = allPills.indexOf( disabledPill );

		// Search backwards (towards the first pill) for an enabled non-all pill.
		for ( var i = disabledIdx - 1; i >= 0; i-- ) {
			var p = allPills[ i ];
			if ( ! p.classList.contains( 'memdir-pill--disabled' ) &&
			     ! p.classList.contains( 'memdir-pill--all' ) ) {
				return p.dataset.section || 'all';
			}
		}

		// Search forwards.
		for ( var j = disabledIdx + 1; j < allPills.length; j++ ) {
			var q = allPills[ j ];
			if ( ! q.classList.contains( 'memdir-pill--disabled' ) &&
			     ! q.classList.contains( 'memdir-pill--all' ) ) {
				return q.dataset.section || 'all';
			}
		}

		// No enabled section pill found — fall back to All Sections view.
		return 'all';
	}

	/**
	 * AJAX: persist section enabled/disabled state.
	 *
	 * Fire-and-forget — the UI is already updated before this is called.
	 *
	 * @param {string}  postId      The member-directory post ID.
	 * @param {string}  sectionKey  The section key (e.g. 'profile', 'business').
	 * @param {boolean} enabled     True = enabled, false = disabled.
	 */
	function saveSectionEnabled( postId, sectionKey, enabled ) {
		var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
			? window.mdAjax.ajaxurl
			: '/wp-admin/admin-ajax.php';
		var nonce = ( window.mdAjax && window.mdAjax.nonce )
			? window.mdAjax.nonce
			: '';

		var formData = new FormData();
		formData.set( 'action',      'memdir_ajax_save_section_enabled' );
		formData.set( 'nonce',       nonce );
		formData.set( 'post_id',     postId );
		formData.set( 'section_key', sectionKey );
		formData.set( 'enabled',     enabled ? '1' : '0' );

		fetch( ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			body:        formData,
		} ).catch( function () {
			// Silently fail — UI is already updated.
		} );
	}

	// -----------------------------------------------------------------------
	// 7. State restore
	//
	// Priority order on DOMContentLoaded:
	//   1. URL param ?active_section={key} — post-save reloads pass this.
	//   2. sessionStorage keyed to post ID — remembers state within a session.
	//   3. Primary section key — default active pill (not 'all').
	//
	// activatePill() writes to sessionStorage on every activation, so the
	// stored value stays current as the user navigates between pills.
	// -----------------------------------------------------------------------

	/**
	 * Restore active pill state from URL params, sessionStorage, or primary default.
	 */
	function restoreState() {
		var nav    = document.querySelector( '.memdir-pills' );
		var postId = nav ? ( nav.dataset.postId || '' ) : '';

		// 1. URL param — post-save page reload passes ?active_section={key}.
		var params = new URLSearchParams( window.location.search );
		var urlKey = params.get( 'active_section' ) || '';
		if ( urlKey ) {
			activatePill( urlKey );
			return;
		}

		// 2. sessionStorage — remembers which pill the user last activated for
		//    this specific post, persisted across navigations in the same session.
		if ( postId ) {
			var stored = '';
			try {
				stored = sessionStorage.getItem( 'memdir_active_pill_' + postId ) || '';
			} catch ( e ) {
				// sessionStorage unavailable — private browsing or quota exceeded.
			}
			if ( stored ) {
				activatePill( stored );
				return;
			}
		}

		// 3. Default: activate the primary section pill (not 'all') so the
		//    header and content are in sync on first load.
		var sticky         = document.querySelector( '.memdir-sticky' );
		var primarySection = ( sticky && sticky.dataset.primarySection ) || 'profile';
		activatePill( primarySection );
	}

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		initTabNav();
		initPillNav();
		initPillCheckboxes();
		initSectionSave();
		initRightPanel();
		restoreState();
	} );

}() );
