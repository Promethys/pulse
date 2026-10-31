<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Models\SourceProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceProposal>
 */
class SourceProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'origin' => SourceProposalOrigin::Scout,
            'kind' => SourceProposalKind::NewSource,
            'status' => SourceProposalStatus::Pending,
            'user_id' => null,
            'source_id' => null,
            'interest_id' => null,
            'url' => fake()->url().'/feed.xml',
            'site_url' => null,
            'name' => fake()->company(),
            'reason' => null,
            'checks' => null,
        ];
    }
}
