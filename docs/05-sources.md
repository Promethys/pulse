# Sources

Sources are rows in the `sources` table, so they can be changed without a deploy (and later from the web app). The initial list is created by `SourceTableSeeder`.

## The seeders

```bash
docker compose exec app php artisan db:seed --class=SourceTableSeeder
docker compose exec app php artisan db:seed --class=TagTableSeeder
```

Both are idempotent: they only create rows that are missing and never change an existing row. Re-running them is safe on every deploy, and edits made in the database (a new URL, a disabled source) are kept. A deleted source comes back on the next run.

## Initial sources

In priority order (`position`): Anthropic News, OpenAI News, Google DeepMind, Hugging Face, MCP Blog, Simon Willison, AINews (smol.ai), Laravel News, Laravel Blog, freek.dev, stitcher.io, Filament Releases, Notion Releases, Railway Blog, Kilo Blog, The Pragmatic Engineer, ByteByteGo, Martin Fowler, Hacker News (front page, 200+ points), Lobsters, Laravel.io Forum, GitHub Trending, Newsletters.

Each source has a default tag (used when the AI model gives none) and a `max_items` limit.

| Source | Note |
|---|---|
| Anthropic News | Anthropic publishes no feed; this uses the community-generated feed from [Olshansk/rss-feeds](https://github.com/Olshansk/rss-feeds). |
| AINews (smol.ai) | Its feed looked paused (last item 2026-09-09) when it was added. |
| Filament | The Insights blog has no feed; only GitHub releases are followed. |
| Reddit | The three Reddit sources were disabled when Reddit ended RSS on **2026-11-13**. Existing rows are kept for history and fresh installs do not seed them. |
| Newsletters | Needs `RESEND_API_KEY`. Subscribe newsletters that have no feed with your Resend receiving address. |

## Adding, changing or disabling a source

Until the web app exists, use Tinker:

```bash
docker compose exec app php artisan tinker
```

```php
App\Models\Source::create([
    'slug' => 'my-blog', 'name' => 'My Blog', 'type' => 'feed',
    'url' => 'https://example.com/feed.xml', 'tag' => 'engineering',
    'max_items' => 5, 'position' => 30,
]);

App\Models\Source::where('slug', 'my-blog')->update(['enabled' => false]);
```

To make a new source part of the initial list for fresh installs, also add it to `SourceTableSeeder`.

## Health check

```bash
docker compose exec app php artisan digest:sources            # last 24 hours
docker compose exec app php artisan digest:sources --since=7d
```

It fetches every enabled source and prints how many items each returned and any error, without storing anything, so it is safe to run at any time, including in production. It exits with an error code if any source failed.

## Rate limits

- On a 429, the feed fetcher handles any feed announcing its wait in `Retry-After` or `x-ratelimit-reset` (originally added for Reddit). It waits that long plus 2 seconds, capped at 60, with up to 3 attempts.
- Every request has a 10-second timeout and sends the `pulse-digest/1.0` User-Agent.
