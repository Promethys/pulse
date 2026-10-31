# Sources

Sources are rows in the `sources` table, so they can be changed without a deploy. The initial list is created by `SourceTableSeeder`.

## Catalog and subscriptions

A catalog source has `user_id = null`; a custom source belongs to a user. `is_default` marks catalog sources offered automatically for an interest. `site_url` is the website address, separate from the fetch URL. `credentials` is encrypted and hidden from serialization.

`interest_source` links sources to interests. `source_subscriptions` stores each user's enabled or disabled subscriptions. An `enabled = false` subscription records an explicit opt-out and is never automatically re-enabled.

`App\Actions\ChooseInterests::handle($user, $interests)` syncs the user's interests, adds missing subscriptions to enabled default catalog sources, and removes enabled catalog subscriptions no longer covered by the chosen interests. It preserves disabled subscriptions and custom source subscriptions.

`SourceCollector::collectFor($users)` fetches only enabled sources with an enabled subscription among those users, skipping sources fetched successfully less than 25 minutes ago. It looks back to the source's last successful fetch minus one hour, or 24 hours ago on the first fetch. `item_source` records each source where an item was seen, preserving its first sighting time. `failuresFor($user)` returns only errors from that user's enabled subscribed sources for their news footer.

The newsletter fetcher reads only `credentials.api_key` on its own source, never `RESEND_API_KEY`. `pulse:adopt` can copy that environment value into the source's encrypted credentials. Without credentials the source fails with a clear message.

## Managing sources in the web app

`/sources` has By interest and My sources tabs; `/sources?tab=mine` opens custom sources directly. The page shows health, last success and fetch errors. Health is `failing` when an error is stored, `ok` after a successful fetch within 48 hours, and `unknown` otherwise. Add or remove interests there to update catalog subscriptions.

Catalog sources can be disabled for the account through its subscription; users cannot edit or delete catalog definitions. Users can add, edit, disable or delete their own custom sources, up to 30 per account. Deleting a custom source removes its subscriptions and source links while preserving shared items and past digests.

Custom sources are either a public RSS/Atom feed or a Resend newsletter inbox. Each requires a name and at least one interest; a feed requires its URL, and a new inbox requires the user's Resend key. The type cannot change after creation. Leaving the key empty on edit keeps the stored key; the resource reports only `has_key`. Pulse does not provision an inbox address.

Test now reads the last 30 days without saving anything and returns up to five headlines. The endpoint allows six requests per minute and uses the same public URL checks as collection.

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

Use the [web app](#managing-sources-in-the-web-app) for personal sources and subscriptions. To manage catalog definitions at the instance level, use Tinker:

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

## Monthly catalog check

`digest:tick` queues one `CheckCatalogSource` job for each enabled catalog source with no check in the current UTC month, and each result is stored in `source_checks`. `php artisan catalog:refresh` does the same by hand, and `--force` ignores the month.

A check never changes `last_success_at`, because the daily collection uses it as its fetch window.

A source is retired, meaning disabled with `retired_at` set and never deleted, after a failed check when either rule applies:

- the previous check failed too;
- it has an enabled subscription and no successful fetch for 30 days.

The 30-day rule needs a subscription because unsubscribed sources are not collected daily. Subscribers see a retired source marked on `/sources`. Its switch is disabled and the server refuses changes to it.

When a feed fails, Pulse looks for a moved feed by following redirects, then through `<link rel="alternate">` on `site_url`. A working URL becomes a pending `url_change` row in `source_proposals`. It never changes the source by itself.

## Scout

Once a month, `digest:tick` queues one `ScoutInterest` job for each live interest that was not scouted this UTC month. `php artisan catalog:scout --interest=<slug> --max=5` runs it by hand, including for draft interests, and `--category=<category>` covers every draft interest of a category.

`SourceScout` first tries a provider of the instance chain that supports web search. If that fails or returns nothing usable, it retries the full chain without search, from what the model knows. A failed search only logs a line.

Models name real sites but often guess the feed path, so when a suggested URL is not a feed, the scout uses the feed the site advertises through `<link rel="alternate">`. Each suggestion goes through `ProposalChecker`: public URL, not already in the catalog (feed URL and domain), the feed parses, and at least 3 posts in the last 30 days. Passing suggestions become pending rows in `source_proposals`, failing ones are dropped. A URL already proposed in any status is skipped, so rejected feeds do not return.

## Reviewing proposals

Admins review proposals at `/admin/catalog`. Approving adds a catalog source with the chosen interests. A default source also subscribes the users of those interests who have no subscription to it, and never changes an existing one. Applying a URL change updates the source, and re-enables it when it was retired. Nothing enters the catalog without this review. Both review tables have search, filters, column toggles and bulk approve and reject. Bulk approval keeps each proposal's own interest and skips what it cannot approve.

## Suggestions from users

A user can suggest one of their custom feeds for the catalog, and suggest a new interest, including during onboarding. A feed suggestion goes through `ProposalChecker` straight away, and a failure is shown to the user. A url that was already proposed, in any status, cannot be suggested again. Users have at most 10 open proposals.

Feed suggestions join the review at `/admin/catalog`, and interest suggestions are reviewed at `/admin/interest-proposals`. Approving an interest creates it as `draft` and queues `ScoutInterest` for it, so it goes live once it has enough default sources.

## Rate limits

- **Reddit catalog feeds** allow about one anonymous request per minute and announces the wait in `x-ratelimit-reset`. On a 429 the feed fetcher waits that long (or `Retry-After`) plus 2 seconds, capped at 60, with up to 3 attempts. These waits can add about a minute for the three subreddits.
- Feed requests have a 10-second timeout and send the `pulse-digest/1.0` User-Agent. Catalog feeds retry up to three times; custom feeds use the public URL checks. The Resend client uses a 15-second timeout and up to two attempts.
