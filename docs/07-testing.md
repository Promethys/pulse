# Testing

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=FeedFetcherTest
```

The suite uses PHPUnit and runs against the `pulse_testing` PostgreSQL database (created by `docker/postgres/init/`), never the development database. `phpunit.xml` forces the test connection with `<server … force="true">` entries because the container's own `DB_*` variables would otherwise take precedence.

## No real network

Tests never reach the internet:

- HTTP sources are faked with `Http::fake()` and `Http::preventStrayRequests()`, using sample files in `tests/Fixtures/` (an RSS feed, a Reddit Atom feed, an hnrss feed, a broken feed, a GitHub trending page, Resend API responses).
- AI agents are faked with the AI SDK (`HealthCheck::fake(...)`), see [AI providers](06-ai.md#tests).
- Waits (rate-limit backoff) are faked with `Sleep::fake()`.

## Before committing

```bash
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app php artisan test
```

Pint skips `tmp/`, which is a git-excluded folder for local scratch files.

## CI

GitHub Actions (`.github/workflows/ci.yml`) runs three jobs on every push to `main` and every pull request:

- **lint**: Pint (`--test`) and Larastan.
- **tests**: the PHPUnit suite against a PostgreSQL 18 service (the front-end is built first, since the starter-kit pages need the Vite manifest).
- **gitleaks**: scans the whole git history for committed secrets (API keys, tokens).

To run the secret scan locally:

```bash
docker run --rm -v "$PWD:/repo" ghcr.io/gitleaks/gitleaks:v8.28.0 git /repo --redact --verbose
```

## Live checks

`digest:sources` and `digest:ai` check the real sources and providers without storing anything. They are the quickest way to confirm a change against the outside world.
