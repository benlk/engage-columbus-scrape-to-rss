<?php

declare(strict_types=1);

require_once __DIR__ . '/scraper/fetch.php';
require_once __DIR__ . '/scraper/parse.php';
require_once __DIR__ . '/scraper/normalize.php';
require_once __DIR__ . '/scraper/store.php';
require_once __DIR__ . '/scraper/feed.php';

const LINKS_CSV_PATH = __DIR__ . '/data/links.csv';
const FEED_PATH = __DIR__ . '/feed.xml';

function utc_timestamp(): string
{
    return gmdate('Y-m-d\\TH:i:s\\Z');
}

function run_scraper(): int
{
    $runId = utc_timestamp();
    fwrite(STDOUT, sprintf("Run started: %s\n", $runId));

    $content = fetch_url(SITEMAP_URL);
    if ($content === null) {
        fwrite(STDERR, "Failed to fetch sitemap; aborting run.\n");
        return 1;
    }

    $rawPairs = parse_sitemap($content);
    fwrite(STDOUT, sprintf("Extracted %d raw pairs from sitemap\n", count($rawPairs)));

    $normalized = [];
    foreach ($rawPairs as $pair) {
        $rawUrl = (string) ($pair[0] ?? '');
        $linkText = (string) ($pair[1] ?? '');
        $url = normalize_url($rawUrl);
        if ($url !== null && !isset($normalized[$url])) {
            $normalized[$url] = $linkText;
        }
    }
    fwrite(STDOUT, sprintf("%d unique columbus.gov URLs after normalization\n", count($normalized)));

    $known = load_known_urls(LINKS_CSV_PATH);
    fwrite(STDOUT, sprintf("%d URLs already recorded in CSV\n", count($known)));

    $newEntries = [];
    foreach ($normalized as $url => $title) {
        if (!isset($known[$url])) {
            $newEntries[] = [
                'url' => $url,
                'title' => $title,
                'first_seen_utc' => $runId,
                'source' => 'sitemap',
                'run_id' => $runId,
            ];
        }
    }

    fwrite(STDOUT, sprintf("%d new URLs discovered this run\n", count($newEntries)));

    if (count($newEntries) === 0) {
        fwrite(STDOUT, "No new URLs found; skipping CSV and feed updates.\n");
        return 0;
    }

    append_new_rows(LINKS_CSV_PATH, $newEntries);
    fwrite(STDOUT, sprintf("Appended %d rows to %s\n", count($newEntries), LINKS_CSV_PATH));

    $xml = build_feed($newEntries, FEED_PATH);
    write_feed(FEED_PATH, $xml);
    fwrite(STDOUT, sprintf("Feed written to %s\n", FEED_PATH));

    fwrite(STDOUT, sprintf("Run %s complete.\n", $runId));
    return 0;
}

exit(run_scraper());
