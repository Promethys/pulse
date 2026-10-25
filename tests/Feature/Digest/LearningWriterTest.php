<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\LearningWriter;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Tests\TestCase;

class LearningWriterTest extends TestCase
{
    public function test_topic_instructions_apply_the_topic_to_the_readers_main_stack_from_the_profile(): void
    {
        $instructions = (new LearningWriter(
            profile: 'A Python and Django developer.',
            recentTipTitles: [],
            existingTags: [],
        ))->instructions();

        $this->assertStringContainsString('Reader profile: A Python and Django developer.', $instructions);
        $this->assertStringContainsString('stack_angle', $instructions);
        $this->assertMatchesRegularExpression('/main stack/i', $instructions);
        $this->assertMatchesRegularExpression('/profile/i', substr($instructions, strpos($instructions, 'stack_angle')));
        $this->assertStringNotContainsString('laravel_angle', $instructions);
        $this->assertStringNotContainsString('how it applies in Laravel or PHP', $instructions);
    }

    public function test_instructions_omit_the_stack_angle_when_the_profile_names_no_stack(): void
    {
        $instructions = (new LearningWriter(
            profile: 'A software developer interested in distributed systems.',
            recentTipTitles: [],
            existingTags: [],
        ))->instructions();

        $this->assertStringContainsString('stack_angle', $instructions);
        $angleInstructions = substr($instructions, strpos($instructions, 'stack_angle'));
        $this->assertMatchesRegularExpression('/omit|empty string/i', $angleInstructions);
        $this->assertMatchesRegularExpression('/no|none|not|without|unspecified|otherwise/i', $angleInstructions);
    }

    public function test_topic_schema_has_stack_angle_instead_of_laravel_angle(): void
    {
        $schema = (new LearningWriter(profile: '', recentTipTitles: [], existingTags: []))
            ->schema(new JsonSchemaTypeFactory);
        $topic = $schema['topic']->toArray();

        $this->assertArrayHasKey('stack_angle', $topic['properties']);
        $this->assertSame('string', $topic['properties']['stack_angle']['type']);
        $this->assertArrayNotHasKey('laravel_angle', $topic['properties']);
    }
}
