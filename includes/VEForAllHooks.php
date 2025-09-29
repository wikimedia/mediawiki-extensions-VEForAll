<?php

namespace VEForAll;

use ExtensionRegistry;
use FatalError;
use MediaWiki\MediaWikiServices;
use MWException;
use OutputPage;
use Skin;

class VEForAllHooks {

	public static function registerClasses() {
		global $wgAutoloadClasses, $wgAPIModules;
		if ( class_exists( 'MediaWiki\Parser\Parsoid\ParsoidParserFactory' ) ) {
			// MW 1.41+
			$wgAutoloadClasses['VEForAll\\ApiParsoidUtils'] = __DIR__ . '/ApiParsoidUtils.php';
			$wgAPIModules['veforall-parsoid-utils'] = 'VEForAll\\ApiParsoidUtils';
		} else {
			// MW 1.39-1.40
			$wgAutoloadClasses['VEForAll\\ApiParsoidUtilsOld'] = __DIR__ . '/ApiParsoidUtilsOld.php';
			$wgAPIModules['veforall-parsoid-utils'] = 'VEForAll\\ApiParsoidUtilsOld';
		}
	}

	/** @var array[] */
	private static $defaultConfig = [
		'normal' => [
			[
				'name' => 'paragraph-format',
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
				'name' => 'text-style',
				'header' => 'visualeditor-toolbar-text-style',
				'title' => 'visualeditor-toolbar-style-tooltip',
				'include' => [
					'bold',
					'italic',
					'moreTextStyle'
				]
			],
			[
				'name' => 'links',
				'include' => [
					'link'
				]
			],
			[
				'name' => 'structure',
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
				'name' => 'insert',
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
				'name' => 'paragraph-format',
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
				'name' => 'text-style',
				'header' => 'visualeditor-toolbar-text-style',
				'title' => 'visualeditor-toolbar-style-tooltip',
				'include' => [
					'bold',
					'italic',
					'moreTextStyle'
				]
			],
			[
				'name' => 'links',
				'include' => [
					'link'
				]
			],
			[
				'name' => 'structure',
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
				'name' => 'insert',
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
	 *
	 * @param OutputPage $output OutputPage object
	 * @param Skin $skin Skin object that will be used to generate the page
	 */
	public static function onBeforePageDisplay( $output, $skin ) {
		$services = MediaWikiServices::getInstance();
		if ( !(
			ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' ) &&
			$services->getService( 'MobileFrontend.Context' )
				->shouldDisplayMobileView()
		) ) {
			$output->addModules( [
				'ext.veforall.core.desktop'
			] );
		}
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
		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'VEForAllToolbarConfig' . ucfirst( $type ), [ &self::$defaultConfig[ $type ] ] );
		return array_values( self::$defaultConfig[ $type ] );
	}

}
