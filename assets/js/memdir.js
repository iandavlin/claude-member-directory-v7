/**
 * Member Directory -- memdir.js
 *
 * Front-end interactions for the Member Directory profile page.
 *
 * Sections:
 *   1. Tab navigation     -- show/hide ACF fields by tab group within a section
 *   2. Pill navigation    -- single-section / all-sections view switching
 *   3. Header swap        -- show correct header variant based on active pill
 *   4. Section save       -- AJAX save for all fields in a section without reload
 *   5. Right panel        -- Primary Section AJAX save + pill DOM update
 *   6. Section toggles     -- right-panel toggle switches enable/disable sections
 *   7. State restore      -- sessionStorage + URL param restore on page load
 *   8. Section PMP        -- 4-button inherit/public/member/private + eyebrow cascade
 *   9. Field PMP          -- per-field icon-button PMP controls injected after each ACF field
 *  11. Trust Network     -- trust request/respond/cancel/remove action buttons + toggle
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

					// Anchor scroll to the top of the section so switching from
					// a tall tab to a short one does not jump to another section.
					var sticky = document.querySelector( '.memdir-sticky' );
					var offset = sticky
						? ( parseInt( getComputedStyle( sticky ).top, 10 ) || 0 ) + sticky.offsetHeight + 8
						: 0;
					var rect = section.getBoundingClientRect();
					if ( rect.top < offset ) {
						window.scrollBy( 0, rect.top - offset );
					}
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
		// Skip fields inside <dialog> — they are managed by initHeaderEditing().
		// Skip sub-fields nested inside repeaters — only the parent repeater is in fieldKeys;
		// hiding sub-fields would make repeater rows appear empty.
		allFields.forEach( function ( field ) {
			if ( field.closest( 'dialog' ) ) { return; }
			if ( field.parentElement && field.parentElement.closest( '.acf-field[data-type="repeater"], .acf-field[data-type="flexible_content"], .acf-field[data-type="group"]' ) ) { return; }
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

				// Disabled pills are not navigable -- only the checkbox can re-enable.
				if ( pill.classList.contains( 'memdir-pill--disabled' ) ) {
					return;
				}

				activatePill( pill.dataset.section || 'all' );
				pill.blur();
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
	// All header variants live in the DOM as .memdir-header-wrap[data-header].
	// JS shows one at a time: the header matching the active pill key, or falls
	// back to the primary section when no matching header block exists.
	//
	// .memdir-sticky[data-primary-section] is the source of truth for the
	// primary section key.
	// -----------------------------------------------------------------------

	/**
	 * Show the header block matching the active pill key.
	 * Falls back to primary section when no matching header block exists.
	 *
	 * @param {string} sectionKey  The newly activated section key.
	 */
	function swapHeader( sectionKey ) {
		var sticky = document.querySelector( '.memdir-sticky' );
		if ( ! sticky ) { return; }
		var primarySection = sticky.dataset.primarySection || 'profile';
		var targetKey = ( sectionKey === 'all' ) ? primarySection : sectionKey;
		var sel = '.memdir-header-wrap[data-header="' + targetKey + '"]';
		var targetWrap = sticky.querySelector( sel );
		if ( ! targetWrap ) {
			// Fall back to primary section.
			targetKey  = primarySection;
			sel        = '.memdir-header-wrap[data-header="' + targetKey + '"]';
			targetWrap = sticky.querySelector( sel );
		}
		if ( ! targetWrap ) {
			// No header block for this key — show the first available one.
			targetWrap = sticky.querySelector( '.memdir-header-wrap[data-header]' );
			if ( targetWrap ) { targetKey = targetWrap.dataset.header || targetKey; }
		}
		sticky.querySelectorAll( '.memdir-header-wrap[data-header]' ).forEach( function ( wrap ) {
			wrap.style.display = ( wrap.dataset.header === targetKey ) ? '' : 'none';
		} );
	}

	
	// -----------------------------------------------------------------------
	// 4. Section save (AJAX)
	//
	// Each .memdir-section--edit wraps:
	//   .memdir-unsaved-banner  -- shown when any field in the section changes.
	//   .memdir-section-save    -- button that collects the section's ACF form
	//                             fields and POSTs them via fetch without a
	//                             full page reload.
	//
	// Flow:
	//   Any input/change inside .memdir-field-content
	//     -> section.classList.add('has-unsaved')
	//     -> banner.style.display = ''
	//
	//   Save button click  OR  Enter in any text input
	//     -> collect FormData from the section's .acf-form
	//     -> POST action=md_save_section, nonce, post_id, acf[...] fields
	//     -> success: show 'Saved checkmark' on button; update .memdir-header__title
	//               in place if field_md_profile_page_name was in the payload
	//     -> error:   show error state on button for 3 s
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

			// Intercept Enter in text inputs -- treat it as a save rather than
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

		var formData = new FormData();

		formData.set( 'action',  'md_save_section' );
		formData.set( 'nonce',   ( window.mdAjax && window.mdAjax.nonce )   ? window.mdAjax.nonce   : '' );
		formData.set( 'post_id', postId );

		// Sync any WYSIWYG (TinyMCE) editors so their textareas hold current content.
		if ( window.tinyMCE ) { window.tinyMCE.triggerSave(); }

		// Collect all form controls inside fieldContent.
		// Use each input's own name attribute — ACF sets correct names for all
		// field types including repeaters (acf[rep_key][row-0][sub_key]).
		var inputs = fieldContent.querySelectorAll( 'input, textarea, select' );
		inputs.forEach( function ( input ) {
			var name = input.name || '';

			// Must start with acf[ to be an ACF-managed field.
			if ( name.indexOf( 'acf[' ) !== 0 ) { return; }

			// Skip file inputs — handled by AJAX uploaders, never submitted via saveSection.
			if ( input.type === 'file' ) { return; }

			// Skip unchecked checkboxes and radios.
			if ( ( input.type === 'checkbox' || input.type === 'radio' ) && ! input.checked ) { return; }

			// Skip custom-flagged inputs (taxonomy search text boxes, old ACF gallery inputs).
			if ( input.dataset && input.dataset.memdirSkip ) { return; }

			formData.append( name, input.value );
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

					// Show 'Saved checkmark' then reload with section/tab params preserved.
					saveBtn.textContent = 'Saved \u2713';
					saveBtn.classList.add( 'memdir-section-save--saved' );
					var reloadSectionKey = section.dataset.section || 'all';
					var reloadTabBtn = section.querySelector( '.memdir-section-controls__tab-item.is-active' );
					var reloadTabLabel = reloadTabBtn ? reloadTabBtn.textContent.trim() : '';
					setTimeout( function () {
						// Strip ACF's beforeunload warning before navigating.
						window.onbeforeunload = null;
						if ( typeof jQuery !== 'undefined' ) { jQuery( window ).off( 'beforeunload' ); }
						var reloadUrl = new URL( window.location.href );
						reloadUrl.searchParams.set( 'active_section', reloadSectionKey );
						reloadUrl.searchParams.set( '_t', Date.now().toString() );
						if ( reloadTabLabel ) {
							reloadUrl.searchParams.set( 'active_tab', reloadTabLabel );
						}
						window.location.href = reloadUrl.toString();
					}, 1500 );

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
	// PRIMARY SECTION buttons -- clicking a .memdir-panel__primary-btn saves
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

						// Reload the page so PHP renders the new primary state
						// correctly (enables section, reorders pills, swaps header).
						// The disabled section may not exist in the DOM at all, so a
						// JS-only update is not reliable.
						window.onbeforeunload = null;
						if ( typeof jQuery !== 'undefined' ) { jQuery( window ).off( 'beforeunload' ); }
						var reloadUrl = new URL( window.location.href );
						reloadUrl.searchParams.set( 'active_section', sectionKey );
						reloadUrl.searchParams.set( '_t', Date.now().toString() );
						window.location.href = reloadUrl.toString();
					} )
					.catch( function ( err ) {
						console.error( 'MemberDirectory: primary section AJAX failed', err );
					} );
			} );
		} );

		// GLOBAL PMP buttons -- clicking a .memdir-panel__global-btn saves the
		// profile-wide default visibility level via AJAX and toggles the active
		// highlight to the clicked button. Also cascades to inherit-mode sections.
		panel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var pmp    = btn.dataset.pmp || '';
				var nav    = document.querySelector( '.memdir-pills' );
				var postId = nav ? ( nav.dataset.postId || '' ) : '';

				if ( ! pmp || ! postId ) {
					return;
				}

				// Optimistic UI: apply the active class immediately and blur the button
				// so BuddyBoss's :focus/:active styles don't override the new background.
				var prevPmp = '';
				panel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( b ) {
					if ( b.classList.contains( 'memdir-panel__global-btn--active' ) ) { prevPmp = b.dataset.pmp || ''; }
					b.classList.toggle( 'memdir-panel__global-btn--active', b.dataset.pmp === pmp );
				} );
				btn.blur();

				// Cascade to inherit-mode sections immediately.
				cascadeGlobalPmpToSections( pmp );

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
							// Revert optimistic change.
							panel.querySelectorAll( '.memdir-panel__global-btn' ).forEach( function ( b ) {
								b.classList.toggle( 'memdir-panel__global-btn--active', b.dataset.pmp === prevPmp );
							} );
							cascadeGlobalPmpToSections( prevPmp );
						}
					} )
					.catch( function ( err ) {
						console.error( 'MemberDirectory: global PMP AJAX failed', err );
					} );
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Section toggles (right panel)
	//
	// Toggle switches in .memdir-panel__sections control whether a non-primary
	// section is enabled. On change: update the pill disabled state, update
	// the All Sections badge, then persist via AJAX + reload.
	// -----------------------------------------------------------------------

	function initSectionToggles() {
		var panel = document.querySelector( '.memdir-right-panel' );
		if ( ! panel ) {
			return;
		}

		var nav = document.querySelector( '.memdir-pills' );

		// Run badge count on init.
		if ( nav ) {
			updateAllSectionsBadge( nav );
		}

		panel.querySelectorAll( '.memdir-panel__toggle input[type="checkbox"]' ).forEach( function ( toggle ) {
			// Skip trust toggle — handled by initTrustNetwork().
			if ( toggle.dataset.trustToggle ) {
				return;
			}
			toggle.addEventListener( 'change', function () {
				var sectionKey = toggle.dataset.sectionKey || '';
				var enabled    = toggle.checked;
				var postId     = nav ? ( nav.dataset.postId || '' ) : '';

				if ( ! sectionKey || ! postId ) {
					return;
				}

				// Update pill disabled state.
				if ( nav ) {
					var pill = nav.querySelector( '.memdir-pill[data-section="' + sectionKey + '"]' );
					if ( pill ) {
						pill.classList.toggle( 'memdir-pill--disabled', ! enabled );
					}
					updateAllSectionsBadge( nav );
				}

				// Persist + reload.
				saveSectionEnabled( postId, sectionKey, enabled );
			} );
		} );
	}

	/**
	 * Update the pill nav DOM when the primary section changes.
	 *
	 * - Moves the new primary pill to immediately after the All Sections pill.
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

		// Mark the new pill as primary.
		newPrimaryPill.classList.add( 'memdir-pill--primary' );

		// Always ensure the pill is visible -- hideEmptySectionPills() may have
		// hidden it via inline style when its section was absent from the DOM.
		newPrimaryPill.style.display = '';

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

		// Remove primary class from the old pill.
		if ( oldPrimaryPill ) {
			oldPrimaryPill.classList.remove( 'memdir-pill--primary' );
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
	// 6. Section toggles
	//
	// Enable/disable toggles live in the right panel (.memdir-panel__toggle).
	// Toggling fires saveSectionEnabled() which persists via AJAX and reloads
	// the page so conditional tabs in other sections update.
	//
	// updateAllSectionsBadge() runs on init to keep the badge accurate.
	// -----------------------------------------------------------------------
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

		// Count pills that are enabled (no --disabled) AND visible (not display:none).
		var enabledCount = Array.from( nav.querySelectorAll(
			'.memdir-pill:not(.memdir-pill--all):not(.memdir-pill--disabled)'
		) ).filter( function ( p ) { return p.style.display !== 'none'; } ).length;

		countEl.textContent = enabledCount + ' enabled';
	}

	/**
	 * AJAX: persist section enabled/disabled state.
	 *
	 * Fire-and-forget -- the UI is already updated before this is called.
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
		} ).then( function () {
			// Reload so conditional tabs in other sections update.
			window.location.reload();
		} ).catch( function () {
			// Silently fail -- UI is already updated.
		} );
	}

	// -----------------------------------------------------------------------
	// 7. State restore
	//
	// Priority order on DOMContentLoaded:
	//   1. URL param ?active_section={key} -- post-save reloads pass this.
	//   2. Primary section key -- default active pill (not 'all').
	// -----------------------------------------------------------------------

	/**
	 * Hide pills whose section was not rendered by PHP (empty or PMP-blocked).
	 *
	 * In view mode PHP silently skips sections with zero visible fields.
	 * On load we detect absent sections and hide their pills, then sync the
	 * badge count so it only reflects actually-visible sections.
	 *
	 * Skips pills that are already --disabled -- those are managed by the
	 * right-panel toggles and must stay visible so the author can re-enable them.
	 */
	function hideEmptySectionPills() {
		var nav = document.querySelector( '.memdir-pills' );
		if ( ! nav ) {
			return;
		}

		nav.querySelectorAll( '.memdir-pill[data-section]' ).forEach( function ( pill ) {
			var key = pill.dataset.section || '';
			if ( ! key || pill.classList.contains( 'memdir-pill--all' ) ) {
				return;
			}
			// Already disabled -- managed by right-panel toggles, leave visible.
			if ( pill.classList.contains( 'memdir-pill--disabled' ) ) {
				return;
			}
			// If no matching section element exists in the DOM, PHP dropped it.
			var section = document.querySelector( '.memdir-section[data-section="' + key + '"]' );
			if ( ! section ) {
				pill.style.display = 'none';
			}
		} );

		// Re-sync badge now that some pills may be hidden.
		updateAllSectionsBadge( nav );
	}

	/**
	 * Restore active pill state from URL params, sessionStorage, or primary default.
	 */
	function restoreState() {
		var nav    = document.querySelector( '.memdir-pills' );
		var postId = nav ? ( nav.dataset.postId || '' ) : '';

		// 1. URL param -- post-save page reload passes ?active_section={key}.
		var params = new URLSearchParams( window.location.search );
		var urlKey = params.get( 'active_section' ) || '';
		if ( urlKey ) {
			activatePill( urlKey );
			return;
		}

		// 2. Default: activate the primary section pill (not 'all') so the
		//    header and content are in sync on every page load.
		var sticky         = document.querySelector( '.memdir-sticky' );
		var primarySection = ( sticky && sticky.dataset.primarySection ) || 'profile';
		activatePill( primarySection );
	}

	// -----------------------------------------------------------------------
	// 8. Section PMP
	//
	// Each .memdir-section--edit has a .memdir-section-controls__pmp block with
	// four buttons: inherit (link), public (globe), member (people), private (lock).
	//
	// Clicking a button:
	//   - Moves is-active to the clicked button (optimistic).
	//   - Updates the eyebrow text (.memdir-section-controls__pmp-status).
	//   - POSTs to memdir_ajax_save_section_pmp.
	//   - On error: reverts button and eyebrow.
	//
	// When global PMP changes, all inherit-mode sections update their eyebrow.
	// -----------------------------------------------------------------------

	/** Human-readable labels matching the PHP $pmp_labels array. */
	var PMP_LABELS = {
		'public':  'Public',
		'member':  'Members only',
		'private': 'Private',
	};

	/**
	 * Wire up PMP button clicks for each edit-mode section.
	 */
	function initSectionPmp() {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var controls = section.querySelector( '.memdir-section-controls' );
			if ( ! controls ) {
				return;
			}

			var btns   = controls.querySelectorAll( '.memdir-section-controls__pmp-btn' );
			var status = controls.querySelector( '.memdir-section-controls__pmp-status' );

			btns.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var pmp        = btn.dataset.pmp || '';
					var postId     = section.dataset.postId || '';
					var sectionKey = section.dataset.section || '';

					if ( ! pmp || ! postId || ! sectionKey ) {
						return;
					}

					// Optimistic: move is-active to clicked button.
					var prevPmp = '';
					btns.forEach( function ( b ) {
						if ( b.classList.contains( 'is-active' ) ) {
							prevPmp = b.dataset.pmp || '';
						}
						b.classList.toggle( 'is-active', b === btn );
					} );

					// Update section eyebrow text immediately.
					if ( status ) {
						updateSectionPmpStatus( status, pmp );
					}

					// Cascade new section PMP to all per-field PMP eyebrows
					// in this section (fields in inherit mode now show the new
					// section value as their winning state).
					refreshSectionFieldPmpEyebrows( section );

					// AJAX save.
					var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
						? window.mdAjax.ajaxurl
						: '/wp-admin/admin-ajax.php';
					var nonce = ( window.mdAjax && window.mdAjax.nonce )
						? window.mdAjax.nonce
						: '';

					var formData = new FormData();
					formData.set( 'action',      'memdir_ajax_save_section_pmp' );
					formData.set( 'nonce',       nonce );
					formData.set( 'post_id',     postId );
					formData.set( 'section_key', sectionKey );
					formData.set( 'pmp',         pmp );

					fetch( ajaxUrl, {
						method:      'POST',
						credentials: 'same-origin',
						body:        formData,
					} )
						.then( function ( response ) { return response.json(); } )
						.then( function ( data ) {
							if ( ! data.success ) {
								console.error( 'MemberDirectory: section PMP AJAX returned error', data );
								// Revert optimistic change.
								btns.forEach( function ( b ) {
									b.classList.toggle( 'is-active', b.dataset.pmp === prevPmp );
								} );
								if ( status ) {
									updateSectionPmpStatus( status, prevPmp );
								}
								refreshSectionFieldPmpEyebrows( section );
							}
						} )
						.catch( function ( err ) {
							console.error( 'MemberDirectory: section PMP AJAX failed', err );
						} );
				} );
			} );
		} );
	}

	/**
	 * Update a section's eyebrow text and data-pmp-mode attribute.
	 *
	 * @param {Element} statusEl  The .memdir-section-controls__pmp-status element.
	 * @param {string}  pmp       The new PMP value: 'inherit'|'public'|'member'|'private'.
	 */
	function updateSectionPmpStatus( statusEl, pmp ) {
		var globalPmp = getGlobalPmp();

		if ( pmp === 'inherit' ) {
			statusEl.textContent     = 'Global default: ' + ( PMP_LABELS[ globalPmp ] || 'Public' );
			statusEl.dataset.pmpMode = 'inherit';
		} else {
			statusEl.textContent     = 'Section override: ' + ( PMP_LABELS[ pmp ] || pmp );
			statusEl.dataset.pmpMode = 'override';
		}
	}

	/**
	 * Read the currently active global PMP from the right panel buttons.
	 *
	 * @returns {string}  'public', 'member', or 'private'.
	 */
	function getGlobalPmp() {
		var activeBtn = document.querySelector( '.memdir-panel__global-btn--active' );
		return activeBtn ? ( activeBtn.dataset.pmp || 'public' ) : 'public';
	}

	/**
	 * Update the eyebrow text of all inherit-mode sections when global PMP changes.
	 * Also cascades to all per-field PMP eyebrows across all sections.
	 *
	 * Called after the global PMP button is clicked (optimistically) and on revert.
	 *
	 * @param {string} newGlobalPmp  The new global PMP value.
	 */
	function cascadeGlobalPmpToSections( newGlobalPmp ) {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var status = section.querySelector( '.memdir-section-controls__pmp-status' );
			if ( ! status ) {
				return;
			}

			// Only update sections currently in inherit mode.
			if ( status.dataset.pmpMode !== 'inherit' ) {
				return;
			}

			status.textContent = 'Global default: ' + ( PMP_LABELS[ newGlobalPmp ] || 'Public' );
		} );

		// Cascade to per-field PMP eyebrows across all sections.
		// computeFieldPmpStatus reads the current DOM state (including the freshly
		// updated global button), so this correctly reflects the new global value.
		document.querySelectorAll( '.memdir-section--edit .memdir-field-pmp' ).forEach( function ( fp ) {
			var statusEl = fp.querySelector( '.memdir-field-pmp__status' );
			if ( statusEl ) {
				statusEl.textContent = computeFieldPmpStatus( fp );
			}
		} );
	}

	// -----------------------------------------------------------------------
	// 9. Field PMP
	//
	// Per-field PMP companions (button_group fields named *_pmp_*) are excluded
	// from acf_form() rendering by PHP. Instead, JS injects custom icon-button
	// controls matching the section-level PMP style directly inside each
	// .acf-field wrapper — so they hide/show with their parent field when tabs
	// switch, and no separate show/hide logic is needed.
	//
	// PHP passes the initial field PMP data via data-field-pmp on the section
	// wrapper (JSON map: fieldKey -> { companionKey, storedPmp }).
	//
	// Each control shows a status eyebrow resolved via the waterfall:
	//   stored_pmp -> section_pmp -> global_pmp
	//
	// Clicking a button:
	//   - Moves is-active to the clicked button (optimistic).
	//   - Updates data-stored-pmp on the control wrapper.
	//   - Recomputes and updates the status eyebrow.
	//   - POSTs to memdir_ajax_save_field_pmp.
	//   - On error: reverts button and eyebrow.
	// -----------------------------------------------------------------------

	/**
	 * Compute the eyebrow text for a field PMP control by reading current DOM state.
	 *
	 * Resolution order: field -> section -> global.
	 * Reads the active section PMP button and the active global PMP button live
	 * from the DOM so cascade changes are reflected without extra data tracking.
	 *
	 * @param {Element} fieldPmpEl  The .memdir-field-pmp wrapper element.
	 * @returns {string}
	 */
	function computeFieldPmpStatus( fieldPmpEl ) {
		var storedPmp  = fieldPmpEl.dataset.storedPmp || 'inherit';
		var fieldLabel = fieldPmpEl.dataset.fieldLabel || '';

		if ( storedPmp !== 'inherit' ) {
			return ( fieldLabel ? fieldLabel + ' · ' : '' ) + 'Field override: ' + ( PMP_LABELS[ storedPmp ] || storedPmp );
		}

		// Inherit from section -- read the currently active section PMP button.
		var section          = fieldPmpEl.closest( '.memdir-section--edit' );
		var activeSectionBtn = section
			? section.querySelector( '.memdir-section-controls__pmp-btn.is-active' )
			: null;
		var sectionPmp = activeSectionBtn ? ( activeSectionBtn.dataset.pmp || 'inherit' ) : 'inherit';

		if ( sectionPmp !== 'inherit' ) {
			return ( fieldLabel ? fieldLabel + ' · ' : '' ) + 'Section: ' + ( PMP_LABELS[ sectionPmp ] || sectionPmp );
		}

		// Inherit from global.
		return ( fieldLabel ? fieldLabel + ' · ' : '' ) + 'Global: ' + ( PMP_LABELS[ getGlobalPmp() ] || 'Public' );
	}

	/**
	 * Refresh the eyebrow text of all field PMP controls within a section.
	 * Called after the section-level PMP changes so inherit-mode fields update.
	 *
	 * @param {Element} section  The .memdir-section--edit element.
	 */
	function refreshSectionFieldPmpEyebrows( section ) {
		section.querySelectorAll( '.memdir-field-pmp' ).forEach( function ( fp ) {
			var statusEl = fp.querySelector( '.memdir-field-pmp__status' );
			if ( statusEl ) {
				statusEl.textContent = computeFieldPmpStatus( fp );
			}
		} );
	}

	/**
	 * Inject per-field PMP controls into each .acf-field wrapper and wire
	 * click handlers and AJAX save for all edit-mode sections.
	 */
	/**
	 * Move ACF field instructions from .acf-label to below .acf-input
	 * so they don't crowd the label row where the PMP controls sit.
	 */
	function relocateFieldInstructions() {
		document.querySelectorAll( '.memdir-section--edit .acf-field > .acf-label > p.description' ).forEach( function ( desc ) {
			var acfInput = desc.closest( '.acf-field' ).querySelector( '.acf-input' );
			if ( acfInput ) {
				acfInput.after( desc );
			}
		} );
	}

	function initFieldPmp() {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var fieldPmpData = {};
			try {
				fieldPmpData = JSON.parse( section.dataset.fieldPmp || '{}' );
			} catch ( e ) {
				fieldPmpData = {};
			}

			Object.keys( fieldPmpData ).forEach( function ( fieldKey ) {
				var data         = fieldPmpData[ fieldKey ];
				var storedPmp    = data.storedPmp    || 'inherit';
				var companionKey  = data.companionKey  || '';
				var companionName = data.companionName || '';

				var fieldEl = section.querySelector( '.acf-field[data-key="' + fieldKey + '"]' );
				if ( ! fieldEl ) {
					return;
				}

				// Build the control wrapper.
				var wrap = document.createElement( 'div' );
				wrap.className            = 'memdir-field-pmp';
				wrap.dataset.fieldKey     = fieldKey;
				wrap.dataset.companionKey = companionKey;
				wrap.dataset.storedPmp    = storedPmp;

				var labelEl = fieldEl.querySelector( ".acf-label label" );
				wrap.dataset.fieldLabel = labelEl ? labelEl.textContent.trim().replace( /\s*\*$/, "" ) : "";

				var row = document.createElement( 'div' );
				row.className = 'memdir-field-pmp__row';

				var pmpValues = [ 'inherit', 'public', 'member', 'private' ];

				pmpValues.forEach( function ( pmpVal ) {
					var btn = document.createElement( 'button' );
					btn.type        = 'button';
					btn.className   = 'memdir-field-pmp__btn memdir-field-pmp__btn--' + pmpVal +
					                  ( storedPmp === pmpVal ? ' is-active' : '' );
					btn.dataset.pmp = pmpVal;
					btn.setAttribute( 'aria-label', {
						inherit: 'Inherit section setting',
						public:  'Public',
						member:  'Members only',
						private: 'Private',
					}[ pmpVal ] || pmpVal );
					row.appendChild( btn );
				} );

				var statusSpan = document.createElement( 'span' );
				statusSpan.className   = 'memdir-field-pmp__status';
				statusSpan.textContent = computeFieldPmpStatus( wrap );
				row.appendChild( statusSpan );

				wrap.appendChild( row );
				var labelEl = fieldEl.querySelector( ".acf-label" );
				( labelEl || fieldEl ).appendChild( wrap );

				// Wire click handlers on the 4 icon buttons.
				row.querySelectorAll( '.memdir-field-pmp__btn' ).forEach( function ( btn ) {
					btn.addEventListener( 'click', function () {
						var pmp    = btn.dataset.pmp || '';
						var postId = section.dataset.postId || '';

						if ( ! pmp || ! postId || ! companionName ) {
							if ( ! companionName ) {
								console.warn( 'MemberDirectory: field PMP missing companionName for', fieldKey );
							}
							return;
						}

						// Optimistic: update stored PMP and button active state.
						var prevPmp = wrap.dataset.storedPmp || 'inherit';
						wrap.dataset.storedPmp = pmp;

						row.querySelectorAll( '.memdir-field-pmp__btn' ).forEach( function ( b ) {
							b.classList.toggle( 'is-active', b === btn );
						} );

						statusSpan.textContent = computeFieldPmpStatus( wrap );

						// AJAX save.
						var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
							? window.mdAjax.ajaxurl
							: '/wp-admin/admin-ajax.php';
						var nonce = ( window.mdAjax && window.mdAjax.nonce )
							? window.mdAjax.nonce
							: '';

						var formData = new FormData();
						formData.set( 'action',        'memdir_ajax_save_field_pmp' );
						formData.set( 'nonce',         nonce );
						formData.set( 'post_id',       postId );
						formData.set( 'companion_name', companionName );
						formData.set( 'pmp',           pmp );

						fetch( ajaxUrl, {
							method:      'POST',
							credentials: 'same-origin',
							body:        formData,
						} )
							.then( function ( response ) { return response.json(); } )
							.then( function ( data ) {
								if ( data.success ) {
									// Brief visual confirmation — flash the status text.
									var savedText = statusSpan.textContent;
									statusSpan.textContent = '✓ Saved';
									statusSpan.classList.add( 'memdir-field-pmp__status--saved' );
									setTimeout( function () {
										statusSpan.textContent = savedText;
										statusSpan.classList.remove( 'memdir-field-pmp__status--saved' );
									}, 1200 );
								} else {
									console.error( 'MemberDirectory: field PMP AJAX error', data );
									// Revert optimistic change.
									wrap.dataset.storedPmp = prevPmp;
									row.querySelectorAll( '.memdir-field-pmp__btn' ).forEach( function ( b ) {
										b.classList.toggle( 'is-active', b.dataset.pmp === prevPmp );
									} );
									statusSpan.textContent = computeFieldPmpStatus( wrap );
								}
							} )
							.catch( function ( err ) {
								console.error( 'MemberDirectory: field PMP AJAX failed', err );
								// Revert optimistic change on network failure.
								wrap.dataset.storedPmp = prevPmp;
								row.querySelectorAll( '.memdir-field-pmp__btn' ).forEach( function ( b ) {
									b.classList.toggle( 'is-active', b.dataset.pmp === prevPmp );
								} );
								statusSpan.textContent = computeFieldPmpStatus( wrap );
							} );
					} );
				} );
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// 10. Sticky section controls
	//
	// The section controls column needs to stick just below the sticky header
	// zone. We measure .memdir-sticky's rendered height + CSS top at runtime
	// and apply it as an inline style so it always tracks the real layout.
	// -----------------------------------------------------------------------

	// -----------------------------------------------------------------------
	// Per-element Header Editing
	//
	// Each header element (avatar, name, categories, social links) gets its
	// own edit trigger and small modal. The avatar uses a camera overlay;
	// name, categories, and social links each get an inline pencil icon.
	// Pencil icons pulse gold when their corresponding fields are empty.
	// -----------------------------------------------------------------------

	var SOCIAL_SUFFIXES = [
		'_website', '_linkedin', '_instagram', '_twitter', '_facebook',
		'_youtube', '_tiktok', '_vimeo', '_linktree'
	];

	function isSocialField( fieldEl ) {
		var name = fieldEl.dataset.name || '';
		var type = fieldEl.dataset.type || '';
		if ( type !== 'url' ) { return false; }
		for ( var s = 0; s < SOCIAL_SUFFIXES.length; s++ ) {
			if ( name.length > SOCIAL_SUFFIXES[ s ].length &&
				name.substring( name.length - SOCIAL_SUFFIXES[ s ].length ) === SOCIAL_SUFFIXES[ s ] ) {
				return true;
			}
		}
		return false;
	}

	
	// Helper: Create custom taxonomy search box (replaces select2)
	// Supports both single-select and multi-select taxonomy fields.
		function createTaxonomySearch( acfField ) {
			var selectElement = acfField.querySelector( 'select' );
			if ( ! selectElement ) { return; }

			var isMulti  = selectElement.multiple;
			var fieldKey = acfField.dataset.key || '';

			// Destroy select2 and hide the entire ACF input wrapper
			var acfInput = acfField.querySelector( '.acf-input' );
			if ( typeof jQuery !== 'undefined' && jQuery.fn.select2 ) {
				try { jQuery( selectElement ).select2( 'destroy' ); } catch ( e ) { /* ok */ }
			}
			if ( acfInput ) { acfInput.style.display = 'none'; }

			// Create search wrapper
			var wrapper = document.createElement( 'div' );
			wrapper.className = 'memdir-taxo-search';

			var input = document.createElement( 'input' );
			input.type = 'text';
			input.className = 'memdir-taxo-search__input';
			input.dataset.memdirSkip = '1';
			input.placeholder = 'Type to search…';

			var results = document.createElement( 'div' );
			results.className = 'memdir-taxo-search__results';

			wrapper.appendChild( input );
			wrapper.appendChild( results );

			// Single-select: one badge element
			var badge = null;
			// Multi-select: badge container for multiple pills
			var badgeContainer = null;

			if ( isMulti ) {
				badgeContainer = document.createElement( 'div' );
				badgeContainer.className = 'memdir-taxo-search__badges';
				wrapper.appendChild( badgeContainer );
			} else {
				badge = document.createElement( 'div' );
				badge.className = 'memdir-taxo-search__badge';
				badge.style.display = 'none';
				wrapper.appendChild( badge );
			}

			// "Browse all" link
		var browseBtn = document.createElement( 'button' );
		browseBtn.type = 'button';
		browseBtn.className = 'memdir-taxo-search__browse';
		browseBtn.textContent = 'Browse all';
		wrapper.appendChild( browseBtn );
		var browseModal = null;  // lazy — built on first click

		acfField.appendChild( wrapper );

			// Build a set of currently selected IDs for quick lookup
			function getSelectedIds() {
				var ids = {};
				Array.from( selectElement.selectedOptions ).forEach( function ( o ) {
					if ( o.value ) { ids[ o.value ] = true; }
				} );
				return ids;
			}

			// Show currently selected value(s)
			function updateSelectedDisplay() {
				if ( isMulti ) {
					// Multi-select: render badge pills with × remove
					badgeContainer.innerHTML = '';
					var hasSelection = false;
					Array.from( selectElement.options ).forEach( function ( opt ) {
						if ( ! opt.selected || ! opt.value ) { return; }
						hasSelection = true;

						var pill = document.createElement( 'span' );
						pill.className = 'memdir-taxo-search__badge-pill';

						var pillText = document.createTextNode( opt.textContent.trim() );
						pill.appendChild( pillText );

						var removeBtn = document.createElement( 'button' );
						removeBtn.type = 'button';
						removeBtn.className = 'memdir-taxo-search__badge-remove';
						removeBtn.innerHTML = '&times;';
						removeBtn.title = 'Remove';
						removeBtn.dataset.value = opt.value;

						removeBtn.addEventListener( 'click', function () {
							opt.selected = false;
							selectElement.dispatchEvent( new Event( 'change', { bubbles: true } ) );
							updateSelectedDisplay();
							// Re-render dropdown if open to update highlights
							if ( results.style.display === 'block' ) {
								updateResultHighlights();
							}
						} );

						pill.appendChild( removeBtn );
						badgeContainer.appendChild( pill );
					} );

					input.value = '';
					input.placeholder = hasSelection ? 'Add more…' : 'Type to search…';
				} else {
					// Single-select: one badge with × clear button
					var selectedOpt = selectElement.querySelector( 'option:checked' );
					if ( selectedOpt && selectedOpt.value ) {
						var name = selectedOpt.textContent.trim();
						badge.innerHTML = '';
						badge.style.display = 'inline-flex';

						var badgeText = document.createTextNode( '✓ ' + name );
						badge.appendChild( badgeText );

						var clearBtn = document.createElement( 'button' );
						clearBtn.type = 'button';
						clearBtn.className = 'memdir-taxo-search__badge-clear';
						clearBtn.innerHTML = '&times;';
						clearBtn.title = 'Clear selection';
						clearBtn.addEventListener( 'click', function () {
							Array.from( selectElement.options ).forEach( function ( o ) { o.selected = false; } );
							var emptyOpt = selectElement.querySelector( 'option[value=""]' );
							if ( emptyOpt ) { emptyOpt.selected = true; }
							selectElement.dispatchEvent( new Event( 'change', { bubbles: true } ) );
							updateSelectedDisplay();
						} );
						badge.appendChild( clearBtn );

						input.value = '';
						input.placeholder = 'Change selection…';
					} else {
						badge.innerHTML = '';
						badge.style.display = 'none';
						input.value = '';
						input.placeholder = 'Type to search…';
					}
				}
			}
			updateSelectedDisplay();

			// Highlight already-selected items in dropdown
			function updateResultHighlights() {
				var selectedIds = getSelectedIds();
				results.querySelectorAll( '.memdir-taxo-search__result-item' ).forEach( function ( item ) {
					var id = item.dataset.id || '';
					item.classList.toggle( 'is-selected', !! selectedIds[ id ] );
				} );
			}

			// Debounce timer
			var debounceTimer = null;

			// Read the taxonomy slug from ACF's field config
			var taxonomySlug = '';
			var acfFieldData = acfField.querySelector( '[data-taxonomy]' );
			if ( acfFieldData ) {
				taxonomySlug = acfFieldData.dataset.taxonomy || '';
			}
			// Fallback: try to find it from select name
			if ( ! taxonomySlug && selectElement.name ) {
				var nameMatch = selectElement.name.match( /acf[.*?]/ );
				if ( ! nameMatch ) { taxonomySlug = selectElement.dataset.taxonomy || ''; }
			}

			// Search terms via our own AJAX endpoint (no ACF nonce issues)
			function searchTerms( query ) {
				results.innerHTML = '<div class="memdir-taxo-search__no-results">Searching…</div>';
				results.style.display = 'block';

				var ajaxUrl = ( typeof mdAjax !== 'undefined' ) ? mdAjax.ajaxurl : '/wp-admin/admin-ajax.php';
				var nonce   = ( typeof mdAjax !== 'undefined' ) ? mdAjax.search_nonce : '';

				var formData = new FormData();
				formData.append( 'action', 'memdir_search_taxonomy_terms' );
				formData.append( 'taxonomy', taxonomySlug );
				formData.append( 'search', query );
				formData.append( '_wpnonce', nonce );

				fetch( ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					results.innerHTML = '';

					var items = ( data && data.results ) ? data.results : [];

					if ( items.length === 0 ) {
						results.innerHTML = '<div class="memdir-taxo-search__no-results">No matches</div>';
						results.style.display = 'block';
						return;
					}

					var selectedIds = getSelectedIds();

					items.forEach( function ( term ) {
						var item = document.createElement( 'div' );
						item.className = 'memdir-taxo-search__result-item';
						if ( selectedIds[ term.id ] ) {
							item.classList.add( 'is-selected' );
						}
						item.textContent = term.text || term.name || '';
						item.dataset.id = term.id || '';

						// Prevent mousedown from stealing focus — stops blur from hiding results
						item.addEventListener( 'mousedown', function ( e ) {
							e.preventDefault();
						} );

						item.addEventListener( 'click', function () {
							if ( isMulti ) {
								// Multi-select: toggle selection
								var existing = selectElement.querySelector( 'option[value="' + term.id + '"]' );
								if ( existing && existing.selected ) {
									// Already selected — deselect
									existing.selected = false;
								} else if ( existing ) {
									existing.selected = true;
								} else {
									var opt = document.createElement( 'option' );
									opt.value = term.id;
									opt.textContent = term.text || term.name || '';
									opt.selected = true;
									selectElement.appendChild( opt );
								}
								selectElement.dispatchEvent( new Event( 'change', { bubbles: true } ) );
								updateSelectedDisplay();
								updateResultHighlights();
								// Keep dropdown open for multi-select
							} else {
								// Single-select: deselect all, select one, close
								Array.from( selectElement.options ).forEach( function ( o ) { o.selected = false; } );

								var existing = selectElement.querySelector( 'option[value="' + term.id + '"]' );
								if ( ! existing ) {
									var opt = document.createElement( 'option' );
									opt.value = term.id;
									opt.textContent = term.text || term.name || '';
									opt.selected = true;
									selectElement.appendChild( opt );
								} else {
									existing.selected = true;
								}
								selectElement.dispatchEvent( new Event( 'change', { bubbles: true } ) );

								// Close results and show badge
								results.innerHTML = '';
								results.style.display = 'none';
								updateSelectedDisplay();
							}
						} );

						results.appendChild( item );
					} );

					results.style.display = 'block';
				} )
				.catch( function ( err ) {
					console.error( 'Taxonomy search error:', err );
					results.innerHTML = '<div class="memdir-taxo-search__no-results">Search error</div>';
				} );
			}

			// Debounced input handler
			input.addEventListener( 'input', function () {
				var query = input.value.trim();
				clearTimeout( debounceTimer );

				if ( ! query ) {
					results.innerHTML = '';
					results.style.display = 'none';
					return;
				}

				debounceTimer = setTimeout( function () {
					searchTerms( query );
				}, 250 );
			} );

			// Close results on blur (fallback; mousedown preventDefault is primary guard)
			input.addEventListener( 'blur', function () {
				setTimeout( function () {
					results.style.display = 'none';
				}, 300 );
			} );

// ── Browse-all modal (multi-select only) ──
		if ( browseBtn ) {
			browseBtn.addEventListener( 'click', function () {
				// Fetch all terms
				var ajaxUrl = ( typeof mdAjax !== 'undefined' ) ? mdAjax.ajaxurl : '/wp-admin/admin-ajax.php';
				var nonce   = ( typeof mdAjax !== 'undefined' ) ? mdAjax.search_nonce : '';

				var formData = new FormData();
				formData.append( 'action', 'memdir_search_taxonomy_terms' );
				formData.append( 'taxonomy', taxonomySlug );
				formData.append( 'search', '' );
				formData.append( 'browse_all', '1' );
				formData.append( '_wpnonce', nonce );

				browseBtn.textContent = 'Loading…';
				browseBtn.disabled = true;

				fetch( ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					browseBtn.textContent = 'Browse all';
					browseBtn.disabled = false;

					var items = ( data && data.results ) ? data.results : [];
					if ( items.length === 0 ) { return; }

					// Build or reuse modal
					if ( ! browseModal ) {
						browseModal = buildBrowseModal();
					}

					// Populate checklist
					populateBrowseChecklist( browseModal.list, items );

					// Show the modal
					document.body.appendChild( browseModal.dialog );
					browseModal.dialog.showModal();
				} )
				.catch( function ( err ) {
					console.error( 'Browse-all fetch error:', err );
					browseBtn.textContent = 'Browse all';
					browseBtn.disabled = false;
				} );
			} );
		}

		// Build the browse-all modal (called once, then reused)
		function buildBrowseModal() {
			var dialog = document.createElement( 'dialog' );
			dialog.className = 'memdir-header-modal memdir-header-modal--media';

			var header = document.createElement( 'div' );
			header.className = 'memdir-header-modal__header';

			var h3 = document.createElement( 'h3' );
			h3.className = 'memdir-header-modal__title';
			h3.textContent = 'Browse All';
			header.appendChild( h3 );

			var closeBtn = document.createElement( 'button' );
			closeBtn.type = 'button';
			closeBtn.className = 'memdir-header-modal__close';
			closeBtn.innerHTML = '&times;';
			closeBtn.addEventListener( 'click', function () { dialog.close(); } );
			header.appendChild( closeBtn );
			dialog.appendChild( header );

			var body = document.createElement( 'div' );
			body.className = 'memdir-header-modal__body';

			var list = document.createElement( 'div' );
			list.className = 'memdir-taxo-browse__list';
			body.appendChild( list );
			dialog.appendChild( body );

			var footer = document.createElement( 'div' );
			footer.className = 'memdir-media-footer';

			var doneBtn = document.createElement( 'button' );
			doneBtn.type = 'button';
			doneBtn.className = 'memdir-media-footer__save';
			doneBtn.textContent = 'Done';
			doneBtn.addEventListener( 'click', function () { dialog.close(); } );
			footer.appendChild( doneBtn );
			dialog.appendChild( footer );

			// Backdrop click to close
			dialog.addEventListener( 'click', function ( e ) {
				if ( e.target === dialog ) { dialog.close(); }
			} );

			return { dialog: dialog, list: list };
		}

		// Populate / re-sync the browse list (checkboxes for multi, radios for single)
		var browseRadioName = 'memdir-browse-' + fieldKey + '-' + Math.random().toString( 36 ).substr( 2, 6 );
		function populateBrowseChecklist( list, items ) {
			list.innerHTML = '';
			var selectedIds = getSelectedIds();

			// Single-select: "None" option at top to clear selection
			if ( ! isMulti ) {
				var noneLabel = document.createElement( 'label' );
				noneLabel.className = 'memdir-taxo-browse__item memdir-taxo-browse__item--none';

				var noneInput = document.createElement( 'input' );
				noneInput.type = 'radio';
				noneInput.name = browseRadioName;
				noneInput.value = '';
				if ( Object.keys( selectedIds ).length === 0 ) { noneInput.checked = true; }

				var noneSpan = document.createElement( 'span' );
				noneSpan.textContent = 'None';
				noneSpan.style.fontStyle = 'italic';
				noneSpan.style.color = 'var(--md-text-muted, #888)';

				noneInput.addEventListener( 'change', function () {
					Array.from( selectElement.options ).forEach( function ( o ) { o.selected = false; } );
					var emptyOpt = selectElement.querySelector( 'option[value=""]' );
					if ( emptyOpt ) { emptyOpt.selected = true; }
					selectElement.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					updateSelectedDisplay();
					if ( browseModal && browseModal.dialog ) {
						setTimeout( function () { browseModal.dialog.close(); }, 150 );
					}
				} );

				noneLabel.appendChild( noneInput );
				noneLabel.appendChild( noneSpan );
				list.appendChild( noneLabel );
			}

			items.forEach( function ( term ) {
				var label = document.createElement( 'label' );
				label.className = 'memdir-taxo-browse__item';

				var input = document.createElement( 'input' );
				if ( isMulti ) {
					input.type = 'checkbox';
				} else {
					input.type = 'radio';
					input.name = browseRadioName;
				}
				input.value = term.id;
				if ( selectedIds[ term.id ] ) {
					input.checked = true;
				}

				var span = document.createElement( 'span' );
				span.textContent = term.text || term.name || '';

				input.addEventListener( 'change', function () {
					if ( isMulti ) {
						// Multi-select: toggle
						var existing = selectElement.querySelector( 'option[value="' + term.id + '"]' );
						if ( input.checked ) {
							if ( existing ) {
								existing.selected = true;
							} else {
								var opt = document.createElement( 'option' );
								opt.value = term.id;
								opt.textContent = term.text || term.name || '';
								opt.selected = true;
								selectElement.appendChild( opt );
							}
						} else {
							if ( existing ) { existing.selected = false; }
						}
					} else {
						// Single-select: deselect all, select this one
						Array.from( selectElement.options ).forEach( function ( o ) { o.selected = false; } );
						var existing = selectElement.querySelector( 'option[value="' + term.id + '"]' );
						if ( existing ) {
							existing.selected = true;
						} else {
							var opt = document.createElement( 'option' );
							opt.value = term.id;
							opt.textContent = term.text || term.name || '';
							opt.selected = true;
							selectElement.appendChild( opt );
						}
						// Auto-close modal after single pick
						if ( browseModal && browseModal.dialog ) {
							setTimeout( function () { browseModal.dialog.close(); }, 150 );
						}
					}

					selectElement.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					updateSelectedDisplay();
				} );

				label.appendChild( input );
				label.appendChild( span );
				list.appendChild( label );
			} );
		}

					return wrapper;
		}

	function initHeaderEditing() {
		var pencilSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>';
		var cameraSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>';

		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var fieldContent = section.querySelector( '.memdir-field-content' );
			if ( ! fieldContent ) { return; }

			var sectionKey = section.dataset.section || '';
			var postId     = section.dataset.postId  || '';

			// Helper: safely show a modal dialog by temporarily moving it
			// to document.body. Prevents the dialog from being trapped inside
			// a hidden section when viewing a different pill (e.g. editing
			// Business header fields while viewing the Workspace section).
			// The dialog's 'close' event (added in createMiniModal) moves it
			// back to fieldContent so saveSection() can still find its fields.
			function showDialogSafe( dlg ) {
				document.body.appendChild( dlg );
				dlg.showModal();
			}

			// \u2500\u2500 Find the header tab button \u2500\u2500
			var headerTabBtn = null;
			section.querySelectorAll( '.memdir-section-controls__tab-item' ).forEach( function ( btn ) {
				if ( ( btn.dataset.tab || '' ).toLowerCase().indexOf( 'header' ) !== -1 ) {
					headerTabBtn = btn;
				}
			} );
			if ( ! headerTabBtn ) { return; }

			var headerFieldKeys = [];
			try {
				headerFieldKeys = JSON.parse( headerTabBtn.dataset.fieldKeys || '[]' );
			} catch ( e ) {
				headerFieldKeys = [];
			}
			if ( ! headerFieldKeys.length ) { return; }

			// Classify header fields by type
			var imageField     = null;
			var imageFieldKey  = null;
			var textFields     = [];
			var taxonomyFields = [];
			var socialFields   = [];

			fieldContent.querySelectorAll( '.acf-field[data-key]' ).forEach( function ( field ) {
				var key  = field.dataset.key  || '';
				var type = field.dataset.type || '';
				if ( headerFieldKeys.indexOf( key ) === -1 ) { return; }

				if ( type === 'image' && ! imageField ) {
					imageField    = field;
					imageFieldKey = key;
				} else if ( type === 'text' ) {
					textFields.push( field );
				} else if ( type === 'taxonomy' ) {
					taxonomyFields.push( field );
				} else if ( isSocialField( field ) ) {
					socialFields.push( field );
				}
			} );

			var hasAnyField = imageField || textFields.length || taxonomyFields.length || socialFields.length;
			if ( ! hasAnyField ) { return; }

			// Find header elements in the rendered header
			var headerWrap = sectionKey
				? document.querySelector( '.memdir-header-wrap[data-header="' + sectionKey + '"]' )
				: null;
			if ( ! headerWrap ) { return; }

			var headerEl    = headerWrap.querySelector( '.memdir-header' );
			if ( ! headerEl ) { return; }

			var avatarImg   = headerWrap.querySelector( '.memdir-header__avatar' );
			var avatarWrap  = headerWrap.querySelector( '.memdir-header__avatar-wrap' );
			var headerText  = headerWrap.querySelector( '.memdir-header__text' );
			var headerTitle = headerWrap.querySelector( '.memdir-header__title' );
			var headerMeta  = headerWrap.querySelector( '.memdir-header__meta' );
			var headerTaxo  = headerWrap.querySelector( '.memdir-header__taxo' );
			var headerSocial = headerWrap.querySelector( '.memdir-header__social' );
			var headerBody  = headerWrap.querySelector( '.memdir-header__body' );

			// Ensure .memdir-header__meta exists when we have taxonomy or social fields
			if ( ! headerMeta && ( taxonomyFields.length || socialFields.length ) ) {
				headerMeta = document.createElement( 'div' );
				headerMeta.className = 'memdir-header__meta';
				headerBody.appendChild( headerMeta );
			}

			// Ensure .memdir-header__taxo exists for pencil placement
			if ( ! headerTaxo && taxonomyFields.length && headerMeta ) {
				headerTaxo = document.createElement( 'div' );
				headerTaxo.className = 'memdir-header__taxo';
				headerMeta.insertBefore( headerTaxo, headerMeta.firstChild );
			}

			// Ensure .memdir-header__social exists for pencil placement
			if ( ! headerSocial && socialFields.length && headerMeta ) {
				headerSocial = document.createElement( 'div' );
				headerSocial.className = 'memdir-header__social';
				headerMeta.appendChild( headerSocial );
			}

			// Ensure divider between taxo and social
			if ( headerTaxo && headerSocial && headerMeta ) {
				var divider = headerMeta.querySelector( '.memdir-header__divider' );
				if ( ! divider ) {
					divider = document.createElement( 'span' );
					divider.className = 'memdir-header__divider';
					divider.setAttribute( 'aria-hidden', 'true' );
					headerMeta.insertBefore( divider, headerSocial );
				}
			}

			// Helper: build a small modal dialog for one field type
			function createMiniModal( title, fields, opts ) {
				opts = opts || {};

				var dialog = document.createElement( 'dialog' );
				dialog.className = 'memdir-header-modal' + ( opts.modifier ? ' memdir-header-modal--' + opts.modifier : '' );

				var mHeader = document.createElement( 'div' );
				mHeader.className = 'memdir-header-modal__header';

				var mTitle = document.createElement( 'h3' );
				mTitle.className = 'memdir-header-modal__title';
				mTitle.textContent = title;

				var closeBtn = document.createElement( 'button' );
				closeBtn.type = 'button';
				closeBtn.className = 'memdir-header-modal__close';
				closeBtn.setAttribute( 'aria-label', 'Close' );
				closeBtn.innerHTML = '&times;';

				mHeader.appendChild( mTitle );
				mHeader.appendChild( closeBtn );
				dialog.appendChild( mHeader );

				// Optional description paragraph below the header
				if ( opts.description ) {
					var descP = document.createElement( 'p' );
					descP.className = 'memdir-header-modal__desc';
					descP.textContent = opts.description;
					dialog.appendChild( descP );
				}

				var body = document.createElement( 'div' );
				body.className = 'memdir-header-modal__body';

				if ( opts.customContent ) {
					body.appendChild( opts.customContent );
				} else {
					fields.forEach( function ( f ) {
						f.style.display = '';  // un-hide from tab nav
						body.appendChild( f );
					} );
				}

				dialog.appendChild( body );

				// Clean up stale select2 after DOM-move and let ACF re-initialize.
				// DOM-moving breaks select2's internal references; removing the
				// container + triggering ACF's 'append' action restores them.
				if ( typeof jQuery !== 'undefined' ) {
					var $body = jQuery( body );
					$body.find( '.select2-container' ).remove();
					$body.find( 'select' ).each( function () {
						try { jQuery( this ).select2( 'destroy' ); } catch ( e ) { /* already gone */ }
					} );
					if ( typeof acf !== 'undefined' ) {
						acf.do_action( 'append', $body );
					}
				}

				if ( ! opts.noSave ) {
					var saveBtn = document.createElement( 'button' );
					saveBtn.type = 'button';
					saveBtn.className = 'memdir-modal-save';
					saveBtn.textContent = 'Save';
					dialog.appendChild( saveBtn );

					saveBtn.addEventListener( 'click', function () {
						// Move dialog back before saving so saveSection() finds fields
						if ( dialog.parentElement !== fieldContent ) {
							fieldContent.appendChild( dialog );
						}
						var sBtn = section.querySelector( '.memdir-section-save' );
						var ban  = section.querySelector( '.memdir-unsaved-banner' );
						if ( sBtn ) { saveSection( section, sBtn, ban ); }
						dialog.close();
						if ( opts.onSave ) { opts.onSave(); }
					} );
				}

				// Append inside fieldContent so saveSection() finds all ACF fields
				fieldContent.appendChild( dialog );

				// When the dialog closes (X, backdrop, Escape, or Save),
				// move it back to fieldContent so saveSection() finds its fields.
				// showDialogSafe() moves it to document.body before opening.
				dialog.addEventListener( 'close', function () {
					if ( dialog.parentElement !== fieldContent ) {
						fieldContent.appendChild( dialog );
					}
				} );

				closeBtn.addEventListener( 'click', function () { dialog.close(); } );
				dialog.addEventListener( 'click', function ( e ) {
					if ( e.target === dialog ) { dialog.close(); }
				} );

				return dialog;
			}

			// Helper: create a pencil trigger button
			function createPencil() {
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'memdir-hdr-edit';
				btn.innerHTML = pencilSvg;
				return btn;
			}

			// ========================================
			// Avatar — camera overlay + modal
			// ========================================
			if ( imageField && avatarWrap ) {
				imageField.style.display = 'none';

				// Check ACF hidden input — empty when member uses a fallback avatar.
				var acfHiddenInput = imageField.querySelector( 'input[type="hidden"]' );
				var hasRealImage   = acfHiddenInput && acfHiddenInput.value && acfHiddenInput.value !== '0';

				var overlay = document.createElement( 'div' );
				overlay.className = 'memdir-header__avatar-edit';
				overlay.innerHTML = cameraSvg;
				overlay.title = 'Change photo';
				avatarWrap.appendChild( overlay );

				var avFragment = document.createElement( 'div' );

				var avPreview = document.createElement( 'img' );
				avPreview.className = 'memdir-header-modal__avatar-preview';
				avPreview.src = hasRealImage && avatarImg ? avatarImg.src : '';
				avPreview.alt = 'Current photo';
				if ( ! hasRealImage ) { avPreview.style.display = 'none'; }
				avFragment.appendChild( avPreview );

				var avStatus = document.createElement( 'p' );
				avStatus.className = 'memdir-header-modal__avatar-status';
				avStatus.textContent = '';
				avFragment.appendChild( avStatus );

				var fileInput = document.createElement( 'input' );
				fileInput.type = 'file';
				fileInput.accept = 'image/*';
				fileInput.style.display = 'none';
				avFragment.appendChild( fileInput );

				var uploadBtn = document.createElement( 'button' );
				uploadBtn.type = 'button';
				uploadBtn.className = 'memdir-header-modal__avatar-btn';
				uploadBtn.textContent = 'Choose New Photo';
				avFragment.appendChild( uploadBtn );

				var deleteBtn = document.createElement( 'button' );
				deleteBtn.type = 'button';
				deleteBtn.className = 'memdir-header-modal__avatar-btn memdir-header-modal__avatar-btn--delete';
				deleteBtn.textContent = 'Delete Photo';
				if ( ! hasRealImage ) { deleteBtn.style.display = 'none'; }
				avFragment.appendChild( deleteBtn );

				deleteBtn.addEventListener( 'click', function () {
					if ( ! confirm( 'Remove your profile photo?' ) ) { return; }

					avStatus.textContent = 'Removing…';
					deleteBtn.disabled = true;
					uploadBtn.disabled = true;

					var fd = new FormData();
					fd.append( 'action',  'md_save_section' );
					fd.append( 'nonce',   window.mdAjax.nonce );
					fd.append( 'post_id', postId );
					fd.append( 'acf[' + imageFieldKey + ']', '' );

					fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							if ( res.success ) {
								avPreview.src = '';
								avPreview.style.display = 'none';
								if ( avatarImg ) { avatarImg.src = ''; avatarImg.style.display = 'none'; }
								avStatus.textContent = 'Photo removed.';
								deleteBtn.style.display = 'none';
								// Sync ACF hidden input so saveSection() sends empty.
								if ( acfHiddenInput ) {
									acfHiddenInput.value = '';
								}
							} else {
								avStatus.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Remove failed.' );
							}
							deleteBtn.disabled = false;
							uploadBtn.disabled = false;
						} )
						.catch( function () {
							avStatus.textContent = 'Network error.';
							deleteBtn.disabled = false;
							uploadBtn.disabled = false;
						} );
				} );

				var avDialog = createMiniModal( 'Update Photo', [], {
					customContent: avFragment,
					modifier: 'avatar',
					noSave: true
				} );

				uploadBtn.addEventListener( 'click', function () { fileInput.click(); } );
				fileInput.addEventListener( 'change', function () {
					var file = fileInput.files[ 0 ];
					if ( ! file ) { return; }

					avStatus.textContent = 'Uploading\u2026';
					uploadBtn.disabled = true;

					var fd = new FormData();
					fd.append( 'action',    'memdir_ajax_upload_avatar' );
					fd.append( 'nonce',     window.mdAjax.nonce );
					fd.append( 'post_id',   postId );
					fd.append( 'field_key', imageFieldKey );
					fd.append( 'image',     file );

					fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) {
							if ( res.success && res.data && res.data.url ) {
								avPreview.src = res.data.url;
								avPreview.style.display = '';
								if ( avatarImg ) { avatarImg.src = res.data.url; avatarImg.style.display = ''; }
								avStatus.textContent = 'Photo updated.';
								deleteBtn.style.display = '';
								// Sync ACF hidden input so saveSection() uses the new ID.
								if ( acfHiddenInput && res.data.id ) {
									acfHiddenInput.value = res.data.id;
								}
							} else {
								avStatus.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Upload failed.' );
							}
							uploadBtn.disabled = false;
							fileInput.value = '';
						} )
						.catch( function () {
							avStatus.textContent = 'Network error.';
							uploadBtn.disabled = false;
							fileInput.value = '';
						} );
				} );

				overlay.addEventListener( 'click', function () { showDialogSafe( avDialog ); } );
			}

			// ========================================
			// Name — pencil next to title + modal
			// ========================================
			if ( textFields.length && headerText ) {
				var nameDialog = createMiniModal( 'Edit Name', textFields, {
					modifier: 'name',
					onSave: function () {
						if ( headerTitle && textFields[ 0 ] ) {
							var inp = textFields[ 0 ].querySelector( 'input' );
							if ( inp && inp.value.trim() ) {
								headerTitle.textContent = inp.value.trim();
							}
						}
						checkNameEmpty();
					}
				} );

				var namePencil = createPencil();
				headerTitle.appendChild( namePencil );  // inline with the name text

				function checkNameEmpty() {
					var empty = textFields.every( function ( f ) {
						var inp = f.querySelector( 'input' );
						return ! inp || ! inp.value.trim();
					} );
					namePencil.classList.toggle( 'memdir-hdr-edit--pulse', empty );
				}
				checkNameEmpty();

				namePencil.addEventListener( 'click', function () { showDialogSafe( nameDialog ); } );
				nameDialog.addEventListener( 'close', function () { checkNameEmpty(); } );
			}

			// ========================================
			// Quick Focus — pencil next to badges + modal
			// ========================================
			if ( taxonomyFields.length && headerTaxo ) {
				var taxoDialog = createMiniModal( 'Edit Quick Focus', taxonomyFields, {
					modifier: 'categories',
					description: 'Pick the tags that define you at a glance \u2014 these are the first things people notice on your profile.',
					onSave: function () {
						checkTaxoEmpty();
					}
				} );

				var taxoPencil = createPencil();
				headerTaxo.appendChild( taxoPencil );

				function checkTaxoEmpty() {
					var empty = taxonomyFields.every( function ( f ) {
						var sel = f.querySelector( 'select' );
						if ( ! sel ) { return true; }
						var selected = Array.from( sel.selectedOptions ).filter( function ( o ) {
							return o.value && o.value !== '';
						} );
						return selected.length === 0;
					} );
					taxoPencil.classList.toggle( 'memdir-hdr-edit--pulse', empty );
				}
				checkTaxoEmpty();

				taxoPencil.addEventListener( 'click', function () {
					showDialogSafe( taxoDialog );

					// Fix select2 dropdownParent — preserve ACF's full config
					// (AJAX search, formatters, etc.) while adding dropdownParent
					// so the dropdown renders inside the dialog, not behind its backdrop.
					// Initialize custom search boxes for taxonomy fields
					taxoDialog.querySelectorAll( '.acf-field[data-type="taxonomy"]' ).forEach( function ( acfField ) {
						// Only create once
						if ( ! acfField.querySelector( '.memdir-taxo-search' ) ) {
							createTaxonomySearch( acfField );
						}
					} );
					var firstInput = taxoDialog.querySelector( '.memdir-taxo-search__input' );
					if ( firstInput ) { setTimeout( function () { firstInput.focus(); }, 50 ); }
				} );

				taxoDialog.addEventListener( 'close', function () { checkTaxoEmpty(); } );
			}

			// ========================================
			// Social Links — pencil next to icons + modal
			// ========================================
			if ( socialFields.length && headerSocial ) {
				var socialDialog = createMiniModal( 'Edit Social Links', socialFields, {
					modifier: 'social',
					onSave: function () {
						checkSocialEmpty();
					}
				} );

				var socialPencil = createPencil();
				headerSocial.appendChild( socialPencil );

				function checkSocialEmpty() {
					var empty = socialFields.every( function ( f ) {
						var inp = f.querySelector( 'input' );
						return ! inp || ! inp.value.trim();
					} );
					socialPencil.classList.toggle( 'memdir-hdr-edit--pulse', empty );
				}
				checkSocialEmpty();

				socialPencil.addEventListener( 'click', function () { showDialogSafe( socialDialog ); } );

				// --- Import social links from other primary section(s) ---
				var socialSources = ( window.mdAjax && window.mdAjax.socialSources ) || {};
				var importSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
				Object.keys( socialSources ).forEach( function ( srcKey ) {
					if ( srcKey === sectionKey ) { return; }
					var srcLabel = socialSources[ srcKey ];

					var importBtn = document.createElement( 'button' );
					importBtn.type = 'button';
					importBtn.className = 'memdir-import-social-btn';
					importBtn.innerHTML = importSvg;
					importBtn.appendChild(document.createTextNode(' Import from ' + srcLabel));

					// Insert at the top of the modal body
					var modalBody = socialDialog.querySelector( '.memdir-header-modal__body' );
					if ( modalBody ) {
						modalBody.insertBefore( importBtn, modalBody.firstChild );
					}

					importBtn.addEventListener( 'click', function () {
						importBtn.disabled = true;
						importBtn.textContent = 'Importing\u2026';

						var fd = new FormData();
						fd.append( 'action',     'memdir_ajax_import_social' );
						fd.append( 'nonce',      window.mdAjax.nonce );
						fd.append( 'post_id',    postId );
						fd.append( 'source_key', srcKey );
						fd.append( 'target_key', sectionKey );

						fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
							.then( function ( r ) { return r.json(); } )
							.then( function ( res ) {
								if ( res.success ) {
									window.onbeforeunload = null;
									window.location.reload();
								} else {
									alert( 'Import failed: ' + ( res.data && res.data.message ? res.data.message : 'Unknown error.' ) );
									importBtn.disabled = false;
									importBtn.innerHTML = importSvg;
									importBtn.appendChild(document.createTextNode(' Import from ' + srcLabel));
								}
							} )
							.catch( function () {
								alert( 'Network error. Please try again.' );
								importBtn.disabled = false;
								importBtn.innerHTML = importSvg;
								importBtn.appendChild(document.createTextNode(' Import from ' + srcLabel));
							} );
					} );
				} );

				socialDialog.addEventListener( 'close', function () { checkSocialEmpty(); } );
			}

			// Hide the "Header" tab button from the sidebar
			headerTabBtn.style.display = 'none';

			if ( headerTabBtn.classList.contains( 'is-active' ) ) {
				var nextTab = section.querySelector( '.memdir-section-controls__tab-item:not([style*="display: none"])' );
				if ( nextTab ) { activateTab( section, nextTab ); }
			}
		} );
	}
	function syncControlsTop() {
		var sticky = document.querySelector( '.memdir-sticky' );
		if ( ! sticky ) { return; }
		var stickyTop    = parseInt( getComputedStyle( sticky ).top, 10 ) || 0;
		var controlsTop  = stickyTop + sticky.offsetHeight + 8;
		document.querySelectorAll( '.memdir-section-controls' ).forEach( function ( el ) {
			el.style.top = controlsTop + 'px';
		} );
	}

	// -----------------------------------------------------------------------
	// Custom image / gallery upload UIs
	// -----------------------------------------------------------------------

	/**
	 * Collect header-tab field keys from a section's tab buttons.
	 * Returns an array of ACF field keys that belong to the header tab.
	 */
	function getHeaderFieldKeys( section ) {
		var keys = [];
		section.querySelectorAll( '.memdir-section-controls__tab-item' ).forEach( function ( btn ) {
			if ( ( btn.dataset.tab || '' ).toLowerCase().indexOf( 'header' ) !== -1 ) {
				try { keys = JSON.parse( btn.dataset.fieldKeys || '[]' ); } catch ( e ) { /* ignore */ }
			}
		} );
		return keys;
	}

	/**
	 * Lightweight modal factory for image/gallery editing dialogs.
	 * Reuses memdir-header-modal CSS (incl. CSS-var redeclarations).
	 * Returns { dialog, body }.
	 */
	function createMediaModal( title ) {
		var dialog = document.createElement( 'dialog' );
		dialog.className = 'memdir-header-modal memdir-header-modal--media';

		var header = document.createElement( 'div' );
		header.className = 'memdir-header-modal__header';

		var h3 = document.createElement( 'h3' );
		h3.className = 'memdir-header-modal__title';
		h3.textContent = title;
		header.appendChild( h3 );

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'memdir-header-modal__close';
		closeBtn.innerHTML = '&times;';
		closeBtn.addEventListener( 'click', function () { dialog.close(); } );
		header.appendChild( closeBtn );
		dialog.appendChild( header );

		var body = document.createElement( 'div' );
		body.className = 'memdir-header-modal__body';
		dialog.appendChild( body );

		// Footer with Save & Close button.
		var footer = document.createElement( 'div' );
		footer.className = 'memdir-media-footer';

		var saveBtn = document.createElement( 'button' );
		saveBtn.type = 'button';
		saveBtn.className = 'memdir-media-footer__save';
		saveBtn.textContent = 'Save \u0026 Close';
		saveBtn.addEventListener( 'click', function () { dialog.close(); } );
		footer.appendChild( saveBtn );
		dialog.appendChild( footer );

		return { dialog: dialog, body: body, footer: footer, saveBtn: saveBtn };
	}

	/**
	 * Replace ACF’s native image uploader with a compact inline preview
	 * and a modal dialog for upload/replace/delete + caption editing.
	 * All saves are instant via AJAX (no reliance on saveSection).
	 */
	function replaceImageUploader( field, fieldKey, postId ) {
		var acfUploader = field.querySelector( '.acf-image-uploader' );
		if ( ! acfUploader ) { return; }

		// Read current state from ACF hidden input.
		var hiddenInput = acfUploader.querySelector( 'input[type="hidden"]' );
		var currentId   = hiddenInput ? ( hiddenInput.value || '' ) : '';
		var currentImg  = acfUploader.querySelector( '.show-if-value img' );
		var currentSrc  = currentImg ? ( currentImg.getAttribute( 'src' ) || '' ) : '';

		// Hide ACF native uploader.
		acfUploader.style.display = 'none';

		// --- Inline display ---
		var inline = document.createElement( 'div' );
		inline.className = 'memdir-image-inline';

		var inlinePreview = document.createElement( 'img' );
		inlinePreview.className = 'memdir-image-inline__preview';
		inlinePreview.src = currentSrc;
		inlinePreview.alt = 'Current image';
		if ( ! currentSrc ) { inlinePreview.style.display = 'none'; }
		inline.appendChild( inlinePreview );

		var inlineCaption = document.createElement( 'p' );
		inlineCaption.className = 'memdir-image-inline__caption';
		inlineCaption.style.display = 'none';
		inline.appendChild( inlineCaption );

		var editBtn = document.createElement( 'button' );
		editBtn.type = 'button';
		editBtn.className = 'memdir-image-inline__edit';
		editBtn.textContent = currentSrc ? 'Edit Image' : 'Upload Image';
		inline.appendChild( editBtn );

		acfUploader.parentNode.insertBefore( inline, acfUploader.nextSibling );

		// --- Modal ---
		var labelEl = field.querySelector( '.acf-label label' );
		var modalTitle = labelEl ? labelEl.textContent.trim() : 'Image';
		var modal = createMediaModal( modalTitle );

		// Modal preview.
		var modalPreview = document.createElement( 'img' );
		modalPreview.className = 'memdir-image-modal__preview';
		modalPreview.src = currentSrc;
		modalPreview.alt = 'Image preview';
		if ( ! currentSrc ) { modalPreview.style.display = 'none'; }
		modal.body.appendChild( modalPreview );

		// Caption input.
		var captionInput = document.createElement( 'input' );
		captionInput.type = 'text';
		captionInput.className = 'memdir-media-caption';
		captionInput.placeholder = 'Add a caption\u2026';
		captionInput.value = '';
		if ( ! currentId ) { captionInput.style.display = 'none'; }
		modal.body.appendChild( captionInput );

		// Status.
		var status = document.createElement( 'p' );
		status.className = 'memdir-media-status';
		modal.body.appendChild( status );

		// Hidden file input.
		var fileInput = document.createElement( 'input' );
		fileInput.type = 'file';
		fileInput.accept = 'image/*';
		fileInput.style.display = 'none';
		modal.body.appendChild( fileInput );

		// Buttons.
		var btnRow = document.createElement( 'div' );
		btnRow.className = 'memdir-image-modal__actions';

		var uploadBtn = document.createElement( 'button' );
		uploadBtn.type = 'button';
		uploadBtn.className = 'memdir-media-btn';
		uploadBtn.textContent = currentSrc ? 'Replace Image' : 'Upload Image';
		btnRow.appendChild( uploadBtn );

		var deleteBtn = document.createElement( 'button' );
		deleteBtn.type = 'button';
		deleteBtn.className = 'memdir-media-btn memdir-media-btn--delete';
		deleteBtn.textContent = 'Delete';
		if ( ! currentId ) { deleteBtn.style.display = 'none'; }
		btnRow.appendChild( deleteBtn );

		modal.body.appendChild( btnRow );

		// Append dialog to document.body (stays there; never collected by saveSection).
		document.body.appendChild( modal.dialog );

		// Open modal on inline edit click.
		editBtn.addEventListener( 'click', function () {
			modal.dialog.showModal();
		} );

		// --- Upload handler ---
		uploadBtn.addEventListener( 'click', function () { fileInput.click(); } );

		fileInput.addEventListener( 'change', function () {
			var file = fileInput.files[ 0 ];
			if ( ! file ) { return; }

			status.textContent = 'Uploading\u2026';
			uploadBtn.disabled = true;
			deleteBtn.disabled = true;

			var fd = new FormData();
			fd.append( 'action',    'memdir_ajax_upload_image' );
			fd.append( 'nonce',     window.mdAjax.nonce );
			fd.append( 'post_id',   postId );
			fd.append( 'field_key', fieldKey );
			fd.append( 'image',     file );

			var cap = captionInput.value.trim();
			if ( cap ) { fd.append( 'caption', cap ); }

			fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success && res.data ) {
						// Update modal.
						modalPreview.src = res.data.url;
						modalPreview.style.display = '';
						captionInput.style.display = '';
						deleteBtn.style.display = '';
						uploadBtn.textContent = 'Replace Image';
						status.textContent = 'Image saved.';

						// Update inline display.
						inlinePreview.src = res.data.url;
						inlinePreview.style.display = '';
						editBtn.textContent = 'Edit Image';

						// Sync ACF hidden input.
						currentId = String( res.data.id );
						if ( hiddenInput ) { hiddenInput.value = currentId; }

						// Update inline caption text.
						if ( res.data.caption ) {
							inlineCaption.textContent = res.data.caption;
							inlineCaption.style.display = '';
						}
					} else {
						status.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Upload failed.' );
					}
					uploadBtn.disabled = false;
					deleteBtn.disabled = false;
					fileInput.value = '';
				} )
				.catch( function () {
					status.textContent = 'Network error.';
					uploadBtn.disabled = false;
					deleteBtn.disabled = false;
					fileInput.value = '';
				} );
		} );

		// --- Delete handler ---
		deleteBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Remove this image? The file will be permanently deleted.' ) ) { return; }

			status.textContent = 'Removing\u2026';
			uploadBtn.disabled = true;
			deleteBtn.disabled = true;

			var fd = new FormData();
			fd.append( 'action',    'memdir_ajax_delete_image' );
			fd.append( 'nonce',     window.mdAjax.nonce );
			fd.append( 'post_id',   postId );
			fd.append( 'field_key', fieldKey );

			fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success ) {
						// Update modal.
						modalPreview.src = '';
						modalPreview.style.display = 'none';
						captionInput.value = '';
						captionInput.style.display = 'none';
						deleteBtn.style.display = 'none';
						uploadBtn.textContent = 'Upload Image';
						status.textContent = 'Image removed.';

						// Update inline display.
						inlinePreview.src = '';
						inlinePreview.style.display = 'none';
						inlineCaption.textContent = '';
						inlineCaption.style.display = 'none';
						editBtn.textContent = 'Upload Image';

						// Sync ACF hidden input.
						currentId = '';
						if ( hiddenInput ) { hiddenInput.value = ''; }
					} else {
						status.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Remove failed.' );
					}
					uploadBtn.disabled = false;
					deleteBtn.disabled = false;
				} )
				.catch( function () {
					status.textContent = 'Network error.';
					uploadBtn.disabled = false;
					deleteBtn.disabled = false;
				} );
		} );

		// --- Caption blur-save ---
		captionInput.addEventListener( 'blur', function () {
			if ( ! currentId ) { return; }
			var fd = new FormData();
			fd.append( 'action',        'memdir_ajax_update_caption' );
			fd.append( 'nonce',         window.mdAjax.nonce );
			fd.append( 'post_id',       postId );
			fd.append( 'attachment_id', currentId );
			fd.append( 'caption',       captionInput.value.trim() );
			fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
				.then( function () {
					// Update inline caption text.
					var cap = captionInput.value.trim();
					inlineCaption.textContent = cap;
					inlineCaption.style.display = cap ? '' : 'none';
				} )
				.catch( function () {} );
		} );
	}

	/**
	 * Add a single thumbnail item to a gallery modal grid.
	 * Returns the created item element.
	 */
	function addGalleryThumb( grid, attachmentId, src, caption, fieldKey, postId, statusEl, inlineGrid, acfField ) {
		var item = document.createElement( 'div' );
		item.className = 'memdir-gallery-modal__item';
		item.dataset.attachmentId = attachmentId;

		var img = document.createElement( 'img' );
		img.src = src;
		img.alt = 'Gallery image';
		item.appendChild( img );

		var removeBtn = document.createElement( 'button' );
		removeBtn.type = 'button';
		removeBtn.className = 'memdir-gallery-modal__remove';
		removeBtn.innerHTML = '&times;';
		removeBtn.title = 'Remove image';
		item.appendChild( removeBtn );

		// Per-image caption.
		var capInput = document.createElement( 'input' );
		capInput.type = 'text';
		capInput.className = 'memdir-gallery-modal__caption';
		capInput.placeholder = 'Caption';
		capInput.value = caption || '';
		item.appendChild( capInput );

		grid.appendChild( item );

		// Remove handler.
		removeBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Remove this image from the gallery?' ) ) { return; }

			statusEl.textContent = 'Removing\u2026';
			removeBtn.disabled = true;

			var fd = new FormData();
			fd.append( 'action',        'memdir_ajax_gallery_remove' );
			fd.append( 'nonce',         window.mdAjax.nonce );
			fd.append( 'post_id',       postId );
			fd.append( 'field_key',     fieldKey );
			fd.append( 'attachment_id', attachmentId );

			fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res.success ) {
						item.remove();
						statusEl.textContent = 'Image removed.';
						if ( acfField ) {
							syncGalleryHiddenInputs( acfField, fieldKey, grid );
						}
						if ( inlineGrid ) { rebuildInlineGallery( inlineGrid, grid ); }
					} else {
						statusEl.textContent = 'Error: ' + ( res.data && res.data.message ? res.data.message : 'Remove failed.' );
						removeBtn.disabled = false;
					}
				} )
				.catch( function () {
					statusEl.textContent = 'Network error.';
					removeBtn.disabled = false;
				} );
		} );

		// Caption blur-save.
		capInput.addEventListener( 'blur', function () {
			var fd = new FormData();
			fd.append( 'action',        'memdir_ajax_update_caption' );
			fd.append( 'nonce',         window.mdAjax.nonce );
			fd.append( 'post_id',       postId );
			fd.append( 'attachment_id', attachmentId );
			fd.append( 'caption',       capInput.value.trim() );
			fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } ).catch( function () {} );
		} );

		return item;
	}

	/**
	 * Rebuild hidden inputs inside the .acf-field wrapper so saveSection()
	 * collects the correct gallery array.
	 */
	function syncGalleryHiddenInputs( field, fieldKey, grid ) {
		// Remove old sync inputs.
		field.querySelectorAll( '.memdir-gallery-sync' ).forEach( function ( el ) { el.remove(); } );

		// Create new ones from current grid state.
		grid.querySelectorAll( '.memdir-gallery-modal__item' ).forEach( function ( item ) {
			var id = item.dataset.attachmentId || '';
			if ( ! id ) { return; }
			var inp = document.createElement( 'input' );
			inp.type  = 'hidden';
			inp.className = 'memdir-gallery-sync';
			inp.name  = 'acf[' + fieldKey + '][]';
			inp.value = id;
			field.appendChild( inp );
		} );
	}

	/**
	 * Rebuild the read-only inline gallery grid from the modal grid state.
	 */
	function rebuildInlineGallery( inlineGrid, modalGrid ) {
		inlineGrid.innerHTML = '';
		modalGrid.querySelectorAll( '.memdir-gallery-modal__item' ).forEach( function ( item ) {
			var src = item.querySelector( 'img' ) ? item.querySelector( 'img' ).src : '';
			if ( ! src ) { return; }
			var thumb = document.createElement( 'div' );
			thumb.className = 'memdir-gallery-inline__item';
			var img = document.createElement( 'img' );
			img.src = src;
			img.alt = 'Gallery image';
			thumb.appendChild( img );
			inlineGrid.appendChild( thumb );
		} );
	}

	/**
	 * Replace ACF’s native gallery with a compact inline preview
	 * and a modal dialog for add/remove + caption editing.
	 */
	function replaceGalleryUploader( field, fieldKey, postId ) {
		var acfGallery = field.querySelector( '.acf-gallery' );
		if ( ! acfGallery ) { return; }

		// Read existing gallery items.
		var existingItems = [];
		acfGallery.querySelectorAll( '.acf-gallery-attachment' ).forEach( function ( att ) {
			var img   = att.querySelector( 'img' );
			var input = att.querySelector( 'input[type="hidden"]' );
			if ( input && input.value ) {
				existingItems.push( { id: input.value, src: img ? img.src : '' } );
			}
		} );

		// Mark ACF’s original inputs so saveSection() skips them.
		acfGallery.querySelectorAll( 'input' ).forEach( function ( inp ) {
			inp.dataset.memdirSkip = '1';
		} );

		// Hide ACF native gallery.
		acfGallery.style.display = 'none';

		// --- Inline display ---
		var inline = document.createElement( 'div' );
		inline.className = 'memdir-gallery-inline';

		var inlineGrid = document.createElement( 'div' );
		inlineGrid.className = 'memdir-gallery-inline__grid';
		inline.appendChild( inlineGrid );

		// Populate inline thumbs.
		existingItems.forEach( function ( item ) {
			var thumb = document.createElement( 'div' );
			thumb.className = 'memdir-gallery-inline__item';
			var img = document.createElement( 'img' );
			img.src = item.src;
			img.alt = 'Gallery image';
			thumb.appendChild( img );
			inlineGrid.appendChild( thumb );
		} );

		var editGalleryBtn = document.createElement( 'button' );
		editGalleryBtn.type = 'button';
		editGalleryBtn.className = 'memdir-gallery-inline__edit';
		editGalleryBtn.textContent = existingItems.length ? 'Edit Gallery (' + existingItems.length + ')' : 'Add Images';
		inline.appendChild( editGalleryBtn );

		acfGallery.parentNode.insertBefore( inline, acfGallery.nextSibling );

		// --- Modal ---
		var labelEl = field.querySelector( '.acf-label label' );
		var modalTitle = labelEl ? labelEl.textContent.trim() : 'Gallery';
		var modal = createMediaModal( modalTitle );

		var modalGrid = document.createElement( 'div' );
		modalGrid.className = 'memdir-gallery-modal__grid';
		modal.body.appendChild( modalGrid );

		var status = document.createElement( 'p' );
		status.className = 'memdir-media-status';
		modal.body.appendChild( status );

		var fileInput = document.createElement( 'input' );
		fileInput.type   = 'file';
		fileInput.accept = 'image/*';
		fileInput.multiple = true;
		fileInput.style.display = 'none';
		modal.body.appendChild( fileInput );

		var addBtn = document.createElement( 'button' );
		addBtn.type      = 'button';
		addBtn.className = 'memdir-media-btn';
		addBtn.textContent = 'Add Images';
		modal.body.appendChild( addBtn );

		// Append dialog to document.body.
		document.body.appendChild( modal.dialog );

		// Render existing items in modal grid.
		existingItems.forEach( function ( item ) {
			addGalleryThumb( modalGrid, item.id, item.src, '', fieldKey, postId, status, inlineGrid, field );
		} );

		// Sync hidden inputs for initial state.
		syncGalleryHiddenInputs( field, fieldKey, modalGrid );

		// Open modal.
		editGalleryBtn.addEventListener( 'click', function () {
			modal.dialog.showModal();
		} );

		// Update inline count + grid when modal closes.
		modal.dialog.addEventListener( 'close', function () {
			var count = modalGrid.querySelectorAll( '.memdir-gallery-modal__item' ).length;
			editGalleryBtn.textContent = count ? 'Edit Gallery (' + count + ')' : 'Add Images';
			rebuildInlineGallery( inlineGrid, modalGrid );
		} );

		// --- Add handler ---
		addBtn.addEventListener( 'click', function () { fileInput.click(); } );

		fileInput.addEventListener( 'change', function () {
			var files = Array.prototype.slice.call( fileInput.files );
			if ( ! files.length ) { return; }

			addBtn.disabled = true;
			var total = files.length;
			var done  = 0;
			var errors = 0;

			function uploadNext() {
				if ( done + errors >= total ) {
					// All finished.
					if ( errors ) {
						status.textContent = done + ' added, ' + errors + ' failed.';
					} else {
						status.textContent = done === 1 ? 'Image added.' : done + ' images added.';
					}
					addBtn.disabled = false;
					fileInput.value = '';
					return;
				}

				var idx = done + errors;
				status.textContent = 'Uploading ' + ( idx + 1 ) + ' of ' + total + '\u2026';

				var fd = new FormData();
				fd.append( 'action',    'memdir_ajax_gallery_upload' );
				fd.append( 'nonce',     window.mdAjax.nonce );
				fd.append( 'post_id',   postId );
				fd.append( 'field_key', fieldKey );
				fd.append( 'image',     files[ idx ] );

				fetch( window.mdAjax.ajaxurl, { method: 'POST', body: fd } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( res ) {
						if ( res.success && res.data ) {
							addGalleryThumb( modalGrid, String( res.data.id ), res.data.url, '', fieldKey, postId, status, inlineGrid, field );
							syncGalleryHiddenInputs( field, fieldKey, modalGrid );
							rebuildInlineGallery( inlineGrid, modalGrid );
							done++;
						} else {
							errors++;
						}
						uploadNext();
					} )
					.catch( function () {
						errors++;
						uploadNext();
					} );
			}

			uploadNext();
		} );
	}

	/**
	 * Scan all edit-mode sections for image and gallery fields,
	 * replace ACF’s native uploaders with modal-based UIs.
	 * Skips fields owned by the header editing system.
	 */
	function initImageUploaders() {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var postId = section.dataset.postId || '';
			if ( ! postId ) { return; }

			var headerKeys   = getHeaderFieldKeys( section );
			var fieldContent = section.querySelector( '.memdir-field-content' );
			if ( ! fieldContent ) { return; }

			// Single image fields.
			fieldContent.querySelectorAll( '.acf-field[data-type="image"]' ).forEach( function ( field ) {
				var key = field.dataset.key || '';
				if ( headerKeys.indexOf( key ) !== -1 ) { return; }
				if ( field.closest( 'dialog' ) ) { return; }
				replaceImageUploader( field, key, postId );
			} );

			// Gallery fields.
			fieldContent.querySelectorAll( '.acf-field[data-type="gallery"]' ).forEach( function ( field ) {
				var key = field.dataset.key || '';
				if ( headerKeys.indexOf( key ) !== -1 ) { return; }
				if ( field.closest( 'dialog' ) ) { return; }
				replaceGalleryUploader( field, key, postId );
			} );
		} );
	}

	/**
	 * Scan all edit-mode sections for taxonomy fields and replace ACF's
	 * native select2 with the custom search UI. Skips header-owned fields
	 * (already handled by initHeaderEditing) and dialog-portaled fields.
	 */
	function initTaxonomySearch() {
		document.querySelectorAll( '.memdir-section--edit' ).forEach( function ( section ) {
			var headerKeys   = getHeaderFieldKeys( section );
			var fieldContent = section.querySelector( '.memdir-field-content' );
			if ( ! fieldContent ) { return; }

			fieldContent.querySelectorAll( '.acf-field[data-type="taxonomy"]' ).forEach( function ( field ) {
				var key = field.dataset.key || '';
				// Skip header-owned taxonomy fields (handled by header modal)
				if ( headerKeys.indexOf( key ) !== -1 ) { return; }
				if ( field.closest( 'dialog' ) ) { return; }
				createTaxonomySearch( field );
			} );
		} );
	}

	/**
	 * Initialise GLightbox on all .glightbox links in view-mode sections.
	 */
	function initLightbox() {
		if ( typeof GLightbox === 'undefined' ) { return; }
		GLightbox( {
			selector:  '.glightbox',
			touchNavigation: true,
			loop:      true,
			closeOnOutsideClick: true,
		} );
	}

	// -----------------------------------------------------------------------
	// 11. Trust Network
	//
	// Event delegation on [data-trust-action] buttons inside the trust section.
	// Each action builds a FormData, POSTs to the matching AJAX endpoint,
	// and reloads on success.
	// -----------------------------------------------------------------------

	function initTrustNetwork() {
		var ajaxUrl = ( window.mdAjax && window.mdAjax.ajaxurl )
			? window.mdAjax.ajaxurl
			: '/wp-admin/admin-ajax.php';
		var nonce = ( window.mdAjax && window.mdAjax.nonce )
			? window.mdAjax.nonce
			: '';

		// --- Trust action buttons (request, respond, cancel, remove) ---
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '[data-trust-action]' );
			if ( ! btn ) { return; }

			var action   = btn.dataset.trustAction || '';
			var formData = new FormData();
			formData.set( 'nonce', nonce );

			switch ( action ) {
				case 'request':
					formData.set( 'action', 'memdir_ajax_trust_request' );
					formData.set( 'target_post_id', btn.dataset.targetPost || '' );
					break;

				case 'respond':
					formData.set( 'action', 'memdir_ajax_trust_respond' );
					formData.set( 'trust_id', btn.dataset.trustId || '' );
					formData.set( 'response', btn.dataset.trustResponse || '' );
					break;

				case 'cancel':
					formData.set( 'action', 'memdir_ajax_trust_cancel' );
					formData.set( 'trust_id', btn.dataset.trustId || '' );
					break;

				case 'remove':
					if ( ! confirm( 'Remove this trust relationship?' ) ) { return; }
					formData.set( 'action', 'memdir_ajax_trust_remove' );
					formData.set( 'trust_id', btn.dataset.trustId || '' );
					break;

				default:
					return;
			}

			btn.disabled = true;
			btn.textContent = 'Saving\u2026';

			var originalText = btn.textContent;

			fetch( ajaxUrl, {
				method:      'POST',
				credentials: 'same-origin',
				body:        formData,
			} ).then( function ( res ) {
				if ( ! res.ok ) {
					console.error( 'Trust AJAX HTTP error:', res.status, res.statusText );
				}
				return res.json();
			} ).then( function ( json ) {
				if ( json.success ) {
					window.location.reload();
				} else {
					var msg = ( json.data && json.data.message ) || 'An error occurred.';
					console.error( 'Trust AJAX error:', msg, json );
					alert( msg );
					btn.disabled = false;
					btn.textContent = originalText;
				}
			} ).catch( function ( err ) {
				console.error( 'Trust AJAX catch:', err );
				alert( 'Request failed. Check browser console for details.' );
				btn.disabled = false;
				btn.textContent = originalText;
			} );
		} );

		// --- Trust toggle in right panel ---
		var trustToggle = document.querySelector( 'input[data-trust-toggle="1"]' );
		if ( trustToggle ) {
			trustToggle.addEventListener( 'change', function () {
				var nav    = document.querySelector( '.memdir-pills' );
				var postId = nav ? ( nav.dataset.postId || '' ) : '';
				var enabled = trustToggle.checked;

				// Update pill disabled state.
				if ( nav ) {
					var pill = nav.querySelector( '.memdir-pill[data-section="trust"]' );
					if ( pill ) {
						pill.classList.toggle( 'memdir-pill--disabled', ! enabled );
					}
					updateAllSectionsBadge( nav );
				}

				// AJAX save via trust-specific endpoint.
				var formData = new FormData();
				formData.set( 'action',  'memdir_ajax_trust_toggle' );
				formData.set( 'nonce',   nonce );
				formData.set( 'post_id', postId );
				formData.set( 'enabled', enabled ? '1' : '0' );

				fetch( ajaxUrl, {
					method:      'POST',
					credentials: 'same-origin',
					body:        formData,
				} ).then( function () {
					window.location.reload();
				} ).catch( function () {
					// Silently fail — UI is already updated.
				} );
			} );
		}
	}
	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		initTabNav();
		initPillNav();
		initSectionSave();
		initRightPanel();
		initSectionToggles();
		initSectionPmp();
		relocateFieldInstructions();
		initFieldPmp();           // inject field PMP controls after section PMP is wired
		initHeaderEditing();  // per-element header pencils + modals
		initImageUploaders(); // custom image/gallery upload UIs
		initTaxonomySearch(); // custom taxonomy search for all non-header taxonomy fields
		initLightbox();       // GLightbox on view-mode images
		initTrustNetwork();  // trust network action buttons + toggle
		hideEmptySectionPills();  // hide pills for PHP-dropped empty/PMP-blocked sections
		restoreState();
		syncControlsTop();
	} );

	window.addEventListener( 'resize', syncControlsTop );

}() );
