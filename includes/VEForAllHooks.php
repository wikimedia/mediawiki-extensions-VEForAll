<?php

namespace VEForAll;

use FatalError;
use Hooks;
use MWException;
use OutputPage;
use ResourceLoader;
use Skin;

class VEForAllHooks {

	private static $defaultConfig = [
		'normal' => [
			[
				'header' => 'visualeditor-toolbar-paragraph-format',
				'title' => 'visualeditor-toolbar-format-tooltip',
				'type' => 'menu',
				'include' => [
					'group' => 'format'
				],
				'promote' => [
					'paragraph'
				],
				'demote' => [
					'preformatted',
					'blockquote'
				]
			],
			[
				'header' => 'visualeditor-toolbar-text-style',
				'title' => 'visualeditor-toolbar-style-tooltip',
				'include' => [
					'bold',
					'italic',
					'moreTextStyle'
				]
			],
			[
				'include' => [
					'link'
				]
			],
			[
				'header' => 'visualeditor-toolbar-structure',
				'title' => 'visualeditor-toolbar-structure',
				'type' => 'list',
				'icon' => 'listBullet',
				'include' => [
					'group' => 'structure'
				],
				'demote' => [
					'outdent',
					'indent'
				]
			],
			[
				'header' => 'visualeditor-toolbar-insert',
				'title' => 'visualeditor-toolbar-insert',
				'type' => 'list',
				'icon' => 'add',
				'label' => '',
				'include' => [
					'insertTable',
					'specialCharacter',
					'warningblock',
					'preformatted',
					'infoblock',
					'ideablock',
					'dontblock',
					'pinblock',
				]
			]
		],
		'wide' => [
			[
				'header' => 'visualeditor-toolbar-paragraph-format',
				'title' => 'visualeditor-toolbar-format-tooltip',
				'type' => 'menu',
				'include' => [
					'group' => 'format'
				],
				'promote' => [
					'paragraph'
				],
				'demote' => [
					'preformatted',
					'blockquote'
				]
			],
			[
				'header' => 'visualeditor-toolbar-text-style',
				'title' => 'visualeditor-toolbar-style-tooltip',
				'include' => [
					'bold',
					'italic',
					'moreTextStyle'
				]
			],
			[
				'include' => [
					'link'
				]
			],
			[
				'header' => 'visualeditor-toolbar-structure',
				'title' => 'visualeditor-toolbar-structure',
				'type' => 'list',
				'icon' => 'listBullet',
				'include' => [
					'group' => 'structure'
				],
				'demote' => [
					'outdent',
					'indent'
				]
			],
			[
				'header' => 'visualeditor-toolbar-insert',
				'title' => 'visualeditor-toolbar-insert',
				'type' => 'list',
				'icon' => 'add',
				'label' => '',
				'include' => [
					'media',
					'insertTable',
					'specialCharacter',
					'warningblock',
					'preformatted',
					'infoblock',
					'ideablock',
					'dontblock',
					'pinblock',
				]
			]
		]
	];

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

	/**
	 * @param array &$vars
	 * @param OutputPage $out
	 *
	 * @throws FatalError
	 * @throws MWException
	 */
	public static function onMakeGlobalVariablesScript( &$vars, $out ) {
		$vars[ 'VEForAllToolbarNormal' ] = self::getVeToolbarConfig( 'normal' );
		$vars[ 'VEForAllToolbarWide' ] = self::getVeToolbarConfig( 'wide' );
	}

	/**
	 * @param string $type
	 *
	 * @return array
	 * @throws FatalError
	 * @throws MWException
	 */
	public static function getVeToolbarConfig( $type = 'normal' ) {
		Hooks::run( 'VEForAllToolbarConfig' . ucfirst( $type ), [ &self::$defaultConfig[ $type ] ] );
		return array_values( self::$defaultConfig[ $type ] );
	}

	/**
	 * ResourceLoaderRegisterModules hook handler
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 * @param ResourceLoader $resourceLoader The ResourceLoader object
	 * @throws MWException
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		global $wgVersion;

		$dir = dirname( __DIR__ ) . DIRECTORY_SEPARATOR;

		$info = [
			'localBasePath' => $dir . 'resources',
			'remoteExtPath' => 'VEForAll/resources',
			'scripts' => [
				'ui/ui.CommandRegistry.js',
				'ui/ui.SwitchEditorAction.js',
				'ui/ui.SwitchEditorTool.js',
				'ext.veforall.target.js',
				'ext.veforall.targetwide.js',
				'ext.veforall.editor.js'
			],
			'dependencies' => [
				'ext.visualEditor.core',
				'ext.visualEditor.core.desktop',
				'ext.visualEditor.data',
				'ext.visualEditor.icons',
				'ext.visualEditor.mediawiki',
				'ext.visualEditor.desktopTarget',
				'ext.visualEditor.mwextensions.desktop',
				'ext.visualEditor.mwimage',
				'ext.visualEditor.mwlink',
				'ext.visualEditor.mwtransclusion',
				'oojs-ui.styles.icons-editing-advanced'
			]
		];

		if ( version_compare( $wgVersion, '1.32', '<' ) ) {
			// Needed for backward compatibility with MediaWiki 1.31 and older.
			$info['scripts'][] = 've/ve.init.sa.Target.js';
		}

		$resourceLoader->register( 'ext.veforall.core', $info );
	}

}
