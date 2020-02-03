/*
 * VEForAll initialization
 *
 * @author Pierre Boutet, Clement Flipo
 * @copyright Copyright © 2016-2017, Wikifab
 */

/* globals jQuery, mw, ve */

( function ( $, mw ) {

	/**
	 * What this file does:
	 * - Loads VisualEditor library
	 * - Watch click on save buttons, to defer the save request after all visualEditor
	 *   requests are done.
	 */

	var veInstances = [];

	function initVisualEditor() {

		var config = mw.config.get( 'VEForAll' );
		if ( !config.VisualEditorEnable ) {
			return;
		}

		// Init VisualEditor platform
		new ve.init.mw.Platform().initialize()
			.fail( function () {
				// $( editor ).text( 'Sorry, this browser is not supported.' );
			} )
			.done( function () {
				// Add i18n messages to VE
				ve.init.platform.addMessages( mw.messages.get() );
			} );

		// Let others know we're done here
		$( document ).trigger( 'VEForAllLoaded' );
	}

	/**
	 * Being called by PageForms on textareas with 'visualeditor' class present
	 *
	 * @return {boolean}
	 */
	jQuery.fn.applyVisualEditor = function () {

		var config = mw.config.get( 'VEForAll' );
		if ( !config.VisualEditorEnable ) {
			return false;
		}

		return this.each( function () {
			var veEditor = new mw.veForAll.Editor( this, $( this ).val() );
			veInstances.push( veEditor );
		} );
	};

	/**
	 * @return {Array}
	 */
	jQuery.fn.getVEInstances = function () {
		return veInstances;
	};

	if ( mw.hook( 'pf.formSetupAfter' ) ) {
		// This hook was added in Page Forms 4.7 - it helps to ensure that the VEForAll
		// init code does not get called too soon.
		mw.hook( 'pf.formSetupAfter' ).add( function () {
			initVisualEditor();
		} );
	} else {
		initVisualEditor();
	}

}( jQuery, mw ) );
