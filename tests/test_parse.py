"""Tests for sitemap HTML and XML parsing."""

from scraper.parse import parse_sitemap

_HTML = """
<html>
<body>
  <a href="https://engage.columbus.gov/topic/1">First Topic</a>
  <a href="https://engage.columbus.gov/topic/2">Second Topic</a>
  <a href="#skip-me">Fragment only, skip</a>
  <a href="">Empty href, skip</a>
  <a href="https://engage.columbus.gov/topic/3">  Lots   of   whitespace  </a>
  <a href="https://other.example.com/page">External link</a>
</body>
</html>
"""

_XML = """<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://engage.columbus.gov/xml-page-1</loc></url>
  <url><loc>https://engage.columbus.gov/xml-page-2</loc></url>
</urlset>
"""

_XML_INDEX = """<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap><loc>https://engage.columbus.gov/sitemap-1.xml</loc></sitemap>
</sitemapindex>
"""


class TestHtmlParsing:
    def test_extracts_href(self):
        pairs = parse_sitemap(_HTML)
        urls = [p[0] for p in pairs]
        assert "https://engage.columbus.gov/topic/1" in urls
        assert "https://engage.columbus.gov/topic/2" in urls

    def test_extracts_link_text(self):
        pairs = parse_sitemap(_HTML)
        text_map = {p[0]: p[1] for p in pairs}
        assert text_map["https://engage.columbus.gov/topic/1"] == "First Topic"

    def test_normalizes_whitespace_in_title(self):
        pairs = parse_sitemap(_HTML)
        text_map = {p[0]: p[1] for p in pairs}
        assert text_map["https://engage.columbus.gov/topic/3"] == "Lots of whitespace"

    def test_skips_fragment_only_anchors(self):
        pairs = parse_sitemap(_HTML)
        urls = [p[0] for p in pairs]
        assert "#skip-me" not in urls

    def test_skips_empty_href(self):
        pairs = parse_sitemap(_HTML)
        urls = [p[0] for p in pairs]
        assert "" not in urls

    def test_includes_external_links(self):
        # Parser is domain-agnostic; filtering happens in normalize_url
        pairs = parse_sitemap(_HTML)
        urls = [p[0] for p in pairs]
        assert "https://other.example.com/page" in urls


class TestXmlParsing:
    def test_extracts_loc_elements(self):
        pairs = parse_sitemap(_XML)
        urls = [p[0] for p in pairs]
        assert "https://engage.columbus.gov/xml-page-1" in urls
        assert "https://engage.columbus.gov/xml-page-2" in urls

    def test_returns_correct_count(self):
        pairs = parse_sitemap(_XML)
        assert len(pairs) == 2

    def test_url_is_used_as_title_fallback(self):
        pairs = parse_sitemap(_XML)
        for url, title in pairs:
            assert title == url

    def test_parses_sitemap_index(self):
        pairs = parse_sitemap(_XML_INDEX)
        assert len(pairs) == 1
        assert pairs[0][0] == "https://engage.columbus.gov/sitemap-1.xml"
