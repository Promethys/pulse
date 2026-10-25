<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property int $delivery_channel_id
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property int $progress
 * @property string|null $external_id
 * @property string|null $last_error
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Post $post
 * @property-read DeliveryChannel $channel
 */
#[Fillable(['post_id', 'delivery_channel_id', 'status', 'attempts', 'progress', 'external_id', 'last_error', 'delivered_at'])]
class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'progress' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'progress' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<DeliveryChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(DeliveryChannel::class, 'delivery_channel_id');
    }
}
