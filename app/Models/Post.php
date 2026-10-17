<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostKind;
use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property PostKind $kind
 * @property PostStatus $status
 * @property int $attempts
 * @property array<string, mixed>|null $rendered
 * @property int $messages_sent
 * @property string|null $discord_thread_id
 * @property Carbon|null $posted_at
 * @property string|null $last_error
 * @property string|null $model_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'kind', 'status', 'attempts', 'rendered', 'messages_sent', 'discord_thread_id', 'posted_at', 'last_error', 'model_used'])]
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
