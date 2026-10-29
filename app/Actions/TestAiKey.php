<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\HealthCheck;
use App\Enums\AiProvider;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

final class TestAiKey
{
    /**
     * @return array{ok: bool, message: string}
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
            $ok = $response instanceof StructuredAgentResponse && ($response->toArray()['ok'] ?? false) === true;

            return ['ok' => $ok, 'message' => $ok ? __('Key works.') : __('Unexpected provider response.')];
        } catch (Throwable $exception) {
            $message = str_replace([$apiKey, rawurlencode($apiKey), urlencode($apiKey)], '[redacted]', $exception->getMessage());
            $message = trim(strip_tags($message));

            return ['ok' => false, 'message' => $message === '' ? __('The key test failed.') : Str::limit($message, 200)];
        }
    }
}
