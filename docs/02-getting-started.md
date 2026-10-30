# Getting started

Pulse runs in Docker for development and production. You don't need PHP or Node on your machine.

## Requirements

- Docker with Compose.
- Ports `8000` (app), `5173` (Vite) and `5433` (PostgreSQL) free on the host. PostgreSQL is published on `5433` so it doesn't clash with a local PostgreSQL on `5432`.

## First run

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm ci
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=InterestTableSeeder
docker compose exec app php artisan db:seed --class=SourceTableSeeder
docker compose exec app php artisan db:seed --class=TagTableSeeder
docker compose exec app php artisan db:seed --class=LearningTopicTableSeeder
```

Start the frontend and open <http://localhost:8000>:

```bash
docker compose exec app npm run dev
```

Register an account, verify its email and follow the five onboarding steps: Interests, Sources, About you, AI key and Delivery. At least one live interest is required; About you and AI key can be skipped. In-app delivery is enabled automatically, and email is optional. With the default `MAIL_MAILER=log`, verification messages are written to `storage/logs/laravel.log`; configure `MAIL_*` for real email delivery.

Then check that the sources respond and the instance AI providers work:

```bash
docker compose exec app php artisan digest:sources
docker compose exec app php artisan digest:ai
```

Both commands only read: they never store anything.

For a local preview with 90 days of fictional digest history, run `docker compose exec app php artisan db:seed --class=ShowcaseDataSeeder`, then sign in as `showcase@example.com` with password `password`. The seeder runs only in `local` and `testing`; rerunning it refreshes that account's history through the latest due digest in Europe/Paris without changing other accounts. It seeds the catalog, includes quiet and failed days, missing digests and a failed email delivery, and is never called by `DatabaseSeeder`.

## The Docker setup

There is one `Dockerfile` with four stages:

| Stage | Used by | Contents |
|---|---|---|
| `base` | every stage | FrankenPHP with PHP 8.4 and the `pdo_pgsql`, `intl`, `zip`, `opcache` and `pcntl` extensions, plus Composer |
| `dev` | `compose.yaml` | `base` plus Node, for the Vue/Vite frontend |
| `build` | `prod` | installs production dependencies and builds the frontend assets |
| `prod` | production | `base` plus the built app, without Node |

`compose.yaml` is only for local development. It runs the `dev` stage with the repository mounted at `/app` (as your user, so files created in the container stay yours) and a `postgres:18` service. Its init script creates two databases: `pulse` for development and `pulse_testing` for the test suite.

Production doesn't use `compose.yaml`: it builds the last stage of the `Dockerfile` (`prod`) and connects to its own PostgreSQL database through the `DB_*` variables.

## Everyday commands

```bash
docker compose exec app php artisan test          # test suite
docker compose exec app vendor/bin/pint           # code style
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app npm run dev               # frontend
```

Run npm inside the container: `node_modules` contains Linux binaries.

## Running digests

Manual commands require the account's email and use its local date:

```bash
docker compose exec app php artisan digest:news --user=<email> --dry-run
docker compose exec app php artisan digest:learning --user=<email> --dry-run
```

Remove `--dry-run` to generate and deliver. Add `--force` to delete today's post and generate it again; it has no effect with `--dry-run`. Learning requires an interest with `has_learning = true`.

For automatic runs, invoke Laravel's scheduler every 15 minutes and keep a queue worker running:

```cron
*/15 * * * * cd /path/to/pulse && php artisan schedule:run
```

```bash
php artisan queue:work
```

On a host that starts cron jobs late, run `php artisan digest:tick` from the cron instead. `schedule:run` only runs tasks due at the minute it starts, while `digest:tick` catches up on every digest whose time has passed today.

Each digest job may run for 300 seconds, since one AI call can take up to 120. Keep `DB_QUEUE_RETRY_AFTER` (default 360) above that timeout, or a slow job runs twice.

The scheduler calls `digest:tick`, which collects sources and queues one job per due user, kind and local date. Accounts must have verified email, completed onboarding and either personal AI keys or instance AI access. Their settings control the enabled digests and times. See [Architecture](04-architecture.md#the-daily-schedule).

## Adopting existing data

**For an existing single-owner install, run `pulse:adopt` before migrating to this version**, using the previous version that supports adoption. The migration that removes the single-owner columns refuses to run while any post has no owner. A fresh install can migrate directly.

Seed the catalog first (`InterestTableSeeder` before `SourceTableSeeder`): adoption chooses the interests linked to enabled catalog sources and warns when there are none. Register the account, then replace `<email>` with its address:

```bash
docker compose exec app php artisan pulse:adopt <email> --dry-run
docker compose exec app php artisan pulse:adopt <email>
```

`pulse:adopt` copies the instance settings from `.env`, chooses interests and source subscriptions, and assigns unowned posts. It makes the newsletters source custom to the user, stores the Resend key as encrypted credentials, and creates Discord destinations from the configured forum ids. It also grants administrator access and instance AI access, marks the email as verified, enables both digests, marks onboarding complete and ensures an in-app channel exists.

The command is idempotent and uses one transaction. `--dry-run` prints counts and rolls back all database changes. Both modes read Discord to resolve the configured forums and guilds, so the bot needs access; missing forum ids are skipped with a warning. Re-running the command reapplies the current environment settings and configured Discord destinations.

The digest commands use the resulting per-user records. The migrations copy historical selections and source sightings. Run adoption on the previous version to import topic usage and delivered Discord thread progress before the old columns are removed. See [Architecture](04-architecture.md).
