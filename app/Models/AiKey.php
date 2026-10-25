<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiProvider;
use Carbon\CarbonImmutable;
use Database\Factories\AiKeyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property AiProvider $provider
 * @property string $model
 * @property string $api_key
 * @property string $key_suffix
 * @property int $position
 * @property CarbonImmutable $consented_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'provider', 'model', 'api_key', 'position', 'consented_at'])]
#[Hidden(['api_key'])]
class AiKey extends Model
{
    /** @use HasFactory<AiKeyFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (AiKey $key) {
            if ($key->isDirty('api_key')) {
                $key->key_suffix = substr($key->api_key, -4);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'position' => 'integer',
            'consented_at' => 'datetime',
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
