/*
 * VEForAll initialization
 *
 * @author Pierre Boutet
 * @author Clement Flipo
 * @author Yaron Koren
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

	// This hook was added in Page Forms 5.0.
	mw.hook( 'pf.formValidation' ).add( function ( args ) {
		var hasMinimizedPlainVETextarea = false;
		$( '.minimized' ).each( function () {
			var instanceDiv = $( this );
			instanceDiv.find( 'textarea.vePartOfTemplate' ).each( function () {
				if ( $( this ).css( 'display' ) !== 'none' ) {
					instanceDiv.removeClass( 'minimized' );
					instanceDiv.find( '.fieldValuesDisplay' ).html( '' );
					instanceDiv.find( '.instanceMain' ).fadeIn();
					instanceDiv.find( '.fieldValuesDisplay' ).remove();
					hasMinimizedPlainVETextarea = true;
				}
			} );
		} );
		if ( hasMinimizedPlainVETextarea ) {
			args.numErrors += 1;
			// Only add this error message if it's not already there.
			if ( $( '#veforall_form_error_header' ).length === 0 ) {
				$( '#contentSub' ).append( '<div id="veforall_form_error_header" class="errorbox" style="font-size: medium"><img src="' +
					mw.config.get( 'wgPageFormsScriptPath' ) + '/skins/MW-Icon-AlertMark.png" />&nbsp;' +
					mw.message( 'veforall-form-instances-not-minimized' ).escaped() + '</div><br clear="both" />' );
			}
		}

	} );

}( jQuery, mw ) );
