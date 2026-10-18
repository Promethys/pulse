# Pulse

Pulse turns the tech sources you follow into two short daily posts: the news (09:00) and a tip + topic of the day (09:30). It runs as a Laravel console app on a schedule; there is no web interface yet.

## How a day works

1. A cron wakes the app several times each morning (UTC) and it decides from the local time what is due.
2. **News**: fetch every enabled source, keep only new links, ask the AI model to pick and summarize up to 20 items, tag them, post them.
3. **Learning**: pick the next software-engineering topic, ask the AI model for a tip and an explanation, post them.
4. Everything (items, tags, posts) is stored in PostgreSQL.

See [Architecture](architecture.md) for the details.

## Progress

The MVP is built task by task on the `feat/tech-digest` branch.

| # | Task | Status |
|---|---|---|
| 0 | Docker setup (FrankenPHP, Postgres) | Done |
| 1 | Tests on PostgreSQL | Done |
| 2 | Configuration, schema, models, seeders | Done |
| 3 | URL normalization and RSS/Atom feeds | Done |
| 4 | GitHub trending and newsletter inbox (Resend) | Done |
| 5 | Collecting from all sources, `digest:sources` | Done |
| 6 | AI providers (`laravel/ai`), `digest:ai` | Done |
| 7 | News selection (prompt, validation, tags) | Done |
| 8 | Rendering the Discord posts | Done |
| 9 | Discord publisher | Done |
| 10 | News pipeline, `digest:news` | Done |
| 11 | Learning pipeline, `digest:learning` | Done |
| 12 | Scheduler, `digest:tick` | Done |
| 13 | Deployment and acceptance | Done (acceptance); deployment pending |

## Scope

The MVP serves a single user: the profile, sources, AI keys and delivery channels are configured for one person through `.env` and the seeders. Multi-user support (per-user profiles, sources, delivery, features and API keys) is planned for v2.
