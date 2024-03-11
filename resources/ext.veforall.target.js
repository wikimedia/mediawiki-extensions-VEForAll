/* globals mediaWiki, OO, ve */

( function ( mw, OO, ve ) {
	'use strict';

	mw.veForAll = mw.veForAll || {
		ui: {}
	};

	/**
	 * Inherits from the standard VE target.
	 *
	 * @param node
	 * @param content
	 * @class
	 * @extends ve.init.mw.Target
	 */
	mw.veForAll.Target = function ( node, content ) {
		var config = {};
		config.toolbarConfig = {};
		config.toolbarConfig.actions = true;
		// disable floatable behavior.
		config.toolbarConfig.floatable = false;

		this.$node = node;

		this.toolbarAutoHide = true;
		this.toolbarPosition = 'bottom';

		if ( node.hasClass( 'toolbarOnTop' ) ) {
			this.toolbarPosition = 'top';
			this.toolbarAutoHide = false;
			config.toolbarConfig.floatable = false;
		}

		mw.veForAll.Target.parent.call( this, config );

		// HACK: stop VE's education popups from appearing (T116643)
		this.dummyToolbar = true;

		this.init( content );
	};

	OO.inheritClass( mw.veForAll.Target, ve.init.mw.Target );

	mw.veForAll.Target.prototype.init = function ( content ) {
		this.convertToHtml( content );
	};

	// Static

	mw.veForAll.Target.static.name = 'veForAll';

	mw.veForAll.Target.static.toolbarGroups = ( function () {
		var toolbarConfig = JSON.parse( JSON.stringify( mw.config.get( 'VEForAllToolbarNormal' ) ) );
		return toolbarConfig.map( function ( x ) {
			if ( 'header' in x ) {
				x.header = OO.ui.deferMsg( x.header );
			}
			if ( 'title' in x ) {
				x.title = OO.ui.deferMsg( x.title );
			}
			return x;
		} );
	}() );

	mw.veForAll.Target.static.actionGroups = [
		{ include: [ 've4aSwitchEditor' ] }
	];

	// Allow pasting links
	mw.veForAll.Target.static.importRules = ve.copy( mw.veForAll.Target.static.importRules );
	mw.veForAll.Target.static.importRules.external.blacklist = OO.simpleArrayDifference(
		mw.veForAll.Target.static.importRules.external.blacklist,
		[ 'link/mwExternal' ]
	);

	// Static methods
	mw.veForAll.Target.static.setSwitchable = function ( switchable ) {
		// FIXME this isn't supposed to be a global state thing, it's supposed to be
		// variable per EditorWidget instance

		if ( switchable ) {
			mw.veForAll.Target.static.actionGroups = [ {
				type: 'list',
				icon: 'edit',
				title: mw.msg( 'visualeditor-mweditmode-tooltip' ),
				include: [ 'editModeVisual', 'editModeSource' ]
			} ];
		} else {
			mw.veForAll.Target.static.actionGroups = [];
		}
	};

	/**
	 * Add listener to show or hide toolbar if the area gets or loses focus.
	 */
	mw.veForAll.Target.prototype.setPulloutToolbar = function () {
		var target = this;
		this.getSurface().getView().on( 'blur', function () {
			target.updateToolbarVisibility();
		} );
		this.getSurface().getView().on( 'focus', function () {
			target.updateToolbarVisibility();
		} );
		this.updateToolbarVisibility();
	};

	/**
	 * Hide toolbar if area not focused (VE area or textarea ).
	 */
	mw.veForAll.Target.prototype.updateToolbarVisibility = function () {
		if ( !this.toolbarAutoHide ) {
			return;
		}
		if ( $( this.$node ).closest( '.ve-area-wrapper' ).find( ':focus' ).length > 0 ) {
			this.getToolbar().$element.show( 100 );
		} else {
			this.getToolbar().$element.hide( 100 );
		}
	};

	/**
	 * Create a new surface with VisualEditor, and add it to the target.
	 *
	 * @param {string} content text to initiate content, in html format
	 */
	mw.veForAll.Target.prototype.createWithHtmlContent = function ( content ) {
		var target = this,
			$focusedElement = $( ':focus' );

		this.addSurface(
			ve.dm.converter.getModelFromDom(
				ve.createDocumentFromHtml( content )
			)
		);
		// this.setSurface( surface );
		// this.$element.insertAfter( this.$node );

		// Append the target to the document
		$( this.$node ).before( this.$element );

		$( this.$node ).hide()
			.removeClass( 'oo-ui-texture-pending' ).prop( 'disabled', false );

		// When editor loses focus, update the field input.
		// this.getSurface().getView().on( 'blur', function ( data ) {
		// target.updateContent();
		// } );

		this.getSurface().on( 'switchEditor', function () {
			target.switchEditor();
		} );

		// show or hide toolbar when lose focus
		this.getSurface().getView().on( 'focus', function () {
			target.updateToolbarVisibility();
			target.$node.addClass( 've-for-all-waiting-for-update' );
		} );
		target.updateToolbarVisibility();
		this.setDir();
		// focus VE instance if textarea had focus
		if ( $focusedElement.length && this.$node.is( $focusedElement ) ) {
			this.getSurface().getView().focus();
		}

		// fix BUG on initialisation of toolbar position :
		target.getToolbar().onWindowResize();
		target.onToolbarResize();
		target.onContainerScroll();

		// emit ready-state event
		target.emit( 'editor-ready' );
	};

	/**
	 * Update the original textarea value with the content of VisualEditor
	 * surface (convert the content into wikitext)
	 *
	 * @return {Promise}
	 */
	mw.veForAll.Target.prototype.updateContent = function () {
		var surface = this.getSurface();
		if ( surface !== null &&
			!$( this.$node ).is( ':visible' ) &&
			this.$node.is( '.ve-for-all-waiting-for-update' ) ) {
			return this.convertToWikiText( surface.getHtml() );
		}
		return $().promise();
	};

	mw.veForAll.Target.prototype.getPageName = function () {
		if ( mw.config.get( 'wgPageFormsTargetName' ) ) {
			return mw.config.get( 'wgPageFormsTargetName' );
		} else {
			return mw.config.get( 'wgPageName' );
		}
	};

	mw.veForAll.Target.prototype.escapePipesInTables = function ( text ) {
		var lines = text.split( '\n' ), i, curLine, withinTable = false;

		// This algorithm will hopefully work for all cases except
		// when there are template calls within the table, and those
		// template calls include a pipe at the beginning of a line.
		// That is hopefully a rare case (a template call within a
		// table within a template call) that hopefully does not
		// justify creating more complex handling.
		for ( i = 0; i < lines.length; i++ ) {
			curLine = lines[ i ];
			// start of table is {|, but could be also escaped, like this: {{{!}}
			if ( curLine.indexOf( '{|' ) === 0 || curLine.indexOf( '{{{!}}' ) === 0 ) {
				withinTable = true;
				lines[ i ] = curLine.replace( /\|/g, '{{!}}' );
			} else if ( withinTable && curLine.indexOf( '|' ) === 0 ) {
				lines[ i ] = curLine.replace( /\|/g, '{{!}}' );
			}
			// Table caption case (`|+`). See https://www.mediawiki.org/wiki/Help:Tables
			if ( withinTable && curLine.indexOf( '|+' ) > -1 ) {
				lines[ i ] = curLine.replace( /\|\+/g, '{{!}}+' );
			}
			// colspan/rowspan case (`|rowspan=`/`|colspan=`). See https://www.mediawiki.org/wiki/Help:Tables
			if ( withinTable && ( curLine.indexOf( 'colspan' ) > -1 || curLine.indexOf( 'rowspan' ) > -1 ) ) {
				lines[ i ] = curLine.replace( /(colspan|rowspan)="(\d+?)"\s{0,}\|/, '$1="$2" {{!}}' ).replace( /^\s{0,}\|/, '{{!}} ' );
			}
			if ( curLine.indexOf( '|}' ) === 0 ) {
				withinTable = false;
			}
		}
		return lines.join( '\n' );
	};

	mw.veForAll.Target.prototype.convertToWikiText = function ( content ) {
		var target = this,
			oldFormat = 'html',
			newFormat = 'wikitext',
			apiCall,
			wikitextVal;

		$( this.$node )
			.prop( 'disabled', true )
			.addClass( 'oo-ui-texture-pending' );

		$( this.$element ).addClass( 'oo-ui-texture-pending' );

		apiCall = new mw.Api().post( {
			action: 'veforall-parsoid-utils',
			from: oldFormat,
			to: newFormat,
			content: content,
			title: this.getPageName()
		} ).then( function ( data ) {
			wikitextVal = data[ 'veforall-parsoid-utils' ].content;
			// Template fields can't contain wikitext tables - it
			// will confuse the parser - so if we're editing a
			// template field, replace every | with {{!}}.
			if ( target.$node.hasClass( 'vePartOfTemplate' ) ) {
				wikitextVal = target.escapePipesInTables( wikitextVal );
			}
			$( target.$node ).val( wikitextVal );
			$( target.$node ).trigger( 'change' );

			$( target.$node )
				.prop( 'disabled', false )
				.removeClass( 'oo-ui-texture-pending ve-for-all-waiting-for-update' );

			$( target.$element ).removeClass( 'oo-ui-texture-pending' );

		} ).fail( function () {
			// console.log( 'Error converting to wikitext' );
		} );

		return apiCall;

	};
	mw.veForAll.Target.prototype.setDir = function () {
		var view = this.surface.getView(),
			dir = $( 'body' ).is( '.rtl' ) ? 'rtl' : 'ltr';
		if ( view ) {
			view.getDocument().setDir( dir );
		}
	};

	mw.veForAll.Target.prototype.convertToHtml = function ( content, callback ) {
		var target = this,
			oldFormat = 'wikitext',
			newFormat = 'html';

		$( this.$node )
			.prop( 'disabled', true )
			.addClass( 'oo-ui-texture-pending' );

		new mw.Api().post( {
			action: 'veforall-parsoid-utils',
			from: oldFormat,
			to: newFormat,
			content: content,
			title: this.getPageName()
		} ).then( function ( data ) {
			var htmlContent = data[ 'veforall-parsoid-utils' ].content;
			// For some reason, certain wikitext (like section names
			// containing commas) sometimes leads to this strange
			// <span> tag within the returned HTML, which in turn
			// leads to the message "Sorry, this element can only be
			// edited in source mode for now" showing up in the
			// editor in those parts. So, just get rid of this tag.
			// @todo - it would be better, of course, to figure out
			// the underlying cause of this problem, and fix that.
			var regex = /<span [^>]* typeof="mw:FallbackId" [^>]*><\/span>/g;
			htmlContent = htmlContent.replace( regex, '' );
			target.createWithHtmlContent( htmlContent );
			$( target.$node )
				.prop( 'disabled', false )
				.removeClass( 'oo-ui-texture-pending' );

			if ( typeof callback === 'function' ) {
				callback( target );
			}

		} ).fail( function () {
			// console.log( 'Error converting to html' );
		} );
	};

	mw.veForAll.Target.prototype.switchEditor = function () {
		var textarea = this.$node,
			target = this;

		if ( $( textarea ).is( ':visible' ) ) {
			// Switch to VE editor

			this.clearSurfaces();
			// $( textarea ).hide();
			// $(this.getSurface().$element).show();
			this.convertToHtml( $( textarea ).val(), function ( target ) {
				target.getSurface().getView().focus();
			} );

			$( textarea ).parent().find( '.oo-ui-icon-eye' )
				.removeClass( 'oo-ui-icon-eye' )
				.addClass( 'oo-ui-icon-wikiText' );

			$( textarea ).parent().find( '.oo-ui-tool-link' )
				.attr( 'title', OO.ui.deferMsg( 'veforall-switch-editor-tool-title' ) );
		} else {
			// Switch to markup editor

			$( textarea ).parent().find( '.oo-ui-icon-wikiText' )
				.removeClass( 'oo-ui-icon-wikiText' )
				.addClass( 'oo-ui-icon-eye' );

			$( textarea ).parent().find( '.oo-ui-tool-link' )
				.attr( 'title', OO.ui.deferMsg( 'visualeditor-welcomedialog-switch-ve' ) );

			this.updateContent().then( function () {
				$( target.getSurface().$element ).hide();
				$( textarea ).show().trigger( 'focus' );
			} );
		}
	};

	/**
	 * Attach the toolbar to the DOM
	 * redefine attach Toolbar function to place on the bottom
	 *
	 */
	mw.veForAll.Target.prototype.attachToolbar = function () {
		var toolbar = this.getToolbar();

		if ( this.toolbarPosition === 'top' ) {
			toolbar.$element.insertBefore( toolbar.getSurface().$element );
		} else {
			$( this.$node ).after( toolbar.$element );
		}
		toolbar.initialize();
		this.getActions().initialize();
	};

	// Registration
	ve.init.mw.targetFactory.register( mw.veForAll.Target );

}( mediaWiki, OO, ve ) );
