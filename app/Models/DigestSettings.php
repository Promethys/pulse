<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DigestSettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $timezone
 * @property string|null $profile
 * @property bool $news_enabled
 * @property string $news_time
 * @property bool $learning_enabled
 * @property string $learning_time
 * @property int $max_items
 * @property CarbonImmutable|null $onboarded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['timezone', 'profile', 'news_enabled', 'news_time', 'learning_enabled', 'learning_time', 'max_items'])]
class DigestSettings extends Model
{
    /** @use HasFactory<DigestSettingsFactory> */
    use HasFactory;

    protected $table = 'digest_settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'news_enabled' => 'boolean',
            'learning_enabled' => 'boolean',
            'max_items' => 'integer',
            'onboarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
