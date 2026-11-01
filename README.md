# Pulse

One calm daily digest of the news you actually follow. Pulse reads your sources, asks an AI model to keep what matters to you, and gives you a short summary each day at the time you choose, in the app, on Discord or by email.

Free and open source. You bring your own AI key. Try it at <https://pulse.promethys.dev>.

If you find Pulse useful, leave a star on GitHub. It helps other people find it.

## What it does

- **Daily news**: items grouped by topic, each with a one-line summary and a link to the original. 09:00 by default, in your timezone.
- **Tip and topic of the day**: a second post for tech interests, at 09:30 by default.
- **Your interests and sources**: 50+ interests with reviewed sources. Switch sources off, or add your own feed or newsletter.
- **Written for you**: a short profile tells the AI what you care about and what to skip.
- **Your AI keys**: Gemini, Anthropic, OpenAI, Groq, OpenRouter or Z.AI, ordered as a fallback chain. Keys are stored encrypted.
- **Delivery**: always in the app, plus as many Discord servers as you like (bot or webhook) and email.
- **Reading**: Today, past days, Archive, Search, tag history and a Learning library.
- **Accounts**: email verification, two-factor authentication and passkeys.

Built with Laravel 13, Vue 3, Inertia, PostgreSQL, Filament and `laravel/ai`.

## Setup

### Option 1: Docker (recommended)

Requirements:

- Docker with Compose
- Ports `8000`, `5173` and `5433` free

PHP, Node and PostgreSQL all run in containers, with the same PHP version as production.

```bash
git clone https://github.com/Promethys/pulse.git
cd pulse
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app npm ci
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm run dev
```

### Option 2: Without Docker

Requirements:

- PHP 8.4.1 or newer with the `pdo_pgsql`, `intl`, `zip` and `pcntl` extensions
- Composer
- Node 24
- PostgreSQL. Pulse does not run on SQLite
- Ports `8000` and `5173` free

```bash
git clone https://github.com/Promethys/pulse.git
cd pulse
cp .env.example .env
createdb pulse
```

Set `DB_USERNAME` and `DB_PASSWORD` in `.env` to your PostgreSQL user, then install, seed and start the app:

```bash
composer setup
php artisan db:seed
composer dev
```

### First steps

Open <http://localhost:8000> and sign in as `test@example.com` with password `password`, or register your own account. The default log mailer writes verification emails to `storage/logs/laravel.log`.

The commands below use Docker. Without Docker, drop `docker compose exec app`.

To see the app full of data, load 90 days of fictional history, then sign in as `showcase@example.com` with password `password`:

```bash
docker compose exec app php artisan db:seed --class=ShowcaseDataSeeder
```

To generate a real digest, complete onboarding with an AI key, then run:

```bash
docker compose exec app php artisan digest:news --user=<email>
```

## Running it for real

Scheduled digests need two processes: `php artisan schedule:run` every 15 minutes, and `php artisan queue:work` always running. Production builds the `prod` stage of the `Dockerfile`, with its own PostgreSQL database. To send email, set the `MAIL_*` variables.

- [Getting started](docs/02-getting-started.md) has the full setup and the everyday commands.
- [Configuration](docs/03-configuration.md) lists every setting.
- Upgrading an old single-owner install? Read [Adopting existing data](docs/02-getting-started.md#adopting-existing-data) before you migrate.

## Documentation

- [Overview](docs/01-overview.md)
- [Getting started](docs/02-getting-started.md)
- [Configuration](docs/03-configuration.md)
- [Architecture](docs/04-architecture.md), including [web app routes and read layer](docs/04-architecture.md#web-app)
- [Sources](docs/05-sources.md)
- [AI providers](docs/06-ai.md)
- [Testing](docs/07-testing.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Coding agents read [AGENTS.md](AGENTS.md).

## License

MIT, see [LICENSE](LICENSE).
