# Engage Columbus Scrape-to-RSS

A GitHub Action that runs daily, scrapes the [Engage Columbus sitemap](https://engage.columbus.gov/sitemap), discovers new pages, and publishes an RSS feed of newly found links.

## How It Works

This project runs a daily scraper which:

1. Fetches `https://engage.columbus.gov/sitemap` each day.
2. Extracts links from the page
3. Filters URLs to just columbus.gov URLs, removes URL parameters.
4. Adds new URLs to a CSV.
5. Generates [an RSS feed](./feed.xml) of items
6. If anything changed, the workflow commits and pushes the updated CSV and feed back to the repository.

## Feed

The RSS feed is available at: https://raw.githubusercontent.com/benlk/engage-columbus-scrape-to-rss/main/feed.xml

RSS item fields:

| Field | Source |
|---|---|
| `<title>` | Link text from sitemap at time of first discovery |
| `<link>` / `<guid>` | Normalized URL |
| `<pubDate>` | UTC timestamp of first discovery |

The feed is capped at the most-recent 500 items; this isn't likely to be hit any time soon.

## Data

`data/links.csv` is the source of truth for all discovered URLs. Columns:

| Column | Description |
|---|---|
| `url` | Normalized URL (primary key) |
| `title` | Anchor text at time of first discovery |
| `first_seen_utc` | ISO 8601 UTC timestamp |
| `source` | Always `sitemap`, for now. |
| `run_id` | Timestamp of the run that discovered the URL |

## Schedule

The workflow runs **daily at 06:00 UTC**. You can also trigger it manually:

1. Go to **Actions** → **Scrape Engage Columbus Sitemap**.
2. Click **Run workflow**.

Only one run executes at a time (concurrency lock). A run that finds no new URLs produces no commit.

To roll back a bad run: use `git log -- data/links.csv` to find the commit and `git revert` or `git checkout <sha> -- data/links.csv feed.xml` to restore a previous state.

## Local Development

```bash
# Optional: validate PHP requirement metadata
php -v

# Run the scraper once
php main.php
```

Requires PHP 8.1+