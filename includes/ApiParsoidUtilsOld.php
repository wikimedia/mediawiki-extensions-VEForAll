<?php

namespace VEForAll;

use ApiBase;
use ApiMessage;
use MediaWiki\Extension\VisualEditor\VisualEditorParsoidClient;
use MediaWiki\Extension\VisualEditor\VisualEditorParsoidClientFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParsoidVirtualRESTService;
use RequestContext;
use Title;

/**
 * Heavily based on the ApiParsoidUtils and Utils classes from the
 * StructuredDiscussions extension.
 */
class ApiParsoidUtilsOld extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$page = $this->getTitleOrPageId( $params );
		$content = $this->convert( $params['from'], $params['to'],
			$params['content'], $page->getTitle() );
		$result = [
			'format' => $params['to'],
			'content' => $content,
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'from' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [ 'html', 'wikitext' ],
			],
			'to' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [ 'html', 'wikitext' ],
			],
			'content' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'title' => null,
			'pageid' => [
				ApiBase::PARAM_ISMULTI => false,
				ApiBase::PARAM_TYPE => 'integer'
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			"action=veforall-parsoid-utils&from=wikitext&to=html&content=''blah''&title=Main_Page"
			=> 'apihelp-veforall-parsoid-utils-example-1',
		];
	}

	/**
	 * Convert from/to wikitext <=> html. Only these pairs are supported.
	 * html => wikitext requires Parsoid.
	 * @param string $from Format of content to convert: html|wikitext
	 * @param string $to Format to convert to: html|wikitext
	 * @param string $content
	 * @param Title $title
	 * @return string The converted content
	 */
	private function convert( $from, $to, $content, Title $title ) {
		if ( $from === $to || $content === '' ) {
			return $content;
		}

		$validValues = [ 'wikitext', 'html' ];

		if ( !in_array( $from, $validValues ) || !in_array( $to, $validValues ) ) {
			$this->dieCustomUsageMessage( 'veforall-api-error-unsupported-conversion',
				[ $from, $to ] );
			return null;
		}

		// MW 1.39 or 1.40
		$convertedContent = '';
		// We don't actually use this object, but it's useful in
		// determining which function to call.
		$vrsObject = $this->getVRSObject();
		if ( $vrsObject === null ) {
			if ( $from !== 'wikitext' || $to !== 'html' ) {
				$this->dieCustomUsageMessage(
					'veforall-api-error-unsupported-parser-conversion', [ $from, $to ] );
				return null;
			}
			$convertedContent = $this->parser( $content, $title );
		} else {
			$convertedContent = $this->parsoid( $from, $to, $content, $title );
		}

		return $convertedContent;
	}

	/**
	 * Convert from/to wikitext/html via Parsoid. This will assume Parsoid is
	 * installed and configured.
	 * @param string $from Format of content to convert: html|wikitext
	 * @param string $to Format to convert to: html|wikitext
	 * @param string $content
	 * @param Title $title
	 * @return string Returns the converted content
	 */
	private function parsoid( $from, $to, $content, Title $title ) {
		if ( class_exists( VisualEditorParsoidClient::class ) ) {
			// MW 1.39
			$client = VisualEditorParsoidClient::factory();
		} else {
			// MW 1.40
			$parsoidClientFactory = MediaWikiServices::getInstance()
				->getService( VisualEditorParsoidClientFactory::SERVICE_NAME );
			$client = $parsoidClientFactory->createParsoidClient( false );
		}

		if ( $from == 'wikitext' ) {
			$response = $client->transformWikitext(
				$title, $title->getPageLanguage(), $content, $bodyOnly = false, $oldid = null, $stash = false
			);
		} else {
			$response = $client->transformHtml(
				$title, $title->getPageLanguage(), $content, $oldid = null, $etag = null
			);
		}

		return $response['body'];
	}

	/**
	 * Convert from wikitext to html using core's parser.
	 * @param string $content The content to be converted from wikitext to html
	 * @param Title $title The title of the page containing the content
	 * @return string Returns the parsed string
	 */
	private function parser( $content, Title $title ) {
		$parser = MediaWikiServices::getInstance()->getParser();
		$options = new ParserOptions( $this->getUser() );
		$output = $parser->parse( $content, $title, $options );
		return $output->getText( [ 'enableSectionEditLinks' => false ] );
	}

	/**
	 * Die with a custom usage message.
	 * @param string $message_name the name of the custom message
	 * @param array $params parameters to the custom message
	 */
	private function dieCustomUsageMessage( $message_name, $params = [] ) {
		$errorMessage = $this->msg( $message_name, $params );
		LoggerFactory::getInstance( 'VEForAll' )->error( $errorMessage );
		$this->dieWithError( [ ApiMessage::create( $errorMessage ) ] );
	}

	/**
	 * Create the Parsoid Virtual REST Service object to be used in API calls.
	 * @return ParsoidVirtualRESTService|null
	 */
	private function getVRSObject() {
		// phpcs:ignore MediaWiki.Usage.ExtendClassUsage.FunctionConfigUsage
		global $wgVirtualRestConfig, $wgVisualEditorParsoidAutoConfig;

		// the params array to create the service object with
		$params = [];
		// the global virtual rest service config object, if any
		if ( isset( $wgVirtualRestConfig['modules'] ) &&
			isset( $wgVirtualRestConfig['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $wgVirtualRestConfig['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} elseif ( $wgVisualEditorParsoidAutoConfig ) {
			$params = $wgVirtualRestConfig['modules']['parsoid'] ?? [];
			$params['restbaseCompat'] = true;
			// forward cookies on private wikis
			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			$params['forwardCookies'] = !$permissionManager->isEveryoneAllowed( 'read' );
		} else {
			return null;
		}
		// merge the global and service-specific params
		if ( isset( $wgVirtualRestConfig['global'] ) ) {
			$params = array_merge( $wgVirtualRestConfig['global'], $params );
		}
		// set up cookie forwarding
		if ( $params['forwardCookies'] &&
			!MediaWikiServices::getInstance()
				->getPermissionManager()
				->isEveryoneAllowed( 'read' )
		) {
			$params['forwardCookies'] =
				RequestContext::getMain()->getRequest()->getHeader( 'Cookie' );
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object
		return new ParsoidVirtualRESTService( $params );
	}

}
