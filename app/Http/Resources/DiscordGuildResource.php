<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DiscordGuild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DiscordGuild */
final class DiscordGuildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guild_id' => $this->guild_id,
            'name' => $this->name,
        ];
    }
}
