# Sources

Sources are rows in the `sources` table, so they can be changed without a deploy. The initial list is created by `SourceTableSeeder`.

## Catalog and subscriptions

A catalog source has `user_id = null`; a custom source belongs to a user. `is_default` marks catalog sources offered automatically for an interest. `site_url` is the website address, separate from the fetch URL. `credentials` is encrypted and hidden from serialization.

`interest_source` links sources to interests. `source_subscriptions` stores each user's enabled or disabled subscriptions. An `enabled = false` subscription records an explicit opt-out and is never automatically re-enabled.

`App\Actions\ChooseInterests::handle($user, $interests)` syncs the user's interests, adds missing subscriptions to enabled default catalog sources, and removes enabled catalog subscriptions no longer covered by the chosen interests. It preserves disabled subscriptions and custom source subscriptions.

`SourceCollector::collectFor($users)` fetches only enabled sources with an enabled subscription among those users, skipping sources fetched successfully less than 25 minutes ago. It looks back to the source's last successful fetch minus one hour, or 24 hours ago on the first fetch. `item_source` records each source where an item was seen, preserving its first sighting time. `failuresFor($user)` returns only errors from that user's enabled subscribed sources for their news footer.

The newsletter fetcher reads only `credentials.api_key` on its own source, never `RESEND_API_KEY`. `pulse:adopt` can copy that environment value into the source's encrypted credentials. Without credentials the source fails with a clear message.

## Interests

`InterestTableSeeder` creates 53 interests across nine categories, initially `draft`. Each has a `has_learning` flag. `Interest::refreshStatuses()` makes an interest `live` when it has at least three enabled default catalog sources; otherwise it is `draft`. `SourceTableSeeder` runs this refresh after linking sources to interests.

## The seeders

```bash
docker compose exec app php artisan db:seed --class=InterestTableSeeder
docker compose exec app php artisan db:seed --class=SourceTableSeeder
docker compose exec app php artisan db:seed --class=TagTableSeeder
docker compose exec app php artisan db:seed --class=LearningTopicTableSeeder
```

Run `InterestTableSeeder` before `SourceTableSeeder`. `DatabaseSeeder` uses the order Interest, Source, Tag, LearningTopic.

The table seeders create missing rows and preserve existing definitions, including edited URLs and disabled sources. `SourceTableSeeder` also links known sources that have no interests yet, preserves existing interest links and refreshes interest statuses. A deleted seeded source comes back on the next run, so disable it instead of deleting it.

## Initial sources

In priority order (`position`): Anthropic News, OpenAI News, Google DeepMind, Hugging Face, MCP Blog, Simon Willison, AINews (smol.ai), Laravel News, Laravel Blog, freek.dev, stitcher.io, Filament Releases, Notion Releases, Railway Blog, Kilo Blog, The Pragmatic Engineer, ByteByteGo, Martin Fowler, Hacker News (front page, 200+ points), Lobsters, Laravel.io Forum, r/laravel, r/ClaudeAI, r/LocalLLaMA, GitHub Trending, Newsletters.

Each source has a default tag (used when the AI model gives none) and a `max_items` limit.

| Source | Note |
|---|---|
| Anthropic News | Anthropic publishes no feed; this uses the community-generated feed from [Olshansk/rss-feeds](https://github.com/Olshansk/rss-feeds). |
| AINews (smol.ai) | Its feed looked paused (last item 2026-09-09) when it was added. |
| Filament | The Insights blog has no feed; only GitHub releases are followed. |
| Reddit | Reddit ends RSS on **2026-11-13**. Disable the three `r-*` sources on that date. |
| Newsletters | Needs `credentials.api_key` on the source. Subscribe newsletters that have no feed with your Resend receiving address. |

## Adding, changing or disabling a source

Use Tinker to manage source definitions:

```bash
docker compose exec app php artisan tinker
```

```php
App\Models\Source::create([
    'slug' => 'my-blog', 'name' => 'My Blog', 'type' => 'feed',
    'url' => 'https://example.com/feed.xml', 'tag' => 'engineering',
    'max_items' => 5, 'position' => 30,
]);

App\Models\Source::where('slug', 'like', 'r-%')->update(['enabled' => false]);
```

A source is collected only when a user has an enabled subscription to it. For a custom source, set `user_id` to its owner and create that user's subscription. To make a catalog source part of the initial list for fresh installs, also add it to `SourceTableSeeder` and link it to the appropriate interests.

Custom feed URLs must be public HTTP(S) addresses. `PublicUrl` validates all resolved addresses before fetching and checks every redirect (up to three); private, loopback, reserved and unresolved addresses are refused. `PublicHttpUrl` is the form validation rule for these URLs.

## Health check

```bash
docker compose exec app php artisan digest:sources            # last 24 hours
docker compose exec app php artisan digest:sources --since=7d
docker compose exec app php artisan digest:sources --user=<email>
```

Without `--user`, it fetches every enabled source; with `--user=<email>`, only enabled sources on that account's enabled subscriptions. It bypasses the collector's 25-minute cache and prints how many items each returned and any error, without storing anything, so it is safe to run at any time, including in production. It exits with an error code if any source failed.

## Rate limits

- **Reddit catalog feeds** allow about one anonymous request per minute and announces the wait in `x-ratelimit-reset`. On a 429 the feed fetcher waits that long (or `Retry-After`) plus 2 seconds, capped at 60, with up to 3 attempts. These waits can add about a minute for the three subreddits.
- Feed requests have a 10-second timeout and send the `pulse-digest/1.0` User-Agent. Catalog feeds retry up to three times; custom feeds use the public URL checks. The Resend client uses a 15-second timeout and up to two attempts.
