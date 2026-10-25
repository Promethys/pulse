<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $item_id
 * @property int $source_id
 * @property CarbonImmutable $first_seen_at
 */
class ItemSource extends Pivot
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
        ];
    }
}
