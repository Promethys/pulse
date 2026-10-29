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
            'latest_delivery' => $this->when($hasLatest, function () {
                /** @var Delivery|null $delivery */
                $delivery = $this->resource->getAttribute('latestDelivery');

                return $delivery === null ? null : new DeliveryResource($delivery);
            }),
        ];
    }
}
