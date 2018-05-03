( function ( mw, OO, ve ) {
	'use strict';

	/**
	 * Tool for switching editors
	 *
	 * @class
	 * @extends ve.ui.Tool
	 *
	 * @constructor
	 * @param {OO.ui.ToolGroup} toolGroup
	 * @param {Object} [config] Configuration options
	 */

	mw.veForAll.ui.SwitchEditorTool = function ( toolGroup, config ) {
		mw.veForAll.ui.SwitchEditorTool.parent.call( this, toolGroup, config );
	};

	OO.inheritClass( mw.veForAll.ui.SwitchEditorTool, ve.ui.Tool );

	// Static
	mw.veForAll.ui.SwitchEditorTool.static.commandName = 've4aSwitchEditor';
	mw.veForAll.ui.SwitchEditorTool.static.name = 've4aSwitchEditor';
	mw.veForAll.ui.SwitchEditorTool.static.icon = 'wikiText';
	mw.veForAll.ui.SwitchEditorTool.static.title = OO.ui.deferMsg( 'veforall-switch-editor-tool-title' );

	ve.ui.toolFactory.register( mw.veForAll.ui.SwitchEditorTool );
}( mediaWiki, OO, ve ) );
