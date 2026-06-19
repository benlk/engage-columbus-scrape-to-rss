"""Tests for RSS feed generation."""

import xml.etree.ElementTree as ET
from pathlib import Path

from scraper.feed import MAX_FEED_ITEMS, build_feed, write_feed


def _row(url: str, title: str = "Test Title") -> dict:
    return {
        "url": url,
        "title": title,
        "first_seen_utc": "2024-01-01T00:00:00Z",
        "source": "sitemap",
        "run_id": "2024-01-01T00:00:00Z",
    }


def _parse(xml_str: str) -> ET.Element:
    """Strip XML declaration and return root element."""
    body = xml_str.split("\n", 1)[1] if xml_str.startswith("<?xml") else xml_str
    return ET.fromstring(body)


class TestBuildFeed:
    def test_produces_valid_xml(self, tmp_path):
        xml_str = build_feed([_row("https://engage.columbus.gov/a")], tmp_path / "feed.xml")
        _parse(xml_str)  # raises on invalid XML

    def test_item_title_equals_link_text(self, tmp_path):
        xml_str = build_feed(
            [_row("https://engage.columbus.gov/a", "My Custom Title")],
            tmp_path / "feed.xml",
        )
        root = _parse(xml_str)
        title = root.findtext("./channel/item/title")
        assert title == "My Custom Title"

    def test_item_link_equals_url(self, tmp_path):
        url = "https://engage.columbus.gov/a"
        xml_str = build_feed([_row(url)], tmp_path / "feed.xml")
        root = _parse(xml_str)
        assert root.findtext("./channel/item/link") == url

    def test_item_guid_equals_url(self, tmp_path):
        url = "https://engage.columbus.gov/a"
        xml_str = build_feed([_row(url)], tmp_path / "feed.xml")
        root = _parse(xml_str)
        assert root.findtext("./channel/item/guid") == url

    def test_no_duplicate_guids(self, tmp_path):
        rows = [_row(f"https://engage.columbus.gov/{i}") for i in range(10)]
        xml_str = build_feed(rows, tmp_path / "feed.xml")
        root = _parse(xml_str)
        guids = [e.text for e in root.findall("./channel/item/guid")]
        assert len(guids) == len(set(guids))

    def test_max_items_cap_enforced(self, tmp_path):
        rows = [
            _row(f"https://engage.columbus.gov/{i}")
            for i in range(MAX_FEED_ITEMS + 20)
        ]
        xml_str = build_feed(rows, tmp_path / "feed.xml")
        root = _parse(xml_str)
        assert len(root.findall("./channel/item")) <= MAX_FEED_ITEMS

    def test_url_used_as_title_when_title_missing(self, tmp_path):
        url = "https://engage.columbus.gov/a"
        row = {
            "url": url,
            "title": "",
            "first_seen_utc": "2024-01-01T00:00:00Z",
            "source": "sitemap",
            "run_id": "2024-01-01T00:00:00Z",
        }
        xml_str = build_feed([row], tmp_path / "feed.xml")
        root = _parse(xml_str)
        assert root.findtext("./channel/item/title") == url

    def test_new_items_prepended_to_existing(self, tmp_path):
        feed_path = tmp_path / "feed.xml"
        # First run
        xml_str = build_feed([_row("https://engage.columbus.gov/a", "A")], feed_path)
        write_feed(feed_path, xml_str)
        # Second run
        xml_str2 = build_feed([_row("https://engage.columbus.gov/b", "B")], feed_path)
        root = _parse(xml_str2)
        titles = [e.text for e in root.findall("./channel/item/title")]
        assert titles[0] == "B"
        assert "A" in titles

    def test_existing_guid_not_duplicated_across_runs(self, tmp_path):
        feed_path = tmp_path / "feed.xml"
        url = "https://engage.columbus.gov/a"
        xml_str = build_feed([_row(url, "A")], feed_path)
        write_feed(feed_path, xml_str)
        # Same URL on second run should be excluded (dedupe by guid)
        xml_str2 = build_feed([_row(url, "A again")], feed_path)
        root = _parse(xml_str2)
        guids = [e.text for e in root.findall("./channel/item/guid")]
        assert guids.count(url) == 1


class TestWriteFeed:
    def test_creates_file(self, tmp_path):
        feed_path = tmp_path / "output" / "feed.xml"
        write_feed(feed_path, "<rss/>")
        assert feed_path.exists()

    def test_content_is_written(self, tmp_path):
        feed_path = tmp_path / "feed.xml"
        write_feed(feed_path, "<rss>content</rss>")
        assert feed_path.read_text(encoding="utf-8") == "<rss>content</rss>"
