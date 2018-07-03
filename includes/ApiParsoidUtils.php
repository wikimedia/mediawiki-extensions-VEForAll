<?php

namespace VEForAll;

use ApiBase;
use MultiHttpClient;
use RequestContext;
use Title;
use User;
use ParserOptions;
use VirtualRESTServiceClient;
use ApiMessage;
use ParsoidVirtualRESTService;
use MediaWiki\Logger\LoggerFactory;

/**
 * Heavily based on the ApiParsoidUtils and Utils classes from the
 * StructuredDiscussions extension.
 */
class ApiParsoidUtils extends ApiBase {

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

		$convertedContent = '';
		$vrsObject = $this->getVRSObject();
		if ( is_null( $vrsObject ) ) {
			if ( $from !== 'wikitext' || $to !== 'html' ) {
				$this->dieCustomUsageMessage(
					'veforall-api-error-unsupported-parser-conversion', [ $from, $to ] );
				return null;
			}
			$convertedContent = $this->parser( $content, $title );
		} else {
			$convertedContent = $this->parsoid( $from, $to, $content, $title,
				$vrsObject );
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
	 * @param ParsoidVirtualRESTService $vrsObject
	 * @return string Returns the converted content
	 */
	private function parsoid( $from, $to, $content, Title $title,
		$vrsObject ) {
		global $wgVersion;

		$serviceClient = new VirtualRESTServiceClient( new MultiHttpClient( [] ) );
		$serviceClient->mount( '/restbase/', $vrsObject );

		$prefixedDbTitle = $title->getPrefixedDBkey();
		$params = [
			$from => $content,
			'body_only' => 'true',
		];
		if ( $from === 'html' ) {
			$params['scrub_wikitext'] = 'true';
		}
		$url = '/restbase/local/v1/transform/' . $from . '/to/' . $to . '/' .
			urlencode( $prefixedDbTitle );
		$request = [
			'method' => 'POST',
			'url' => $url,
			'body' => $params,
			'headers' => [
				'Accept' => 'text/html; charset=utf-8;',
				'User-Agent' => "VEForAll-MediaWiki/$wgVersion",
			],
		];
		$response = $serviceClient->run( $request );
		if ( $response['code'] !== 200 ) {
			if ( $response['error'] !== '' ) {
				$statusMsg = $response['error'];
			} else {
				$statusMsg = $response['code'];
			}
			$vrsInfo = $serviceClient->getMountAndService( '/restbase/' );
			$serviceName = $vrsInfo[1] ? $vrsInfo[1]->getName() : 'VRS service';
			$this->dieCustomUsageMessage( 'veforall-api-error-parsoid-error',
				[ $serviceName, $from, $to, $prefixedDbTitle, $statusMsg ] );
			return null;
		}

		$content = $response['body'];
		// HACK remove trailing newline inserted by Parsoid (T106925)
		if ( $to === 'wikitext' ) {
			$content = preg_replace( '/\\n$/', '', $content );
		}
		return $content;
	}

	/**
	 * Convert from wikitext to html using core's parser.
	 * @param string $content The content to be converted from wikitext to html
	 * @param Title $title The title of the page containing the content
	 * @return string Returns the parsed string
	 */
	private function parser( $content, Title $title ) {
		global $wgParser;
		$options = new ParserOptions;
		$options->setTidy( true );
		$output = $wgParser->parse( $content, $title, $options );
		return $output->getText( [ 'enableSectionEditLinks' => false ] );
	}

	/**
	 * Create the Parsoid Virtual REST Service object to be used in API calls.
	 * @return ParsoidVirtualRESTService|null
	 */
	private function getVRSObject() {
		global $wgVirtualRestConfig;

		// the params array to create the service object with
		$params = [];
		// the global virtual rest service config object, if any
		if ( isset( $wgVirtualRestConfig['modules'] ) &&
			isset( $wgVirtualRestConfig['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $wgVirtualRestConfig['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} else {
			return null;
		}
		// merge the global and service-specific params
		if ( isset( $wgVirtualRestConfig['global'] ) ) {
			$params = array_merge( $wgVirtualRestConfig['global'], $params );
		}
		// set up cookie forwarding
		if ( $params['forwardCookies'] && !User::isEveryoneAllowed( 'read' ) ) {
			$params['forwardCookies'] =
				RequestContext::getMain()->getRequest()->getHeader( 'Cookie' );
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object
		return new ParsoidVirtualRESTService( $params );
	}

	/**
	 * Die with a custom usage message.
	 * @param string $message_name the name of the custom message
	 * @param params $params parameters to the custom message
	 */
	private function dieCustomUsageMessage( $message_name, $params = [] ) {
		$errorMessage = wfMessage( $message_name, $params );
		LoggerFactory::getInstance( 'VEForAll' )->error( $errorMessage );
		$this->dieWithError(
			[
				ApiMessage::create( $errorMessage )
			]
		);
	}
}
