<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DeliveryChannelType;
use App\Models\DiscordGuild;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DisconnectDiscordGuild
{
    public function handle(User $user, DiscordGuild $guild): void
    {
        DB::transaction(function () use ($user, $guild): void {
            $owned = $user->discordGuilds()->whereKey($guild->id)->firstOrFail();
            foreach ($user->deliveryChannels()->where('type', DeliveryChannelType::DiscordBot)->get() as $channel) {
                if (($channel->settings['guild_id'] ?? null) === $owned->guild_id) {
                    $channel->delete();
                }
            }
            $owned->delete();
        });
    }
}
