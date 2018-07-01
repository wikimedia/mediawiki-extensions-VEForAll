<?php

namespace VEForAll\Conversion;

use VEForAll\Exception\ParsoidException;
use VEForAll\Exception\ParameterException;
use MultiHttpClient;
use RequestContext;
use Title;
use User;
use VirtualRESTServiceClient;
use MediaWiki\Logger\LoggerFactory;

/**
 * Heavily based on the Utils class from the StructuredDiscussions extension.
 */
abstract class Utils {
	/**
	 * @var \VirtualRESTService
	 */
	private static $vrsObject = null;

	/**
	 * Convert from/to wikitext <=> html.
	 * Only these pairs are supported.  html => wikitext requires Parsoid.
	 *
	 * @param string $from Format of content to convert: html|wikitext
	 * @param string $to Format to convert to: html|wikitext
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws ParameterException When the requested conversion is unsupported
	 * @throws ParsoidException When Parsoid operation fails
	 */
	public static function convert( $from, $to, $content, Title $title ) {
		if ( $from === $to || $content === '' ) {
			return $content;
		}

		if ( $from === 'wikitext' || $from === 'html' ) {
			if ( $to === 'wikitext' || $to === 'html' ) {
				if ( self::configureVRSObject() ) {
					return self::parsoid( $from, $to, $content, $title );
				} else {
					return self::parser( $from, $to, $content, $title );
				}
			} else {
				throw new ParameterException( "Conversion from '$from' to '$to'" .
					"was requested, but this is not supported." );
			}
		} else {
			throw new ParameterException( "Conversion from '$from' to '$to'" .
				"was requested, but this is not supported." );
		}
	}

	/**
	 * Convert from/to wikitext/html via Parsoid.
	 *
	 * This will assume Parsoid is installed and configured.
	 *
	 * @param string $from Format of content to convert: html|wikitext
	 * @param string $to Format to convert to: html|wikitext
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws ParsoidException When Parsoid operation fails
	 * @throws ParameterException When conversion is unsupported
	 */
	private static function parsoid( $from, $to, $content, Title $title ) {
		global $wgVersion;

		$serviceClient = new VirtualRESTServiceClient( new MultiHttpClient( [] ) );
		$serviceClient->mount( '/restbase/', self::$vrsObject );

		if ( $from !== 'html' && $from !== 'wikitext' ) {
			throw new ParameterException( 'Unknown source format: ' . $from );
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
			$msg = "Request to " . $serviceName . " for \"$from\" to \"$to\" conversion of " .
				"content connected to title \"$prefixedDbTitle\" failed: $statusMsg";
			LoggerFactory::getInstance( 'VEForAll' )->error(
				'Request to {service} for "{sourceFormat}" to "{targetFormat}" conversion of ' .
				'content connected to title "{title}" failed. ' .
				'Code: {code}, Reason: "{reason}", Body: "{body}", Error: "{error}", Content: "{content}"',
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
				'content' => $content,
				]
			);
			throw new ParsoidException( $msg );
		}

		$content = $response['body'];
		// HACK remove trailing newline inserted by Parsoid (T106925)
		if ( $to === 'wikitext' ) {
			$content = preg_replace( '/\\n$/', '', $content );
		}
		return $content;
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
	 * @throws ParameterException When the conversion is unsupported
	 */
	private static function parser( $from, $to, $content, Title $title ) {
		if ( $from !== 'wikitext' || $to !== 'html' ) {
			throw new ParameterException(
				"Conversion from '$from' to '$to' was requested, but core's Parser only " .
				"supports 'wikitext' to 'html' conversion"
			);
		}

		global $wgParser;

		$options = new \ParserOptions;
		$options->setTidy( true );

		$output = $wgParser->parse( $content, $title, $options );
		return $output->getText( [ 'enableSectionEditLinks' => false ] );
	}

	/**
	 * Creates the Parsoid Virtual REST Service object to be used in
	 * API calls if it does not yet exist.
	 *
	 * @return true if configured correctly, false otherwise
	 */
	private static function configureVRSObject() {
		global $wgVirtualRestConfig;

		// the params array to create the service object with
		$params = [];
		// the VRS class to use; defaults to Parsoid
		$class = 'ParsoidVirtualRESTService';
		// the global virtual rest service config object, if any
		$vrs = $wgVirtualRestConfig;
		if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $vrs['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} else {
			return false;
		}
		// merge the global and service-specific params
		if ( isset( $vrs['global'] ) ) {
			$params = array_merge( $vrs['global'], $params );
		}
		// set up cookie forwarding
		if ( $params['forwardCookies'] && !User::isEveryoneAllowed( 'read' ) ) {
			$params['forwardCookies'] = RequestContext::getMain()->getRequest()->getHeader( 'Cookie' );
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object
		self::$vrsObject = new $class( $params );
		return true;
	}
}
