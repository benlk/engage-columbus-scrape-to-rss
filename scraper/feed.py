"""RSS 2.0 feed generation."""

import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from email.utils import formatdate
from pathlib import Path

MAX_FEED_ITEMS = 500

_FEED_TITLE = "Engage Columbus — New Pages"
_FEED_LINK = "https://engage.columbus.gov/sitemap"
_FEED_DESCRIPTION = (
    "Newly discovered pages on Engage Columbus (engage.columbus.gov)"
)


def _to_rfc2822(iso_ts: str) -> str:
    """Convert an ISO 8601 UTC timestamp string to RFC 2822 format."""
    ts = iso_ts.replace("Z", "+00:00")
    dt = datetime.fromisoformat(ts)
    return formatdate(dt.timestamp(), usegmt=True)


def _load_existing_items(feed_path: Path) -> list[dict]:
    """Parse an existing feed.xml and return its items as a list of dicts."""
    if not feed_path.exists():
        return []
    try:
        root = ET.parse(feed_path).getroot()
    except ET.ParseError:
        return []

    channel = root.find("channel")
    if channel is None:
        return []

    items = []
    for elem in channel.findall("item"):
        items.append(
            {
                "title": elem.findtext("title", ""),
                "link": elem.findtext("link", ""),
                "guid": elem.findtext("guid", ""),
                "pubDate": elem.findtext("pubDate", ""),
            }
        )
    return items


def build_feed(new_rows: list[dict], feed_path: Path) -> str:
    """Build an RSS 2.0 XML string.

    New items are prepended to any existing items; the total is capped at
    MAX_FEED_ITEMS. Items are sorted newest-first within the new batch.
    """
    existing = _load_existing_items(feed_path)
    existing_guids = {item["guid"] for item in existing}

    new_items = []
    for row in new_rows:
        url = row["url"]
        if url in existing_guids:
            continue
        new_items.append(
            {
                "title": row.get("title") or url,
                "link": url,
                "guid": url,
                "pubDate": _to_rfc2822(row["first_seen_utc"]),
            }
        )

    all_items = new_items + existing
    all_items = all_items[:MAX_FEED_ITEMS]

    rss = ET.Element("rss")
    rss.set("version", "2.0")
    channel = ET.SubElement(rss, "channel")
    ET.SubElement(channel, "title").text = _FEED_TITLE
    ET.SubElement(channel, "link").text = _FEED_LINK
    ET.SubElement(channel, "description").text = _FEED_DESCRIPTION
    ET.SubElement(channel, "lastBuildDate").text = formatdate(usegmt=True)

    for item in all_items:
        item_elem = ET.SubElement(channel, "item")
        ET.SubElement(item_elem, "title").text = item["title"]
        ET.SubElement(item_elem, "link").text = item["link"]
        guid_elem = ET.SubElement(item_elem, "guid")
        guid_elem.set("isPermaLink", "true")
        guid_elem.text = item["guid"]
        ET.SubElement(item_elem, "pubDate").text = item["pubDate"]

    ET.indent(rss, space="  ")
    return '<?xml version="1.0" encoding="UTF-8"?>\n' + ET.tostring(
        rss, encoding="unicode"
    )


def write_feed(feed_path: Path, xml_str: str) -> None:
    """Write *xml_str* to *feed_path*, creating parent directories as needed."""
    feed_path.parent.mkdir(parents=True, exist_ok=True)
    feed_path.write_text(xml_str, encoding="utf-8")
