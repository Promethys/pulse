<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryChannelType;
use App\Enums\PostKind;
use Carbon\CarbonImmutable;
use Database\Factories\DeliveryChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property DeliveryChannelType $type
 * @property string $name
 * @property list<string> $kinds
 * @property bool $enabled
 * @property array<string, string>|null $settings
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Delivery> $deliveries
 * @property-read Delivery|null $latestDelivery
 */
#[Fillable(['user_id', 'type', 'name', 'kinds', 'enabled', 'settings', 'verified_at'])]
#[Hidden(['settings'])]
class DeliveryChannel extends Model
{
    /** @use HasFactory<DeliveryChannelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeliveryChannelType::class,
            'kinds' => 'array',
            'enabled' => 'boolean',
            'settings' => 'encrypted:array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * @return HasOne<Delivery, $this>
     */
    public function latestDelivery(): HasOne
    {
        return $this->hasOne(Delivery::class, 'delivery_channel_id')->latestOfMany();
    }

    public function takes(PostKind $kind): bool
    {
        return in_array($kind->value, $this->kinds, true);
    }

    /**
     * @param  Builder<DeliveryChannel>  $query
     */
    public function scopeReceiving(Builder $query, PostKind $kind): void
    {
        $query->where('enabled', true)->whereJsonContains('kinds', $kind->value);
    }
}
