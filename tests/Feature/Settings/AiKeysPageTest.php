<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Ai\Agents\HealthCheck;
use App\Enums\AiProvider;
use App\Models\AiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Onboarding\OnboardingStepsTest;
use Tests\TestCase;

class AiKeysPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        HealthCheck::fake([['ok' => true]])->preventStrayPrompts();
    }

    public function test_page_orders_only_own_keys_and_exposes_suffixes_without_secrets(): void
    {
        $user = User::factory()->onboarded()->create();
        $last = AiKey::factory()->for($user)->create(['api_key' => 'fake-secret-last-abcd', 'position' => 8]);
        $first = AiKey::factory()->for($user)->provider(AiProvider::Groq)->create(['position' => 2]);
        AiKey::factory()->create(['api_key' => 'fake-other-secret']);
        $this->assertSame(url('/settings/ai-keys'), route('ai-keys.index'));
        $response = $this->actingAs($user)->get('/settings/ai-keys')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/AiKeys', false)->has('keys', 2)->where('keys.0.id', $first->id)->where('keys.1.id', $last->id)
            ->where('keys.1.key_suffix', 'abcd')->missing('keys.0.api_key')->missing('keys.0.user_id')
            ->has('providers', 6)->where('providers.0.value', 'gemini')->has('providers.0.suggested_models')
            ->where('uses_instance_ai_notice', false)->missing('uses_instance_ai'));
        $this->assertStringNotContainsString('fake-secret-last-abcd', $response->getContent());
        $this->assertStringNotContainsString('fake-other-secret', $response->getContent());
        Http::assertNothingSent();
    }

    #[DataProvider('instanceNotice')]
    public function test_instance_notice_depends_on_account_flag_and_absence_of_keys(bool $flag, bool $hasKey, bool $notice): void
    {
        $user = User::factory()->onboarded()->create(['uses_instance_ai' => $flag]);
        if ($hasKey) {
            AiKey::factory()->for($user)->create();
        }
        $this->actingAs($user)->get('/settings/ai-keys')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/AiKeys', false)->where('uses_instance_ai_notice', $notice)->missing('uses_instance_ai'));
    }

    public static function instanceNotice(): array
    {
        return [[false, false, false], [true, false, true], [true, true, false]];
    }

    public function test_save_encrypts_appends_and_replaces_only_the_same_users_provider(): void
    {
        $user = User::factory()->onboarded()->create();
        AiKey::factory()->for($user)->provider(AiProvider::Groq)->create(['position' => 4]);
        $other = AiKey::factory()->create();
        $before = $other->fresh()->getAttributes();
        $this->actingAs($user)->from('/settings/ai-keys')->post('/settings/ai-keys', $this->payload())
            ->assertRedirect('/settings/ai-keys')->assertSessionHasNoErrors();
        $key = $user->aiKeys()->where('provider', 'gemini')->sole();
        $this->assertSame('fake-key-abcd', $key->api_key);
        $this->assertSame('abcd', $key->key_suffix);
        $this->assertSame(5, $key->position);
        $this->assertNotNull($key->consented_at);
        $this->assertStringNotContainsString('fake-key-abcd', $key->getRawOriginal('api_key'));
        $this->post('/settings/ai-keys', [...$this->payload(), 'api_key' => 'replacement-key', 'model' => 'replacement-model'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $user->aiKeys()->count());
        $this->assertSame('replacement-key', $key->fresh()->api_key);
        $this->assertSame('replacement-model', $key->fresh()->model);
        $this->assertSame(5, $key->fresh()->position);
        $this->assertSame($before, $other->fresh()->getAttributes());
        HealthCheck::assertNeverPrompted();
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_keys_are_not_stored(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/settings/ai-keys', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('ai_keys', 0);
        HealthCheck::assertNeverPrompted();
    }

    public static function invalidKeys(): array
    {
        return OnboardingStepsTest::invalidAiKeys();
    }

    public function test_fifth_key_is_allowed_sixth_rejected_and_replacement_allowed_at_limit(): void
    {
        $user = User::factory()->onboarded()->create();
        foreach ([AiProvider::Gemini, AiProvider::Anthropic, AiProvider::OpenAi, AiProvider::Groq] as $index => $provider) {
            AiKey::factory()->for($user)->provider($provider)->create(['position' => $index + 1]);
        }
        $this->actingAs($user)->postJson('/settings/ai-keys', [...$this->payload(), 'provider' => 'openrouter'])->assertRedirect();
        $this->postJson('/settings/ai-keys', [...$this->payload(), 'provider' => 'zai'])->assertUnprocessable()->assertJsonValidationErrors('provider');
        $this->postJson('/settings/ai-keys', [...$this->payload(), 'api_key' => 'updated-key'])->assertRedirect();
        $this->assertSame(5, $user->aiKeys()->count());
        $this->assertSame('updated-key', $user->aiKeys()->where('provider', 'gemini')->sole()->api_key);
    }

    public function test_reorder_requires_all_and_only_own_ids_without_duplicates(): void
    {
        $user = User::factory()->onboarded()->create();
        $first = AiKey::factory()->for($user)->create(['position' => 1]);
        $second = AiKey::factory()->for($user)->provider(AiProvider::Groq)->create(['position' => 2]);
        $other = AiKey::factory()->create();
        $before = $other->fresh()->getAttributes();
        $this->actingAs($user)->from('/settings/ai-keys')->put('/settings/ai-keys/order', ['ids' => [$second->id, $first->id]])
            ->assertRedirect('/settings/ai-keys')->assertSessionHasNoErrors();
        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
        foreach ([[], [$first->id], [$first->id, $first->id], [$first->id, $other->id], [$first->id, $second->id, 999999], 'invalid'] as $ids) {
            $this->putJson('/settings/ai-keys/order', ['ids' => $ids])->assertUnprocessable();
            $this->assertSame(1, $second->fresh()->position);
            $this->assertSame(2, $first->fresh()->position);
        }
        $this->assertSame($before, $other->fresh()->getAttributes());
    }

    public function test_delete_and_saved_test_forbid_other_readers_keys(): void
    {
        $user = User::factory()->onboarded()->create();
        $other = AiKey::factory()->create();
        $before = $other->fresh()->getAttributes();
        $this->actingAs($user)->delete("/settings/ai-keys/{$other->id}")->assertForbidden();
        $this->postJson("/settings/ai-keys/{$other->id}/test")->assertForbidden();
        $this->assertSame($before, $other->fresh()->getAttributes());
        HealthCheck::assertNeverPrompted();
        $own = AiKey::factory()->for($user)->create();
        $this->from('/settings/ai-keys')->delete("/settings/ai-keys/{$own->id}")->assertRedirect('/settings/ai-keys');
        $this->assertModelMissing($own);
        $this->assertModelExists($other);
    }

    public static function savedOrNewKey(): array
    {
        return ['saved key' => [true], 'new key' => [false]];
    }

    #[DataProvider('savedOrNewKey')]
    public function test_testing_uses_the_right_credentials_without_persisting_results_and_is_throttled(bool $saved): void
    {
        $this->freezeSecond();
        HealthCheck::fake(array_fill(0, 8, ['ok' => true]))->preventStrayPrompts();
        $user = User::factory()->onboarded()->create();
        $key = AiKey::factory()->for($user)->create(['api_key' => 'fake-saved-key', 'model' => 'saved-model']);
        $before = $key->fresh()->getAttributes();
        $url = $saved ? "/settings/ai-keys/{$key->id}/test" : '/settings/ai-keys/test';
        $payload = $saved ? [] : ['provider' => 'gemini', 'model' => 'custom-model', 'api_key' => 'fake-new-key'];
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson($url, $payload)->assertOk()->assertExactJson(['ok' => true, 'message' => 'Key works.']);
        }
        $this->postJson($url, $payload)->assertTooManyRequests();
        HealthCheck::assertPromptedTimes(6);
        HealthCheck::assertPrompted(fn (AgentPrompt $prompt) => $prompt->model === ($saved ? 'saved-model' : 'custom-model')
            && $prompt->provider->providerCredentials()['key'] === ($saved ? 'fake-saved-key' : 'fake-new-key'));
        $this->travel(61)->seconds();
        $this->postJson($url, $payload)->assertOk();
        $this->assertSame($before, $key->fresh()->getAttributes());
        $this->assertDatabaseCount('ai_keys', 1);
        Http::assertNothingSent();
    }

    public function test_test_failure_is_friendly_and_logs_without_exposing_the_key(): void
    {
        Log::spy();
        HealthCheck::fake(function () {
            throw new RuntimeException('raw failure fake-key-abcd');
        })->preventStrayPrompts();
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/settings/ai-keys/test', $this->payload())
            ->assertOk()->assertExactJson(['ok' => false, 'message' => 'The key test failed.']);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => ! str_contains(json_encode($context), 'fake-key-abcd'));
        $this->assertDatabaseCount('ai_keys', 0);
    }

    public function test_new_key_test_validates_before_prompting_but_does_not_require_consent(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/settings/ai-keys/test', ['provider' => 'unknown'])
            ->assertUnprocessable()->assertJsonValidationErrors(['provider', 'model', 'api_key']);
        HealthCheck::assertNeverPrompted();
        $payload = $this->payload();
        unset($payload['consent']);
        $this->postJson('/settings/ai-keys/test', $payload)->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseCount('ai_keys', 0);
    }

    private function payload(): array
    {
        return ['provider' => 'gemini', 'model' => 'custom-model', 'api_key' => 'fake-key-abcd', 'consent' => true];
    }
}
