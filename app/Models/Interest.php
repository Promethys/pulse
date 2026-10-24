<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InterestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property InterestCategory $category
 * @property string $icon
 * @property int $position
 * @property bool $has_learning
 * @property InterestStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, User> $users
 */
#[Fillable(['slug', 'name', 'category', 'icon', 'position', 'has_learning', 'status'])]
class Interest extends Model
{
    /** @use HasFactory<InterestFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
        'has_learning' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => InterestCategory::class,
            'position' => 'integer',
            'has_learning' => 'boolean',
            'status' => InterestStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @param  Builder<Interest>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', InterestStatus::Live);
    }
}
