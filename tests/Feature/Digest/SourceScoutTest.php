<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\SourceScout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;
use Tests\TestCase;

class SourceScoutTest extends TestCase
{
    public function test_uses_text_output_and_limits_web_search_to_five_searches(): void
    {
        $agent = new SourceScout('Astronomy', 3, ['existing.test']);

        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertInstanceOf(HasTools::class, $agent);
        $this->assertNotInstanceOf(HasStructuredOutput::class, $agent);
        $this->assertContains(Promptable::class, class_uses_recursive($agent));
        $tools = collect($agent->tools())->all();
        $this->assertCount(1, $tools);
        $this->assertInstanceOf(WebSearch::class, $tools[0]);
        $this->assertSame(5, $tools[0]->maxSearches);
    }

    public function test_instructions_include_the_interest_limit_domains_and_feed_quality_rules(): void
    {
        $instructions = (string) (new SourceScout('Astronomy', 3, ['existing.test', 'another.test']))->instructions();

        foreach (['Astronomy', '3', 'existing.test', 'another.test', 'RSS', 'Atom', 'JSON', 'proposals', 'name', 'url', 'site_url', 'reason'] as $text) {
            $this->assertStringContainsString($text, $instructions);
        }
        foreach (['active', 'original', 'regular', 'paywall', 'spam', 'aggregator'] as $text) {
            $this->assertStringContainsString($text, strtolower($instructions));
        }
        $this->assertMatchesRegularExpression('/(?:only\s+JSON|JSON\s+only)/i', $instructions);
    }

    public function test_instructions_treat_web_results_as_data_and_never_as_instructions(): void
    {
        $instructions = (string) (new SourceScout('Gardening', 5, []))->instructions();

        $this->assertMatchesRegularExpression('/(?:web|search)\s+results.*data/is', $instructions);
        $this->assertMatchesRegularExpression('/(?:never|not|ignore|do not).*instructions/is', $instructions);
    }

    public function test_without_search_it_has_no_tools_and_only_suggests_known_feeds(): void
    {
        $agent = new SourceScout('Gardening', 5, [], webSearch: false);

        $this->assertSame([], collect($agent->tools())->all());
        $this->assertStringNotContainsString('web search', strtolower((string) $agent->instructions()));
        $this->assertStringContainsString('confident', (string) $agent->instructions());
    }
}
