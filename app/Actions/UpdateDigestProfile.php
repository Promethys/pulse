<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final class UpdateDigestProfile
{
    public function handle(User $user, ?string $profile, string $timezone): void
    {
        $user->digestSettings()->firstOrCreate()->update(['profile' => $profile, 'timezone' => $timezone]);
    }
}
