<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\LearningTopicUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $learning_topic_id
 * @property CarbonImmutable $used_on
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read LearningTopic $topic
 */
#[Fillable(['user_id', 'learning_topic_id', 'used_on'])]
class LearningTopicUsage extends Model
{
    /** @use HasFactory<LearningTopicUsageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_on' => 'date',
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
     * @return BelongsTo<LearningTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'learning_topic_id');
    }
}
