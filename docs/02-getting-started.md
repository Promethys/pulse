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
docker compose exec app php artisan db:seed --class=SourceTableSeeder
docker compose exec app php artisan db:seed --class=TagTableSeeder
docker compose exec app php artisan db:seed --class=LearningTopicTableSeeder
```

Then check that the sources respond and the AI providers work:

```bash
docker compose exec app php artisan digest:sources
docker compose exec app php artisan digest:ai
```

Both commands only read: they never store anything.

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
docker compose exec app npm run dev               # frontend (kept for the future web app)
```

Run npm inside the container: `node_modules` contains Linux binaries.
