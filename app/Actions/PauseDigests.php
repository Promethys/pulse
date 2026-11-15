<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final class PauseDigests
{
    public function handle(User $user, ?string $until = null): void
    {
        $user->digestSettings()->firstOrCreate()->forceFill([
            'paused_at' => now(),
            'paused_until' => $until,
        ])->save();
    }
}
