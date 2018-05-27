<?php

namespace VEForAll\Conversion;

use FauxResponse;
use VEForAll\Container;
use VEForAll\Exception\NoParserException;
use VEForAll\Exception\WikitextException;
use Language;
use Linker;
use MultiHttpClient;
use RequestContext;
use Sanitizer;
use Title;
use User;
use VirtualRESTServiceClient;

/**
 * Heavily based on the Utils class from the Flow extension.
 */
abstract class Utils {
	/**
	 * @var VirtualRESTServiceClient
	 */
	protected static $serviceClient = null;

	/**
	 * @var \VirtualRESTService
	 */
	protected static $vrsObject = null;

	/**
	 * Convert from/to wikitext <=> html or topic-title-wikitext => topic-title-html.
	 * Only these pairs are supported.  html => wikitext requires Parsoid, and
	 * topic-title-html => topic-title-wikitext is not supported.
	 *
	 * @param string $from Format of content to convert: html|wikitext|topic-title-wikitext
	 * @param string $to Format to convert to: html|wikitext|topic-title-html
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws WikitextException When the requested conversion is unsupported
	 * @throws NoParserException When the conversion fails
	 */
	public static function convert( $from, $to, $content, Title $title ) {
		if ( $from === $to || $content === '' ) {
			return $content;
		}

		if ( $from === 'wt' ) {
			$from = 'wikitext';
		}

		if ( $from === 'wikitext' || $from === 'html' ) {
			if ( $to === 'wikitext' || $to === 'html' ) {
				if ( self::isParsoidConfigured() ) {
					return self::parsoid( $from, $to, $content, $title );
				} else {
					return self::parser( $from, $to, $content, $title );
				}
			} else {
				throw new WikitextException( "Conversion from '$from' to '$to'" .
					"was requested, but this is not supported." );
			}
		} else {
			return self::commentParser( $from, $to, $content );
		}
	}

	/**
	 * Basic conversion of html to plaintext for use in recent changes, history,
	 * and other places where a roundtrip is undesired.
	 *
	 * @param string $html
	 * @param int|null $truncateLength Maximum length (including ellipses) or null for whole string.
	 * @param Language|null $lang Language to use for truncation.  Defaults to $wgLang
	 * @return string plaintext
	 */
	public static function htmlToPlaintext( $html, $truncateLength = null, Language $lang = null ) {
		/** @var Language $wgLang */
		global $wgLang;

		$plain = trim( Sanitizer::stripAllTags( $html ) );

		if ( $truncateLength === null ) {
			return $plain;
		} else {
			$lang = $lang ?: $wgLang;
			return $lang->truncate( $plain, $truncateLength );
		}
	}

	/**
	 * Convert from/to wikitext/html via Parsoid/RESTBase.
	 *
	 * This will assume Parsoid/RESTBase is installed and configured.
	 *
	 * @param string $from Format of content to convert: html|wikitext
	 * @param string $to Format to convert to: html|wikitext
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws NoParserException When Parsoid/RESTBase operation fails
	 * @throws WikitextException When conversion is unsupported
	 */
	protected static function parsoid( $from, $to, $content, Title $title ) {
		global $wgVersion;

		$serviceClient = self::getServiceClient();

		if ( $from !== 'html' && $from !== 'wikitext' ) {
			throw new WikitextException( 'Unknown source format: ' . $from, 'process-wikitext' );
		}

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
				'Accept' => 'text/html; charset=utf-8; ' .
					'profile="https://www.mediawiki.org/wiki/Specs/HTML/1.2.1"',
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
			$msg = "Request to " . $serviceName . " for \"$from\" to \"$to\" conversion of " .
				"content connected to title \"$prefixedDbTitle\" failed: $statusMsg";
			Container::get( 'default_logger' )->error(
				'Request to {service} for "{sourceFormat}" to "{targetFormat}" conversion of ' .
				'content connected to title "{title}" failed. ' .
				'Code: {code}, Reason: "{reason}", Body: "{body}", Error: "{error}"',
				[
				'service' => $serviceName,
				'sourceFormat' => $from,
				'targetFormat' => $to,
				'title' => $prefixedDbTitle,
				'code' => $response['code'],
				'reason' => $response['reason'],
				'error' => $response['error'], // This is sometimes/always empty string
				'headers' => $response['headers'],
				'body' => $response['body'],
				'response' => $response,
				]
			);
			throw new NoParserException( $msg, 'process-wikitext' );
		}

		$content = $response['body'];
		// HACK remove trailing newline inserted by Parsoid (T106925)
		if ( $to === 'wikitext' ) {
			$content = preg_replace( '/\\n$/', '', $content );
		}
		return $content;
	}

	/**
	 * Convert from/to topic-title-wikitext/topic-title-html using Linker::formatLinksInComment
	 *
	 * @param string $from Format of content to convert: topic-title-wikitext
	 * @param string $to Format of content to convert to: topic-title-html
	 * @param string $content Content to convert, in topic-title-wikitext format.
	 * @return string $content in HTML
	 * @throws WikitextException
	 */
	protected static function commentParser( $from, $to, $content ) {
		if (
			$from !== 'topic-title-wikitext' ||
			( $to !== 'topic-title-html' && $to !== 'topic-title-plaintext' )
		) {
			throw new WikitextException(
				"Conversion from '$from' to '$to' was requested, but this is not supported."
			);
		}

		$html = Linker::formatLinksInComment( Sanitizer::escapeHtmlAllowEntities( $content ) );
		if ( $to === 'topic-title-plaintext' ) {
			return self::htmlToPlaintext( $html );
		} else {
			return $html;
		}
	}

	/**
	 * Convert from/to wikitext/html using Parser.
	 *
	 * This only supports wikitext to HTML.
	 *
	 * @param string $from Format of content to convert: wikitext
	 * @param string $to Format to convert to: html
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws WikitextException When the conversion is unsupported
	 */
	protected static function parser( $from, $to, $content, Title $title ) {
		if ( $from !== 'wikitext' || $to !== 'html' ) {
			throw new WikitextException(
				"Conversion from '$from' to '$to' was requested, but core's Parser only " .
				"supports 'wikitext' to 'html' conversion",
				'process-wikitext'
			);
		}

		global $wgParser;

		$options = new \ParserOptions;
		$options->setTidy( true );

		$output = $wgParser->parse( $content, $title, $options );
		return $output->getText( [ 'enableSectionEditLinks' => false ] );
	}

	/**
	 * Check to see whether a Parsoid or RESTBase service is configured.
	 *
	 * @return bool
	 */
	public static function isParsoidConfigured() {
		try {
			self::getVRSObject();
			return true;
		} catch ( NoParserException $e ) {
			return false;
		}
	}

	/**
	 * Returns Flow's Virtual REST Service for Parsoid/RESTBase.
	 * The Parsoid/RESTBase service will be mounted at /restbase/
	 * and will answer RESTBase v1 API requests.
	 *
	 * @return VirtualRESTServiceClient
	 * @throws NoParserException When Parsoid/RESTBase is unconfigured
	 */
	protected static function getServiceClient() {
		if ( self::$serviceClient === null ) {
			$sc = new VirtualRESTServiceClient( new MultiHttpClient( [] ) );
			$sc->mount( '/restbase/', self::getVRSObject() );
			self::$serviceClient = $sc;
		}
		return self::$serviceClient;
	}

	/**
	 * @return \VirtualRESTService
	 * @throws NoParserException
	 */
	private static function getVRSObject() {
		if ( !self::$vrsObject ) {
			self::$vrsObject = self::makeVRSObject();
		}
		return self::$vrsObject;
	}

	/**
	 * Creates the Virtual REST Service object to be used in Flow's
	 * API calls.  The method determines whether to instantiate a
	 * ParsoidVirtualRESTService or a RestbaseVirtualRESTService
	 * object based on configuration directives: if
	 * `$wgVirtualRestConfig['modules']['restbase']` is defined,
	 * RESTBase is chosen; otherwise Parsoid is used.
	 * For backwards compatibility, $wgFlowParsoid* variables are used
	 * to specify a Parsoid configuration as a fall back.
	 *
	 * @return \VirtualRESTService the VirtualRESTService object to use
	 * @throws NoParserException When Parsoid/RESTBase is not configured
	 */
	private static function makeVRSObject() {
		global $wgVirtualRestConfig, $wgFlowParsoidURL, $wgFlowParsoidPrefix,
		$wgFlowParsoidTimeout, $wgFlowParsoidForwardCookies,
		$wgFlowParsoidHTTPProxy;

		// the params array to create the service object with
		$params = [];
		// the VRS class to use; defaults to Parsoid
		$class = 'ParsoidVirtualRESTService';
		// the global virtual rest service config object, if any
		$vrs = $wgVirtualRestConfig;
		// HACK: don't use RESTbase because it'll drop data-parsoid, see T115236
		/* if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['restbase'] ) ) {
		  // if restbase is available, use it
		  $params = $vrs['modules']['restbase'];
		  $params['parsoidCompat'] = false; // backward compatibility
		  $class = 'RestbaseVirtualRESTService';
		  } else
		 */
		if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $vrs['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} else {
			// no global modules defined, fall back to old defaults
			if ( !$wgFlowParsoidURL ) {
				throw new NoParserException( 'Flow Parsoid configuration is unavailable', 'process-wikitext' );
			}
			$params = [
				'URL' => $wgFlowParsoidURL,
				'prefix' => $wgFlowParsoidPrefix,
				'timeout' => $wgFlowParsoidTimeout,
				'HTTPProxy' => $wgFlowParsoidHTTPProxy,
				'forwardCookies' => $wgFlowParsoidForwardCookies
			];
		}
		// merge the global and service-specific params
		if ( isset( $vrs['global'] ) ) {
			$params = array_merge( $vrs['global'], $params );
		}
		// set up cookie forwarding
		if ( $params['forwardCookies'] && !User::isEveryoneAllowed( 'read' ) ) {
			if ( PHP_SAPI === 'cli' ) {
				// From the command line we need to generate a cookie
				$params['forwardCookies'] = self::generateForwardedCookieForCli();
			} else {
				$params['forwardCookies'] = RequestContext::getMain()->getRequest()->getHeader( 'Cookie' );
			}
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object and return it
		return new $class( $params );
	}

	// @todo move into FauxRequest
	public static function generateForwardedCookieForCli() {
		global $wgCookiePrefix;

		$user = Container::get( 'occupation_controller' )->getTalkpageManager();
		// This takes a request object, but doesnt set the cookies against it.
		// patch at https://gerrit.wikimedia.org/r/177403
		$user->setCookies( null, null, /* rememberMe */ true );
		$response = RequestContext::getMain()->getRequest()->response();
		if ( !$response instanceof FauxResponse ) {
			throw new MWException( 'Expected a FauxResponse in CLI environment' );
		}
		$cookies = $response->getCookies();

		// now we need to convert the array into the cookie format of
		// foo=bar; baz=bang
		$output = [];
		foreach ( $cookies as $key => $value ) {
			$output[] = "$wgCookiePrefix$key={$value['value']}";
		}

		return implode( '; ', $output );
	}
}
