<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $source_slug
 * @property string $url
 * @property string $url_normalized
 * @property string $title
 * @property string $excerpt
 * @property list<string> $links
 * @property CarbonImmutable|null $published_at
 * @property ItemStatus $status
 * @property string|null $summary
 * @property int|null $post_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['source_slug', 'url', 'url_normalized', 'title', 'excerpt', 'links', 'published_at', 'status', 'summary', 'post_id'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'candidate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'published_at' => 'immutable_datetime',
            'status' => ItemStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
