<?php

declare(strict_types=1);

const MAX_FEED_ITEMS = 500;
const FEED_TITLE = 'Engage Columbus - New Pages';
const FEED_LINK = 'https://engage.columbus.gov/sitemap';
const FEED_DESCRIPTION = 'Newly discovered pages on Engage Columbus (engage.columbus.gov)';

function to_rfc2822(string $isoTimestamp): string
{
    $ts = strtotime($isoTimestamp);
    if ($ts === false) {
        $ts = time();
    }
    return gmdate(DATE_RSS, $ts);
}

function load_existing_items(string $feedPath): array
{
    if (!file_exists($feedPath)) {
        return [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($feedPath);
    if ($xml === false || !isset($xml->channel)) {
        return [];
    }

    $items = [];
    foreach ($xml->channel->item as $item) {
        $items[] = [
            'title' => (string) ($item->title ?? ''),
            'link' => (string) ($item->link ?? ''),
            'guid' => (string) ($item->guid ?? ''),
            'pubDate' => (string) ($item->pubDate ?? ''),
        ];
    }

    return $items;
}

function build_feed(array $newRows, string $feedPath): string
{
    $existing = load_existing_items($feedPath);
    $existingGuids = [];
    foreach ($existing as $item) {
        $existingGuids[$item['guid']] = true;
    }

    $newItems = [];
    foreach ($newRows as $row) {
        $url = (string) ($row['url'] ?? '');
        if ($url === '' || isset($existingGuids[$url])) {
            continue;
        }

        $newItems[] = [
            'title' => (string) (($row['title'] ?? '') !== '' ? $row['title'] : $url),
            'link' => $url,
            'guid' => $url,
            'pubDate' => to_rfc2822((string) ($row['first_seen_utc'] ?? '')),
        ];
    }

    $allItems = array_slice(array_merge($newItems, $existing), 0, MAX_FEED_ITEMS);

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    $rss = $dom->createElement('rss');
    $rss->setAttribute('version', '2.0');
    $dom->appendChild($rss);

    $channel = $dom->createElement('channel');
    $rss->appendChild($channel);
    $channel->appendChild($dom->createElement('title', FEED_TITLE));
    $channel->appendChild($dom->createElement('link', FEED_LINK));
    $channel->appendChild($dom->createElement('description', FEED_DESCRIPTION));
    $channel->appendChild($dom->createElement('lastBuildDate', gmdate(DATE_RSS)));

    foreach ($allItems as $item) {
        $itemElement = $dom->createElement('item');
        $itemElement->appendChild($dom->createElement('title', $item['title']));
        $itemElement->appendChild($dom->createElement('link', $item['link']));

        $guid = $dom->createElement('guid', $item['guid']);
        $guid->setAttribute('isPermaLink', 'true');
        $itemElement->appendChild($guid);

        $itemElement->appendChild($dom->createElement('pubDate', $item['pubDate']));
        $channel->appendChild($itemElement);
    }

    return $dom->saveXML();
}

function write_feed(string $feedPath, string $xmlContent): void
{
    $dir = dirname($feedPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($feedPath, $xmlContent);
}
