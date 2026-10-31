<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SourceProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property SourceProposalOrigin $origin
 * @property SourceProposalKind $kind
 * @property SourceProposalStatus $status
 * @property int|null $user_id
 * @property int|null $source_id
 * @property int|null $interest_id
 * @property string $url
 * @property string|null $site_url
 * @property string|null $name
 * @property string|null $reason
 * @property array<string, mixed>|null $checks
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Source|null $source
 * @property-read Interest|null $interest
 */
#[Fillable(['origin', 'kind', 'status', 'user_id', 'source_id', 'interest_id', 'url', 'site_url', 'name', 'reason', 'checks'])]
class SourceProposal extends Model
{
    /** @use HasFactory<SourceProposalFactory> */
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
            'origin' => SourceProposalOrigin::class,
            'kind' => SourceProposalKind::class,
            'status' => SourceProposalStatus::class,
            'checks' => 'array',
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
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<Interest, $this>
     */
    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }
}
