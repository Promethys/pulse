# Architecture

Pulse is a Laravel 13 app used from the command line. The Vue/Inertia starter kit is kept for the future web app but the digest doesn't depend on it.

## Code layout

| Path | Responsibility |
|---|---|
| `app/Digest/Sources/` | Fetching: one fetcher per source type, URL normalization, the collector |
| `app/Digest/Ai/` | The AI provider chain read from `DIGEST_AI_PROVIDERS` |
| `app/Digest/Support/` | Small shared helpers (HTML to plain text) |
| `app/Ai/Agents/` | `laravel/ai` agents (prompt + JSON schema) |
| `app/Console/Commands/` | `digest:*` commands |
| `app/Models/`, `app/Enums/` | Data model |
| `database/seeders/` | Initial sources and tags (idempotent) |

## Collecting

`SourceCollector::collect($since)` walks the enabled sources in priority order (`Source::query()->enabled()`):

1. The source's `type` (`SourceType` enum) names its fetcher, resolved from the container:
   - `FeedFetcher`: RSS 2.0 and Atom (blogs, hnrss.org, Reddit, GitHub releases).
   - `GithubTrendingFetcher`: reads the GitHub trending page (there is no API or feed).
   - `ResendInboxFetcher`: newsletters received through Resend Receiving.
2. A fetcher returns `Candidate` objects: title, URL, plain-text excerpt (300 characters, 3,000 for newsletters), publication date, and the `links` the AI model is allowed to cite for it.
3. Each candidate becomes an `Item` with status `candidate`, unless its normalized URL is already known (`UrlNormalizer`: lowercase host, no `www.`, no tracking parameters, sorted query, no trailing slash or fragment).
4. The source row records `last_fetched_at`, `last_success_at` and `last_error`. A failing source is reported and skipped; it never stops the run.

## Selecting the news

`NewsSelector::select($post, $candidates)` (in `app/Digest/News/`):

1. Sends the candidates to the `NewsCurator` agent through the provider chain. If no provider answers, it throws `AiUnavailableException` and changes nothing, so the pipeline can post a headlines-only version instead.
2. `SelectionValidator` keeps only what the model may legitimately return, and drops (never repairs) the rest:
   - the `candidate_id` must be one of the candidates;
   - the `url` must be one of that candidate's own `links` (compared after normalization): no invented links;
   - titles are cut to 80 characters and summaries to 160;
   - tags are turned into slugs, deduplicated and capped at 3; with none left, the source's default tag is used;
   - duplicate URLs keep the first entry, and the list stops at `DIGEST_MAX_ITEMS`.
3. In one transaction: each pick updates its candidate, or, for a link inside a newsletter, creates a new item for that link. Picks become `selected`, are linked to the post and tagged (`TagResolver` reuses tags by slug and creates the missing ones). Every other candidate becomes `skipped`.
4. It returns the selected items and the `provider:model` that answered, stored later as the post's `model_used`.

## Rendering the news post

`NewsRenderer::render($date, $items, $failures, $headlinesOnly)` (in `app/Digest/Rendering/`) returns a `RenderedPost`: a title, the list of Discord messages and up to 5 forum tags. It is stored as JSON in `posts.rendered`, so a failed delivery can be retried without asking the AI model again.

- **Title**: `📰 Tech digest — Fri 16 Oct`.
- **Sections**: one per tag, `## AI`, `## Laravel`… Each item goes into the section of its tag that is most common that day (an item tagged `laravel, ai` lands in AI when AI is the day's big topic), so there are fewer one-item sections. Sections are ordered by size. An item without tags uses its source's default tag.
- **Lines**: `- **Title**: summary <url>`. The link is wrapped in `<>` so Discord doesn't add a preview card. Titles and summaries are plain text passed through `DiscordText::escape()`, which escapes Markdown characters and neutralizes `@everyone`, `@here` and `<@…>` mentions.
- **Footer**: `⚠️ Not fetched: …` when sources failed, and `⚠️ Summaries unavailable today, headlines only.` for the no-AI fallback.
- **Quiet night**: no items gives a single message, `Quiet night: nothing notable.`, plus the footer.
- **Forum tags**: the slugs of the 5 largest sections (Discord allows 5 per post).
- **Length**: `MessageSplitter` packs the sections into messages of at most 2,000 characters (Discord's limit), splitting on section boundaries, then on lines, and cutting a single overlong line as a last resort. When a section has to be split, each continuation starts with its heading followed by "(continued)", e.g. `## AI (continued)`.

`NewsRenderer::headlines($candidates)` is the no-AI fallback: up to `DIGEST_MAX_ITEMS` candidates, ordered by source priority and then by recency, rendered without summaries.

## Publishing to Discord

`ForumPublisher::publish($post, $forumChannelId)` (in `app/Digest/Discord/`) posts a rendered post as a forum thread through a Discord bot (`DiscordClient`, REST API v10, `Authorization: Bot <token>`).

1. **Forum tags**: it reads the forum's tags and matches each of the post's tags by its display name (cut to Discord's 20 characters). Each forum has its own tag ids, so nothing is stored: the match is done per forum, on every post. Missing tags are created while the forum has room (Discord allows 20 tags per forum), and existing forum tags are sent back unchanged. At most 5 are applied to the post.
2. **Thread**: it creates the thread with the title (cut to 100 characters) and the first message, then **immediately** saves the thread id and `messages_sent = 1`.
3. **Replies**: the remaining messages are posted in the thread, saving `messages_sent` after each one.
4. The post becomes `posted` with its `posted_at`.

If Discord fails halfway, the error is raised with the progress already saved, so the next attempt resumes at `messages_sent` instead of creating a second thread. Every message is sent with `allowed_mentions: {parse: []}`, so nothing in a post can ping anyone. On a 429 the client waits Discord's `retry_after` (capped at 30 seconds) and retries once.

The bot needs these permissions on the two forum channels: View Channel, Manage Channels (to create forum tags), Create Posts, Send Messages and Send Messages in Threads.

## The news pipeline

`NewsPipeline::run($today)` builds and posts one day's news. It is safe to run several times: every step is recorded on the day's `posts` row.

1. Find or create today's news post. If it is already `posted`, stop.
2. If it has no `rendered` content yet, **generate** it:
   - collect the sources published since the last posted news (or the last 24 hours the first time);
   - take every `candidate` item not yet attached to a post;
   - with no candidates, skip the AI and render the quiet-night message;
   - otherwise select with the AI. If no provider answers, fall back to the headlines-only version: the chosen headlines are stored as this post's items, tagged with their source's default tag, and `model_used` is `none`;
   - render, then save `rendered`, `model_used` and the status `generated`.
3. **Publish** to `DISCORD_NEWS_FORUM_ID`.
4. On any error: the post becomes `failed`, `attempts` goes up by one, `last_error` keeps the message, and the error is raised again. The next run reuses the saved `rendered` content and only retries the publishing, so the AI is never asked twice for the same day.

### `digest:news`

```bash
docker compose exec app php artisan digest:news            # build and post today's news
docker compose exec app php artisan digest:news --dry-run  # build it and print it; stores and posts nothing
docker compose exec app php artisan digest:news --force    # post today's news again
```

`--dry-run` runs the whole pipeline (real sources, real AI) inside a database transaction that is rolled back, and prints the title and each Discord message. `--force` resets today's post (thread, progress, rendered content) and puts its selected items back to candidates, so the new run can pick them again.

## The learning pipeline

`LearningPipeline::run($today)` builds and posts the 09:30 tip + topic, with the same resumable steps as the news pipeline (`posts` row of kind `learning`, publishing to `DISCORD_LEARNING_FORUM_ID`).

1. **Topic**: `TopicPicker` returns the topic already picked today (so retries and `--force` keep the same topic), else a never-used topic, else the least recently used one, avoiding the previous day's category when another category is available. The topics are seeded by `LearningTopicTableSeeder` (66 intermediate-to-advanced topics in 11 categories; idempotent).
2. **Writing**: the `LearningWriter` agent receives the profile, the existing tags and the last 60 tip titles (so tips don't repeat). It returns a tip (title, Markdown body, tags) and the topic (Markdown body, an "In Laravel" paragraph, 1–2 reference links, tags). Its instructions require an intermediate-to-advanced level, never basics. If no provider answers, the post fails and is retried later; there is no fallback text.
3. **Links**: each suggested link is checked (`HEAD`, or `GET` when `HEAD` is refused) and dropped if it doesn't answer with a non-error status; at most 2 are kept.
4. **Storing**: two `learning_posts` rows (tip and topic, tagged). The "In Laravel" paragraph is part of the topic's Markdown body.
5. **Rendering** (`LearningRenderer`): title `🧠 Tip & topic — Fri 16 Oct`, a `## 💡` section for the tip and a `## 📚` section for the topic with its suggested reading. Titles are escaped; bodies are Markdown and kept as written. Forum tags: the tip's and the topic's tags, at most 5.

### `digest:learning`

```bash
docker compose exec app php artisan digest:learning
docker compose exec app php artisan digest:learning --dry-run
docker compose exec app php artisan digest:learning --force   # same topic, written and posted again
```

## The daily schedule

Production runs one command from a cron: `php artisan digest:tick`. The production cron runs in UTC while the posts are due at local times (`DIGEST_TIMEZONE`, which moves between UTC+1 and UTC+2 in Paris), so the cron fires several times each morning and `digest:tick` decides what to do:

```
0,30 7,8 * * *     → 07:00, 07:30, 08:00 and 08:30 UTC
```

This always covers 09:00 and 09:30 in Paris, in winter and in summer, including the days the clocks change.

`TickScheduler::due()` converts the time to the local timezone. The news is due once the local time has passed `DIGEST_NEWS_TIME`, and the learning post once it has passed `DIGEST_LEARNING_TIME`, as long as today's post isn't `posted` and has fewer than `DIGEST_MAX_ATTEMPTS` failed attempts. Later ticks therefore retry a failed post and do nothing once it is posted. When both are due, news runs first; a news failure doesn't prevent the learning post.

After running what is due, `digest:tick` deletes `skipped` items older than 30 days that have a publication date (undated ones are kept: they are the only record that an undated feed item was already seen). It exits with an error code only when everything that was due failed.

## Data model

| Table | Holds |
|---|---|
| `sources` | Definition (slug, name, type, URL, default tag, max items, priority `position`, `enabled`) and fetch state |
| `items` | Every candidate and selected item, unique by `url_normalized`; status `candidate` / `selected` / `skipped` |
| `tags` | Tag slug and display name (`ai` → "AI") |
| `posts` | One per day and kind (`news` / `learning`): status, attempts, rendered messages, Discord thread, model used |
| `learning_topics` | The rotating list of software-engineering topics |
| `learning_posts` | The tip and the topic of a learning post (bodies in Markdown) |

Item titles and summaries are plain text; learning bodies are Markdown.

## AI

AI calls go through the [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk) (`laravel/ai`). Each task is an agent class with instructions and a JSON schema, so every provider returns the same structure, and providers fail over in the order of `DIGEST_AI_PROVIDERS`. See [AI providers](06-ai.md).
