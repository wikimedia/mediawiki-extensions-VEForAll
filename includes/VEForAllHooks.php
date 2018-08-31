<?php

namespace VEForAll;

class VEForAllHooks {

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Initializes variables to be passed to JavaScript.
	 *
	 * @param OutputPage $output OutputPage object
	 * @param Skin $skin Skin object that will be used to generate the page
	 */
	public static function onBeforePageDisplay( $output, $skin ) {
		$user = $output->getUser();
		$vars = [];
		$vars['VisualEditorEnable'] = $user->getOption( 'visualeditor-enable' );
		$output->addJSConfigVars( 'VEForAll', $vars );
	}
}
