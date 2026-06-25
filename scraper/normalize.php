<?php
/**
 * URL normalization and domain filtering.
 */

declare(strict_types=1);

const ALLOWED_DOMAIN = 'columbus.gov';
const ALLOWED_SUFFIX = '.columbus.gov';

/**
 * Normalize a URL and filter to columbus.gov domain.
 *
 * @param  string $raw The raw URL to normalize.
 * @return ?string Normalized URL, or null if rejected.
 */
function normalize_url( string $raw ): ?string {
	$raw = trim( $raw );
	if ($raw === '') {
		return null;
	}

	// Prepend domain to relative URLs starting with /
	if (str_starts_with( $raw, '/' )) {
		$raw = 'https://engage.columbus.gov' . $raw;
	}

	$parts = @parse_url( $raw );
	if ($parts === false) {
		return null;
	}

	$scheme = strtolower( (string) ($parts['scheme'] ?? '') );
	if ($scheme !== 'http' && $scheme !== 'https') {
		return null;
	}

	$host = strtolower( (string) ($parts['host'] ?? '') );
	if ($host === '') {
		return null;
	}

	if ($host !== ALLOWED_DOMAIN && !str_ends_with( $host, ALLOWED_SUFFIX )) {
		return null;
	}

	$path = (string) ($parts['path'] ?? '');
	if ($path !== '/' && $path !== '') {
		$path = rtrim( $path, '/' );
	}
	if ($path === '') {
		$path = '/';
	}

	return $scheme . '://' . $host . $path;
}
