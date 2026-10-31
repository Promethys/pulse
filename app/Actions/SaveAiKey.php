<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AiProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveAiKey
{
    public function handle(User $user, AiProvider $provider, string $model, string $apiKey): void
    {
        DB::transaction(function () use ($user, $provider, $model, $apiKey): void {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $key = $user->aiKeys()->where('provider', $provider)->first();

            if ($key === null && $user->aiKeys()->count() >= 5) {
                throw ValidationException::withMessages(['provider' => __('You may save up to five AI keys.')]);
            }

            $user->aiKeys()->updateOrCreate(['provider' => $provider], [
                'model' => $model,
                'api_key' => $apiKey,
                'position' => $key->position ?? ((int) $user->aiKeys()->max('position') + 1),
                'consented_at' => now(),
            ]);
        });
    }
}
