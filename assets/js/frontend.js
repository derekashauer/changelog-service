/**
 * ChangelogWP Frontend JavaScript
 *
 * Handles interactive features: collapsible sections, search, filtering, copy links.
 *
 * @package ChangelogWP
 */

(function() {
	'use strict';

	/**
	 * Initialize all interactive features.
	 */
	function init() {
		initSearch();
		initFilterBadges();
		initCopyLinks();
	}

	/**
	 * Initialize search functionality.
	 */
	function initSearch() {
		const containers = document.querySelectorAll('.caas-searchable');
		
		containers.forEach(function(container) {
			const searchInput = container.querySelector('.caas-search-input');
			if (!searchInput) {
				return;
			}
			
			const versionSections = container.querySelectorAll('.caas-version-section');
			
			searchInput.addEventListener('input', function() {
				const query = this.value.toLowerCase().trim();
				
				if (query === '') {
					// Show all items when search is empty.
					versionSections.forEach(function(section) {
						section.style.display = '';
						const items = section.querySelectorAll('ul li');
						items.forEach(function(item) {
							item.style.display = '';
						});
					});
					return;
				}
				
				versionSections.forEach(function(section) {
					const items = section.querySelectorAll('ul li');
					let hasMatch = false;

					// Check release notes for match.
					const releaseNotes = section.querySelector('.caas-release-notes');
					var notesMatch = releaseNotes && releaseNotes.textContent.toLowerCase().includes(query);

					if (notesMatch) {
						hasMatch = true;
					}

					items.forEach(function(item) {
						const itemText = item.textContent.toLowerCase();
						if (itemText.includes(query) || notesMatch) {
							item.style.display = '';
							hasMatch = true;
						} else {
							item.style.display = 'none';
						}
					});

					// Show version section if it has matching items or release notes.
					if (hasMatch) {
						section.style.display = '';
					} else {
						section.style.display = 'none';
					}
				});
			});
		});
	}

	/**
	 * Initialize badge filtering.
	 */
	function initFilterBadges() {
		const containers = document.querySelectorAll('.caas-filterable');
		
		containers.forEach(function(container) {
			const filterButtons = container.querySelectorAll('.caas-filter-btn');
			const versionSections = container.querySelectorAll('.caas-version-section');
			const badgeFilters = container.querySelector('.caas-badge-filters');
			
			filterButtons.forEach(function(button) {
				button.addEventListener('click', function() {
					const filter = this.getAttribute('data-filter');
					
					// Update active state.
					filterButtons.forEach(function(btn) {
						btn.classList.remove('active');
						btn.setAttribute('aria-pressed', 'false');
					});
					this.classList.add('active');
					this.setAttribute('aria-pressed', 'true');
					
					// Update container class for opacity styling.
					if (filter === 'all') {
						badgeFilters.classList.add('showing-all');
					} else {
						badgeFilters.classList.remove('showing-all');
					}
					
					// Filter sections and items.
					versionSections.forEach(function(section) {
						if (filter === 'all') {
							// Show all items.
							section.style.display = '';
							const items = section.querySelectorAll('ul li');
							items.forEach(function(item) {
								item.style.display = '';
							});
						} else {
							// Filter by badge type.
							const items = section.querySelectorAll('ul li');
							let hasMatch = false;
							
							items.forEach(function(item) {
								const badge = item.querySelector('.caas-badge.' + filter);
								if (badge) {
									item.style.display = '';
									hasMatch = true;
								} else {
									item.style.display = 'none';
								}
							});
							
							// Show version section if it has matching items.
							if (hasMatch) {
								section.style.display = '';
							} else {
								section.style.display = 'none';
							}
						}
					});
				});
			});
		});
	}

	/**
	 * Initialize copy link buttons.
	 */
	function initCopyLinks() {
		const copyButtons = document.querySelectorAll('.caas-copy-link');
		
		copyButtons.forEach(function(button) {
			// Create tooltip element dynamically (not in HTML for SEO).
			const tooltip = document.createElement('span');
			tooltip.className = 'caas-copied-text';
			tooltip.textContent = 'Copied!';
			button.appendChild(tooltip);
			
			function copyToClipboard() {
				const url = button.getAttribute('data-url');
				
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(function() {
						button.classList.add('copied');
						setTimeout(function() {
							button.classList.remove('copied');
						}, 2000);
					}).catch(function(err) {
						// Clipboard copy failed silently
					});
				} else {
					// Fallback for older browsers.
					const textarea = document.createElement('textarea');
					textarea.value = url;
					textarea.style.position = 'fixed';
					textarea.style.opacity = '0';
					document.body.appendChild(textarea);
					textarea.select();
					try {
						document.execCommand('copy');
						button.classList.add('copied');
						setTimeout(function() {
							button.classList.remove('copied');
						}, 2000);
					} catch (err) {
						// Clipboard copy failed silently
					}
					document.body.removeChild(textarea);
				}
			}
			
			button.addEventListener('click', function(e) {
				e.preventDefault();
				copyToClipboard();
			});
			
			// Support keyboard navigation (Enter and Space keys).
			button.addEventListener('keydown', function(e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					copyToClipboard();
				}
			});
		});
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

