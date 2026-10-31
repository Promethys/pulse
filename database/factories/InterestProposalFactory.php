<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InterestCategory;
use App\Enums\SourceProposalStatus;
use App\Models\InterestProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterestProposal>
 */
class InterestProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'category' => InterestCategory::Technology,
            'reason' => fake()->sentence(),
            'status' => SourceProposalStatus::Pending,
            'interest_id' => null,
        ];
    }
}
