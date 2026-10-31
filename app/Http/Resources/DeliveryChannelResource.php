<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\DeliveryChannelType;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeliveryChannel */
final class DeliveryChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->settings ?? [];
        $hasLatest = $this->relationLoaded('latestDelivery') || array_key_exists('latestDelivery', $this->getAttributes());

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'kinds' => $this->kinds,
            'enabled' => $this->enabled,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'target' => match ($this->type) {
                DeliveryChannelType::DiscordBot => ['guild_id' => $settings['guild_id'] ?? null, 'channel_id' => $settings['channel_id'] ?? null],
                DeliveryChannelType::Email => ['address' => $settings['address'] ?? null],
                DeliveryChannelType::DiscordWebhook => ['webhook' => '…'.substr($settings['webhook_url'] ?? '', -4)],
                DeliveryChannelType::InApp => (object) [],
            },
            'latest_delivery' => $this->when($hasLatest, function () use ($request, $settings) {
                /** @var Delivery|null $delivery */
                $delivery = $this->resource->getAttribute('latestDelivery');

                if ($delivery === null) {
                    return null;
                }
                $data = (new DeliveryResource($delivery))->resolve($request);
                if ($delivery->last_error !== null) {
                    $secrets = array_filter([$settings['webhook_url'] ?? '', (string) config('services.discord.token')]);
                    if (isset($settings['webhook_url'])) {
                        $secrets[] = basename($settings['webhook_url']);
                    }
                    $data['last_error'] = str_replace($secrets, '[redacted]', $delivery->last_error);
                }

                return $data;
            }),
        ];
    }
}
