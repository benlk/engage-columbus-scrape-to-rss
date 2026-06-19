"""CSV persistence layer for discovered links."""

import csv
import os
import tempfile
from pathlib import Path

FIELDNAMES = ["url", "title", "first_seen_utc", "source", "run_id"]
_HEADER_LINE = ",".join(FIELDNAMES) + "\n"


def load_known_urls(path: Path) -> set[str]:
    """Load all known URLs from the CSV file.

    If the file does not exist it is created with a header row.
    Returns a set of URL strings.
    """
    if not path.exists():
        path.parent.mkdir(parents=True, exist_ok=True)
        with open(path, "w", newline="", encoding="utf-8") as fh:
            csv.DictWriter(fh, fieldnames=FIELDNAMES).writeheader()
        return set()

    known: set[str] = set()
    with open(path, newline="", encoding="utf-8") as fh:
        for row in csv.DictReader(fh):
            url = row.get("url", "").strip()
            if url:
                known.add(url)
    return known


def append_new_rows(path: Path, rows: list[dict]) -> None:
    """Append *rows* to the CSV file atomically (via temp-file + os.replace).

    If *rows* is empty, the file is not touched.
    """
    if not rows:
        return

    path.parent.mkdir(parents=True, exist_ok=True)

    # Read existing content to preserve it in the atomic rewrite
    existing: list[str] = []
    if path.exists():
        with open(path, newline="", encoding="utf-8") as fh:
            existing = fh.readlines()

    if not existing:
        existing = [_HEADER_LINE]

    fd, tmp_path = tempfile.mkstemp(dir=path.parent, suffix=".tmp")
    try:
        with os.fdopen(fd, "w", newline="", encoding="utf-8") as fh:
            fh.writelines(existing)
            writer = csv.DictWriter(
                fh, fieldnames=FIELDNAMES, extrasaction="ignore"
            )
            for row in rows:
                writer.writerow(row)
        os.replace(tmp_path, path)
    except Exception:
        try:
            os.unlink(tmp_path)
        except OSError:
            pass
        raise
