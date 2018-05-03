<?php

namespace VEForAll\Exception;

use MWException;
use OutputPage;
use RequestContext;

/**
 * Category: Parsoid
 */
class NoParserException extends MWException {
	protected function getErrorCodeList() {
		return [ 'process-wikitext' ];
	}
}

/**
 * Category: wikitext/html conversion exception
 */
class WikitextException extends MWException {
	protected function getErrorCodeList() {
		return [ 'process-wikitext' ];
	}
}
