/*
 * VEForAll initialization and validation
 *
 * @author Pierre Boutet
 * @author Clement Flipo
 * @author Yaron Koren
 */

/* globals jQuery, mw, ve */

( function ( $, mw ) {

	const veInstances = [];

	function initVisualEditor() {
		// Init VisualEditor platform
		new ve.init.mw.Platform().initialize()
			.fail( () => {
				// $( editor ).text( 'Sorry, this browser is not supported.' );
			} )
			.done( () => {
				// Add i18n messages to VE
				ve.init.platform.addMessages( mw.messages.get() );
			} );

		// Let others know we're done here
		$( document ).trigger( 'VEForAllLoaded' );
	}

	/**
	 * The main function called by outside extensions - applies VisualEditor
	 * onto any jQuery group of textareas.
	 *
	 * @return {boolean}
	 */
	jQuery.fn.applyVisualEditor = function () {
		return this.each( function () {
			const veEditor = new mw.veForAll.Editor( this, $( this ).val() );
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
		// If this is being called from within the Page Forms extension,
		// make sure that the init code is called after the form has
		// already been created.
		mw.hook( 'pf.formSetupAfter' ).add( () => {
			initVisualEditor();
		} );
	} else {
		initVisualEditor();
	}

	mw.hook( 'pf.formValidation' ).add( ( args ) => {
		let hasMinimizedPlainVETextarea = false;
		$( '.minimized' ).each( function () {
			const instanceDiv = $( this );
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
