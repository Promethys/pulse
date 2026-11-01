# Pulse

Pulse turns the sources a reader follows into two short daily posts: the news, and a developer tip with a topic of the day. Accounts have their own interests, sources, schedule, AI keys and destinations (in-app, Discord, email), and read their digests in a Vue and Inertia web app.

## Rules

- **Tests**: PHPUnit (not Pest) for PHP, Vitest (`vp test`) with Vue Test Utils and Testing Library for JS. TDD for both: write the failing test, watch it fail, implement, watch it pass. Cover every state, interaction and edge case. Run the tests related to your change; CI runs the full suites.
- **AI**: always through `laravel/ai` agents (provider-agnostic), never hand-written provider clients.
- **Docs**: keep `README.md`, `docs/*.md` (numbered `NN-name.md`) and the user docs in `resources/docs/` up to date with behavior, commands and settings. No task progress in them.
- **No hosting details in committed files**: don't name a hosting provider or its config in code, docs, comments or tests, and add no provider-specific config files.
- **No personal data committed**: reader profiles live in the database or in `.env`.
- **`.env.example`**: Pulse variables are commented out, because an empty `KEY=` makes `env()` return `""` instead of the default.
- **Seeders**: named `XxxTableSeeder`, idempotent with `firstOrCreate`: only create missing rows, never change existing ones. Disable sources (`enabled = false`) instead of deleting them, and to remove one for good, also remove it from the seeder.
- **Commits**: conventional commits (`feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`, `style:`) with a short subject line.
- **Code**: minimal comments, one terse line at most and only when the code can't say it. No em-dashes in written text.

## Stack and environment

- Laravel 13, PHP 8.4, PostgreSQL 18, PHPUnit 12, Pint, Larastan level 7, `laravel/ai`, Vue 3, Inertia, Filament 5 (admin panel at `/admin`).
- `AppServiceProvider` sets `Date::use(CarbonImmutable::class)`, so model date properties are `CarbonImmutable`.
- Docker: one `Dockerfile` (`base`, `dev` with Node, `build`, `prod`). `compose.yaml` runs the `dev` stage with the repo mounted at `/app` (uid and gid default to 1000), plus `postgres:18` (host port 5433, user and password `pulse`, databases `pulse` and `pulse_testing`). **Everything runs in the container.** `node_modules` holds Linux binaries.

```bash
docker compose up -d
docker compose exec app php artisan test --filter=SomeTest
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app npx vp check
docker compose exec app npx vp test run tests/js/path/to.test.ts
docker compose exec app npx vue-tsc --noEmit
docker compose exec app php artisan wayfinder:generate --with-form   # after new routes
docker compose exec app php artisan digest:sources [--since=7d]      # live source check, stores nothing
docker compose exec app php artisan digest:ai                        # checks the instance's AI providers
docker compose exec app php artisan digest:news --user=<email> --dry-run
docker compose exec app php artisan digest:learning --user=<email> --dry-run
docker compose exec app php artisan digest:tick                      # cron entry point
docker compose exec app php artisan catalog:refresh [--force]        # monthly source checks
docker compose exec app php artisan catalog:scout --interest=<slug>  # propose new feeds
docker compose exec app php artisan boost:update --no-discover       # generates the agent skills
```

`docker compose exec -T` reads stdin: add `< /dev/null` when running it inside scripts or loops.

Agent skills are not committed. `boost.json` lists them, and `boost:update` writes them to `.claude/skills/` and `.agents/skills/`, so run it once after `composer install`.

## Architecture map

See `docs/04-architecture.md` for the details.

- `app/Digest/Sources/`: one `SourceFetcher` per `SourceType`, `Candidate` DTO, `UrlNormalizer`, `SourceCollector` (one failing source never stops a run).
- `app/Digest/Ai/`: `ProviderChain::fromConfig()` parses `DIGEST_AI_PROVIDERS="provider:model,..."` into the SDK failover list. `AiChainResolver` picks a user's keys or the instance fallback.
- `app/Ai/Agents/`: `HealthCheck`, `NewsCurator`, `LearningWriter`, `SourceScout`.
- `app/Digest/News/`, `app/Digest/Learning/`: per-user pipelines.
- `app/Digest/Rendering/`, `app/Digest/Delivery/`: post rendering and one channel per destination type.
- `app/Digest/Catalog/`: monthly health check, moved feed detection, scout and proposal checks.
- `app/Digest/TickScheduler.php` and `digest:tick`: decides what is due from each user's local time and the `posts` table.
- `app/Reading/`, `app/Http/Resources/`: read layer for the web app. `app/Actions/`: write actions. `app/Filament/`: admin panel.
- `app/Docs/`, `config/docs.php`, `resources/docs/`: public user documentation. Public pages use `PublicLayout`.
- Text formats: item title and summary are plain text (escaped when rendered), learning bodies are Markdown (not escaped), `posts.rendered` is a structured array holding the title, Discord Markdown messages and tags. The web app converts learning Markdown and strips raw HTML and unsafe links.

## Gotchas

- `phpunit.xml` must use `<server name="DB_*" force="true">`: compose sets real env vars and Laravel reads `$_SERVER` first, so `<env>` silently runs tests on the dev database. It also sets a 512 MB memory limit, which Filament needs across the suite.
- `laravel/ai` fake callbacks receive `(string $prompt, $attachments, $provider, string $model)`, not `AgentPrompt`, despite the docs. `assertPrompted` callbacks do get `AgentPrompt`. SDK failover only triggers on `FailoverableException` (rate limit, overloaded, credits, connection errors).
- `prompt()` on a `HasStructuredOutput` agent returns `StructuredAgentResponse`, so read it with `->toArray()`. Agents without a schema return `AgentResponse`.
- `Http::fake()` stubs accumulate: a second `Http::fake([...])` for the same URL doesn't replace the first, so use `Http::sequence()`.
- Laravel `retry($times)` counts total attempts. With `throw: false`, the sleep and when callbacks still get the `RequestException`.
- Don't name a test helper `post()` (clashes with `TestCase::post()`).
- Reddit anonymous RSS allows about one request per minute per IP, announced through `x-ratelimit-reset`. Reddit ends RSS on 2026-11-13.
- Discord: forum tag names up to 20 characters, up to 20 tags per forum and 5 per post, thread title up to 100, message up to 2,000. Each forum has its own tag ids. Send `allowed_mentions: {parse: []}`. `403 Missing Access` means the bot is not in the server or has no channel access.
- Filament pages are not Inertia pages. Link to `/admin` with a plain `<a>`; `ForceFullPageLoad` catches Inertia visits. Panel colors are applied only when the panel serves a request, so read them from the panel in tests.
- Wayfinder generates helpers from the routes that exist at build time, so `REGISTRATION_ENABLED` must stay unset during the front-end build.
- `public/images/shots/` holds screenshots of the real UI, shown on Landing, Features and the docs, in light and dark. A design change can leave them out of date: check them, and refresh them if needed.
- `tmp/` is optional local scratch space. Pint scans it unless excluded (`pint.json` excludes it).
