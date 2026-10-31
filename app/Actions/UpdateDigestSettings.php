<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final class UpdateDigestSettings
{
    /** @param array{profile?: string|null, timezone: string, news_enabled: bool, news_time: string, learning_enabled: bool, learning_time: string, max_items: int} $data */
    public function handle(User $user, array $data): void
    {
        $user->digestSettings()->firstOrCreate()->update($data);
    }
}
