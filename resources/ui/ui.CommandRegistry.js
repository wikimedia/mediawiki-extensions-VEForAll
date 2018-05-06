
mw.veForAll = mw.veForAll || {};
mw.veForAll.ui = mw.veForAll.ui || {};

( function ( ve ) {
	'use strict';
	ve.ui.commandRegistry.register(
			new ve.ui.Command(
					've4aSwitchEditor',
					've4aSwitchEditor',
					'switch', // method to call on action
					{ args: [] } // arguments to pass to action
			)
			);
}( ve ) );
