<?php
/**
 * HTTP fetching with retry and exponential backoff.
 */

declare(strict_types=1);

const SITEMAP_URL = 'https://engage.columbus.gov/sitemap';
const USER_AGENT = 'engage-columbus-scrape-to-rss/1.0 (https://github.com/; respectful bot, daily schedule)';
const REQUEST_TIMEOUT_SECONDS = 20;
const MAX_RETRIES = 3;

/**
 * Fetch a URL with retry logic and exponential backoff.
 *
 * @param  string $url The URL to fetch.
 * @return ?string The response body, or null on failure.
 */
function fetch_url( string $url ): ?string {
	for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			[
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT => REQUEST_TIMEOUT_SECONDS,
				CURLOPT_USERAGENT => USER_AGENT,
			]
		);

		$body = curl_exec( $ch );
		$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $ch );

		if (is_string( $body ) && $status >= 200 && $status < 300) {
			return $body;
		}

		fwrite(
			STDERR,
			sprintf(
				"Attempt %d/%d failed for %s (status=%d, error=%s)\n",
				$attempt,
				MAX_RETRIES,
				$url,
				$status,
				$error
			)
		);
		if ($attempt < MAX_RETRIES) {
			sleep( (int) pow( 2, $attempt ) );
		}
	}

	return null;
}
