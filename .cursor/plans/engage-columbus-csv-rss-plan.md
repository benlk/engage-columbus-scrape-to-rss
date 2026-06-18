# Engage Columbus Scrape-to-RSS Plan

## Overview

A Python scraper runs daily via GitHub Actions. It fetches the sitemap page at
`https://engage.columbus.gov/sitemap`, extracts anchor elements (href + link
text), normalizes and filters URLs, appends newly discovered URLs to a CSV
file stored in the repository, and regenerates an RSS feed for newly discovered
links. Changes to the CSV and feed are committed back to the repository
automatically.

## Architecture

```
Daily GitHub Action
  └─► Fetch sitemap page (HTML)
        └─► Extract anchors: normalized_url + link_text
              └─► Filter: *.columbus.gov only
                    └─► Load known URLs from links.csv
                          └─► Diff → new URLs only
                                ├─► Append new rows to links.csv
                                └─► Generate RSS items (title = link_text)
                                      └─► Write feed.xml
                                            └─► Commit if changed
```

## Decisions and Rules

| Concern | Decision |
|---|---|
| Query parameters | Stripped (removed entirely) |
| URL fragments | Stripped |
| Host normalization | Lowercase scheme + host |
| Domain filter | Only `*.columbus.gov` URLs stored |
| RSS title source | Anchor text from sitemap page at time of first discovery |
| Stored title behavior | Title frozen at first discovery; not updated on subsequent runs |
| Run cadence | Daily (`schedule: cron`) |
| Persistence | CSV file committed to repo |
| Overlap protection | GitHub Actions concurrency group (single active run) |

## Outputs

### CSV Schema

File: `data/links.csv`

| Column | Description |
|---|---|
| `url` | Normalized URL (uniqueness key) |
| `title` | Anchor text from sitemap at time of first discovery |
| `first_seen_utc` | ISO 8601 UTC timestamp of first discovery |
| `source` | Always `sitemap` for current scope |
| `run_id` | ISO 8601 timestamp identifying the run that discovered the URL |

### RSS Feed

File: `feed.xml`

- Items sorted newest first.
- Maximum item cap: 500 (configurable).
- Item fields:
  - `<title>`: value from `title` column
  - `<link>` and `<guid>`: value from `url` column
  - `<pubDate>`: value from `first_seen_utc`

## Implementation Steps

### Step 1 — Define canonical URL and domain rules
- Implement `normalize_url(raw: str) -> str | None`
  - Lowercase scheme and host
  - Remove query string
  - Remove fragment
  - Strip trailing slash (or enforce consistently)
  - Return `None` if host is not `*.columbus.gov`
- **Validation:** unit tests confirm query strings removed, fragments removed,
  non-columbus.gov URLs rejected, same-resource variants collapse to one form.

### Step 2 — Implement sitemap scraper (href + anchor text)
- `fetch_sitemap(url: str) -> list[tuple[str, str]]`
  - HTTP GET with timeout (20 s), retry with exponential backoff (3 attempts)
  - Identify content type: try XML sitemap parse first, fall back to HTML anchor extraction
  - Return list of `(raw_href, link_text)` pairs
  - Set identifying `User-Agent` header
- **Validation:** returns pairs; empty list on HTTP error (logged, not raised).

### Step 3 — Implement CSV persistence layer
- `load_known_urls(path: Path) -> set[str]`
  - Initialize file with header if missing
  - Return set of all known `url` values
- `append_new_rows(path: Path, rows: list[dict]) -> None`
  - Write to temp file then atomically replace to avoid corruption
- **Validation:** second load after append returns superset; no duplicate rows.

### Step 4 — Build discovery pipeline (dedupe + append)
- Combine Steps 2 and 3:
  1. Fetch sitemap → raw pairs
  2. Normalize each URL
  3. Diff against known set → `new_entries`
  4. Append `new_entries` to CSV
- **Validation:** rerunning against unchanged sitemap produces zero appended rows
  and no file modification.

### Step 5 — Build RSS generation from newly discovered rows
- `build_feed(new_rows: list[dict], existing_feed_path: Path) -> str`
  - Load existing feed items (up to cap)
  - Prepend new items (title from `title`, link/guid from `url`, pubDate from `first_seen_utc`)
  - Enforce max 500 items
  - Return valid RSS 2.0 XML string
- **Validation:** XML validates; title equals stored link text; no duplicate GUIDs.

### Step 6 — Configure daily GitHub Action
- `.github/workflows/scrape.yml`
  - Triggers: `schedule` (daily at 06:00 UTC) + `workflow_dispatch`
  - Concurrency group: `scrape-rss` with `cancel-in-progress: false`
  - Permissions: `contents: write`
  - Steps:
    1. `actions/checkout`
    2. Set up Python, install pinned dependencies
    3. Run scraper script
    4. Commit and push if `data/links.csv` or `feed.xml` changed
- **Validation:** workflow runs end-to-end; no commit produced when sitemap unchanged.

### Step 7 — Add tests and guardrails
- Tests for: normalization, domain filter, HTML/XML parsing, dedupe, no-change
  run, RSS title correctness, CSV atomic write.
- Add feed XML validation step in workflow.
- **Validation:** all tests pass in CI.

### Step 8 — Document operational behavior
- `README.md` covering:
  - Daily schedule and manual trigger
  - columbus.gov-only policy and query-stripping behavior
  - Title source and frozen-title policy
  - Feed file location and max cap
  - How to inspect/roll back CSV via git history
- **Validation:** new contributor can understand and operate the project from README alone.

## Project Structure

```
engage-columbus-scrape-to-rss/
├── .github/
│   └── workflows/
│       └── scrape.yml
├── .cursor/
│   └── plans/
│       └── engage-columbus-csv-rss-plan.md   ← this file
├── data/
│   └── links.csv
├── scraper/
│   ├── __init__.py
│   ├── fetch.py        # HTTP fetch + retry
│   ├── parse.py        # Sitemap XML / HTML anchor extraction
│   ├── normalize.py    # URL normalization + domain filter
│   ├── store.py        # CSV read/write
│   └── feed.py         # RSS generation
├── tests/
│   ├── test_normalize.py
│   ├── test_parse.py
│   ├── test_store.py
│   └── test_feed.py
├── main.py             # Entrypoint wiring all modules
├── requirements.txt
└── README.md
```

## Open Question (deferred)

If a URL's anchor text changes in the sitemap after first discovery:
the stored `title` is **frozen** at first discovery and will not be updated.
A future enhancement could optionally track title changes in a separate audit
column without affecting RSS output.
