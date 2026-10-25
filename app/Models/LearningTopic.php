<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LearningTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string $category
 * @property CarbonImmutable|null $last_used_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, LearningTopicUsage> $usages
 */
#[Fillable(['title', 'category', 'last_used_on'])]
class LearningTopic extends Model
{
    /** @use HasFactory<LearningTopicFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_on' => 'date',
        ];
    }

    /**
     * @return HasMany<LearningTopicUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(LearningTopicUsage::class);
    }
}
