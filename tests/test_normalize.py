"""Tests for URL normalization and domain filtering."""

import pytest

from scraper.normalize import normalize_url


def test_strips_query_string():
    assert (
        normalize_url("https://engage.columbus.gov/page?foo=bar")
        == "https://engage.columbus.gov/page"
    )


def test_strips_fragment():
    assert (
        normalize_url("https://engage.columbus.gov/page#section")
        == "https://engage.columbus.gov/page"
    )


def test_strips_both_query_and_fragment():
    assert (
        normalize_url("https://engage.columbus.gov/page?foo=bar#section")
        == "https://engage.columbus.gov/page"
    )


def test_lowercases_host():
    assert (
        normalize_url("https://ENGAGE.COLUMBUS.GOV/page")
        == "https://engage.columbus.gov/page"
    )


def test_lowercases_scheme():
    assert (
        normalize_url("HTTPS://engage.columbus.gov/page")
        == "https://engage.columbus.gov/page"
    )


def test_rejects_non_columbus_gov():
    assert normalize_url("https://example.com/page") is None


def test_rejects_lookalike_domain():
    assert normalize_url("https://notcolumbus.gov/page") is None


def test_accepts_subdomain():
    assert (
        normalize_url("https://engage.columbus.gov/page")
        == "https://engage.columbus.gov/page"
    )


def test_accepts_root_columbus_gov():
    assert (
        normalize_url("https://columbus.gov/page")
        == "https://columbus.gov/page"
    )


def test_accepts_deep_subdomain():
    result = normalize_url("https://sub.engage.columbus.gov/page")
    assert result == "https://sub.engage.columbus.gov/page"


def test_strips_trailing_slash_on_path():
    assert (
        normalize_url("https://engage.columbus.gov/page/")
        == "https://engage.columbus.gov/page"
    )


def test_preserves_root_path_slash():
    result = normalize_url("https://engage.columbus.gov/")
    assert result is not None
    assert result.startswith("https://engage.columbus.gov")


def test_rejects_non_http_scheme():
    assert normalize_url("ftp://engage.columbus.gov/page") is None


def test_rejects_empty_string():
    assert normalize_url("") is None


def test_rejects_bare_path():
    assert normalize_url("/relative/path") is None


def test_same_url_after_normalization_is_idempotent():
    url = "https://engage.columbus.gov/some/path"
    assert normalize_url(url) == normalize_url(normalize_url(url))
