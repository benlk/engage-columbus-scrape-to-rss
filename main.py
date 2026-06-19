"""Entrypoint: orchestrates fetch → parse → normalize → dedupe → store → feed."""

import logging
import sys
from datetime import datetime, timezone
from pathlib import Path

from scraper.feed import build_feed, write_feed
from scraper.fetch import SITEMAP_URL, fetch_url
from scraper.normalize import normalize_url
from scraper.parse import parse_sitemap
from scraper.store import append_new_rows, load_known_urls

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
    stream=sys.stdout,
)
logger = logging.getLogger(__name__)

_LINKS_CSV = Path("data/links.csv")
_FEED_FILE = Path("feed.xml")


def main() -> int:
    run_id = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
    logger.info("Run started: %s", run_id)

    # 1. Fetch sitemap
    content = fetch_url(SITEMAP_URL)
    if content is None:
        logger.error("Failed to fetch sitemap; aborting run.")
        return 1

    # 2. Parse into (raw_url, link_text) pairs
    raw_pairs = parse_sitemap(content)
    logger.info("Extracted %d raw pairs from sitemap", len(raw_pairs))

    # 3. Normalize and filter; keep first occurrence of each URL
    normalized: dict[str, str] = {}  # url -> title
    for raw_url, link_text in raw_pairs:
        url = normalize_url(raw_url)
        if url and url not in normalized:
            normalized[url] = link_text
    logger.info(
        "%d unique columbus.gov URLs after normalization", len(normalized)
    )

    # 4. Load known URLs and compute diff
    known = load_known_urls(_LINKS_CSV)
    logger.info("%d URLs already recorded in CSV", len(known))

    new_entries = [
        {
            "url": url,
            "title": title,
            "first_seen_utc": run_id,
            "source": "sitemap",
            "run_id": run_id,
        }
        for url, title in normalized.items()
        if url not in known
    ]
    logger.info("%d new URLs discovered this run", len(new_entries))

    if not new_entries:
        logger.info("No new URLs found; skipping CSV and feed updates.")
        return 0

    # 5. Persist new URLs
    append_new_rows(_LINKS_CSV, new_entries)
    logger.info("Appended %d rows to %s", len(new_entries), _LINKS_CSV)

    # 6. Regenerate RSS feed
    xml_str = build_feed(new_entries, _FEED_FILE)
    write_feed(_FEED_FILE, xml_str)
    logger.info("Feed written to %s", _FEED_FILE)

    logger.info("Run %s complete.", run_id)
    return 0


if __name__ == "__main__":
    sys.exit(main())
