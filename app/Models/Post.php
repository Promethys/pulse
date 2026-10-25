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
 * @property int|null $user_id
 * @property CarbonImmutable $date
 * @property PostKind $kind
 * @property PostStatus $status
 * @property int $attempts
 * @property array<string, mixed>|null $rendered
 * @property int $messages_sent
 * @property string|null $discord_thread_id
 * @property CarbonImmutable|null $posted_at
 * @property string|null $last_error
 * @property string|null $model_used
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, PostItem> $postItems
 */
#[Fillable(['user_id', 'date', 'kind', 'status', 'attempts', 'rendered', 'messages_sent', 'discord_thread_id', 'posted_at', 'last_error', 'model_used'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
        'messages_sent' => 0,
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
            'messages_sent' => 'integer',
            'posted_at' => 'datetime',
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
     * @return HasMany<PostItem, $this>
     */
    public function postItems(): HasMany
    {
        return $this->hasMany(PostItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return HasMany<LearningPost, $this>
     */
    public function learningPosts(): HasMany
    {
        return $this->hasMany(LearningPost::class);
    }
}
