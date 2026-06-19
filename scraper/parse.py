"""Sitemap content parsing: XML sitemap format and HTML anchor extraction."""

import logging
from html.parser import HTMLParser
from typing import Optional
from xml.etree import ElementTree

logger = logging.getLogger(__name__)


class _AnchorParser(HTMLParser):
    """Minimal HTML parser that collects (href, link_text) pairs from <a> tags."""

    def __init__(self) -> None:
        super().__init__()
        self.pairs: list[tuple[str, str]] = []
        self._current_href: Optional[str] = None
        self._current_text: list[str] = []

    def handle_starttag(
        self, tag: str, attrs: list[tuple[str, Optional[str]]]
    ) -> None:
        if tag == "a":
            attrs_dict = dict(attrs)
            href = (attrs_dict.get("href") or "").strip()
            # Skip empty hrefs and fragment-only anchors
            if href and not href.startswith("#"):
                self._current_href = href
                self._current_text = []

    def handle_data(self, data: str) -> None:
        if self._current_href is not None:
            self._current_text.append(data)

    def handle_endtag(self, tag: str) -> None:
        if tag == "a" and self._current_href is not None:
            text = " ".join("".join(self._current_text).split()).strip()
            if text:
                self.pairs.append((self._current_href, text))
            self._current_href = None
            self._current_text = []


def parse_sitemap(content: str) -> list[tuple[str, str]]:
    """Parse sitemap content and return a list of (raw_url, link_text) pairs.

    Tries XML sitemap format first; falls back to HTML anchor extraction.
    For XML sitemaps the URL itself is used as the link_text placeholder
    (no anchor text is available in that format).
    """
    xml_result = _try_xml(content)
    if xml_result is not None:
        return xml_result
    return _try_html(content)


def _try_xml(content: str) -> Optional[list[tuple[str, str]]]:
    """Return parsed (url, url) pairs from an XML sitemap, or None if not XML."""
    try:
        root = ElementTree.fromstring(content)
    except ElementTree.ParseError:
        return None

    root_tag = root.tag.lower()
    if "sitemap" not in root_tag and "urlset" not in root_tag:
        return None

    pairs: list[tuple[str, str]] = []
    for elem in root.iter():
        local = elem.tag.split("}")[-1] if "}" in elem.tag else elem.tag
        if local == "loc" and elem.text:
            url = elem.text.strip()
            pairs.append((url, url))

    logger.debug("Parsed XML sitemap: %d URLs", len(pairs))
    return pairs


def _try_html(content: str) -> list[tuple[str, str]]:
    """Extract (href, link_text) pairs from HTML anchor elements."""
    parser = _AnchorParser()
    parser.feed(content)
    logger.debug("Parsed HTML sitemap: %d anchors", len(parser.pairs))
    return parser.pairs
