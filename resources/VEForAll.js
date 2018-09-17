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

			var textarea = this,
				veEditor = new mw.veForAll.Editor( this, $( this ).val() );

			veEditor.initCallbacks.push( function () {
				// Handle keyup events on ve surfaces and textarea to let other know that something has changed there
				veEditor.target.on( 'editor-ready', function () {

					// Catch keyup events on surface to comply with saveAndContinue button state and changes warning
					veEditor.target.getSurface().getView().on( 'keyup', function () {
						$( textarea ).trigger( 'change' );
					} );

					// Catch keyup events on raw textarea to use changes warning on page reload
					veEditor.target.$node.on( 'keyup', function () {
						$( textarea ).trigger( 'change' );
					} );
				} );
			} );

			veInstances.push( veEditor );
		} );
	};

	/**
	 * @return {Array}
	 */
	jQuery.fn.getVEInstances = function () {
		return veInstances;
	};

	initVisualEditor();

}( jQuery, mw ) );
