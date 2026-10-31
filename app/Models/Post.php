<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostKind;
use App\Enums\PostStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $date
 * @property PostKind $kind
 * @property PostStatus $status
 * @property int $attempts
 * @property array<string, mixed>|null $rendered
 * @property string|null $last_error
 * @property string|null $model_used
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, PostItem> $postItems
 * @property-read Collection<int, Delivery> $deliveries
 */
#[Fillable(['user_id', 'date', 'kind', 'status', 'attempts', 'rendered', 'last_error', 'model_used'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kind' => PostKind::class,
            'status' => PostStatus::class,
            'attempts' => 'integer',
            'rendered' => 'array',
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
     * @return HasMany<PostItem, $this>
     */
    public function postItems(): HasMany
    {
        return $this->hasMany(PostItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<LearningPost, $this>
     */
    public function learningPosts(): HasMany
    {
        return $this->hasMany(LearningPost::class);
    }
}
