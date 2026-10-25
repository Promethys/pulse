# Configuration

Digest behavior is stored per account: timezone, schedule, toggles, profile, maximum news items, interests, subscriptions, AI keys and destinations. Instance services use environment variables in `.env` (copied from `.env.example`). Pulse variables are commented out in the example: an empty `KEY=` makes Laravel read an empty string instead of the default. Uncomment only what you set.

`pulse:adopt` copies the starting values below into an account. Changing them afterward does not change that account's records. See [Getting started](02-getting-started.md#adopting-existing-data) for the required upgrade order.

## Schedule and content

| Variable | What it does | Default |
|---|---|---|
| `DIGEST_TIMEZONE` | Starting timezone for `pulse:adopt`. | `Europe/Paris` |
| `DIGEST_NEWS_TIME` | Starting local news time for `pulse:adopt`. | `09:00` |
| `DIGEST_LEARNING_TIME` | Starting local learning time for `pulse:adopt`. | `09:30` |
| `DIGEST_MAX_ITEMS` | Starting news item limit for `pulse:adopt`. | `20` |
| `DIGEST_PROFILE` | Starting reader profile for `pulse:adopt`; stored in the account's settings. | empty |
| `DIGEST_MAX_ATTEMPTS` | Maximum failed generation attempts per post and failed delivery attempts per destination. | `4` |

New accounts default to `UTC`, 09:00 news, 09:30 learning and 20 items. Settings validation accepts times in 15-minute steps and an item limit from 1 to 20. The scheduler and a queue worker are both required; see [Getting started](02-getting-started.md#running-digests).

## AI providers

| Variable | What it does | Default |
|---|---|---|
| `DIGEST_AI_PROVIDERS` | Instance failover chain used by accounts with `uses_instance_ai` and no personal keys, `provider:model` pairs separated by commas, e.g. `gemini:<model>,anthropic:claude-haiku-5-5`. Any provider defined in `config/ai.php` works. | required for instance AI |
| `DIGEST_AI_TIMEOUT` | HTTP timeout of one AI request, in seconds. | `120` |
| `GEMINI_API_KEY`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, … | API key of each provider you list. The full list is in `config/ai.php`. | empty |

See [AI providers](06-ai.md).

## Sources

| Variable | What it does | Default |
|---|---|---|
| `RESEND_API_KEY` | Starting key for `pulse:adopt`, copied into the newsletters source's encrypted credentials. | empty |

The inbox fetcher reads only its source's `credentials.api_key`; it never falls back to `RESEND_API_KEY`. Sources and subscriptions are database records. See [Sources](05-sources.md).

## Delivery

| Variable | What it does | Default |
|---|---|---|
| `DISCORD_BOT_TOKEN` | Token of the Discord bot that posts and manages forum tags. | empty |
| `DISCORD_CLIENT_ID` | Discord application client id for the Add to Discord OAuth2 flow. | empty |
| `DISCORD_CLIENT_SECRET` | Discord application client secret for the OAuth2 code exchange. | empty |
| `DISCORD_NEWS_FORUM_ID` | Starting news forum for `pulse:adopt`. | empty |
| `DISCORD_LEARNING_FORUM_ID` | Starting learning forum for `pulse:adopt`. | empty |

Register `<APP_URL>/discord/callback` as an OAuth2 redirect URL in the Discord developer portal. The install flow requests the `bot` scope with permissions integer `274877975568`. The bot token is still required for Discord API calls.

Destinations are per-user `delivery_channels` rows. Bot settings contain `guild_id` and `channel_id`; webhook settings contain `webhook_url`; email settings contain `address`. Settings are encrypted and hidden from serialization. Each destination accepts news, learning or both.

Email delivery uses Laravel's mail configuration (`MAIL_*`, including the transport and sender settings in `config/mail.php`). Webhook delivery uses the stored Discord webhook URL. The `DiscordWebhookUrl` form rule and delivery driver accept only Discord webhook URLs, including supported Discord subdomains and optional API version paths; query strings and other hosts are rejected.

## Database

In development the database settings come from `compose.yaml` (`DB_HOST=postgres`, database `pulse`, user and password `pulse`). Tests always use the `pulse_testing` database: `phpunit.xml` overrides the connection with `<server … force="true">`, because the container's environment variables would otherwise win over `<env>` entries.
