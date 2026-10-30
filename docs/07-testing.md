# Testing

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=FeedFetcherTest
docker compose exec app npm run test
docker compose exec app npm run test -- tests/js/components/PasswordInput.test.ts
```

The suite uses PHPUnit and runs against the `pulse_testing` PostgreSQL database (created by `docker/postgres/init/`), never the development database. `phpunit.xml` forces the test connection with `<server … force="true">` entries because the container's own `DB_*` variables would otherwise take precedence.

## No real network

Tests never reach the internet:

- HTTP sources are faked with `Http::fake()` and `Http::preventStrayRequests()`, using sample files in `tests/Fixtures/` (an RSS feed, a Reddit Atom feed, an hnrss feed, a broken feed, a GitHub trending page, Resend API responses).
- AI agents are faked with the AI SDK (`HealthCheck::fake(...)`), see [AI providers](06-ai.md#tests).
- Waits (rate-limit backoff) are faked with `Sleep::fake()`.

## Digest and delivery tests

The feature suite covers subscriber-scoped collection and source sightings, per-user candidate windows and selection isolation, AI chains and fallback behavior, topic rotation and personal tip history, and adoption and ownership migrations.

Delivery tests cover independent channel failures and attempt limits, Discord thread resumption, bot tag matching, destination disabling on bot 403 or webhook 404, Markdown email and in-app delivery. Scheduling tests use faked queues and verify due users, local dates, unique `RunDigest` jobs, delivery retries and pruning.

URL safety tests inject DNS results and fake HTTP responses to check public addresses, mixed public/private results and redirects without real DNS or HTTP requests. Webhook tests cover accepted URL patterns, rejected tampered URLs and errors that omit webhook secrets.

```bash
docker compose exec app php artisan test --filter=UserNewsPipelineTest
docker compose exec app php artisan test --filter=UserLearningPipelineTest
docker compose exec app php artisan test tests/Feature/Delivery
docker compose exec app php artisan test --filter=DigestTickTest
```

## Front-end tests

The front-end tests live in `tests/js/`, mirroring `resources/js/`. They run with Vitest through Vite+ (`vp test`, configured in the `test` block of `vite.config.ts`) in a `jsdom` browser environment, with Vue Test Utils and Testing Library. Vitest is capped at four workers (`maxWorkers: 4`) to bound memory use. Import the test API from `vite-plus/test`, not from `vitest`.

- **Query like a user**: by role, label or visible text (`screen.getByRole('button', { name: 'Delete account' })`), and interact with `@testing-library/user-event`.
- **Inertia**: `tests/js/setup.ts` replaces `usePage()` and `useHttp()` from `@inertiajs/vue3` with the controllable objects in `tests/js/support/inertia.ts` (`page.url`, `page.props`, `http.submit`), reset after each test. The real router stays: spy on it to check a request (`vi.spyOn(router, 'visit')`) without sending it.
- **Browser state**: `tests/js/support/matchMedia.ts` fakes the system color scheme (`mockMatchMedia(true)`, `changeSystemTheme(false)`). Local storage, cookies and the `dark` class are cleared after each test.
- **Generated routes**: tests import the Wayfinder helpers (`@/routes/...`, `@/actions/...`), generated when the Vite plugins load; this needs PHP, so run the tests in the container.

## Web app tests

`tests/Feature/Reading/` covers account-scoped day, archive, search, tag and learning queries. Page and action tests cover email verification and onboarding gates, local dates, pagination, resource field allowlists, sanitized learning HTML, ownership checks, source and key limits, delivery settings and test throttles. `ShowcaseDataSeederTest` covers local-only seeding, repeatable counts, moving dates and isolation from other accounts.

Vue tests cover the shell, onboarding, reading pages, settings, search debounce and filters, calendar navigation, theme behavior and empty/error states. Delivery settings tests fake mail and Discord requests; live delivery tests from the web app send real messages.

## Before committing

```bash
docker compose exec app php artisan wayfinder:generate --with-form
docker compose exec app npm run check
docker compose exec app npm run types:check
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app php artisan test
docker compose exec app npm run test
```

Pint skips `tmp/`, which is a git-excluded folder for local scratch files.

## CI

GitHub Actions (`.github/workflows/ci.yml`) runs three jobs on every push to `main` and every pull request:

- **lint**: generates Wayfinder routes, runs `vp check` (front-end formatting and lint), `vue-tsc --noEmit` through `npm run types:check`, Pint (`--test`) and Larastan.
- **tests**: the PHPUnit suite against a PostgreSQL 18 service (the front-end is built first, since the Inertia pages need the Vite manifest), then the front-end tests.
- **gitleaks**: scans the whole git history for committed secrets (API keys, tokens).

To run the secret scan locally:

```bash
docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.28.0 git /repo --redact --verbose
```

## Live checks

`digest:sources [--user=<email>]` checks real sources, and `digest:ai` checks the instance AI chain without storing anything. They are the quickest way to confirm a change against the outside world.
