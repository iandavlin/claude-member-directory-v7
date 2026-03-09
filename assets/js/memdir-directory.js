/**
 * Member Directory — Directory Listing JS.
 *
 * Handles search debounce, taxonomy filter interactions (inline multi-select
 * search + browse-all dialogs, mirroring the profile edit form pattern),
 * AJAX live reload, pagination, URL state management, Leaflet map with
 * markers (circle / pin / avatar+cluster), and the unified filter-stack
 * for the [memdir_directory] shortcode.
 */

(function () {
	'use strict';

	var container = document.querySelector('.memdir-directory');
	if (!container) return;

	// Config from localized script or data attribute.
	var cfg = typeof mdDirectory !== 'undefined'
		? mdDirectory
		: JSON.parse(container.dataset.config || '{}');

	var ajaxurl = cfg.ajaxurl || '';
	var nonce = cfg.nonce || '';
	var pinStyle = cfg.pinStyle || 'circle';
	var customPinIcon = cfg.customPinIcon || '';
	var customPinWidth = cfg.customPinWidth || 25;
	var customPinHeight = cfg.customPinHeight || 41;

	if (!ajaxurl) return;

	// State: current filter selections.
	var state = {
		search: '',
		filters: {},  // { taxonomy_slug: [ 'term1', 'term2' ] }
		page: 1,
		section: '',
	};

	// Leaflet map instance + markers layer.
	var map = null;
	var markersLayer = null;

	// ── Init ──────────────────────────────────────────────────────────

	function init() {
		// Read initial state from DOM.
		var searchInput = container.querySelector('[data-memdir-search]');
		if (searchInput) {
			state.search = searchInput.value;
		}

		// Read active filter pills from the filter stack (now in main column).
		container.querySelectorAll('[data-filter-stack] .memdir-directory__filter-pill').forEach(function (pill) {
			var tax = pill.dataset.taxonomy;
			var term = pill.dataset.term;
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
		initFilterStack();
		initFilterGroups();
		initPagination();
		initMap();
	}

	// ── Search ────────────────────────────────────────────────────────

	function initSearch() {
		var input = container.querySelector('[data-memdir-search]');
		if (!input) return;

		var timer = null;

		input.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				state.search = input.value.trim();
				state.page = 1;
				fetchResults();
			}, 300);
		});
	}

	// ── Unified filter stack (main column pills area) ────────────────

	function initFilterStack() {
		container.addEventListener('click', function (e) {
			// Remove a single pill from the stack.
			var pill = e.target.closest('.memdir-directory__filter-pill');
			if (pill) {
				e.preventDefault();
				var tax = pill.dataset.taxonomy;
				var term = pill.dataset.term;

				if (tax && term) {
					removeTerm(tax, term);
					pill.remove();

					maybeHideClearAll();
					state.page = 1;
					fetchResults();
				}
			}

			// Clear all button.
			var clearBtn = e.target.closest('[data-clear-all]');
			if (clearBtn) {
				e.preventDefault();
				state.filters = {};
				var stack = container.querySelector('[data-filter-stack]');
				if (stack) stack.innerHTML = '';

			state.page = 1;
				fetchResults();
			}
		});
	}

	// ── Per-group inline multi-select search ──────────────────────────

	function initFilterGroups() {
		container.querySelectorAll('.memdir-directory__filter-group').forEach(function (group) {
			var tax = group.dataset.taxonomy;
			var dataEl = group.querySelector('.memdir-directory__terms-data');
			if (!dataEl) return;

			var allTerms;
			try {
				allTerms = JSON.parse(dataEl.textContent);
			} catch (err) {
				return;
			}

			var input = group.querySelector('[data-filter-input]');
			var resultsDiv = group.querySelector('[data-filter-results]');
			var browseBtn = group.querySelector('[data-filter-browse]');

			if (!input || !resultsDiv) return;

			var debounceTimer = null;

			// ── Inline search input ──

			input.addEventListener('input', function () {
				clearTimeout(debounceTimer);
				debounceTimer = setTimeout(function () {
					var q = input.value.trim().toLowerCase();
					if (q.length < 1) {
						resultsDiv.innerHTML = '';
						resultsDiv.style.display = 'none';
						return;
					}

					var matches = allTerms.filter(function (t) {
						return t.name.toLowerCase().indexOf(q) !== -1;
					});

					renderDropdown(resultsDiv, matches, tax, input);
				}, 150);
			});

			input.addEventListener('focus', function () {
				if (input.value.trim().length >= 1) {
					var q = input.value.trim().toLowerCase();
					var matches = allTerms.filter(function (t) {
						return t.name.toLowerCase().indexOf(q) !== -1;
					});
					renderDropdown(resultsDiv, matches, tax, input);
				}
			});

			// Close dropdown when clicking outside.
			document.addEventListener('click', function (e) {
				if (!group.contains(e.target)) {
					resultsDiv.style.display = 'none';
				}
			});

			// ── Browse all button ──
			if (browseBtn) {
				browseBtn.addEventListener('click', function (e) {
					e.preventDefault();
					openBrowseDialog(tax, allTerms, group, input);
				});
			}
		});
	}

	function renderDropdown(resultsDiv, matches, tax, input) {
		resultsDiv.innerHTML = '';

		if (matches.length === 0) {
			resultsDiv.innerHTML = '<div class="memdir-directory__filter-no-results">No matches</div>';
			resultsDiv.style.display = 'block';
			return;
		}

		var activeTerms = state.filters[tax] || [];

		matches.forEach(function (t) {
			var item = document.createElement('div');
			item.className = 'memdir-directory__filter-result-item';
			if (activeTerms.includes(t.slug)) {
				item.classList.add('is-selected');
			}
			item.textContent = t.name;
			item.dataset.slug = t.slug;
			item.dataset.name = t.name;

			item.addEventListener('click', function () {
				if (activeTerms.includes(t.slug)) {
					// Deselect.
					removeTerm(tax, t.slug);
					removeStackPill(tax, t.slug);
					item.classList.remove('is-selected');
				} else {
					// Select.
					addTerm(tax, t.slug, t.name);
					item.classList.add('is-selected');
				}

				maybeHideClearAll();
				input.value = '';
				resultsDiv.style.display = 'none';
				state.page = 1;
				fetchResults();
			});

			resultsDiv.appendChild(item);
		});

		resultsDiv.style.display = 'block';
	}

	// ── Unified stack pill management ─────────────────────────────────

	function addTerm(tax, termSlug, termName) {
		if (!state.filters[tax]) {
			state.filters[tax] = [];
		}
		if (!state.filters[tax].includes(termSlug)) {
			state.filters[tax].push(termSlug);
			addPillToStack(tax, termSlug, termName);
		}
	}

	function removeTerm(tax, term) {
		if (!state.filters[tax]) return;
		state.filters[tax] = state.filters[tax].filter(function (t) { return t !== term; });
		if (state.filters[tax].length === 0) {
			delete state.filters[tax];
		}
	}

	function addPillToStack(tax, termSlug, termName) {
		var stack = container.querySelector('[data-filter-stack]');
		if (!stack) return;

		var pill = document.createElement('button');
		pill.className = 'memdir-directory__filter-pill';
		pill.dataset.term = termSlug;
		pill.dataset.taxonomy = tax;
		pill.innerHTML = escHtml(termName) + ' <span class="remove">&times;</span>';

		var clearBtn = stack.querySelector('[data-clear-all]');
		if (clearBtn) {
			stack.insertBefore(pill, clearBtn);
		} else {
			stack.appendChild(pill);
		}

		ensureClearAllButton();
	}

	function removeStackPill(tax, termSlug) {
		var stack = container.querySelector('[data-filter-stack]');
		if (!stack) return;
		var pill = stack.querySelector('.memdir-directory__filter-pill[data-taxonomy="' + tax + '"][data-term="' + termSlug + '"]');
		if (pill) pill.remove();
	}

	function ensureClearAllButton() {
		var stack = container.querySelector('[data-filter-stack]');
		if (!stack) return;

		var clearBtn = stack.querySelector('[data-clear-all]');
		if (!clearBtn) {
			clearBtn = document.createElement('button');
			clearBtn.className = 'memdir-directory__filter-clear';
			clearBtn.dataset.clearAll = '';
			clearBtn.textContent = 'Clear all';
			stack.appendChild(clearBtn);
		}
	}

	function maybeHideClearAll() {
		var stack = container.querySelector('[data-filter-stack]');
		if (!stack) return;

		var pills = stack.querySelectorAll('.memdir-directory__filter-pill');
		var clearBtn = stack.querySelector('[data-clear-all]');
		if (clearBtn && pills.length === 0) {
			clearBtn.remove();
		}
	}

	// ── Browse all dialog ─────────────────────────────────────────────

	function openBrowseDialog(tax, terms, group, filterInput) {
		// Remove existing dialog if any.
		var existing = document.querySelector('.memdir-directory__browse-dialog');
		if (existing) existing.remove();

		var labelEl = group.querySelector('.memdir-directory__filter-label');
		var title = labelEl ? labelEl.textContent : tax;

		var activeTerms = state.filters[tax] || [];

		var dialog = document.createElement('dialog');
		dialog.className = 'memdir-directory__browse-dialog';

		var searchHtml = '';
		if (terms.length > 10) {
			searchHtml = '<div class="memdir-directory__browse-search-wrap">' +
				'<input type="text" class="memdir-directory__browse-search" placeholder="Search...">' +
				'</div>';
		}

		var checkboxesHtml = '';
		terms.forEach(function (t) {
			var checked = activeTerms.includes(t.slug) ? ' checked' : '';
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
		var searchInputEl = dialog.querySelector('.memdir-directory__browse-search');
		if (searchInputEl) {
			searchInputEl.addEventListener('input', function () {
				var q = searchInputEl.value.toLowerCase().trim();
				dialog.querySelectorAll('.memdir-directory__browse-body label').forEach(function (lbl) {
					var name = lbl.dataset.termName || '';
					lbl.style.display = (!q || name.includes(q)) ? '' : 'none';
				});
			});
		}

		// Close button.
		dialog.querySelector('.memdir-directory__browse-close').addEventListener('click', function () {
			dialog.close();
			dialog.remove();
		});

		// Done button — sync checkboxes back to state + stack pills.
		dialog.querySelector('.memdir-directory__browse-done').addEventListener('click', function () {
			var newTerms = [];
			dialog.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
				if (cb.checked) {
					newTerms.push({ slug: cb.value, name: cb.dataset.name });
				}
			});

			// Remove all stack pills for this taxonomy.
			var stack = container.querySelector('[data-filter-stack]');
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

			maybeHideClearAll();
			if (filterInput) filterInput.value = '';
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
		var mapEl = document.getElementById('memdir-directory__map');
		if (!mapEl || typeof L === 'undefined') return;

		map = L.map(mapEl, {
			scrollWheelZoom: false,
			zoomControl: true,
		}).setView([39.8, -98.5], 4);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
			maxZoom: 18,
		}).addTo(map);

		// Use MarkerClusterGroup for avatar mode, plain LayerGroup otherwise.
		if (pinStyle === 'avatar' && typeof L.markerClusterGroup === 'function') {
			markersLayer = L.markerClusterGroup({
				maxClusterRadius: 50,
				spiderfyOnMaxZoom: true,
				showCoverageOnHover: false,
				iconCreateFunction: function (cluster) {
					var count = cluster.getChildCount();
					var size = count < 10 ? 'small' : count < 50 ? 'medium' : 'large';
					return L.divIcon({
						html: '<div class="memdir-cluster memdir-cluster--' + size + '">' + count + '</div>',
						className: 'memdir-cluster-icon',
						iconSize: L.point(40, 40),
					});
				},
			});
		} else {
			markersLayer = L.layerGroup();
		}
		markersLayer.addTo(map);

		var markersEl = document.getElementById('memdir-directory__markers');
		if (markersEl) {
			try {
				var markers = JSON.parse(markersEl.textContent);
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

			var popupContent = buildPopup(m);
			var marker;

			if (pinStyle === 'avatar') {
				marker = createAvatarMarker(m, lat, lng);
			} else if (pinStyle === 'custom' && customPinIcon) {
				marker = createCustomMarker(lat, lng);
			} else if (pinStyle === 'pin') {
				marker = L.marker([lat, lng]);
			} else {
				// Default: circle marker.
				marker = L.circleMarker([lat, lng], {
					radius: 7,
					fillColor: '#87986A',
					color: '#fff',
					weight: 2,
					fillOpacity: 0.9,
				});
			}

			marker.bindPopup(popupContent, { maxWidth: 260 });
			markersLayer.addLayer(marker);
			bounds.push([lat, lng]);
		});

		if (bounds.length > 0) {
			map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
		}
	}

	function createAvatarMarker(m, lat, lng) {
		var imgHtml = m.avatar
			? '<img src="' + escAttr(m.avatar) + '" alt="' + escAttr(m.title) + '">'
			: '<span class="memdir-avatar-pin__fallback">' + escHtml(m.title.charAt(0)) + '</span>';

		var icon = L.divIcon({
			html: '<div class="memdir-avatar-pin">' + imgHtml + '</div>',
			className: 'memdir-avatar-pin-wrapper',
			iconSize: [38, 38],
			iconAnchor: [19, 38],
			popupAnchor: [0, -34],
		});

		return L.marker([lat, lng], { icon: icon });
	}

	// Cached custom icon instance (same image for all markers).
	var _customIcon = null;

	function createCustomMarker(lat, lng) {
		if (!_customIcon) {
			_customIcon = L.icon({
				iconUrl: customPinIcon,
				iconSize: [customPinWidth, customPinHeight],
				iconAnchor: [Math.round(customPinWidth / 2), customPinHeight],
				popupAnchor: [0, -customPinHeight + 4],
			});
		}
		return L.marker([lat, lng], { icon: _customIcon });
	}

	function buildPopup(m) {
		var avatarHtml = m.avatar
			? '<img src="' + escAttr(m.avatar) + '" style="width:36px;height:36px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle;">'
			: '';

		return '<div style="display:flex;align-items:center;min-width:160px;">' +
				avatarHtml +
				'<div>' +
					'<strong style="font-size:13px;">' + escHtml(m.title) + '</strong>' +
					(m.location ? '<br><span style="font-size:11px;color:#6b7280;">' + escHtml(m.location) + '</span>' : '') +
					'<br><a href="' + escAttr(m.permalink) + '" style="font-size:11px;color:#87986A;">View Profile &rarr;</a>' +
				'</div>' +
			'</div>';
	}

	// ── Pagination ────────────────────────────────────────────────────

	function initPagination() {
		container.addEventListener('click', function (e) {
			var btn = e.target.closest('.memdir-directory__page-btn');
			if (btn && !btn.classList.contains('memdir-directory__page-btn--active')) {
				e.preventDefault();
				state.page = parseInt(btn.dataset.page, 10) || 1;
				fetchResults();
				container.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	// ── AJAX fetch ────────────────────────────────────────────────────

	var fetchController = null;

	function fetchResults() {
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

					var oldPag = container.querySelector('.memdir-directory__pagination');
					if (oldPag) oldPag.remove();

					if (json.data.pagination) {
						grid.insertAdjacentHTML('afterend', json.data.pagination);
					}

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

		if (state.search) {
			params.set('memdir_search', state.search);
		} else {
			params.delete('memdir_search');
		}

		if (state.page > 1) {
			params.set('memdir_page', state.page);
		} else {
			params.delete('memdir_page');
		}

		container.querySelectorAll('.memdir-directory__filter-group').forEach(function (g) {
			params.delete(g.dataset.taxonomy);
		});

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
