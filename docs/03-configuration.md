# Configuration

Digest behavior is stored per account: timezone, schedule, toggles, profile, maximum news items, interests, subscriptions, AI keys and destinations. Instance services use environment variables in `.env` (copied from `.env.example`). Pulse variables are commented out in the example: an empty `KEY=` makes Laravel read an empty string instead of the default. Uncomment only what you set.

`pulse:adopt` copies the starting values below into an account. Changing them afterward does not change that account's records. See [Getting started](02-getting-started.md#adopting-existing-data) for the required upgrade order.

## App name

`APP_NAME` defaults to `Pulse` in `config/app.php` and is shared with Inertia as `name`. Browser page titles use `Pulse` in `resources/js/app.ts`; `VITE_APP_NAME` is no longer used.

## Support email

`SUPPORT_EMAIL` sets the address shown on `/privacy` and `/terms`. It defaults to the value in `config/app.php` and is shared with Inertia as `supportEmail`. Set it when you run your own instance.

## Registration

`REGISTRATION_ENABLED` defaults to `true`. Set it to `false` at runtime to close registration for everyone: `/register` returns a 404 and the Create an account links disappear, while existing accounts can still log in. Leave it unset during the front-end build, because the Wayfinder route helpers are generated from the routes that exist at that moment.

## Login limits

Login allows 5 attempts per minute per account and IP, 30 per hour per account, and 30 per minute per IP. IPv6 addresses share limits within a /64 network. Passkeys allow 10 requests per minute per IP, and two-factor challenges 5 per minute per pending login, falling back to the session.

## Trusted proxies

`TRUSTED_PROXIES` says whose forwarded headers to believe, which decides the client IP and whether a request counts as HTTPS. Rate limits depend on it, so set it to match how you host Pulse.

| Setup | Value |
|---|---|
| Behind one reverse proxy or platform router | leave unset (default `REMOTE_ADDR`, the direct peer) |
| Also behind a CDN or edge network | `REMOTE_ADDR,` followed by the network's published IP ranges, comma separated |
| Exposed directly, with no proxy | `none`, otherwise clients can forge their IP |

## Schedule and content

| Variable | What it does | Default |
|---|---|---|
| `DIGEST_TIMEZONE` | Starting timezone for `pulse:adopt`. | `Europe/Paris` |
| `DIGEST_NEWS_TIME` | Starting local news time for `pulse:adopt`. | `09:00` |
| `DIGEST_LEARNING_TIME` | Starting local learning time for `pulse:adopt`. | `09:30` |
| `DIGEST_MAX_ITEMS` | Starting news item limit for `pulse:adopt`. | `20` |
| `DIGEST_PROFILE` | Starting reader profile for `pulse:adopt`; stored in the account's settings. | empty |
| `DB_QUEUE_RETRY_AFTER` | Seconds before the database queue retries a reserved job; keep it above the 300-second digest job timeout. | `360` |
| `DIGEST_MAX_ATTEMPTS` | Maximum failed generation attempts per post and failed delivery attempts per destination. | `4` |

New accounts default to `UTC`, 09:00 news, 09:30 learning and 20 items. `/settings/digest` edits the profile (up to 2,000 characters), timezone, toggles, times and item limit; learning cannot be enabled without a learning interest. Settings validation accepts times in 15-minute steps and an item limit from 1 to 20. The scheduler and a queue worker are both required; see [Getting started](02-getting-started.md#running-digests).

## AI providers

| Variable | What it does | Default |
|---|---|---|
| `DIGEST_AI_PROVIDERS` | Instance failover chain used by accounts with `uses_instance_ai` and no personal keys, `provider:model` pairs separated by commas, e.g. `gemini:<model>,anthropic:claude-haiku-5-5`. Any provider defined in `config/ai.php` works. | required for instance AI |
| `DIGEST_AI_TIMEOUT` | HTTP timeout of one AI request, in seconds. | `120` |
| `GEMINI_API_KEY`, `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`, … | API key of each provider you list. The full list is in `config/ai.php`. | empty |

Personal keys are managed at `/settings/ai-keys`, with up to five keys per account, one per provider. The page supports ordering, testing and deletion. See [AI providers](06-ai.md).

## Sources

| Variable | What it does | Default |
|---|---|---|
| `RESEND_API_KEY` | Sends mail when `MAIL_MAILER=resend`. Also the starting key for `pulse:adopt`, copied into the newsletters source's encrypted credentials. | empty |

The inbox fetcher reads only its source's `credentials.api_key`; it never falls back to `RESEND_API_KEY`. Sources and subscriptions are database records. See [Sources](05-sources.md).

## Delivery

| Variable | What it does | Default |
|---|---|---|
| `DISCORD_BOT_TOKEN` | Token of the Discord bot that posts and manages forum tags. | empty |
| `DISCORD_CLIENT_ID` | Discord application client id for the Add to Discord OAuth2 flow. | empty |
| `DISCORD_CLIENT_SECRET` | Discord application client secret for the OAuth2 code exchange. | empty |
| `DISCORD_NEWS_FORUM_ID` | Starting news forum for `pulse:adopt`. | empty |
| `DISCORD_LEARNING_FORUM_ID` | Starting learning forum for `pulse:adopt`. | empty |

Set both Discord client credentials to enable the Add to Discord flow; the delivery page hides the button when `DISCORD_CLIENT_ID` is unset. Register `<APP_URL>/discord/callback` as an OAuth2 redirect URL in the Discord developer portal. The install flow requests the `bot` scope with permissions integer `274877975568`. The bot token is still required for Discord API calls.

At `/settings/delivery`, users can add forum destinations from installed Discord servers, add webhooks, configure one email destination, choose news, learning or both, and enable, test or delete external destinations. Each account can have up to 20 Discord destinations in total (bot and webhook combined). The in-app channel cannot be edited, disabled or deleted through these routes. Disconnecting a server removes its saved guild and bot destinations but leaves the bot in the Discord server.

Destinations are per-user `delivery_channels` rows. Bot settings contain `guild_id` and `channel_id`; webhook settings contain `webhook_url`; email settings contain `address`. Settings are encrypted and hidden from serialization. Each destination accepts news, learning or both.

Email delivery uses Laravel's mail configuration (`MAIL_*`, including the transport and sender settings in `config/mail.php`). To send through Resend, set `MAIL_MAILER=resend`, `RESEND_API_KEY`, and a `MAIL_FROM_ADDRESS` on a domain verified in Resend. Account emails, such as verification and password reset, use the same settings. Webhook delivery uses the stored Discord webhook URL. The `DiscordWebhookUrl` form rule and delivery driver accept only Discord webhook URLs, including supported Discord subdomains and optional API version paths; query strings and other hosts are rejected.

## Web test limits

Source tests and AI key tests (new or saved, including onboarding) use `throttle:6,1`: six requests per minute for authenticated users. Delivery tests allow six per minute and twenty per hour for each user. Results are transient. Source and AI key tests do not save records; delivery tests send a real Discord post or email.

## Account mail limits

These routes send email, so their limits protect the sending quota and the domain's reputation. Limiters live in `FortifyServiceProvider`.

| Route | Per IP | Per account | Whole instance |
| --- | --- | --- | --- |
| `POST /register` | 5 per minute, 20 per hour | | 50 accounts created per day |
| `POST /forgot-password` | 5 per minute, 20 per hour | 5 per hour | 50 links sent per day |
| `POST /reset-password` | 5 per minute, 20 per hour | | |
| `POST /email/verification-notification` | | 6 per minute, 12 per hour | |

The instance limits count only mail actually sent, so failed forms cannot use them up. Once an instance limit is reached, real signups or reset requests get a 429 until the counter expires, so set `DAILY_MAIL_BUDGET` to match your mail plan.

## Database

In development the database settings come from `compose.yaml` (`DB_HOST=postgres`, database `pulse`, user and password `pulse`). Tests always use the `pulse_testing` database: `phpunit.xml` overrides the connection with `<server … force="true">`, because the container's environment variables would otherwise win over `<env>` entries.
