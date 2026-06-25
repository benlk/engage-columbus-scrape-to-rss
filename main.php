<?php
/**
 * CLI entrypoint for sitemap scraping and feed generation.
 */

declare(strict_types=1);

require_once __DIR__ . '/scraper/fetch.php';
require_once __DIR__ . '/scraper/parse.php';
require_once __DIR__ . '/scraper/normalize.php';
require_once __DIR__ . '/scraper/store.php';
require_once __DIR__ . '/scraper/feed.php';

const LINKS_CSV_PATH = __DIR__ . '/data/links.csv';
const FEED_PATH      = __DIR__ . '/feed.xml';

/**
 * Return the current UTC timestamp in ISO 8601 format.
 *
 * @return string UTC timestamp.
 */
function utc_timestamp(): string {
	return gmdate( 'Y-m-d\\TH:i:s\\Z' );
}

/**
 * Execute one scraper run.
 *
 * @return int Process exit code.
 */
function run_scraper(): int {
	$run_id = utc_timestamp();
	fwrite( STDOUT, sprintf( "Run started: %s\n", $run_id ) );

	$content = fetch_url( SITEMAP_URL );
	if ($content === null) {
		fwrite( STDERR, "Failed to fetch sitemap; aborting run.\n" );
		return 1;
	}

	// Store raw HTML for debugging
	file_put_contents( __DIR__ . '/scraper-output.html', $content );
	fwrite( STDOUT, "Saved fetched content to scraper-output.html\n" );

	$raw_pairs = parse_sitemap( $content );
	fwrite( STDOUT, sprintf( "Extracted %d raw pairs from sitemap\n", count( $raw_pairs ) ) );

	$normalized = [];
	foreach ($raw_pairs as $pair) {
		$raw_url   = (string) ( $pair[0] ?? '' );
		$link_text = (string) ( $pair[1] ?? '' );
		$url       = normalize_url( $raw_url );
		if ($url !== null && ! isset( $normalized[ $url ] )) {
			$normalized[ $url ] = $link_text;
		}
	}
	fwrite( STDOUT, sprintf( "%d unique columbus.gov URLs after normalization\n", count( $normalized ) ) );

	$known = load_known_urls( LINKS_CSV_PATH );
	fwrite( STDOUT, sprintf( "%d URLs already recorded in CSV\n", count( $known ) ) );

	$new_entries = [];
	foreach ($normalized as $url => $title) {
		if (! isset( $known[ $url ] )) {
			$new_entries[] = [
			'url'            => $url,
			'title'          => $title,
			'first_seen_utc' => $run_id,
			'source'         => 'sitemap',
			'run_id'         => $run_id,
			];
		}
	}

	fwrite( STDOUT, sprintf( "%d new URLs discovered this run\n", count( $new_entries ) ) );

	if (count( $new_entries ) === 0) {
		fwrite( STDOUT, "No new URLs found; skipping CSV and feed updates.\n" );
		return 0;
	}

	append_new_rows( LINKS_CSV_PATH, $new_entries );
	fwrite( STDOUT, sprintf( "Appended %d rows to %s\n", count( $new_entries ), LINKS_CSV_PATH ) );

	$xml = build_feed( $new_entries, FEED_PATH );
	write_feed( FEED_PATH, $xml );
	fwrite( STDOUT, sprintf( "Feed written to %s\n", FEED_PATH ) );

	fwrite( STDOUT, sprintf( "Run %s complete.\n", $run_id ) );

	// Archive the debug log
	if (file_exists( __DIR__ . '/scraper-output.html' )) {
		rename( __DIR__ . '/scraper-output.html', __DIR__ . '/scraper-debug.log' );
	}

	return 0;
}

exit( run_scraper() );
