<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DeliveryChannelType;
use App\Models\DeliveryChannel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDeliveryChannel
{
    /** @param array{type?: string, enabled?: bool, kinds: list<string>, guild_id?: string, channel_id?: string, name?: string, webhook_url?: string, address?: string} $data */
    public function handle(User $user, array $data, ?DeliveryChannel $channel = null): void
    {
        DB::transaction(function () use ($user, $data, $channel): void {
            $user->newQuery()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $type = $channel === null ? DeliveryChannelType::from($data['type'] ?? '') : $channel->type;
            if ($channel === null) {
                if ($type === DeliveryChannelType::Email && $user->deliveryChannels()->where('type', $type)->exists()) {
                    throw ValidationException::withMessages(['address' => __('You may add one email destination.')]);
                }
                if (in_array($type, [DeliveryChannelType::DiscordBot, DeliveryChannelType::DiscordWebhook], true)
                    && $user->deliveryChannels()->whereIn('type', [DeliveryChannelType::DiscordBot, DeliveryChannelType::DiscordWebhook])->count() >= 20) {
                    throw ValidationException::withMessages(['type' => __('You may add up to twenty Discord destinations.')]);
                }
            }
            $settings = $channel === null ? [] : ($channel->settings ?? []);
            foreach (match ($type) {
                DeliveryChannelType::DiscordBot => $channel === null ? ['guild_id', 'channel_id'] : ['channel_id'],
                DeliveryChannelType::DiscordWebhook => ['webhook_url'],
                DeliveryChannelType::Email => ['address'],
                DeliveryChannelType::InApp => [],
            } as $field) {
                if (isset($data[$field])) {
                    $settings[$field] = $data[$field];
                }
            }
            $values = [
                'name' => $data['name'] ?? ($channel === null ? match ($type) {
                    DeliveryChannelType::DiscordBot => __('Forum channel'),
                    DeliveryChannelType::Email => __('Email'),
                    default => __('Webhook'),
                } : $channel->name),
                'enabled' => $data['enabled'] ?? true,
                'kinds' => $data['kinds'],
                'settings' => $settings,
            ];
            if ($channel === null) {
                $user->deliveryChannels()->create(['type' => $type, ...$values]);
            } else {
                $user->deliveryChannels()->whereKey($channel->id)->firstOrFail()->update($values);
            }
        });
    }
}
