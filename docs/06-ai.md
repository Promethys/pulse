# AI providers

Pulse calls AI models through the [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk) (`laravel/ai`). It is not tied to one provider: any provider defined in `config/ai.php` (Anthropic, Gemini, OpenAI, Mistral, Groq, DeepSeek, OpenRouter, xAI, Ollama, OpenAI-compatible endpoints, …) can be used by changing `.env`.

## Choosing providers

`DIGEST_AI_PROVIDERS` is an ordered list of `provider:model` pairs:

```dotenv
DIGEST_AI_PROVIDERS="gemini:<model>,anthropic:claude-haiku-5-5"
GEMINI_API_KEY=...
ANTHROPIC_API_KEY=...
```

The first provider is tried first. The SDK fails over to the next one when a provider is rate limited, overloaded or out of credits. Other errors (an invalid request, for example) don't fail over; the pipeline then falls back to a plain list of headlines without summaries.

The provider names are the keys of `config/ai.php`; each provider reads its own API key there (`GEMINI_API_KEY`, `ANTHROPIC_API_KEY`, …). A model must always be given, so a provider upgrade never silently changes the model.

## Agents

Each AI task is an agent class in `app/Ai/Agents/` that implements the SDK's `Agent` and `HasStructuredOutput` contracts: it has instructions and a JSON schema, so the answer always has the same shape whichever provider produced it.

| Agent | Used by | Returns |
|---|---|---|
| `HealthCheck` | `digest:ai` | `{ok}` |
| `NewsCurator` | the news selection | `{items: [{candidate_id, url, title, summary, tags}]}` |
| `LearningWriter` | the tip + topic of the day | `{tip: {title, body, tags}, topic: {body, laravel_angle, links: [{title, url}], tags}}` |

`NewsCurator`'s instructions include the reader profile (`DIGEST_PROFILE`, left out when empty), the maximum number of items and the existing tags, so the model reuses them instead of creating near-duplicates ("ai" vs "artificial-intelligence"). The candidates are sent as JSON in the prompt.

## Health check

```bash
docker compose exec app php artisan digest:ai
```

It sends one tiny structured prompt to **each** provider of the chain separately (through the chain, a broken first provider would be hidden by the failover) and prints which ones work. It exits with an error code if any provider fails or the configuration is invalid.

## Tests

Agents are faked with the SDK's testing helpers, so the test suite never calls a real provider:

```php
HealthCheck::fake([['ok' => true]])->preventStrayPrompts();
HealthCheck::assertPromptedTimes(1);
```

With `laravel/ai` 1.2, a fake callback receives `(string $prompt, $attachments, $provider, string $model)`, not the `AgentPrompt` object shown in the SDK docs; `assertPrompted()` callbacks do receive an `AgentPrompt`.
