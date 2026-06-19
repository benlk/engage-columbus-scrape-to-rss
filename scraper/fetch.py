"""HTTP fetching with retry and exponential backoff."""

import logging
import time

import requests

logger = logging.getLogger(__name__)

SITEMAP_URL = "https://engage.columbus.gov/sitemap"
_USER_AGENT = (
    "engage-columbus-scrape-to-rss/1.0 "
    "(https://github.com/; respectful bot, daily schedule)"
)
_TIMEOUT = 20
_MAX_RETRIES = 3
_BACKOFF_BASE = 2.0


def fetch_url(url: str) -> str | None:
    """Fetch *url* and return the response text.

    Retries up to _MAX_RETRIES times with exponential backoff.
    Returns None (and logs an error) on persistent failure.
    """
    for attempt in range(1, _MAX_RETRIES + 1):
        try:
            resp = requests.get(
                url,
                headers={"User-Agent": _USER_AGENT},
                timeout=_TIMEOUT,
            )
            resp.raise_for_status()
            return resp.text
        except requests.RequestException as exc:
            logger.warning(
                "Attempt %d/%d failed for %s: %s", attempt, _MAX_RETRIES, url, exc
            )
            if attempt < _MAX_RETRIES:
                time.sleep(_BACKOFF_BASE**attempt)

    logger.error("All %d attempts failed for %s", _MAX_RETRIES, url)
    return None
