# Contributing

Pulse is a Laravel and Vue web app that turns the sources each reader follows into two short daily posts: the news, and a developer tip with a topic of the day.

Use the issue templates for bug reports and feature ideas. Keep each pull request focused on one change.

## Setup

Follow [Getting started](docs/02-getting-started.md) to set up the app with Docker and PostgreSQL.

## Conventions

- Use PHPUnit, not Pest. Write a failing test first, then implement the change and make it pass.
- Use conventional commit prefixes: `feat:`, `fix:`, `chore:`, `docs:`, `test:`, `ci:`.
- Make AI calls only through `laravel/ai` agents. Never write provider clients by hand.
- Never commit secrets or personal data. Reader profiles live in the database.
- Disable sources with `enabled = false` rather than deleting them. Seeders only create missing rows, so deleted sources return on the next run.
- Keep the documentation in `docs/` updated when behavior or setup changes.
- `AGENTS.md` holds the guidance for AI coding agents, and `CLAUDE.md` is a symlink to it, so edit `AGENTS.md`. Generate the agent skills listed in `boost.json` with `php artisan boost:update --no-discover`.

## Before opening a pull request

Run all three checks inside the container:

```bash
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app php artisan test
```

CI runs three jobs: `lint` (Pint and Larastan), `tests` (PHPUnit against PostgreSQL 18), and `gitleaks` (a secret scan of the full git history). See [Testing](docs/07-testing.md) for details.
