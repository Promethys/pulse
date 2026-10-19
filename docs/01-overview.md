# Pulse

Pulse turns the tech sources you follow into two short daily posts: the news (09:00) and a tip + topic of the day (09:30). It runs as a Laravel console app on a schedule; there is no web interface yet.

## How a day works

1. A cron wakes the app several times each morning (UTC) and it decides from the local time what is due.
2. **News**: fetch every enabled source, keep only new links, ask the AI model to pick and summarize up to 20 items, tag them, post them.
3. **Learning**: pick the next software-engineering topic, ask the AI model for a tip and an explanation, post them.
4. Everything (items, tags, posts) is stored in PostgreSQL.

See [Architecture](04-architecture.md) for the details.

## Scope

The MVP serves a single user: the profile, sources, AI keys and delivery channels are configured for one person through `.env` and the seeders. Multi-user support (per-user profiles, sources, delivery, features and API keys) is planned for v2.
