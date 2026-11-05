# Architecture

Pulse is a Laravel 13 app with a Vue 3, Inertia 3 and Tailwind CSS 4 web app for reading and account settings. The digest runs from the command line and does not depend on the web app. Its pipelines generate one post per user, local date and kind, using that user's settings, subscriptions and AI chain, then deliver it to their enabled destinations.

## Code layout

| Path | Responsibility |
|---|---|
| `app/Digest/Sources/` | Fetching: one fetcher per source type, URL normalization, the collector |
| `app/Digest/Ai/` | The instance provider chain and per-user `AiChainResolver` |
| `app/Digest/News/` | Per-user candidate query, selection and news pipeline |
| `app/Digest/Learning/` | Per-user topic rotation, writing, link checks and learning pipeline |
| `app/Digest/Rendering/` | Markdown rendering and message splitting |
| `app/Digest/Delivery/` | Per-destination delivery drivers and `PostDeliverer` |
| `app/Digest/Discord/` | Bot API client and forum tag matching |
| `app/Digest/Support/` | Text, tags and public URL checks |
| `app/Jobs/RunDigest.php` | Queued generation and delivery for one user, kind and date |
| `app/Ai/Agents/` | `laravel/ai` agents (prompt + JSON schema) |
| `app/Reading/` | Account-scoped day, archive, search, tag, learning and source queries |
| `app/Http/Resources/` | Explicit field allowlists for Inertia props and rendered learning HTML |
| `app/Policies/` | Ownership checks for posts, learning, sources, AI keys and destinations |
| `app/Actions/` | Registration, onboarding, interests, custom sources, AI keys and delivery settings |
| `app/Http/Controllers/`, `app/Http/Requests/` | Web pages, actions and request validation |
| `resources/js/pages/`, `resources/js/components/pulse/` | Inertia pages and shared digest components |
| `resources/js/layouts/`, `resources/css/app.css` | App, auth and settings layouts, typography and light/dark tokens |
| `app/Http/Controllers/Discord/` | Add to Discord install and callback |
| `app/Console/Commands/` | `digest:*` commands, `catalog:refresh`, `catalog:scout` and `pulse:adopt` |
| `app/Models/`, `app/Enums/` | Data model |
| `database/seeders/` | Interests, sources, tags and learning topics |

## Web app

`routes/web.php` defines reading, sources, onboarding and Discord routes; `routes/settings.php` defines account settings. Reading pages and sources require authentication, verified email and completed onboarding. `EnsureOnboarded` redirects verified accounts with no `onboarded_at` to `/onboarding`, creating default digest settings when missing. Onboarding and Discord install/callback require authentication and verified email but stay outside this gate.

### Pages and navigation

| GET path | Page and behavior |
|---|---|
| `/` | Public landing page for guests and signed-in users |
| `/features`, `/open-source` | Public pages, open to guests and signed-in users |
| `/privacy`, `/terms` | Public legal pages. The contact address comes from `SUPPORT_EMAIL` |
| `/docs` | Public user documentation home, with search in the browser over titles, summaries and headings |
| `/docs/{section}/{article}` | One article; slugs not listed in `config/docs.php` show the docs 404 |
| `/dashboard` | Today, after authentication, email verification and onboarding |
| `/onboarding` | Interests, Sources, About you, AI key, Delivery; completed accounts redirect to `/dashboard` |
| `/days/{date}` | Saved day for the account; real `Y-m-d` required, today redirects to `/dashboard`, missing days return 404 |
| `/archive` | One row per saved date, newest first, 30 dates per page |
| `/search` | News and Learning tabs, totals for both, 30 results per page on the active tab |
| `/tags/{tag:slug}` | Personal news selections for an existing tag, total count and 12 weekly activity counts |
| `/learning` | Tips and topics grouped by digest date, filtered by kind, category and tag, 30 entries per page |
| `/learning/{id}` | Full tip or topic; unknown entries and another account's entries return 404 |
| `/sources` | Catalog subscriptions by interest and the account's custom sources |
| `/settings` | Settings index, Sources link and logout |
| `/settings/profile`, `/settings/security`, `/settings/appearance` | Profile, password/passkeys/two-factor security and theme |
| `/settings/digest`, `/settings/delivery`, `/settings/ai-keys` | Digest preferences, external destinations and personal AI chain |

Settings use the onboarding gate. Profile viewing and updating require authentication; the other settings routes also require verified email. Security requires password confirmation. Fortify provides registration, login, email verification, password reset and two-factor authentication, with `/dashboard` as its home path. The email verification page displays a muted notice about the instance's limited daily email quota and manual verification when the link does not arrive.

App logos return to the public landing page; signed-in visitors use **Open Pulse** on the public site to reach `/dashboard`. The desktop sidebar links Today, Archive, Search, Learning and Sources; the user menu opens Settings. Mobile uses bottom tabs with Settings, whose index also links to Sources. `resources/js/lib/navigation.ts` defines navigation and active states; `settingsSections.ts` defines the settings tabs. The app supports light, dark and system appearance through `useAppearance`. Calendar popovers use reka-ui and `@internationalized/date`; digest dates remain `Y-m-d` values without browser timezone shifts.

Onboarding saves Interests, Sources, About you and AI key on Continue. At least one live interest is required, draft interests are disabled, and About you and AI key can be skipped. The final step offers optional email delivery alongside in-app delivery and sets `onboarded_at`. Completing onboarding without an AI key is allowed; the scheduler still requires personal keys or instance AI access.

Today reads the account's local date and falls back to the latest saved day only when today has no post at all. It shows a banner while today's news is being prepared, a note when it falls back to an older day, and a checklist for interests, enabled subscriptions, AI access and an external destination. Day views distinguish pending, failed and quiet news, show available learning cards, and navigate between saved dates. On Today, the banner replaces the pending news line. Learning cards link to full entries; their tags filter Learning, while news tags open the news tag page.

Archive, Search, Tag and Learning redirect pages beyond the last page to the last valid page. Search and Learning preserve their query filters in pagination links. A known tag without personal news renders an empty page; an unknown slug returns 404. Tag activity uses twelve consecutive seven-day windows ending on the account's local today, oldest first.

### User documentation

Articles are Markdown files at `resources/docs/{section}/{article}.md`. `config/docs.php` holds the order, titles and summaries: an article needs both a config entry and a file. `App\Docs\DocsLibrary` finds articles through config only, and renders them on the server with raw HTML stripped and unsafe links disabled. Callouts use `> [!NOTE]`, `> [!TIP]` and `> [!WARNING]`. The article text is written for readers; these `docs/` pages are for developers.

### Read layer and resources

Every query in `app/Reading/` takes the account explicitly and scopes personal records through `posts.user_id`. `DayQuery` and the Discord news renderer share `NewsSections`: assign an item to its most frequent tag that day, break ties by slug, use `general` for untagged items, then order sections by size and name. Archive counts dates rather than posts; Tag counts personal selections rather than shared fetched items. Source relations expose only catalog sources and the account's own custom sources.

Controllers resolve `app/Http/Resources/` to plain arrays for Inertia props. Resources allowlist fields, format digest dates as `Y-m-d` and timestamps as ISO 8601, and serialize enum values. AI keys expose a suffix, sources expose `has_key`, and delivery destinations expose only their allowed target fields with a masked webhook suffix. Encrypted credentials and settings are never returned wholesale. Policies authorize model actions; custom source edits/deletes, AI key actions and delivery changes require ownership. In-app destination changes are explicitly rejected by the delivery request/controller.

`LearningPostResource` converts stored Markdown to `body_html` on the server, strips raw HTML and rejects unsafe links. `phiki/phiki` highlights fenced code through its CommonMark extension with GitHub light and dark themes. Vue renders that HTML in the shared `.pulse-prose` style; list cards can expand their body and detail pages show it in full.

### Search

`SearchQuery` searches personal `post_items` titles and summaries for news, and `learning_posts` titles and bodies for learning. Both tables have a hidden, stored generated `search` column of type `tsvector` and a GIN index. The migrations coalesce nullable text before building the English vector:

```sql
to_tsvector('english', coalesce(title, '') || ' ' || coalesce(summary, ''))
```

Learning uses `body` in place of `summary`. Queries use the bound expression `search @@ websearch_to_tsquery('english', ?)`, supporting English stemming and web-search syntax. This avoids `whereFullText` concatenation of separate vectors: `tsvector || NULL` is NULL, which would hide headlines with no summary. Results are ordered by digest date descending, then news position or learning kind (tip before topic), rather than relevance.

`SearchFilters` carries `q`, `tags`, `from`, `to`, `type` (news by default), `source`, `kind` and `category`. Tags match any selected slug; date bounds are inclusive digest dates. Source applies to news, kind and category to learning. Filter options come only from the account's saved history. With no text query, search browses the filtered history. The UI debounces text by 300 ms, applies filters immediately, keeps them in the URL and resets pagination on changes. Switching tabs keeps text, tags and dates and clears tab-specific filters. Clear filters keeps the text and active tab.

See [Sources](05-sources.md#managing-sources-in-the-web-app), [Configuration](03-configuration.md) and [AI providers](06-ai.md#per-user-keys) for writes, limits and provider ordering.

### Errors

With debug disabled, browser errors 403, 404, 419, 429, 500 and 503 render the Inertia Error page; JSON and API responses keep their normal format. The page links to Today and offers reload for 500 and 503. Secret form fields (`api_key`, `webhook_url`, `settings`) are excluded from flashed input. Source, AI key and delivery test endpoints return friendly JSON results and log failures with secrets redacted.

## Accounts

Registration creates a `digest_settings` row and an enabled `in_app` delivery channel for both news and learning in one transaction. Settings hold the timezone (default `UTC`), profile, news and learning toggles and times (default `09:00` and `09:30`), `max_items` (default 20) and nullable `onboarded_at`. Validation rules accept times in 15-minute steps and `max_items` from 1 to 20.

`users.is_admin` and `users.uses_instance_ai` default to false. Both are excluded from mass assignment and serialization; they are set through confirmed administrator actions or Artisan commands (`pulse:adopt` grants both). `User::learningAvailable()` returns true only when the user has at least one interest with `has_learning = true`. Both the scheduler and learning pipeline enforce this check.

The Filament admin panel lives at `/admin`, for verified users with `is_admin`. It has no login of its own: guests go to the app's login, so two-factor authentication still applies. Inertia visits to `/admin` become a full page load through `ForceFullPageLoad`, so link to it with a plain `<a>` when you can. The app's user menu links to the panel for those users (`auth.canAccessAdmin`), and the panel's user menu links back.

`UserResource` exposes account facts only, including `digestSettings.onboarded_at`, with confirmed verification and access actions. Administrator removal locks the administrator rows and refuses to remove the last one. `SourceResource` scopes every record lookup to catalog sources, including table action resolution, and selects the latest check result with a subquery. Source metadata edits use a modal with read-only URL and type. `InterestResource` uses modal creation and editing, validates slug uniqueness and loads source and reader counts with `withCount`. None of these resources offers deletion, and users and sources have no creation action. User resources have no edit form or relation managers. See [Admin access](02-getting-started.md#admin-access) for the available controls.

Personal news selections live in `post_items`, with a copy of each selected item's title, summary, position and tags (`post_item_tag`). Shared items remain unchanged by curation. `learning_topic_usages` records one topic per user and day.

Delivery channels store a type (`discord_bot`, `discord_webhook`, `email` or `in_app`), accepted post `kinds`, `enabled`, encrypted settings hidden from serialization, and `verified_at`. `PostDeliverer` selects enabled channels of the post's owner that accept its kind. Verification time is stored but is not a delivery filter. A `deliveries` row tracks each post/channel pair with status (`pending`, `delivered`, `failed`), failed attempts, progress, external id, last error and delivery time.

### Add to Discord

Authenticated users with verified email can visit `/discord/install`. The OAuth2 flow requests the `bot` scope and permissions `274877975568`. `/discord/callback` consumes a single-use session state and records the installed guild in `discord_guilds`. The guild comes only from Discord's token response, never the callback's guild query parameter. Installing records the guild and redirects to `/settings/delivery`; it does not create a delivery channel. Users select a forum there to add a destination. Forum discovery is cached for five minutes per guild; a Discord failure leaves the page usable with an empty forum list and an error indicator.

`DiscordClient::forumChannels($guildId)` returns forum channel ids and names, ordered by Discord channel position. `DiscoverDiscordForums` caches a non-empty list for 5 minutes and never caches an empty one, so a forum created in Discord shows up on the next page load. See [Configuration](03-configuration.md) for the client credentials and redirect URL.

### `pulse:adopt`

`php artisan pulse:adopt <email> [--dry-run]` imports the instance configuration and history for an existing user. It copies digest settings, selects interests linked to enabled catalog sources and creates subscriptions through `ChooseInterests`. The newsletters source becomes the user's custom source, with the Resend key saved as credentials if credentials are empty.

The command resolves the configured news and learning forums through Discord, records their guilds and creates verified bot delivery channels (one channel when both forum ids match). It grants administrator and instance AI access, assigns posts whose `user_id` is null and ensures an in-app channel exists. Settings enable both digests and mark onboarding complete.

Existing single-owner installs must run adoption on the previous version before migrating to this version: the migration that removes the single-owner columns refuses unowned posts. Migrations copy historical selections and source sightings; adoption on the previous version imports topic usage and delivered Discord thread progress before the old columns are removed.

The import is idempotent and transactional; `--dry-run` prints counts and rolls back database changes, but still reads Discord. Missing forum ids are skipped with warnings. Re-running reapplies environment settings and Discord destinations. See [Getting started](02-getting-started.md) for commands.

## Collecting

`SourceCollector::collectFor($users)` fetches enabled sources with at least one enabled subscription among the supplied users. Sources fetched successfully less than 25 minutes ago are skipped, so multiple users share recent results.

1. The source's `SourceType` resolves its fetcher: `FeedFetcher` (RSS/Atom), `GithubTrendingFetcher` (HTML) or `ResendInboxFetcher` (Resend Receiving).
2. The fetch window starts one hour before that source's last successful fetch, or 24 hours ago when it has never succeeded.
3. Fetchers return `Candidate` objects: title, URL, plain-text excerpt (300 characters, 3,000 for newsletters), publication date and allowed citation `links`.
4. Items are deduplicated by normalized URL (`UrlNormalizer`: lowercase host, no `www.`, tracking parameters, trailing slash or fragment, sorted query). `item_source` records every source in which an item was seen and preserves its `first_seen_at` for that source.
5. Source rows record `last_fetched_at`, `last_success_at` and `last_error`. One failing source never stops collection. `failuresFor($user)` limits the news footer to that user's enabled subscribed sources with errors.

Custom feeds are fetched only from public HTTP(S) addresses. `PublicUrl` checks every resolved address and each redirect (up to three); private, loopback and reserved addresses are refused. `PublicHttpUrl` provides the corresponding form validation rule. The inbox fetcher reads only the source's encrypted `credentials.api_key`.

## Selecting the news

`CandidateQuery::for($user, $since)` finds items first seen on enabled sources with an enabled subscription for that user. Sources must be catalog sources or belong to the user. Items already selected in any of the user's posts are excluded, while another user's selections do not affect eligibility.

The window starts at the creation time of the user's previous news post, regardless of its status, capped at three days back; without a previous post it starts 24 hours ago. This is based on `item_source.first_seen_at`, not the item's publication date.

`NewsSelector::selectFor($user, $post, $candidates, $providers)`:

1. Sends candidates to `NewsCurator` with the user's profile, interest names, maximum items and existing tags, using `AiChainResolver::for($user)`.
2. `SelectionValidator` drops invalid entries: the candidate id must be known (integer or digit string), and the URL must be one of its own allowed links after normalization. Titles are capped at 80 characters, summaries at 160 and tags at three deduplicated slugs, with a source default tag when none remain. Duplicate URLs keep the first entry, and selection stops at the user's item limit.
3. In a transaction, stores personal selections and tags in `post_items` and `post_item_tag`. A story linked inside a newsletter gets its own shared item and source sighting. Other users' picks and the shared candidate's title stay unchanged.
4. Returns the answering `provider:model`, saved as `posts.model_used`.

On `AiUnavailableException` (SDK failover exhaustion or missing structured output), `headlinesFor` saves up to the user's item limit, ordered by source priority then publication recency, with source default tags and no summaries. `model_used` is `none`. Missing AI configuration fails generation when candidates require AI; it does not trigger this fallback. With no candidates, generation skips AI and renders the quiet-night message.

## Rendering the news post

`NewsRenderer::render($date, $lines, $failures, $headlinesOnly)` (in `app/Digest/Rendering/`) returns a `RenderedPost`: a title, the list of Discord messages and up to 5 forum tags. It is stored as JSON in `posts.rendered`, so a failed delivery can be retried without asking the AI model again.

- **Title**: `📰 News digest — Fri 16 Oct`.
- **Sections**: one per tag, `## AI`, `## Laravel`… Each item goes into the section of its tag that is most common that day (an item tagged `laravel, ai` lands in AI when AI is the day's big topic), so there are fewer one-item sections. Sections are ordered by size. An untagged line uses `general`; selection normally supplies the source's default tag.
- **Lines**: `- **Title**: summary <url>`. The link is wrapped in `<>` so Discord doesn't add a preview card. Titles and summaries are plain text passed through `DiscordText::escape()`, which escapes Markdown characters and neutralizes `@everyone`, `@here` and `<@…>` mentions.
- **Footer**: `⚠️ Not fetched: …` when sources failed, and `⚠️ Summaries unavailable today, headlines only.` for the no-AI fallback.
- **Quiet night**: no items gives a single message, `Quiet night: nothing notable.`, plus the footer.
- **Forum tags**: the slugs of the 5 largest sections (Discord allows 5 per post).
- **Length**: `MessageSplitter` packs the sections into messages of at most 2,000 characters (Discord's limit), splitting on section boundaries, then on lines, and cutting a single overlong line as a last resort. When a section has to be split, each continuation starts with its heading followed by "(continued)", e.g. `## AI (continued)`.

## Delivering posts

`PostDeliverer::deliver($post)` sends saved rendered content to every enabled destination of the owner that accepts the post's kind. Each post/channel pair has its own `deliveries` row. Delivered destinations and destinations at `DIGEST_MAX_ATTEMPTS` failures are skipped. One channel's failure does not block the others. Delivery failures update that delivery's attempts and error; the post stays `generated`.

| Channel | Behavior |
|---|---|
| Discord bot | `DiscordBotChannel` creates a forum thread through `DiscordClient` (REST v10). `ForumTagMatcher` matches tags by display name per forum, creates missing tags while there is room (20 per forum), and applies at most five. A 403 disables the destination. |
| Discord webhook | `DiscordWebhookChannel` creates a forum thread through its stored webhook. It sends no tags because webhooks cannot read the forum's tags. A 404 disables the destination. |
| Email | `EmailChannel` sends `PostMail`, a Markdown mail with the rendered title as subject and the rendered messages as its body. |
| In-app | `InAppChannel` requires no external send; the stored post is marked delivered for that destination. |

Both Discord channels cut thread titles to 100 characters, save `external_id` and `progress` immediately after creating a thread, then save progress after each reply. Retries resume from that delivery's progress. All messages disable mentions with `allowed_mentions: {parse: []}`. The bot client waits Discord's `retry_after` (capped at 30 seconds) and retries once on a 429.

Webhook URLs must match `DiscordWebhookUrl::PATTERN`, checked by the form rule and again by the delivery driver. Errors omit the webhook URL, including connection failures, so the secret is not exposed in delivery errors.

The bot needs View Channel, Manage Channels (to create forum tags), Create Posts, Send Messages and Send Messages in Threads on its destination forums.

## The news pipeline

`NewsPipeline::runFor($user, $date)` finds or creates the user's news post for that date. If it has no rendered content, it collects for the user, queries candidates, selects and renders, then saves `rendered`, `model_used` and status `generated`. Generation errors mark the post `failed`, increment its attempts and are raised again.

The pipeline then calls `PostDeliverer`. Repeated runs reuse saved rendered content and skip successful deliveries, so delivery retries do not ask AI again.

### `digest:news`

```bash
docker compose exec app php artisan digest:news --user=<email>
docker compose exec app php artisan digest:news --user=<email> --dry-run
docker compose exec app php artisan digest:news --user=<email> --force
```

`--user` is required and determines the account and local date. `--dry-run` generates with real sources and AI in a rolled-back transaction, prints the title and messages, and skips delivery. If a post is already rendered, it prints that saved content. `--force` deletes today's post, selections and delivery records before generating again; it is ignored with `--dry-run`. Existing external messages are not deleted.

## The learning pipeline

`LearningPipeline::runFor($user, $date)` requires an interest with `has_learning = true`. It generates a learning post for that user and date, reuses saved rendering on retries and delivers through `PostDeliverer`.

1. **Topic**: `TopicPicker::nextFor` reuses today's `learning_topic_usages` record, else picks a never-used topic or the least recently used one for this user, avoiding their previous category when another is available. When generation reaches its final failed attempt, today's usage is deleted so the topic can return another day. Topics are seeded by `LearningTopicTableSeeder` (66 topics in 11 categories; idempotent).
2. **Writing**: `LearningWriter` receives the user's profile, existing tags and only that user's last 60 tip titles. It uses their AI chain and requests intermediate-to-advanced material. The topic's `stack_angle` applies the explanation to the stack in the reader's profile; it is empty when no stack applies. AI failure fails generation for a later retry, with no fallback text.
3. **Links**: each suggested link is checked (`HEAD`, or `GET` when `HEAD` is refused); failed links are dropped and at most two are kept. Only public addresses are requested, with every redirect (up to three) checked before following it.
4. **Storing**: two tagged `learning_posts` rows hold the tip and topic. A nonempty stack angle is appended to the topic body as `**In practice:**`.
5. **Rendering**: `LearningRenderer` uses title `🧠 Tip & topic — Fri 16 Oct`, a `## 💡` tip section and a `## 📚` topic section with suggested reading. Titles are escaped; bodies remain Markdown. At most five forum tags are requested.

### `digest:learning`

```bash
docker compose exec app php artisan digest:learning --user=<email>
docker compose exec app php artisan digest:learning --user=<email> --dry-run
docker compose exec app php artisan digest:learning --user=<email> --force
```

Options behave as for news. `--force` keeps today's per-user topic usage, so the same topic is written and delivered again.

## The daily schedule

`routes/console.php` schedules `digest:tick` every 15 minutes through Laravel. Production runs `php artisan schedule:run` every 15 minutes (or `digest:tick` directly on a host that starts cron jobs late), plus a persistent `php artisan queue:work` process. `RunDigest` has one try and a 300-second timeout, below the database queue's 360-second `retry_after`. See [Getting started](02-getting-started.md#running-digests).

`TickScheduler::due($nowUtc)` returns `(user, kind, local date)` entries. It considers users with verified email, completed onboarding and either AI keys or `uses_instance_ai`. For each user's timezone, it checks digest toggles and configured times; learning also requires `learningAvailable()`. A digest is due after its local time when no post exists, or when generation is unfinished and below `DIGEST_MAX_ATTEMPTS`. Rendered posts are not regenerated by later ticks.

`digest:tick`:

1. Collects once for the distinct due users.
2. Dispatches one queued `RunDigest` per due digest. Jobs are unique by user, kind and local date for 15 minutes, with one queue attempt; later ticks retry unfinished generation below the post's attempt limit.
3. Retries failed deliveries below their attempt limit for posts created in the last two days, reusing rendered content.
4. Prunes items created more than 30 days ago that no `post_items` row references, including undated items.

The command exits with failure only when no digests were dispatched, delivery retries were attempted and every retried post had a delivery failure. Queued generation runs independently of this exit status.

## Data model

| Table | Holds |
|---|---|
| `users` | Accounts, with hidden administrator and instance AI flags |
| `digest_settings` | One settings row per user: timezone, profile, toggles, times, max items and onboarding time |
| `interests` | Interest catalog: category, icon, position, learning availability and draft/live status |
| `interest_user` | Each user's chosen interests |
| `sources` | Catalog or custom source definition, default flag, site URL, encrypted credentials and fetch state |
| `interest_source` | Links between interests and sources |
| `source_subscriptions` | One enabled or disabled subscription per user/source pair |
| `items` | Shared fetched items, unique by `url_normalized`; original title, excerpt, links and publication date |
| `item_source` | Source sightings per item, with `first_seen_at` |
| `tags` | Tag slug and display name (`ai` → "AI") |
| `posts` | One per required owner, day and kind (`news` / `learning`): generation status, attempts, rendered messages and model used |
| `post_items` | Per-post copies of selected item titles, summaries and positions, with a generated search vector |
| `post_item_tag` | Tags attached to personal selections |
| `learning_topics` | The rotating list of software-engineering topics |
| `learning_posts` | The tip and the topic of a learning post (bodies in Markdown), with a generated search vector |
| `learning_post_tag` | Tags attached to tips and topics |
| `learning_topic_usages` | Per-user topic history, unique by user and day |
| `delivery_channels` | Per-user destinations, accepted kinds, enabled flag, encrypted settings and verification time |
| `deliveries` | Delivery status and retry progress, unique by post and channel |
| `discord_guilds` | Installed guild id and name, unique by user and guild |
| `ai_keys` | Per-user provider, model, encrypted key, suffix, failover position and consent time |

`posts.user_id` is required and `(user_id, date, kind)` is unique. Post statuses are `pending`, `generated` and `failed`; delivery completion is tracked separately.

Shared items have no status, summary, post id or source slug. Personal summaries and tags live on selections, source associations on `item_source`, and topic rotation on `learning_topic_usages`. Discord thread ids, message progress and delivery times live on `deliveries`.

Selection titles and summaries are plain text; learning bodies are Markdown.

## AI

AI calls go through the [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk) (`laravel/ai`). Each task is an agent class with instructions and a JSON schema, so every provider returns the same structure, and providers fail over in the order of the user's resolved chain. See [AI providers](06-ai.md).
