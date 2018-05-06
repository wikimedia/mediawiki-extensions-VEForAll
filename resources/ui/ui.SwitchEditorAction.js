( function ( mw, OO, ve ) {

	/**
	 * Action to switch from VisualEditor to the Wikitext editing interface
	 * within Flow.
	 *
	 * @class
	 * @extends ve.ui.Action
	 *
	 * @constructor
	 * @param {ve.ui.Surface} surface Surface to act on
	 */
	mw.veForAll.ui.SwitchEditorAction = function ( surface ) {
		// Parent constructor
		ve.ui.Action.call( this, surface );
	};

	/* Inheritance */

	OO.inheritClass( mw.veForAll.ui.SwitchEditorAction, ve.ui.Action );

	/* Static Properties */

	/**
	 * Name of this action
	 *
	 * @static
	 * @property
	 */
	mw.veForAll.ui.SwitchEditorAction.static.name = 've4aSwitchEditor';

	/**
	 * List of allowed methods for the action.
	 *
	 * @static
	 * @property
	 */
	mw.veForAll.ui.SwitchEditorAction.static.methods = [ 'switch' ];

	/* Methods */

	/**
	 * Switch to wikitext editing.
	 *
	 * @method
	 */
	mw.veForAll.ui.SwitchEditorAction.prototype.switch = function () {
		this.surface.emit( 'switchEditor' );

	};

	ve.ui.actionFactory.register( mw.veForAll.ui.SwitchEditorAction );

}( mediaWiki, OO, ve ) );
