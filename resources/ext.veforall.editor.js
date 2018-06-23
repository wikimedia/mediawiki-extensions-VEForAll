/*!
 * VEForAll Editor class.
 *
 * @author Pierre Boutet
 * @copyright Copyright © 2016-2017, Wikifab
 */

( function ( $, mw, OO, ve ) {
	'use strict';

	/**
	 * this launch the visual editor on a given textarea
	 *
	 * usage :
	 *   new mw.veForAll.Editor(node, initialContent);
	 * where :
	 * - node is the html element of the textarea
	 * - initialContent is the text content (wikitext format)
	 *
	 */

	/**
	 * @class
	 * @constructor
	 * @param {jQuery} $node Node to replace with a VisualEditor
	 * @param {string} [content='']
	 */
	mw.veForAll.Editor = function ( $node, content ) {
		var modules;

		// mixin constructor
		OO.EventEmitter.call( this );

		// node the editor is associated with.
		this.$node = $( $node );

		// HACK: make textarea look pending in case we didn't come from an editor switch
		// Once this is an OO.ui.TextInputWidget we'll be able to use real PendingElement
		// functionality for this
		this.$node
			.prop( 'disabled', true )
			.addClass( 'oo-ui-texture-pending' );

		// The main module should already be loaded.
		modules = mw.config.get( 'wgVisualEditorConfig' ).pluginModules.filter( mw.loader.getState );

		// load dependencies & init editor
		mw.loader.using( modules, $.proxy( this.init, this, content || '' ) );
	};

	OO.mixinClass( mw.veForAll.Editor, OO.EventEmitter );

	/**
	 * List of callbacks to execute when VE is fully loaded
	 */
	mw.veForAll.Editor.prototype.initCallbacks = [];

	mw.veForAll.Editor.prototype.createTarget = function () {
		if ( $( this.$node ).hasClass( 'toolbarOnTop' ) ) {
			this.target = new mw.veForAll.Targetwide( this.$node, $( this.$node ).val() );
		} else {
			this.target = new mw.veForAll.Target( this.$node, $( this.$node ).val() );
		}
		return this.target;
	};

	/**
	 * Callback function, executed after all VE dependencies have been loaded.
	 *
	 * @param {string} [content='']
	 */
	mw.veForAll.Editor.prototype.init = function ( content ) {
		var $veNode, htmlDoc, surface, $documentNode,
			$focusedElement = $( ':focus' );

		// ve.createDocumentFromHtml documents support for an empty string
		// to create an empty document, but does not mention other falsy values.
		content = content || '';

		// this.target = ve.init.mw.targetFactory.create( 'pageForms' );
		this.target = this.createTarget();

		$.each( this.initCallbacks, $.proxy( function ( k, callback ) {
			callback.apply( this );
		}, this ) );
	};

	mw.veForAll.Editor.prototype.destroy = function () {
		if ( this.target ) {
			this.target.destroy();
		}

		// re-display original node
		this.$node.show();
	};

	/**
	 * Gets HTML of Flow field
	 *
	 * @return {string}
	 */
	mw.veForAll.Editor.prototype.getRawContent = function () {
		var doc, html;

		// If we haven't fully loaded yet, just return nothing.
		if ( !this.target ) {
			return '';
		}

		// get document from ve
		doc = ve.dm.converter.getDomFromModel( this.dmDoc );

		// document content will include html, head & body nodes; get only content inside body node
		html = ve.properInnerHtml( $( doc.documentElement ).find( 'body' )[ 0 ] );
		return html;
	};

	/**
	 * Checks if the document is empty
	 *
	 * @return {boolean} True if and only if it's empty
	 */
	mw.veForAll.Editor.prototype.isEmpty = function () {
		if ( !this.dmDoc ) {
			return true;
		}

		// Per Roan
		return this.dmDoc.data.countNonInternalElements() <= 2;
	};

	mw.veForAll.Editor.prototype.focus = function () {
		if ( !this.target ) {
			this.initCallbacks.push( function () {
				this.focus();
			} );
			return;
		}

		this.target.surface.getView().focus();
	};

	mw.veForAll.Editor.prototype.moveCursorToEnd = function () {
		var data, cursorPos;

		if ( !this.target ) {
			this.initCallbacks.push( function () {
				this.moveCursorToEnd();
			} );
			return;
		}

		data = this.target.surface.getModel().getDocument().data;
		cursorPos = data.getNearestContentOffset( data.getLength(), -1 );

		this.target.surface.getModel().setSelection( new ve.Range( cursorPos ) );
	};

	// Static fields

	/**
	 * Type of content to use
	 *
	 * @var {string}
	 */
	mw.veForAll.Editor.static.format = 'html';

	/**
	 * Name of this editor
	 *
	 * @var string
	 */
	mw.veForAll.Editor.static.name = 'visualeditor';

	// Static methods

	mw.veForAll.Editor.static.isSupported = function () {
		var isMobileTarget = ( mw.config.get( 'skin' ) === 'minerva' );

		/* global VisualEditorSupportCheck */
		return !!(
				!isMobileTarget &&
				mw.loader.getState( 'ext.visualEditor.core' ) &&
				mw.config.get( 'wgFlowEditorList' ).indexOf( 'visualeditor' ) !== -1 &&
				window.VisualEditorSupportCheck && VisualEditorSupportCheck
				);
	};

}( jQuery, mediaWiki, OO, ve ) );

