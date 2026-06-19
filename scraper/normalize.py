"""URL normalization and domain filtering."""

from typing import Optional
from urllib.parse import urlparse, urlunparse

_ALLOWED_DOMAIN = "columbus.gov"
_ALLOWED_SUFFIX = ".columbus.gov"


def normalize_url(raw: str) -> Optional[str]:
    """Normalize a URL and return it, or None if it should be rejected.

    Rules applied:
    - Scheme and host are lowercased.
    - Query string is removed entirely.
    - Fragment is removed.
    - Trailing slash on non-root paths is stripped.
    - Only http/https schemes are accepted.
    - Only *.columbus.gov and columbus.gov hosts are accepted.
    """
    if not raw or not raw.strip():
        return None

    try:
        parsed = urlparse(raw.strip())
    except Exception:
        return None

    scheme = parsed.scheme.lower()
    if scheme not in ("http", "https"):
        return None

    if not parsed.netloc:
        return None

    # Strip port before domain check
    host = parsed.netloc.lower().split(":")[0]

    if host != _ALLOWED_DOMAIN and not host.endswith(_ALLOWED_SUFFIX):
        return None

    path = parsed.path
    if path != "/" and path.endswith("/"):
        path = path.rstrip("/")

    # Reconstruct without query or fragment
    return urlunparse((scheme, host, path, "", "", ""))
