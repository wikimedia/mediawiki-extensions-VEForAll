<?php

namespace VEForAll;

use ApiParsoidTrait;
use FatalError;
use Hooks;
use MWException;
use OutputPage;
use Skin;

class VEForAllHooks {

	public static function registerClasses() {
		global $wgAutoloadClasses, $wgAPIModules;
		if ( class_exists( ApiParsoidTrait::class ) ) {
			// MW 1.35.? +
			$wgAutoloadClasses['VEForAll\\ApiParsoidUtils'] = __DIR__ . '/ApiParsoidUtils.php';
			$wgAPIModules['veforall-parsoid-utils'] = 'VEForAll\\ApiParsoidUtils';
		} else {
			$wgAutoloadClasses['VEForAll\\ApiParsoidUtilsOld'] = __DIR__ . '/ApiParsoidUtilsOld.php';
			$wgAPIModules['veforall-parsoid-utils'] = 'VEForAll\\ApiParsoidUtilsOld';
		}
	}

	/** @var array[] */
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

}
