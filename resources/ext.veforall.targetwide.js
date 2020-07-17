/* globals mediaWiki, OO, ve */

( function ( mw, OO, ve ) {
	'use strict';
	mw.veForAll = mw.veForAll || {
		ui: {}
	};

	/**
	 * Inherits from the stand-alone target.
	 *
	 * @param node
	 * @param content
	 * @class
	 * @extends ve.init.sa.Target
	 */
	mw.veForAll.Targetwide = function ( node, content ) {
		mw.veForAll.Targetwide.parent.call( this, node, content );
	};

	OO.inheritClass( mw.veForAll.Targetwide, mw.veForAll.Target );

	mw.veForAll.Targetwide.static.name = 'veForAllWide';

	mw.veForAll.Targetwide.static.toolbarGroups = ( function () {
		var toolbarConfig = JSON.parse( JSON.stringify( mw.config.get( 'VEForAllToolbarWide' ) ) );
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

	ve.init.mw.targetFactory.register( mw.veForAll.Targetwide );

}( mediaWiki, OO, ve ) );
