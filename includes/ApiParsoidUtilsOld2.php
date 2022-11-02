<?php

namespace VEForAll;

use ApiBase;
use ApiMessage;
use ApiParsoidTrait;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParsoidVirtualRESTService;
use Title;
use VirtualRESTServiceClient;

/**
 * Heavily based on the ApiParsoidUtils and Utils classes from the
 * StructuredDiscussions extension.
 */
class ApiParsoidUtilsOld2 extends ApiBase {
	use ApiParsoidTrait;

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

		$convertedContent = '';
		$vrsObject = $this->getVRSObject();
		if ( $vrsObject === null ) {
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

		$serviceClient = new VirtualRESTServiceClient(
			MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient() );
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
		$parser = MediaWikiServices::getInstance()->getParser();
		$options = new ParserOptions( $this->getUser() );
		$output = $parser->parse( $content, $title, $options );
		return $output->getText( [ 'enableSectionEditLinks' => false ] );
	}

	/**
	 * Die with a custom usage message.
	 * @param string $message_name the name of the custom message
	 * @param params $params parameters to the custom message
	 */
	private function dieCustomUsageMessage( $message_name, $params = [] ) {
		// phpcs:ignore MediaWiki.Usage.ExtendClassUsage.FunctionVarUsage
		$errorMessage = wfMessage( $message_name, $params );
		LoggerFactory::getInstance( 'VEForAll' )->error( $errorMessage );
		$this->dieWithError(
			[
				ApiMessage::create( $errorMessage )
			]
		);
	}
}
