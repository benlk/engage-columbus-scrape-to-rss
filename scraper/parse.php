<?php
/**
 * Sitemap content parsing: XML sitemap and HTML anchor extraction.
 */

declare(strict_types=1);

/**
 * Parse sitemap content and return URL and link text pairs.
 *
 * @param  string $content The sitemap HTML or XML content.
 * @return array Array of [url, link_text] pairs.
 */
function parse_sitemap( string $content ): array {
	$xml_pairs = try_parse_xml_sitemap( $content );
	if ($xml_pairs !== null) {
		return $xml_pairs;
	}

	return parse_html_anchors( $content );
}

/**
 * Try to parse XML sitemap format.
 *
 * @param  string $content The content to parse.
 * @return ?array Array of [url, url] pairs, or null if not XML sitemap.
 */
function try_parse_xml_sitemap(string $content): ?array {
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $content );
	if ($xml === false) {
		return null;
	}

	$name = strtolower( $xml->getName() );
	if (strpos( $name, 'urlset' ) === false && strpos( $name, 'sitemapindex' ) === false) {
		return null;
	}

	$pairs = [];
	$nodes = $xml->xpath( "//*[local-name()='loc']" );
	if ($nodes === false) {
		return [];
	}

	foreach ($nodes as $node) {
		$url = trim( (string) $node );
		if ($url !== '') {
			$pairs[] = [$url, $url];
		}
	}

	return $pairs;
}

/**
 * Extract href and link text pairs from HTML anchors.
 *
 * @param  string $content The HTML content to parse.
 * @return array Array of [href, link_text] pairs.
 */
function parse_html_anchors(string $content): array {
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	if (!$dom->loadHTML( $content )) {
		return [];
	}

	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query( '//a[@href]' );
	if ($nodes === false) {
		return [];
	}

	$pairs = [];
	foreach ($nodes as $node) {
		$href = trim( (string) $node->getAttribute( 'href' ) );
		if ($href === '' || str_starts_with( $href, '#' )) {
			continue;
		}

		$text = preg_replace( '/\s+/', ' ', trim( (string) $node->{'textContent'} ) );
		$text = $text ?? '';
		if ($text === '') {
			continue;
		}

		$pairs[] = [$href, $text];
	}

	return $pairs;
}
