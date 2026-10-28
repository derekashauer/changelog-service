/**
 * ChangelogWP Admin Scripts
 *
 * @package ChangelogWP
 */

( function( $ ) {
	'use strict';

	$( document ).ready( function() {
		// Auto-resize textarea
		var $editor = $( '#caas-content-editor' );

		if ( $editor.length ) {
			$editor.on( 'input', function() {
				this.style.height = 'auto';
				this.style.height = ( this.scrollHeight + 2 ) + 'px';
			} );

			// Trigger on load
			$editor.trigger( 'input' );
		}

		// Tab key support in textarea
		$editor.on( 'keydown', function( e ) {
			if ( e.key === 'Tab' ) {
				e.preventDefault();
				var start = this.selectionStart;
				var end = this.selectionEnd;

				this.value = this.value.substring( 0, start ) + '\t' + this.value.substring( end );
				this.selectionStart = this.selectionEnd = start + 1;
			}
		} );
	} );

} )( jQuery );

