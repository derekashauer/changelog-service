/**
 * ChangelogWP Settings Page Scripts
 *
 * @package ChangelogWP
 */

( function( $ ) {
	'use strict';

	var badgeIndex = 0;
	var currentEmojiInput = null;

	/**
	 * Initialize tabs.
	 */
	function initTabs() {
		$( '.nav-tab' ).on( 'click', function( e ) {
			e.preventDefault();

			var tab = $( this ).data( 'tab' );

			// Update active tab.
			$( '.nav-tab' ).removeClass( 'nav-tab-active' );
			$( this ).addClass( 'nav-tab-active' );

			// Show corresponding content.
			$( '.caas-tab-content' ).removeClass( 'active' );
			$( '#tab-' + tab ).addClass( 'active' );

			// Update hidden input for form submission.
			$( '#caas-active-tab' ).val( tab );

			// Update URL hash.
			window.location.hash = tab;
		} );

		// Check URL param first (preserved after save), then hash.
		var urlParams = new URLSearchParams( window.location.search );
		var tabParam = urlParams.get( 'tab' );
		var hash = window.location.hash.replace( '#', '' );
		var activeTab = tabParam || hash;

		if ( activeTab && $( '.nav-tab[data-tab="' + activeTab + '"]' ).length ) {
			$( '.nav-tab[data-tab="' + activeTab + '"]' ).trigger( 'click' );
		}
	}

	/**
	 * Initialize color pickers.
	 */
	function initColorPickers() {
		$( '.caas-color-picker' ).each( function() {
			if ( ! $( this ).hasClass( 'wp-color-picker' ) ) {
				$( this ).wpColorPicker( {
					change: function( event, ui ) {
						updateBadgePreview( $( event.target ) );
					}
				} );
			}
		} );
	}

	/**
	 * Update badge preview.
	 */
	function updateBadgePreview( $input ) {
		var $row = $input.closest( 'tr' );
		if ( ! $row.length ) {
			return;
		}

		var $preview = $row.find( '.caas-badge-preview' );

		if ( ! $preview.length ) {
			return;
		}

		// Color picker wraps the input, so we need to get the value from the original input.
		var $colorInput = $row.find( '.badge-color-input' );
		var color = $colorInput.val() || '#666666';

		var label = $row.find( '.badge-label-input' ).val() || '';
		var emoji = $row.find( '.badge-emoji-input' ).val() || '';
		var showEmojis = $( 'input[name$="[show_emojis]"]' ).is( ':checked' );

		// Show emoji in preview only if emojis are enabled.
		var previewText = ( showEmojis && emoji ) ? emoji + ' ' + label : label;

		$preview.css( 'background-color', color ).text( previewText );
	}

	/**
	 * Initialize badge preview updates.
	 */
	function initBadgePreviews() {
		$( document ).on( 'input', '.badge-label-input, .badge-emoji-input', function() {
			updateBadgePreview( $( this ) );
		} );

		// Toggle emoji column visibility based on checkbox.
		var $showEmojisCheckbox = $( 'input[name$="[show_emojis]"]' );

		function toggleEmojiColumn() {
			var showEmojis = $showEmojisCheckbox.is( ':checked' );
			$( '.col-emoji' ).toggle( showEmojis );

			// Update all previews.
			$( '.badge-label-input' ).each( function() {
				updateBadgePreview( $( this ) );
			} );
		}

		// Initialize on page load.
		toggleEmojiColumn();

		// Toggle on checkbox change.
		$showEmojisCheckbox.on( 'change', toggleEmojiColumn );
	}

	/**
	 * Generate unique key for new badges.
	 */
	function generateBadgeKey() {
		return 'custom_' + Date.now() + '_' + badgeIndex++;
	}

	/**
	 * Initialize badge management.
	 */
	function initBadges() {
		// Count existing custom badges.
		badgeIndex = $( '.badge-row.custom' ).length;

		// Add new badge.
		$( '#add-badge' ).on( 'click', function() {
			var key = generateBadgeKey();
			var template = $( '#badge-row-template' ).html();
			template = template.replace( /\{\{KEY\}\}/g, key );

			var $newRow = $( template );
			$( '#badges-tbody' ).append( $newRow );

			// Initialize color picker on new row.
			$newRow.find( '.caas-color-picker' ).wpColorPicker( {
				change: function( event, ui ) {
					updateBadgePreview( $( event.target ) );
				}
			} );

			// Focus on prefixes field.
			$newRow.find( '.badge-prefixes-input' ).focus();
		} );

		// Remove badge.
		$( document ).on( 'click', '.remove-badge', function() {
			if ( confirm( caasSettings.confirmDelete ) ) {
				$( this ).closest( 'tr' ).remove();
			}
		} );
	}

	/**
	 * Initialize emoji picker.
	 */
	function initEmojiPicker() {
		var $container = $( '#emoji-picker-container' );

		if ( ! $container.length ) {
			return;
		}

		// Open picker when clicking emoji input field.
		$( document ).on( 'click', '.badge-emoji-input', function( e ) {
			e.preventDefault();
			e.stopPropagation();

			var $input = $( this );
			currentEmojiInput = $input;

			// Toggle visibility.
			if ( $container.is( ':visible' ) ) {
				$container.hide();
				return;
			}

			// Position directly below the input field.
			var offset = $input.offset();
			var inputHeight = $input.outerHeight();
			var inputWidth = $input.outerWidth();

			// Calculate position.
			var topPos = offset.top + inputHeight + 2;
			var leftPos = offset.left;

			// Get picker dimensions (approximate).
			var pickerHeight = 400;
			var pickerWidth = 350;

			// Ensure it doesn't go off screen bottom.
			var windowHeight = $( window ).height() + $( window ).scrollTop();
			if ( topPos + pickerHeight > windowHeight ) {
				topPos = offset.top - pickerHeight - 2; // Position above input.
			}

			// Ensure it doesn't go off screen right.
			var windowWidth = $( window ).width();
			if ( leftPos + pickerWidth > windowWidth ) {
				leftPos = offset.left + inputWidth - pickerWidth; // Align right edge.
			}

			$container.css( {
				top: topPos + 'px',
				left: leftPos + 'px'
			} ).show();
		} );

		// Handle emoji selection from emoji-picker-element.
		document.addEventListener( 'emoji-click', function( event ) {
			if ( currentEmojiInput && event.detail && event.detail.unicode ) {
				currentEmojiInput.val( event.detail.unicode );
				updateBadgePreview( currentEmojiInput );
				$container.hide();
				currentEmojiInput = null;
			}
		} );

		// Close emoji picker when clicking outside.
		$( document ).on( 'click', function( e ) {
			if ( ! $( e.target ).closest( '.badge-emoji-input, #emoji-picker-container' ).length ) {
				if ( $container.is( ':visible' ) ) {
					$container.hide();
					currentEmojiInput = null;
				}
			}
		} );

		// Close on escape key.
		$( document ).on( 'keydown', function( e ) {
			if ( e.key === 'Escape' && $container.is( ':visible' ) ) {
				$container.hide();
				currentEmojiInput = null;
			}
		} );
	}

	/**
	 * Document ready.
	 */
	$( document ).ready( function() {
		initTabs();
		initColorPickers();
		initBadgePreviews();
		initBadges();
		initEmojiPicker();
	} );

} )( jQuery );
