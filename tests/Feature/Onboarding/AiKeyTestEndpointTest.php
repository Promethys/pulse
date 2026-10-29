<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Ai\Agents\HealthCheck;
use App\Enums\AiProvider;
use App\Models\AiKey;
use App\Models\DigestSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AiKeyTestEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    #[DataProvider('providers')]
    public function test_a_key_is_checked_with_the_submitted_credentials_and_model_without_consent_or_storage(string $provider): void
    {
        config(["ai.providers.$provider.key" => 'fake-instance-key']);
        $configured = config("ai.providers.$provider");
        HealthCheck::fake([['ok' => true]])->preventStrayPrompts();
        $user = $this->onboardingUser();
        $key = AiKey::factory()->for($user)->provider(AiProvider::Gemini)->create();
        $before = $key->fresh()->getAttributes();
        $settings = $user->digestSettings()->sole()->getAttributes();

        $this->actingAs($user)->postJson('/onboarding/ai-key/test', [
            'provider' => $provider, 'model' => 'my-custom-model', 'api_key' => 'fake-submitted-key',
        ])->assertOk()->assertExactJson(['ok' => true, 'message' => 'Key works.']);

        HealthCheck::assertPromptedTimes(1);
        HealthCheck::assertPrompted(fn (AgentPrompt $prompt) => $prompt->model === 'my-custom-model'
            && $prompt->provider->driver() === $configured['driver']
            && $prompt->provider->providerCredentials()['key'] === 'fake-submitted-key'
            && (! isset($configured['url']) || $prompt->provider->additionalConfiguration()['url'] === $configured['url']));
        $this->assertSame($configured, config("ai.providers.$provider"));
        $this->assertEquals($before, $key->fresh()->getAttributes());
        $this->assertSame($settings, $user->digestSettings()->sole()->getAttributes());
        $this->assertDatabaseCount('ai_keys', 1);
        Http::assertNothingSent();
    }

    public static function providers(): array
    {
        return [
            'gemini' => ['gemini'], 'anthropic' => ['anthropic'], 'openai' => ['openai'],
            'groq' => ['groq'], 'openrouter' => ['openrouter'], 'zai' => ['zai'],
        ];
    }

    public function test_provider_errors_return_http_200_error_json_and_store_nothing(): void
    {
        HealthCheck::fake(function (string $prompt, mixed $attachments, mixed $provider, string $model) {
            throw new RuntimeException('Invalid API key.');
        })->preventStrayPrompts();
        $user = $this->onboardingUser();
        $settings = $user->digestSettings()->sole()->getAttributes();

        $this->actingAs($user)->postJson('/onboarding/ai-key/test', [
            'provider' => 'gemini', 'model' => 'custom-model', 'api_key' => 'fake-invalid-key',
        ])->assertOk()->assertExactJson(['ok' => false, 'message' => 'Invalid API key.']);

        HealthCheck::assertPromptedTimes(1);
        $this->assertDatabaseCount('ai_keys', 0);
        $this->assertSame($settings, $user->digestSettings()->sole()->getAttributes());
        Http::assertNothingSent();
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_key_tests_are_rejected_before_prompting_or_saving(array $payload, string $field): void
    {
        HealthCheck::fake()->preventStrayPrompts();
        $user = $this->onboardingUser();

        $this->actingAs($user)->postJson('/onboarding/ai-key/test', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        HealthCheck::assertNeverPrompted();
        $this->assertDatabaseCount('ai_keys', 0);
        Http::assertNothingSent();
    }

    public static function invalidKeys(): array
    {
        $cases = [];
        foreach (OnboardingStepsTest::invalidAiKeys() as $name => [$payload, $field]) {
            if ($field !== 'consent') {
                unset($payload['consent']);
                $cases[$name] = [$payload, $field];
            }
        }

        return $cases;
    }

    public function test_key_testing_accepts_the_minimum_and_maximum_lengths_without_storing_keys(): void
    {
        HealthCheck::fake([['ok' => true], ['ok' => true]])->preventStrayPrompts();
        $user = $this->onboardingUser();
        $this->actingAs($user);

        foreach ([[1, 1], [100, 500]] as [$modelLength, $keyLength]) {
            $this->postJson('/onboarding/ai-key/test', [
                'provider' => 'gemini', 'model' => str_repeat('m', $modelLength), 'api_key' => str_repeat('k', $keyLength),
            ])->assertOk()->assertExactJson(['ok' => true, 'message' => 'Key works.']);
        }

        HealthCheck::assertPromptedTimes(2);
        $this->assertDatabaseCount('ai_keys', 0);
    }

    public function test_key_testing_is_throttled_at_six_per_minute_per_user(): void
    {
        $this->freezeSecond();
        HealthCheck::fake(array_fill(0, 8, ['ok' => true]))->preventStrayPrompts();
        $user = $this->onboardingUser();
        $other = $this->onboardingUser();
        $payload = ['provider' => 'gemini', 'model' => 'custom-model', 'api_key' => 'fake-key'];
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/onboarding/ai-key/test', $payload)->assertOk()->assertJsonPath('ok', true);
        }
        $this->postJson('/onboarding/ai-key/test', $payload)->assertTooManyRequests();
        HealthCheck::assertPromptedTimes(6);

        $this->actingAs($other)->postJson('/onboarding/ai-key/test', $payload)->assertOk();
        $this->travel(61)->seconds();
        $this->actingAs($user)->postJson('/onboarding/ai-key/test', $payload)->assertOk();

        HealthCheck::assertPromptedTimes(8);
        $this->assertDatabaseCount('ai_keys', 0);
        Http::assertNothingSent();
    }

    private function onboardingUser(): User
    {
        $user = User::factory()->create();
        DigestSettings::factory()->for($user)->create();

        return $user;
    }
}
