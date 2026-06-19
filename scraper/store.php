<?php

declare(strict_types=1);

const CSV_FIELDS = [ 'url', 'title', 'first_seen_utc', 'source', 'run_id' ];

function ensure_csv_exists( string $path ): void {
	if ( file_exists( $path ) ) {
		return;
	}

	$dir = dirname( $path );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0777, true );
	}

	$fh = fopen( $path, 'w' );
	if ( $fh === false ) {
		throw new RuntimeException( 'Unable to create CSV file: ' . $path );
	}
	fputcsv( $fh, CSV_FIELDS );
	fclose( $fh );
}

function load_known_urls( string $path ): array {
	ensure_csv_exists( $path );

	$known = [];
	$fh    = fopen( $path, 'r' );
	if ( $fh === false ) {
		throw new RuntimeException( 'Unable to read CSV file: ' . $path );
	}

	$header = fgetcsv( $fh );
	if ( $header === false ) {
		fclose( $fh );
		return $known;
	}

	while ( ( $row = fgetcsv( $fh ) ) !== false ) {
		$mapped = array_combine( $header, $row );
		if ( ! is_array( $mapped ) ) {
			continue;
		}

		$url = trim( (string) ( $mapped['url'] ?? '' ) );
		if ( $url !== '' ) {
			$known[ $url ] = true;
		}
	}

	fclose( $fh );
	return $known;
}

function append_new_rows( string $path, array $rows ): void {
	if ( count( $rows ) === 0 ) {
		return;
	}

	ensure_csv_exists( $path );
	$existing = file_get_contents( $path );
	if ( $existing === false ) {
		throw new RuntimeException( 'Unable to read existing CSV for append: ' . $path );
	}

	$dir     = dirname( $path );
	$tmpPath = tempnam( $dir, 'links_' );
	if ( $tmpPath === false ) {
		throw new RuntimeException( 'Unable to create temp file in ' . $dir );
	}

	$tmpFile = fopen( $tmpPath, 'w' );
	if ( $tmpFile === false ) {
		throw new RuntimeException( 'Unable to open temp file for writing: ' . $tmpPath );
	}

	fwrite( $tmpFile, $existing );
	foreach ( $rows as $row ) {
		fputcsv(
			$tmpFile,
			[
				(string) ( $row['url'] ?? '' ),
				(string) ( $row['title'] ?? '' ),
				(string) ( $row['first_seen_utc'] ?? '' ),
				(string) ( $row['source'] ?? 'sitemap' ),
				(string) ( $row['run_id'] ?? '' ),
			]
		);
	}
	fclose( $tmpFile );

	rename( $tmpPath, $path );
}
