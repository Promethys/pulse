# Pulse

Pulse turns the tech sources you follow into two short daily posts: the news and a tip + topic of the day. Each account has its own timezone and post times (09:00 and 09:30 by default). The web app provides account registration, account settings and a Discord install flow.

## How a day works

1. Laravel's scheduler runs `digest:tick` every 15 minutes and checks which users and digest kinds are due in their local timezone.
2. **Collecting**: fetch only enabled sources subscribed to by due users, sharing recent fetches and recording where each item was seen.
3. **News**: a queued job selects new items from the user's subscriptions, using their profile, interests, item limit and AI chain.
4. **Learning**: when the user has an interest with learning content, a queued job picks a topic from their rotation and writes a tip and explanation for their profile.
5. Each rendered post goes to all of its owner's enabled destinations that accept its kind: Discord bot, Discord webhook, email and in-app. Delivery failures are retried separately from generation.
6. Items, personal selections, topic history, posts and delivery progress are stored in PostgreSQL.

See [Architecture](04-architecture.md) for the details.

## Scope

Digest generation and delivery use per-user settings, interests, subscriptions, selections, AI keys and destinations. `pulse:adopt` copies the instance's starting configuration and assigns existing history to an account. Existing single-owner installs must run it before the migration that makes post ownership required. See [Getting started](02-getting-started.md#adopting-existing-data).
