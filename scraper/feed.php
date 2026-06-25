<?php
/**
 * RSS 2.0 feed generation.
 */

declare(strict_types=1);

const MAX_FEED_ITEMS = 500;
const FEED_TITLE = 'Engage Columbus - New Pages';
const FEED_LINK = 'https://engage.columbus.gov/sitemap';
const FEED_DESCRIPTION = 'Newly discovered pages on Engage Columbus (engage.columbus.gov)';

/**
 * Convert ISO 8601 UTC timestamp to RFC 2822 format.
 *
 * @param  string $iso_timestamp ISO 8601 UTC timestamp string.
 * @return string RFC 2822 formatted date.
 */
function to_rfc2822(string $iso_timestamp): string {
	$ts = strtotime($iso_timestamp);
	if ($ts === false) {
		$ts = time();
	}
	return gmdate(DATE_RSS, $ts);
}

/**
 * Load existing feed items from an RSS file.
 *
 * @param  string $feed_path The path to the RSS file.
 * @return array Array of item arrays.
 */
function load_existing_items(string $feed_path): array {
	if (!file_exists( $feed_path )) {
		return [];
	}

	libxml_use_internal_errors( true );
	$xml = simplexml_load_file( $feed_path );
	if ($xml === false || !isset( $xml->channel )) {
		return [];
	}

	$items = [];
	foreach ($xml->channel->item as $item) {
		$items[] = [
			'title' => (string) ($item->title ?? ''),
			'link' => (string) ($item->link ?? ''),
			'guid' => (string) ($item->guid ?? ''),
			'pub_date' => (string) ($item->{'pubDate'} ?? ''),
		];
	}

	return $items;
}

/**
 * Build RSS XML content by prepending new rows to existing feed items.
 *
 * @param  array  $new_rows  New CSV rows to include in the feed.
 * @param  string $feed_path Path to the existing feed XML file.
 * @return string RSS XML content.
 */
function build_feed(array $new_rows, string $feed_path): string {
	$existing = load_existing_items( $feed_path );
	$existing_guids = [];
	foreach ($existing as $item) {
		$existing_guids[$item['guid']] = true;
	}

	$new_items = [];
	foreach ($new_rows as $row) {
		$url = (string) ($row['url'] ?? '');
		if ($url === '' || isset( $existing_guids[$url] )) {
			continue;
		}

		$new_items[] = [
			'title' => (string) (($row['title'] ?? '') !== '' ? $row['title'] : $url),
			'link' => $url,
			'guid' => $url,
			'pub_date' => to_rfc2822( (string) ($row['first_seen_utc'] ?? '') ),
		];
	}

	$all_items = array_slice( array_merge( $new_items, $existing ), 0, MAX_FEED_ITEMS );

	$dom = new DOMDocument( '1.0', 'UTF-8' );
	$dom->{'formatOutput'} = true;

	$rss = $dom->createElement( 'rss' );
	$rss->setAttribute( 'version', '2.0' );
	$dom->appendChild( $rss );

	$channel = $dom->createElement( 'channel' );
	$rss->appendChild( $channel );
	$channel->appendChild( $dom->createElement( 'title', FEED_TITLE ) );
	$channel->appendChild( $dom->createElement( 'link', FEED_LINK ) );
	$channel->appendChild( $dom->createElement( 'description', FEED_DESCRIPTION ) );
	$channel->appendChild( $dom->createElement( 'lastBuildDate', gmdate( DATE_RSS ) ) );

	foreach ($all_items as $item) {
		$item_element = $dom->createElement( 'item' );
		$item_element->appendChild( $dom->createElement( 'title', $item['title'] ) );
		$item_element->appendChild( $dom->createElement( 'link', $item['link'] ) );

		$guid = $dom->createElement( 'guid', $item['guid'] );
		$guid->setAttribute( 'isPermaLink', 'true' );
		$item_element->appendChild( $guid );

		$item_element->appendChild( $dom->createElement( 'pubDate', $item['pub_date'] ) );
		$channel->appendChild( $item_element );
	}

	return $dom->saveXML();
}

/**
 * Write RSS XML content to the feed path.
 *
 * @param  string $feed_path   Destination feed path.
 * @param  string $xml_content RSS XML content.
 * @return void
 */
function write_feed(string $feed_path, string $xml_content): void {
	$dir = dirname($feed_path);
	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
	}

	file_put_contents( $feed_path, $xml_content );
}
