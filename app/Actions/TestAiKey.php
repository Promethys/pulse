<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\HealthCheck;
use App\Enums\AiProvider;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

final class TestAiKey
{
    /**
     * @return array{ok: bool, message: string, detail?: string}
     */
    public function handle(User $user, AiProvider $provider, string $model, string $apiKey): array
    {
        try {
            $name = "test_user_{$user->id}_{$provider->value}";
            Ai::build([
                ...(array) config("ai.providers.{$provider->value}"),
                'name' => $name,
                'key' => $apiKey,
            ]);
            $response = (new HealthCheck)->prompt('Health check.', provider: [$name => $model], timeout: (int) config('digest.ai_timeout'));
            $ok = ($response->toArray()['ok'] ?? false) === true;

            return ['ok' => $ok, 'message' => $ok ? __('Key works.') : __('Unexpected provider response.')];
        } catch (Throwable $exception) {
            Log::warning('AI key test failed.', [
                'provider' => $provider->value,
                'model' => $model,
                'error' => Str::limit(str_replace([$apiKey, rawurlencode($apiKey)], '[redacted]', $exception->getMessage()), 500),
            ]);

            $result = ['ok' => false, 'message' => $this->explain($exception, $provider)];
            for ($error = $exception; $error !== null; $error = $error->getPrevious()) {
                if ($error instanceof RequestException) {
                    foreach (['error.message', 'message', '0.error.message'] as $path) {
                        $detail = $error->response->json($path);
                        if (is_string($detail) && $detail !== '') {
                            $result['detail'] = Str::limit(str_replace([$apiKey, rawurlencode($apiKey)], '[redacted]', $detail), 300);
                            break;
                        }
                    }
                    break;
                }
            }

            return $result;
        }
    }

    private function explain(Throwable $exception, AiProvider $provider): string
    {
        for ($error = $exception; $error !== null; $error = $error->getPrevious()) {
            $message = match (true) {
                $error instanceof RateLimitedException => __('The provider is rate limiting this key, or its account has no balance left. Try again later.'),
                $error instanceof InsufficientCreditsException => __('The provider account has no credit left.'),
                $error instanceof ProviderOverloadedException => __('The provider is overloaded right now. Try again in a minute.'),
                $error instanceof ProviderConnectionException, $error instanceof ConnectionException => __('Pulse could not reach the provider. Try again.'),
                $error instanceof RequestException => match ($error->response->status()) {
                    401, 403 => __(':provider rejected this API key.', ['provider' => $provider->label()]),
                    400 => str_contains($error->response->body(), 'API_KEY_INVALID')
                        ? __(':provider rejected this API key.', ['provider' => $provider->label()])
                        : __(':provider rejected the request. Check the model name.', ['provider' => $provider->label()]),
                    404 => __(':provider rejected the request. Check the model name.', ['provider' => $provider->label()]),
                    default => __('The key test failed (HTTP :status).', ['status' => $error->response->status()]),
                },
                default => null,
            };

            if ($message !== null) {
                return $message;
            }
        }

        return __('The key test failed.');
    }
}
