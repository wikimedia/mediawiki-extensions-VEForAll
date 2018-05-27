( function ( mw, OO, ve ) {
	'use strict';
	mw.veForAll = mw.veForAll || {
		ui: {}
	};

	/**
	 * Inherits from the stand-alone target.
	 *
	 * @class
	 * @extends ve.init.sa.Target
	 */
	mw.veForAll.Targetwide = function ( node, content ) {
		mw.veForAll.Targetwide.parent.call( this, node, content );
	};

	OO.inheritClass( mw.veForAll.Targetwide, mw.veForAll.Target );

	mw.veForAll.Targetwide.static.name = 'veForAllWide';

	mw.veForAll.Targetwide.static.toolbarGroups = [
		// History
		// { include: [ 'undo', 'redo' ] },
		// Format
		{
			header: OO.ui.deferMsg( 'visualeditor-toolbar-paragraph-format' ),
			title: OO.ui.deferMsg( 'visualeditor-toolbar-format-tooltip' ),
			type: 'menu',
			include: [ { group: 'format' } ],
			promote: [ 'paragraph' ],
			demote: [ 'preformatted', 'blockquote' ]
		},
		// Text style
		{
			header: OO.ui.deferMsg( 'visualeditor-toolbar-text-style' ),
			title: OO.ui.deferMsg( 'visualeditor-toolbar-style-tooltip' ),
			include: [ 'bold', 'italic', 'moreTextStyle' ]
		},
		// Link
		{ include: [ 'link' ] },
		// Structure
		{
			header: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
			title: OO.ui.deferMsg( 'visualeditor-toolbar-structure' ),
			type: 'list',
			icon: 'listBullet',
			include: [ { group: 'structure' } ],
			demote: [ 'outdent', 'indent' ]
		},
		// Insert
		{
			header: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
			title: OO.ui.deferMsg( 'visualeditor-toolbar-insert' ),
			type: 'list',
			icon: 'add',
			label: '',
			include: [ 'media', 'insertTable', 'specialCharacter', 'warningblock', 'preformatted', 'infoblock', 'ideablock', 'dontblock', 'pinblock' ]
		}
		// Special character toolbar
		// { include: [ 'specialCharacter' ] }
	];

	ve.init.mw.targetFactory.register( mw.veForAll.Targetwide );

}( mediaWiki, OO, ve ) );
