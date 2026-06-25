<?php
/**
 * CSV persistence layer for discovered links.
 */

declare(strict_types=1);

const CSV_FIELDS = ['url', 'title', 'first_seen_utc', 'source', 'run_id'];

/**
 * Ensure CSV file exists and is initialized with headers.
 *
 * @param  string $path The path to the CSV file.
 * @return void
 */
function ensure_csv_exists(string $path): void {
	if (file_exists( $path )) {
		return;
	}

	$dir = dirname( $path );
	if (!is_dir( $dir )) {
		mkdir( $dir, 0777, true );
	}

	$fh = fopen( $path, 'w' );
	if ($fh === false) {
		throw new RuntimeException( 'Unable to create CSV file: ' . $path );
	}
	fputcsv( $fh, CSV_FIELDS );
	fclose( $fh );
}

/**
 * Load all known URLs from the CSV file.
 *
 * @param  string $path The path to the CSV file.
 * @return array Associative array with URLs as keys.
 */
function load_known_urls( string $path ): array {
	ensure_csv_exists( $path );

	$known = [];
	$fh = fopen( $path, 'r' );
	if ($fh === false) {
		throw new RuntimeException( 'Unable to read CSV file: ' . $path );
	}

	$header = fgetcsv( $fh );
	if ($header === false) {
		fclose( $fh );
		return $known;
	}

	while (($row = fgetcsv( $fh )) !== false) {
		$mapped = array_combine( $header, $row );
		if (!is_array( $mapped )) {
			continue;
		}

		$url = trim( (string) ($mapped['url'] ?? '') );
		if ($url !== '') {
			$known[$url] = true;
		}
	}

	fclose( $fh );
	return $known;
}

/**
 * Append new rows to the CSV file atomically.
 *
 * @param  string $path The path to the CSV file.
 * @param  array  $rows Array of row arrays to append.
 * @return void
 */
function append_new_rows( string $path, array $rows ): void {
	if (count( $rows ) === 0) {
		return;
	}

	ensure_csv_exists( $path );
	$existing = file_get_contents( $path );
	if ($existing === false) {
		throw new RuntimeException( 'Unable to read existing CSV for append: ' . $path );
	}

	$dir = dirname( $path );
	$tmp_path = tempnam( $dir, 'links_' );
	if ($tmp_path === false) {
		throw new RuntimeException( 'Unable to create temp file in ' . $dir );
	}

	$tmp_file = fopen( $tmp_path, 'w' );
	if ($tmp_file === false ) {
		throw new RuntimeException( 'Unable to open temp file for writing: ' . $tmp_path );
	}

	fwrite( $tmp_file, $existing );
	foreach ($rows as $row) {
		fputcsv(
			$tmp_file,
			[
				(string) ($row['url'] ?? ''),
				(string) ($row['title'] ?? ''),
				(string) ($row['first_seen_utc'] ?? ''),
				(string) ($row['source'] ?? 'sitemap'),
				(string) ($row['run_id'] ?? ''),
			]
		);
	}
	fclose( $tmp_file );

	rename( $tmp_file, $path );
}
