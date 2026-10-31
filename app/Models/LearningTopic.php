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
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, LearningTopicUsage> $usages
 */
#[Fillable(['title', 'category'])]
class LearningTopic extends Model
{
    /** @use HasFactory<LearningTopicFactory> */
    use HasFactory;

    /**
     * @return HasMany<LearningTopicUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(LearningTopicUsage::class);
    }
}
