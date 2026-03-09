/**
 * Member Directory — Directory Listing JS.
 *
 * Handles search debounce, taxonomy filter interactions, AJAX live reload,
 * pagination, URL state management, Leaflet map with markers, and the
 * unified filter-stack for the [memdir_directory] shortcode.
 */

(function () {
	'use strict';

	const container = document.querySelector('.memdir-directory');
	if (!container) return;

	// Config from localized script or data attribute.
	const cfg = typeof mdDirectory !== 'undefined'
		? mdDirectory
		: JSON.parse(container.dataset.config || '{}');

	const ajaxurl = cfg.ajaxurl || '';
	const nonce = cfg.nonce || '';

	if (!ajaxurl) return;

	// State: current filter selections.
	const state = {
		search: '',
		filters: {},  // { taxonomy_slug: [ 'term1', 'term2' ] }
		page: 1,
		section: '',
	};

	// Leaflet map instance + markers layer.
	let map = null;
	let markersLayer = null;

	// ── Init ──────────────────────────────────────────────────────────

	function init() {
		// Read initial state from DOM.
		const searchInput = container.querySelector('[data-memdir-search]');
		if (searchInput) {
			state.search = searchInput.value;
		}

		// Read active filter pills from the filter stack.
		container.querySelectorAll('[data-filter-stack] .memdir-directory__filter-pill').forEach(function (pill) {
			const tax = pill.dataset.taxonomy;
			const term = pill.dataset.term;
			if (tax && term) {
				if (!state.filters[tax]) state.filters[tax] = [];
				if (!state.filters[tax].includes(term)) {
					state.filters[tax].push(term);
				}
			}
		});

		// Read section from data attribute if present.
		state.section = container.dataset.section || '';

		initSearch();
		initFilters();
		initPagination();
		initMap();
	}

	// ── Search ────────────────────────────────────────────────────────

	function initSearch() {
		const input = container.querySelector('[data-memdir-search]');
		if (!input) return;

		let timer = null;

		input.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				state.search = input.value.trim();
				state.page = 1;
				fetchResults();
			}, 300);
		});
	}

	// ── Filters ───────────────────────────────────────────────────────

	function initFilters() {
		// Remove pill clicks from the unified filter stack (event delegation).
		container.addEventListener('click', function (e) {
			const pill = e.target.closest('.memdir-directory__filter-pill');
			if (pill) {
				e.preventDefault();
				const tax = pill.dataset.taxonomy;
				const term = pill.dataset.term;

				if (tax && term) {
					removeTerm(tax, term);
					pill.remove();
					updateFilterCounts();
					maybeHideClearAll();
					state.page = 1;
					fetchResults();
				}
			}

			// Clear all button.
			const clearBtn = e.target.closest('[data-clear-all]');
			if (clearBtn) {
				e.preventDefault();
				state.filters = {};
				const stack = container.querySelector('[data-filter-stack]');
				if (stack) stack.innerHTML = '';
				updateFilterCounts();
				state.page = 1;
				fetchResults();
			}
		});

		// Filter group headers open the browse-all dialog.
		container.querySelectorAll('.memdir-directory__filter-header').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				const group = btn.closest('.memdir-directory__filter-group');
				if (!group) return;

				const tax = group.dataset.taxonomy;
				const dataEl = group.querySelector('.memdir-directory__terms-data');
				if (!dataEl) return;

				let terms;
				try {
					terms = JSON.parse(dataEl.textContent);
				} catch (err) {
					return;
				}

				openBrowseDialog(tax, terms, group);
			});
		});
	}

	function removeTerm(tax, term) {
		if (!state.filters[tax]) return;
		state.filters[tax] = state.filters[tax].filter(function (t) { return t !== term; });
		if (state.filters[tax].length === 0) {
			delete state.filters[tax];
		}
	}

	function addTerm(tax, termSlug, termName) {
		if (!state.filters[tax]) {
			state.filters[tax] = [];
		}
		if (!state.filters[tax].includes(termSlug)) {
			state.filters[tax].push(termSlug);

			// Add pill to the unified filter stack.
			addPillToStack(tax, termSlug, termName);
		}
	}

	function addPillToStack(tax, termSlug, termName) {
		const stack = container.querySelector('[data-filter-stack]');
		if (!stack) return;

		const pill = document.createElement('button');
		pill.className = 'memdir-directory__filter-pill';
		pill.dataset.term = termSlug;
		pill.dataset.taxonomy = tax;
		pill.innerHTML = escHtml(termName) + ' <span class="remove">&times;</span>';

		// Insert before the "Clear all" button if it exists, otherwise append.
		const clearBtn = stack.querySelector('[data-clear-all]');
		if (clearBtn) {
			stack.insertBefore(pill, clearBtn);
		} else {
			stack.appendChild(pill);
		}

		// Ensure "Clear all" button exists.
		ensureClearAllButton();
	}

	function ensureClearAllButton() {
		const stack = container.querySelector('[data-filter-stack]');
		if (!stack) return;

		let clearBtn = stack.querySelector('[data-clear-all]');
		if (!clearBtn) {
			clearBtn = document.createElement('button');
			clearBtn.className = 'memdir-directory__filter-clear';
			clearBtn.dataset.clearAll = '';
			clearBtn.textContent = 'Clear all';
			stack.appendChild(clearBtn);
		}
	}

	function maybeHideClearAll() {
		const stack = container.querySelector('[data-filter-stack]');
		if (!stack) return;

		const pills = stack.querySelectorAll('.memdir-directory__filter-pill');
		const clearBtn = stack.querySelector('[data-clear-all]');
		if (clearBtn && pills.length === 0) {
			clearBtn.remove();
		}
	}

	function updateFilterCounts() {
		container.querySelectorAll('.memdir-directory__filter-group').forEach(function (group) {
			const tax = group.dataset.taxonomy;
			const count = (state.filters[tax] || []).length;
			let badge = group.querySelector('.memdir-directory__filter-count');

			if (count > 0) {
				if (!badge) {
					badge = document.createElement('span');
					badge.className = 'memdir-directory__filter-count';
					const label = group.querySelector('.memdir-directory__filter-label');
					if (label) label.insertAdjacentElement('afterend', badge);
				}
				badge.textContent = count;
			} else if (badge) {
				badge.remove();
			}
		});
	}

	function openBrowseDialog(tax, terms, group) {
		// Remove existing dialog if any.
		const existing = document.querySelector('.memdir-directory__browse-dialog');
		if (existing) existing.remove();

		const headerBtn = group.querySelector('.memdir-directory__filter-header');
		const label = headerBtn ? headerBtn.querySelector('.memdir-directory__filter-label') : null;
		const title = label ? label.textContent : tax;

		const activeTerms = state.filters[tax] || [];

		const dialog = document.createElement('dialog');
		dialog.className = 'memdir-directory__browse-dialog';

		let searchHtml = '';
		if (terms.length > 10) {
			searchHtml = '<div class="memdir-directory__browse-search-wrap">' +
				'<input type="text" class="memdir-directory__browse-search" placeholder="Search...">' +
				'</div>';
		}

		let checkboxesHtml = '';
		terms.forEach(function (t) {
			const checked = activeTerms.includes(t.slug) ? ' checked' : '';
			checkboxesHtml += '<label data-term-name="' + escAttr(t.name.toLowerCase()) + '"><input type="checkbox" value="' + escAttr(t.slug) + '" data-name="' + escAttr(t.name) + '"' + checked + '> ' + escHtml(t.name) + '</label>';
		});

		dialog.innerHTML =
			'<div class="memdir-directory__browse-header">' +
				'<h3>' + escHtml(title) + '</h3>' +
				'<button class="memdir-directory__browse-close" type="button">&times;</button>' +
			'</div>' +
			searchHtml +
			'<div class="memdir-directory__browse-body">' +
				checkboxesHtml +
			'</div>' +
			'<div class="memdir-directory__browse-footer">' +
				'<button class="memdir-directory__browse-done" type="button">Done</button>' +
			'</div>';

		document.body.appendChild(dialog);
		dialog.showModal();

		// In-dialog search filtering.
		const searchInput = dialog.querySelector('.memdir-directory__browse-search');
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				const q = searchInput.value.toLowerCase().trim();
				dialog.querySelectorAll('.memdir-directory__browse-body label').forEach(function (lbl) {
					const name = lbl.dataset.termName || '';
					lbl.style.display = (!q || name.includes(q)) ? '' : 'none';
				});
			});
		}

		// Close button.
		dialog.querySelector('.memdir-directory__browse-close').addEventListener('click', function () {
			dialog.close();
			dialog.remove();
		});

		// Done button.
		dialog.querySelector('.memdir-directory__browse-done').addEventListener('click', function () {
			// Rebuild filter state from checkboxes.
			const newTerms = [];
			dialog.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
				if (cb.checked) {
					newTerms.push({ slug: cb.value, name: cb.dataset.name });
				}
			});

			// Remove all pills for this taxonomy from the stack.
			const stack = container.querySelector('[data-filter-stack]');
			if (stack) {
				stack.querySelectorAll('.memdir-directory__filter-pill[data-taxonomy="' + tax + '"]').forEach(function (p) {
					p.remove();
				});
			}

			// Reset filter state for this taxonomy.
			state.filters[tax] = [];

			// Re-add checked terms.
			newTerms.forEach(function (t) {
				addTerm(tax, t.slug, t.name);
			});

			if (newTerms.length === 0) {
				delete state.filters[tax];
			}

			updateFilterCounts();
			maybeHideClearAll();
			state.page = 1;
			dialog.close();
			dialog.remove();
			fetchResults();
		});

		// Click backdrop to close.
		dialog.addEventListener('click', function (e) {
			if (e.target === dialog) {
				dialog.close();
				dialog.remove();
			}
		});
	}

	// ── Map ───────────────────────────────────────────────────────────

	function initMap() {
		const mapEl = document.getElementById('memdir-directory__map');
		if (!mapEl || typeof L === 'undefined') return;

		map = L.map(mapEl, {
			scrollWheelZoom: false,
			zoomControl: true,
		}).setView([39.8, -98.5], 4); // Default: center of US

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
			maxZoom: 18,
		}).addTo(map);

		markersLayer = L.layerGroup().addTo(map);

		// Load initial markers from embedded JSON.
		const markersEl = document.getElementById('memdir-directory__markers');
		if (markersEl) {
			try {
				const markers = JSON.parse(markersEl.textContent);
				updateMapMarkers(markers);
			} catch (err) {
				// Silently fail.
			}
		}
	}

	function updateMapMarkers(markers) {
		if (!map || !markersLayer) return;

		markersLayer.clearLayers();

		if (!markers || markers.length === 0) return;

		var bounds = [];

		markers.forEach(function (m) {
			var lat = parseFloat(m.lat);
			var lng = parseFloat(m.lng);
			if (isNaN(lat) || isNaN(lng)) return;

			var avatarHtml = m.avatar
				? '<img src="' + escAttr(m.avatar) + '" style="width:36px;height:36px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle;">'
				: '';

			var popupContent =
				'<div style="display:flex;align-items:center;min-width:160px;">' +
					avatarHtml +
					'<div>' +
						'<strong style="font-size:13px;">' + escHtml(m.title) + '</strong>' +
						(m.location ? '<br><span style="font-size:11px;color:#6b7280;">' + escHtml(m.location) + '</span>' : '') +
						'<br><a href="' + escAttr(m.permalink) + '" style="font-size:11px;color:#87986A;">View Profile &rarr;</a>' +
					'</div>' +
				'</div>';

			var marker = L.circleMarker([lat, lng], {
				radius: 7,
				fillColor: '#87986A',
				color: '#fff',
				weight: 2,
				fillOpacity: 0.9,
			});

			marker.bindPopup(popupContent, { maxWidth: 260 });
			markersLayer.addLayer(marker);
			bounds.push([lat, lng]);
		});

		if (bounds.length > 0) {
			map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
		}
	}

	// ── Pagination ────────────────────────────────────────────────────

	function initPagination() {
		container.addEventListener('click', function (e) {
			var btn = e.target.closest('.memdir-directory__page-btn');
			if (btn && !btn.classList.contains('memdir-directory__page-btn--active')) {
				e.preventDefault();
				state.page = parseInt(btn.dataset.page, 10) || 1;
				fetchResults();

				// Scroll to top of directory.
				container.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	// ── AJAX fetch ────────────────────────────────────────────────────

	var fetchController = null;

	function fetchResults() {
		// Abort any pending request.
		if (fetchController) {
			fetchController.abort();
		}
		fetchController = new AbortController();

		showLoading();
		updateURL();

		var formData = new FormData();
		formData.append('action', 'memdir_directory_filter');
		formData.append('nonce', nonce);
		formData.append('search', state.search);
		formData.append('page', state.page);
		formData.append('section', state.section);

		// Serialize filters.
		Object.keys(state.filters).forEach(function (tax) {
			state.filters[tax].forEach(function (term) {
				formData.append('filters[' + tax + '][]', term);
			});
		});

		fetch(ajaxurl, {
			method: 'POST',
			body: formData,
			signal: fetchController.signal,
		})
			.then(function (res) { return res.json(); })
			.then(function (json) {
				if (json.success && json.data) {
					var grid = container.querySelector('.memdir-directory__grid');
					if (grid) {
						grid.innerHTML = json.data.html || '';
					}

					// Replace pagination.
					var oldPag = container.querySelector('.memdir-directory__pagination');
					if (oldPag) oldPag.remove();

					if (json.data.pagination) {
						grid.insertAdjacentHTML('afterend', json.data.pagination);
					}

					// Show/hide empty message.
					var emptyMsg = container.querySelector('.memdir-directory__empty');
					if (json.data.found_posts === 0) {
						if (!emptyMsg) {
							emptyMsg = document.createElement('p');
							emptyMsg.className = 'memdir-directory__empty';
							emptyMsg.textContent = 'No members found.';
							grid.insertAdjacentElement('afterend', emptyMsg);
						}
					} else if (emptyMsg) {
						emptyMsg.remove();
					}

					// Update map markers.
					if (json.data.markers) {
						updateMapMarkers(json.data.markers);
					}
				}
				hideLoading();
			})
			.catch(function (err) {
				if (err.name !== 'AbortError') {
					hideLoading();
				}
			});
	}

	// ── Loading overlay ───────────────────────────────────────────────

	function showLoading() {
		if (container.querySelector('.memdir-directory__loading')) return;

		var overlay = document.createElement('div');
		overlay.className = 'memdir-directory__loading';
		overlay.innerHTML = '<div class="memdir-directory__spinner"></div>';
		container.appendChild(overlay);
	}

	function hideLoading() {
		var overlay = container.querySelector('.memdir-directory__loading');
		if (overlay) overlay.remove();
	}

	// ── URL state management ──────────────────────────────────────────

	function updateURL() {
		var params = new URLSearchParams(window.location.search);

		// Search.
		if (state.search) {
			params.set('memdir_search', state.search);
		} else {
			params.delete('memdir_search');
		}

		// Page.
		if (state.page > 1) {
			params.set('memdir_page', state.page);
		} else {
			params.delete('memdir_page');
		}

		// Taxonomy filters.
		// First remove all existing taxonomy params.
		container.querySelectorAll('.memdir-directory__filter-group').forEach(function (g) {
			params.delete(g.dataset.taxonomy);
		});

		// Add active ones.
		Object.keys(state.filters).forEach(function (tax) {
			if (state.filters[tax].length) {
				params.set(tax, state.filters[tax].join(','));
			}
		});

		var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
		history.replaceState(null, '', newUrl);
	}

	// ── Utility ───────────────────────────────────────────────────────

	function escHtml(str) {
		var div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function escAttr(str) {
		return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	// ── Boot ──────────────────────────────────────────────────────────

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
