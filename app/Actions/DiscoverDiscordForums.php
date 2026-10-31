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
            $key = 'discord.forums.'.$guildId;
            $forums = Cache::get($key);

            if ($forums === null) {
                $forums = $this->discord->forumChannels($guildId);

                if ($forums !== []) {
                    Cache::put($key, $forums, 300);
                }
            }

            return ['forums' => $forums, 'forums_error' => false];
        } catch (Throwable) {
            return ['forums' => [], 'forums_error' => true];
        }
    }
}
