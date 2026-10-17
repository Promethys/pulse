<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LearningKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property LearningKind $kind
 * @property int|null $learning_topic_id
 * @property string $title
 * @property string $body
 * @property list<array{title: string, url: string}> $links
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['post_id', 'kind', 'learning_topic_id', 'title', 'body', 'links'])]
class LearningPost extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => LearningKind::class,
            'links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<LearningTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(LearningTopic::class, 'learning_topic_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
