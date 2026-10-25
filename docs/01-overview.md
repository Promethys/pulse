# Pulse

Pulse turns the tech sources you follow into two short daily posts: the news (09:00) and a tip + topic of the day (09:30). The digest runs as a Laravel console app on a schedule. The web app provides account registration, account settings and a Discord install flow.

## How a day works

1. A cron wakes the app several times each morning (UTC) and it decides from the local time what is due.
2. **News**: fetch every enabled source, keep only new links, ask the AI model to pick and summarize up to 20 items, tag them, post them.
3. **Learning**: pick the next software-engineering topic, ask the AI model for a tip and an explanation, post them.
4. Everything (items, tags, posts) is stored in PostgreSQL.

See [Architecture](04-architecture.md) for the details.

## Scope

The data model supports per-user digest settings, interests, source subscriptions, selections, AI keys and delivery channels. The scheduled digest pipelines still serve one instance configuration through `.env` and the enabled sources table; they do not use the per-user records. `pulse:adopt` assigns that configuration and existing history to an account. See [Architecture](04-architecture.md).
