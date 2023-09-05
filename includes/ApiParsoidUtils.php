<?php

namespace VEForAll;

use ApiBase;
use ApiMessage;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MWException;
use ParserOptions;
use WikitextContent;

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

		if ( $from == 'wikitext' ) {
			return $this->wikitextToHTML( $content, $title );
		} else {
			return $this->htmlToWikitext( $content, $title );
		}
	}

	/**
	 * @param string $wikitext
	 * @param Title $title
	 *
	 * @return string The converted wikitext to HTML
	 *
	 * Copied from StructuredDiscussions' Utils::wikitextToHTML().
	 */
	private static function wikitextToHTML( string $wikitext, Title $title ) {
		$parserOptions = ParserOptions::newFromAnon();
		$parserOptions->setRenderReason( __METHOD__ );

		$parserFactory = MediaWikiServices::getInstance()->getParsoidParserFactory()->create();
		$parserOutput = $parserFactory->parse( $wikitext, $title, $parserOptions );
		return $parserOutput->getRawText();
	}

	/**
	 * @param string $html
	 * @param Title $title
	 *
	 * @return string The converted HTML to wikitext
	 * @throws MWException When the conversion is unsupported
	 *
	 * Based on StructuredDiscussions' Utils::htmlToWikitext().
	 */
	private static function htmlToWikitext( string $html, Title $title ) {
		$transform = MediaWikiServices::getInstance()->getHtmlTransformFactory()
			->getHtmlToContentTransform( $html, $title );

		$transform->setOptions( [
			'contentmodel' => CONTENT_MODEL_WIKITEXT,
			'offsetType' => 'byte'
		] );

		/** @var TextContent $content */
		$content = $transform->htmlToContent();

		if ( !$content instanceof WikitextContent ) {
			throw new MWException( 'Conversion to wikitext failed.' );
		}

		return trim( $content->getTextForSearchIndex() );
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

}
