# Pulse

Pulse turns the sources you follow into two short daily posts: the news and a tip + topic of the day. Each account has its own timezone and post times (09:00 and 09:30 by default). The Vue 3/Inertia web app provides registration, onboarding, daily reading, history, search and account settings.

## How a day works

1. Laravel's scheduler runs `digest:tick` every 15 minutes and checks which users and digest kinds are due in their local timezone.
2. **Collecting**: fetch only enabled sources subscribed to by due users, sharing recent fetches and recording where each item was seen.
3. **News**: a queued job selects new items from the user's subscriptions, using their profile, interests, item limit and AI chain.
4. **Learning**: when the user has an interest with learning content, a queued job picks a topic from their rotation and writes a tip and explanation for their profile.
5. Each rendered post goes to all of its owner's enabled destinations that accept its kind: Discord bot, Discord webhook, email and in-app. Delivery failures are retried separately from generation.
6. Items, personal selections, topic history, posts and delivery progress are stored in PostgreSQL.

See [Architecture](04-architecture.md) for the details.

## Web app

- **Today and day view**: read news grouped by tag alongside the tip and topic, with previous/next navigation and a calendar. Today uses the account's timezone and shows the latest saved day when today has no post.
- **Archive**: browse saved days, including quiet, pending and failed news posts.
- **Search**: search personal news titles and summaries or learning titles and bodies, with tags and date filters. News also filters by source; learning by kind and category.
- **Tags and Learning**: browse news by tag with a 12-week activity chart, or tips and topics by kind, category and tag, with full learning detail pages.
- **Sources and Settings**: choose interests, manage catalog subscriptions and custom sources, configure the digest, order AI keys and manage Discord and email delivery. In-app delivery is always available.
- **Admin panel**: at `/admin`, for verified accounts with `is_admin`: manage account access, catalog sources and interests, and review catalog proposals and suggested interests. Users can suggest feeds and interests, and a monthly scout proposes feeds. Nothing enters the catalog without review.

New accounts verify their email, then complete onboarding before entering the app. An AI key can be added later, but scheduled digests require personal keys or instance AI access. See [Architecture](04-architecture.md#web-app).

## Scope

Digest generation and delivery use per-user settings, interests, subscriptions, selections, AI keys and destinations. `pulse:adopt` copies the instance's starting configuration and assigns existing history to an account. Existing single-owner installs must run it before the migration that makes post ownership required. See [Getting started](02-getting-started.md#adopting-existing-data).
