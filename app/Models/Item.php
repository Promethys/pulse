<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $url
 * @property string $url_normalized
 * @property string $title
 * @property string $excerpt
 * @property list<string> $links
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Source> $sources
 * @property-read Collection<int, PostItem> $postItems
 */
#[Fillable(['url', 'url_normalized', 'title', 'excerpt', 'links', 'published_at'])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsToMany<Source, $this, ItemSource>
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class)->using(ItemSource::class)->withPivot('first_seen_at');
    }

    /**
     * @return HasMany<PostItem, $this>
     */
    public function postItems(): HasMany
    {
        return $this->hasMany(PostItem::class);
    }
}
