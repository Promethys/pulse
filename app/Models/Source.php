<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourceType;
use Carbon\CarbonImmutable;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $user_id
 * @property bool $is_default
 * @property string|null $site_url
 * @property array<string, string>|null $credentials
 * @property string $slug
 * @property string $name
 * @property SourceType $type
 * @property string|null $url
 * @property string $tag
 * @property int $max_items
 * @property int $position
 * @property bool $enabled
 * @property CarbonImmutable|null $retired_at
 * @property CarbonImmutable|null $last_fetched_at
 * @property CarbonImmutable|null $last_success_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, Interest> $interests
 * @property-read Collection<int, SourceSubscription> $subscriptions
 * @property-read Collection<int, Item> $items
 * @property-read Collection<int, SourceCheck> $checks
 */
#[Fillable(['slug', 'name', 'type', 'url', 'tag', 'max_items', 'position', 'enabled', 'last_fetched_at', 'last_success_at', 'last_error', 'retired_at', 'is_default', 'site_url', 'credentials'])]
#[Hidden(['credentials'])]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'max_items' => 'integer',
            'position' => 'integer',
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
            'retired_at' => 'datetime',
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
     * @return BelongsToMany<Interest, $this>
     */
    public function interests(): BelongsToMany
    {
        return $this->belongsToMany(Interest::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Item, $this, ItemSource>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class)->using(ItemSource::class)->withPivot('first_seen_at');
    }

    /**
     * @return HasMany<SourceSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SourceSubscription::class);
    }

    /**
     * @return HasMany<SourceCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(SourceCheck::class);
    }

    /**
     * @param  Builder<Source>  $query
     */
    public function scopeCatalog(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /**
     * Enabled sources, highest priority first.
     *
     * @param  Builder<Source>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true)->orderBy('position');
    }
}
