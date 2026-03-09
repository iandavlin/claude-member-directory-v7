/**
 * Member Directory — Directory Listing JS.
 *
 * Handles search debounce, taxonomy filter interactions, AJAX live reload,
 * pagination, and URL state management for the [memdir_directory] shortcode.
 *
 * Separate file from memdir.js to avoid CRLF issues and keep directory
 * concerns isolated.
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

	// ── Init ──────────────────────────────────────────────────────────

	function init() {
		// Read initial state from DOM.
		const searchInput = container.querySelector('[data-memdir-search]');
		if (searchInput) {
			state.search = searchInput.value;
		}

		// Read active filter pills.
		container.querySelectorAll('.memdir-directory__filter-group').forEach(group => {
			const tax = group.dataset.taxonomy;
			const pills = group.querySelectorAll('.memdir-directory__filter-pill');
			if (pills.length) {
				state.filters[tax] = Array.from(pills).map(p => p.dataset.term);
			}
		});

		// Read section from data attribute if present.
		state.section = container.dataset.section || '';

		initSearch();
		initFilters();
		initPagination();
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
		// Remove pill clicks (event delegation).
		container.addEventListener('click', function (e) {
			const pill = e.target.closest('.memdir-directory__filter-pill');
			if (pill) {
				e.preventDefault();
				const group = pill.closest('.memdir-directory__filter-group');
				const tax = group ? group.dataset.taxonomy : '';
				const term = pill.dataset.term;

				if (tax && term) {
					removeTerm(tax, term);
					pill.remove();
					state.page = 1;
					fetchResults();
				}
			}
		});

		// Browse all buttons.
		container.querySelectorAll('.memdir-directory__filter-browse').forEach(btn => {
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
		state.filters[tax] = state.filters[tax].filter(t => t !== term);
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

			// Add pill to the DOM.
			const group = container.querySelector('.memdir-directory__filter-group[data-taxonomy="' + tax + '"]');
			if (group) {
				const selected = group.querySelector('.memdir-directory__filter-selected');
				if (selected) {
					const pill = document.createElement('button');
					pill.className = 'memdir-directory__filter-pill';
					pill.dataset.term = termSlug;
					pill.innerHTML = escHtml(termName) + ' <span class="remove">&times;</span>';
					selected.appendChild(pill);
				}
			}
		}
	}

	function openBrowseDialog(tax, terms, group) {
		// Remove existing dialog if any.
		const existing = document.querySelector('.memdir-directory__browse-dialog');
		if (existing) existing.remove();

		const label = group.querySelector('label');
		const title = label ? label.textContent : tax;

		const activeTerms = state.filters[tax] || [];

		const dialog = document.createElement('dialog');
		dialog.className = 'memdir-directory__browse-dialog';

		let checkboxesHtml = '';
		terms.forEach(function (t) {
			const checked = activeTerms.includes(t.slug) ? ' checked' : '';
			checkboxesHtml += '<label><input type="checkbox" value="' + escAttr(t.slug) + '" data-name="' + escAttr(t.name) + '"' + checked + '> ' + escHtml(t.name) + '</label>';
		});

		dialog.innerHTML =
			'<div class="memdir-directory__browse-header">' +
				'<h3>' + escHtml(title) + '</h3>' +
				'<button class="memdir-directory__browse-close" type="button">&times;</button>' +
			'</div>' +
			'<div class="memdir-directory__browse-body">' +
				checkboxesHtml +
			'</div>' +
			'<div class="memdir-directory__browse-footer">' +
				'<button class="memdir-directory__browse-done" type="button">Done</button>' +
			'</div>';

		document.body.appendChild(dialog);
		dialog.showModal();

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

			// Clear existing pills.
			const selected = group.querySelector('.memdir-directory__filter-selected');
			if (selected) selected.innerHTML = '';

			// Reset filter state.
			state.filters[tax] = [];

			// Re-add checked terms.
			newTerms.forEach(function (t) {
				addTerm(tax, t.slug, t.name);
			});

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

	// ── Pagination ────────────────────────────────────────────────────

	function initPagination() {
		container.addEventListener('click', function (e) {
			const btn = e.target.closest('.memdir-directory__page-btn');
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

	let fetchController = null;

	function fetchResults() {
		// Abort any pending request.
		if (fetchController) {
			fetchController.abort();
		}
		fetchController = new AbortController();

		showLoading();
		updateURL();

		const formData = new FormData();
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
					const grid = container.querySelector('.memdir-directory__grid');
					if (grid) {
						grid.innerHTML = json.data.html || '';
					}

					// Replace pagination.
					const oldPag = container.querySelector('.memdir-directory__pagination');
					if (oldPag) oldPag.remove();

					if (json.data.pagination) {
						grid.insertAdjacentHTML('afterend', json.data.pagination);
					}

					// Show/hide empty message.
					let emptyMsg = container.querySelector('.memdir-directory__empty');
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

		const overlay = document.createElement('div');
		overlay.className = 'memdir-directory__loading';
		overlay.innerHTML = '<div class="memdir-directory__spinner"></div>';
		container.appendChild(overlay);
	}

	function hideLoading() {
		const overlay = container.querySelector('.memdir-directory__loading');
		if (overlay) overlay.remove();
	}

	// ── URL state management ──────────────────────────────────────────

	function updateURL() {
		const params = new URLSearchParams(window.location.search);

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

		const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
		history.replaceState(null, '', newUrl);
	}

	// ── Utility ───────────────────────────────────────────────────────

	function escHtml(str) {
		const div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	function escAttr(str) {
		return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	// ── Boot ──────────────────────────────────────────────────────────

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
