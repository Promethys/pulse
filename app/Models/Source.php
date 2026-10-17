<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property SourceType $type
 * @property string|null $url
 * @property string $tag
 * @property int $max_items
 * @property int $position
 * @property bool $enabled
 * @property CarbonImmutable|null $last_fetched_at
 * @property CarbonImmutable|null $last_success_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['slug', 'name', 'type', 'url', 'tag', 'max_items', 'position', 'enabled', 'last_fetched_at', 'last_success_at', 'last_error'])]
class Source extends Model
{
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
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
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
