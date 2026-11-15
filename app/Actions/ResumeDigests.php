<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final class ResumeDigests
{
    public function handle(User $user): void
    {
        $user->digestSettings()->firstOrCreate()->forceFill([
            'paused_at' => null,
            'paused_until' => null,
        ])->save();
    }
}
