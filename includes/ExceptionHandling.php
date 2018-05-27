<?php

namespace VEForAll\Exception;

use MWException;

/**
 * Category: Parsoid
 *
 * Heavily based on the NoParserException class from the
 * StructuredDiscussions extension.
 */
class NoParserException extends MWException {
	protected function getErrorCodeList() {
		return [ 'process-wikitext' ];
	}
}

/**
 * Category: wikitext/html conversion exception
 *
 * Heavily based on the WikitextException class from the
 * StructuredDiscussions extension.
 */
class WikitextException extends MWException {
	protected function getErrorCodeList() {
		return [ 'process-wikitext' ];
	}
}
