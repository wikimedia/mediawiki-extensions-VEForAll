/*!
 * VEForAll initialization
 *
 * @author Pierre Boutet, Clement Flipo
 * @copyright Copyright © 2016-2017, Wikifab
 */
( function ( $, mw ) {

	/**
	 * What this file does:
	 * - Loads VisualEditor library
	 * - Watch click on save button, to defer the save request after all visualEditor
	 *   requests are done.
	 */

	var veInstances = [],
		clickCount = [];

	function initVisualEditor() {
		// Init VisualEditor platform
		new ve.init.mw.Platform( ).initialize()
				.fail( function () {
					$( editor ).text( 'Sorry, this browser is not supported.' );
				} )
				.done( function () {
					// Add i18n messages to VE
					ve.init.platform.addMessages( mw.messages.get() );
				} );

		// we catch event on save button, to wait that every VE content is up to date
		// (ie api calls triggered and received)
		catchAndDelayClickEvent( 'wpSave' );
		catchAndDelayClickEvent( 'wpSaveAndContinue' );

		$( document ).trigger( 'VEForAllLoaded' );
	}

	function catchAndDelayClickEvent( buttonId ) {
		var updateNeeded, i;

		if ( !clickCount[ buttonId ] ) {
			clickCount[ buttonId ] = 0;
		}

		$( '#' + buttonId ).click( function ( event ) {
			clickCount[ buttonId ]++;
			// the click count var is a security to avoid infinite loop if api calls do not end
			updateNeeded = false;
			// if one VE area is focused, we force to update its data by blurring it
			for ( i = 0; i < veInstances.length; i++ ) {
				if ( veInstances[ i ].target.getSurface().getView().isFocused() ) {
					veInstances[ i ].target.getSurface().getView().blur();
					updateNeeded = true;
				}
			}
			if ( ( updateNeeded || jQuery.active > 0 ) && clickCount[ buttonId ] < 2 ) {
				// if an update is needed, stop event propagation, and delay before relaunch
				event.preventDefault();
				setTimeout( function () {
					clickWhenApiCallDone( '#' + buttonId );
				}, 100 );
			} else {
				// if success, we can reset the clickCount to 0 to re-enable other calls.
				clickCount[ buttonId ] = 0;
			}
		} );
	}

	function clickWhenApiCallDone( button, maxCount ) {
		if ( maxCount === null ) {
			maxCount = 5;
		}
		if ( jQuery.active > 0 && maxCount > 0 ) {
			setTimeout( function () {
				clickWhenApiCallDone( button, maxCount - 1 );
			}, 500 );
		} else {
			$( button ).click();
		}
	}

	jQuery.fn.applyVisualEditor = function () {
		// var logo = $('<div class="ve-demo-logo"></div>');
		// var toolbar = $('<div class="ve-demo-toolbar ve-demo-targetToolbar"></div>');
		// var editor = $('<div class="ve-demo-editor"></div>');

		return this.each( function () {
			// $(this).before(logo, editor, toolbar);
			var veEditor = new mw.veForAll.Editor( this, $( this ).val() );
			veInstances.push( veEditor );
		} );
	};

	jQuery.fn.getVEInstances = function () {
		return veInstances;
	};

	// mw.loader.using( 'ext.veforall.main', $.proxy( initVisualEditor ) );
	initVisualEditor();

}( jQuery, mw ) );

$ = jQuery;
