# Configuration

Pulse is configured through environment variables in `.env` (copied from `.env.example`). In `.env.example` the Pulse variables are commented out on purpose: an empty `KEY=` line makes Laravel read an empty string instead of the default value. Uncomment only what you set.

## Schedule and content

| Variable | What it does | Default |
|---|---|---|
| `DIGEST_TIMEZONE` | Timezone for "today", the post times and the daily windows. | `Europe/Paris` |
| `DIGEST_NEWS_TIME` | Local time of the news post. | `09:00` |
| `DIGEST_LEARNING_TIME` | Local time of the tip + topic post. | `09:30` |
| `DIGEST_MAX_ITEMS` | Maximum number of news items per day (0 is allowed on a quiet day). | `20` |
| `DIGEST_MAX_ATTEMPTS` | How many times a failed post is retried in one morning. | `4` |
| `DIGEST_PROFILE` | Who the digest is written for: role, stack, interests, level. Sent to the AI model with every prompt. Personal, so it lives only in `.env`. | empty |

## AI providers

| Variable | What it does | Default |
|---|---|---|
| `DIGEST_AI_PROVIDERS` | Ordered failover chain, `provider:model` pairs separated by commas, e.g. `gemini:<model>,anthropic:claude-haiku-5-5`. Any provider defined in `config/ai.php` works. | required |
| `DIGEST_AI_TIMEOUT` | HTTP timeout of one AI request, in seconds. | `120` |
| `GEMINI_API_KEY`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, … | API key of each provider you list. The full list is in `config/ai.php`. | empty |

See [AI providers](06-ai.md).

## Sources

| Variable | What it does | Default |
|---|---|---|
| `RESEND_API_KEY` | Reads the newsletters received on your Resend receiving address (source `newsletters`). | empty (that source then fails with a clear message) |

The sources themselves are rows in the database, not configuration. See [Sources](05-sources.md).

## Delivery

| Variable | What it does | Default |
|---|---|---|
| `DISCORD_BOT_TOKEN` | Token of the Discord bot that posts and manages forum tags. | empty |
| `DISCORD_NEWS_FORUM_ID` | Forum channel for the 09:00 news post. | empty |
| `DISCORD_LEARNING_FORUM_ID` | Forum channel for the 09:30 tip + topic post. | empty |

## Database

In development the database settings come from `compose.yaml` (`DB_HOST=postgres`, database `pulse`, user and password `pulse`). Tests always use the `pulse_testing` database: `phpunit.xml` overrides the connection with `<server … force="true">`, because the container's environment variables would otherwise win over `<env>` entries.
