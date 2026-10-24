# Pulse

A personal daily digest, built with Laravel. Every morning Pulse reads the tech sources you follow (blogs, release notes, Hacker News, GitHub trending, newsletters), asks an AI model to keep only what matters, and posts:

- **09:00**: the news of the night, grouped by topic, each item with a one-line summary and its source link;
- **09:30**: a developer tip and a software-engineering topic of the day.

Everything is stored, so the history can be searched by tag later (web app, then Android app).

> Status: MVP, single user.

## Quick start

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=SourceTableSeeder
docker compose exec app php artisan db:seed --class=TagTableSeeder
docker compose exec app php artisan db:seed --class=LearningTopicTableSeeder
docker compose exec app php artisan digest:sources
```

## Documentation

- [Overview](docs/01-overview.md)
- [Getting started](docs/02-getting-started.md)
- [Configuration](docs/03-configuration.md)
- [Architecture](docs/04-architecture.md)
- [Sources](docs/05-sources.md)
- [AI providers](docs/06-ai.md)
- [Testing](docs/07-testing.md)

## License

MIT. The `LICENSE` file is added before the repository goes public.
