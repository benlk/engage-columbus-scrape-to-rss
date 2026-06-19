"""Tests for CSV persistence layer."""

import csv
from pathlib import Path

from scraper.store import FIELDNAMES, append_new_rows, load_known_urls


def _row(url: str, run_id: str = "2024-01-01T00:00:00Z") -> dict:
    return {
        "url": url,
        "title": f"Title for {url}",
        "first_seen_utc": run_id,
        "source": "sitemap",
        "run_id": run_id,
    }


class TestLoadKnownUrls:
    def test_creates_file_with_header_when_missing(self, tmp_path):
        csv_path = tmp_path / "data" / "links.csv"
        known = load_known_urls(csv_path)
        assert known == set()
        assert csv_path.exists()
        with open(csv_path, newline="") as fh:
            reader = csv.reader(fh)
            assert next(reader) == FIELDNAMES

    def test_returns_urls_from_existing_file(self, tmp_path):
        csv_path = tmp_path / "links.csv"
        append_new_rows(csv_path, [_row("https://engage.columbus.gov/a")])
        known = load_known_urls(csv_path)
        assert "https://engage.columbus.gov/a" in known

    def test_returns_set_of_all_urls(self, tmp_path):
        csv_path = tmp_path / "links.csv"
        rows = [
            _row("https://engage.columbus.gov/a"),
            _row("https://engage.columbus.gov/b"),
        ]
        append_new_rows(csv_path, rows)
        known = load_known_urls(csv_path)
        assert known == {
            "https://engage.columbus.gov/a",
            "https://engage.columbus.gov/b",
        }


class TestAppendNewRows:
    def test_appends_rows_to_empty_file(self, tmp_path):
        csv_path = tmp_path / "links.csv"
        append_new_rows(csv_path, [_row("https://engage.columbus.gov/a")])
        known = load_known_urls(csv_path)
        assert "https://engage.columbus.gov/a" in known

    def test_does_not_modify_file_when_rows_empty(self, tmp_path):
        csv_path = tmp_path / "links.csv"
        load_known_urls(csv_path)  # initialize
        mtime_before = csv_path.stat().st_mtime
        append_new_rows(csv_path, [])
        assert csv_path.stat().st_mtime == mtime_before

    def test_preserves_existing_rows(self, tmp_path):
        csv_path = tmp_path / "links.csv"
        append_new_rows(csv_path, [_row("https://engage.columbus.gov/a")])
        append_new_rows(csv_path, [_row("https://engage.columbus.gov/b")])
        known = load_known_urls(csv_path)
        assert "https://engage.columbus.gov/a" in known
        assert "https://engage.columbus.gov/b" in known

    def test_creates_parent_directories(self, tmp_path):
        csv_path = tmp_path / "deep" / "nested" / "links.csv"
        append_new_rows(csv_path, [_row("https://engage.columbus.gov/a")])
        assert csv_path.exists()

    def test_header_written_once(self, tmp_path):
        csv_path = tmp_path / "links.csv"
        append_new_rows(csv_path, [_row("https://engage.columbus.gov/a")])
        append_new_rows(csv_path, [_row("https://engage.columbus.gov/b")])
        with open(csv_path, newline="") as fh:
            lines = fh.readlines()
        header_count = sum(1 for line in lines if line.startswith("url,"))
        assert header_count == 1


class TestDedupeIntegration:
    def test_second_run_with_same_url_is_already_known(self, tmp_path):
        csv_path = tmp_path / "links.csv"
        url = "https://engage.columbus.gov/page"
        append_new_rows(csv_path, [_row(url)])
        known = load_known_urls(csv_path)
        # Simulate discovery diff: URL already known → new_entries is empty
        new_entries = [_row(url)] if url not in known else []
        assert new_entries == []
