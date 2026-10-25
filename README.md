# Pulse

A personal daily digest, built with Laravel. Every morning Pulse reads the tech sources you follow (blogs, release notes, Hacker News, Reddit, GitHub trending, newsletters), asks an AI model to keep only what matters, and posts:

- **09:00**: the news of the night, grouped by topic, each item with a one-line summary and its source link;
- **09:30**: a developer tip and a software-engineering topic of the day.

Items, tags and posts are stored in PostgreSQL.

Accounts have per-user settings, interests, sources, AI keys and delivery records. The scheduled digest pipelines still use the instance configuration in `.env`.

## Quick start

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=InterestTableSeeder
docker compose exec app php artisan db:seed --class=SourceTableSeeder
docker compose exec app php artisan db:seed --class=TagTableSeeder
docker compose exec app php artisan db:seed --class=LearningTopicTableSeeder
docker compose exec app php artisan digest:sources
```

To assign existing configuration and history to a registered account:

```bash
docker compose exec app php artisan pulse:adopt <email> --dry-run
docker compose exec app php artisan pulse:adopt <email>
```

Replace `<email>` with the account's address. See [Getting started](docs/02-getting-started.md) for details.

## Documentation

- [Overview](docs/01-overview.md)
- [Getting started](docs/02-getting-started.md)
- [Configuration](docs/03-configuration.md)
- [Architecture](docs/04-architecture.md)
- [Sources](docs/05-sources.md)
- [AI providers](docs/06-ai.md)
- [Testing](docs/07-testing.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT, see [LICENSE](LICENSE).
