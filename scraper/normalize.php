<?php

declare(strict_types=1);

const ALLOWED_DOMAIN = 'columbus.gov';
const ALLOWED_SUFFIX = '.columbus.gov';

function normalize_url(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    $parts = @parse_url($raw);
    if ($parts === false) {
        return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '') {
        return null;
    }

    if ($host !== ALLOWED_DOMAIN && !str_ends_with($host, ALLOWED_SUFFIX)) {
        return null;
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path !== '/' && $path !== '') {
        $path = rtrim($path, '/');
    }
    if ($path === '') {
        $path = '/';
    }

    return $scheme . '://' . $host . $path;
}
