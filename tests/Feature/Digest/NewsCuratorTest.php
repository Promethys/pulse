<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\NewsCurator;
use Tests\TestCase;

class NewsCuratorTest extends TestCase
{
    public function test_instructions_include_the_readers_profile_interests_limit_and_existing_tags(): void
    {
        $instructions = (new NewsCurator(
            profile: 'A reader following astronomy and gardening.',
            existingTags: ['space', 'gardening'],
            maxItems: 7,
            interests: ['Astronomy', 'Gardening'],
        ))->instructions();

        $this->assertStringContainsString('Reader profile: A reader following astronomy and gardening.', $instructions);
        $this->assertStringContainsString('Reader interests: Astronomy, Gardening', $instructions);
        $this->assertStringContainsString('at most 7 items', $instructions);
        $this->assertStringContainsString('Existing tags: space, gardening', $instructions);
        $this->assertDoesNotMatchRegularExpression('/\btech\b/i', $instructions);
    }

    public function test_empty_interests_and_blank_profile_omit_the_reader_lines_and_still_avoid_tech(): void
    {
        $instructions = (new NewsCurator(profile: '  ', existingTags: [], maxItems: 3, interests: []))->instructions();

        $this->assertStringNotContainsString('Reader interests:', $instructions);
        $this->assertStringNotContainsString('Reader profile:', $instructions);
        $this->assertStringContainsString('at most 3 items', $instructions);
        $this->assertStringContainsString('Existing tags: (none yet)', $instructions);
        $this->assertDoesNotMatchRegularExpression('/\btech\b/i', $instructions);
    }
}
