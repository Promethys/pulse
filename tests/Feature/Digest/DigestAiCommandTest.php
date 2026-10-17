<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\HealthCheck;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class DigestAiCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['digest.ai_providers' => 'gemini:gemini-test-flash,anthropic:claude-haiku-5-5']);
    }

    public function test_checks_each_provider_separately(): void
    {
        HealthCheck::fake([['ok' => true], ['ok' => true]])->preventStrayPrompts();

        $this->artisan('digest:ai')
            ->expectsTable(['Provider', 'Model', 'Result'], [['gemini', 'gemini-test-flash', 'OK'], ['anthropic', 'claude-haiku-5-5', 'OK']])
            ->assertExitCode(0);

        HealthCheck::assertPromptedTimes(2);
        HealthCheck::assertPrompted(fn (AgentPrompt $prompt) => $prompt->model === 'gemini-test-flash');
        HealthCheck::assertPrompted(fn (AgentPrompt $prompt) => $prompt->model === 'claude-haiku-5-5');
    }

    public function test_reports_a_failing_provider_and_fails(): void
    {
        // laravel/ai 1.2 calls fake callbacks with (prompt text, attachments, provider, model).
        HealthCheck::fake(function (string $prompt, mixed $attachments, mixed $provider, string $model) {
            if ($model === 'gemini-test-flash') {
                throw new RateLimitedException('quota exceeded');
            }

            return ['ok' => true];
        });

        $this->artisan('digest:ai')
            ->expectsTable(['Provider', 'Model', 'Result'], [['gemini', 'gemini-test-flash', 'quota exceeded'], ['anthropic', 'claude-haiku-5-5', 'OK']])
            ->assertExitCode(1);
    }

    public function test_invalid_configuration_fails_with_its_message(): void
    {
        config(['digest.ai_providers' => '']);

        $this->artisan('digest:ai')
            ->expectsOutputToContain('DIGEST_AI_PROVIDERS is not set')
            ->assertExitCode(1);
    }
}
