<?php

declare(strict_types=1);

namespace App\Actions;

use App\Digest\Discord\DiscordClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class DiscoverDiscordForums
{
    public function __construct(private readonly DiscordClient $discord) {}

    /** @return array{forums: list<array{id: string, name: string}>, forums_error: bool} */
    public function handle(string $guildId): array
    {
        try {
            $forums = Cache::remember('discord.forums.'.$guildId, 300, fn () => $this->discord->forumChannels($guildId));

            return ['forums' => $forums, 'forums_error' => false];
        } catch (Throwable) {
            return ['forums' => [], 'forums_error' => true];
        }
    }
}
