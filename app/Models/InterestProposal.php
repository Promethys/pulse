<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterestCategory;
use App\Enums\SourceProposalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InterestProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property InterestCategory $category
 * @property string $reason
 * @property SourceProposalStatus $status
 * @property int|null $interest_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Interest|null $interest
 */
#[Fillable(['user_id', 'name', 'category', 'reason', 'status', 'interest_id'])]
class InterestProposal extends Model
{
    /** @use HasFactory<InterestProposalFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => InterestCategory::class,
            'status' => SourceProposalStatus::class,
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
     * @return BelongsTo<Interest, $this>
     */
    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }
}
