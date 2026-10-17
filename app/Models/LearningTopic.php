<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LearningTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $category
 * @property Carbon|null $last_used_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
}
