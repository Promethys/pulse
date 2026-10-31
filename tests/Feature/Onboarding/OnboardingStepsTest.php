<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Ai\Agents\HealthCheck;
use App\Enums\AiProvider;
use App\Enums\DeliveryChannelType;
use App\Enums\InterestStatus;
use App\Models\AiKey;
use App\Models\DeliveryChannel;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OnboardingStepsTest extends TestCase
{
    use RefreshDatabase;

    public function test_choosing_live_interests_syncs_selection_and_default_subscriptions(): void
    {
        $user = $this->onboardingUser();
        $old = Interest::factory()->create(['status' => InterestStatus::Live]);
        $chosen = Interest::factory()->create(['status' => InterestStatus::Live]);
        $user->interests()->attach($old);
        $oldSource = Source::factory()->create(['is_default' => true]);
        $old->sources()->attach($oldSource);
        SourceSubscription::factory()->for($user)->for($oldSource)->create();
        $newSource = Source::factory()->create(['is_default' => true]);
        $disabled = Source::factory()->create(['is_default' => true]);
        $chosen->sources()->attach([$newSource->id, $disabled->id]);
        SourceSubscription::factory()->for($user)->for($disabled)->create(['enabled' => false]);
        $excluded = [
            Source::factory()->create(['is_default' => true, 'enabled' => false]),
            Source::factory()->create(),
            Source::factory()->for(User::factory())->create(['is_default' => true]),
        ];
        $chosen->sources()->attach(array_map(fn (Source $source) => $source->id, $excluded));
        $other = SourceSubscription::factory()->for($oldSource)->create();

        $this->actingAs($user)->from('/onboarding')->post('/onboarding/interests', ['interests' => [$chosen->slug]])
            ->assertRedirect('/onboarding')->assertSessionHasNoErrors();

        $this->assertSame([$chosen->id], $user->interests()->pluck('interests.id')->all());
        $this->assertEqualsCanonicalizing([$newSource->id, $disabled->id], $user->sourceSubscriptions()->pluck('source_id')->all());
        $this->assertTrue($user->sourceSubscriptions()->where('source_id', $newSource->id)->sole()->enabled);
        $this->assertFalse($user->sourceSubscriptions()->where('source_id', $disabled->id)->sole()->enabled);
        $this->assertModelExists($other);
        $this->assertNull($user->digestSettings()->sole()->onboarded_at);

        $this->post('/onboarding/interests', ['interests' => [$chosen->slug]])->assertSessionHasNoErrors();
        $this->assertSame(2, $user->sourceSubscriptions()->count());
    }

    public function test_sources_toggle_only_owned_subscriptions_by_source_id_and_ignore_unknown_sources(): void
    {
        $user = $this->onboardingUser();
        Source::factory()->count(3)->create();
        $disable = SourceSubscription::factory()->for($user)->create();
        $enable = SourceSubscription::factory()->for($user)->create(['enabled' => false]);
        $unchanged = SourceSubscription::factory()->for($user)->create();
        $other = SourceSubscription::factory()->create();
        $unsubscribed = Source::factory()->create();

        $this->actingAs($user)->from('/onboarding')->put('/onboarding/sources', ['subscriptions' => [
            $disable->source_id => false,
            $enable->source_id => true,
            $other->source_id => false,
            $unsubscribed->id => true,
            99999999 => true,
        ]])->assertRedirect('/onboarding')->assertSessionHasNoErrors();

        $this->assertFalse($disable->fresh()->enabled);
        $this->assertTrue($enable->fresh()->enabled);
        $this->assertTrue($unchanged->fresh()->enabled);
        $this->assertTrue($other->fresh()->enabled);
        $this->assertSame(3, $user->sourceSubscriptions()->count());
        $this->assertDatabaseMissing('source_subscriptions', ['user_id' => $user->id, 'source_id' => $unsubscribed->id]);

        $this->put('/onboarding/sources', ['subscriptions' => []])->assertSessionHasNoErrors();
        $this->assertSame(3, $user->sourceSubscriptions()->count());
    }

    #[DataProvider('profiles')]
    public function test_about_saves_optional_profile_and_timezone_without_changing_digest_schedule(?string $profile): void
    {
        $user = $this->onboardingUser();
        $other = DigestSettings::factory()->create(['profile' => 'Other reader']);
        $settings = $user->digestSettings()->sole();
        $settings->update(['profile' => 'Old profile', 'news_time' => '11:15', 'learning_enabled' => false]);

        $this->actingAs($user)->from('/onboarding')->put('/onboarding/about', [
            'profile' => $profile, 'timezone' => 'Europe/Paris',
        ])->assertRedirect('/onboarding')->assertSessionHasNoErrors();

        $settings->refresh();
        $this->assertSame($profile, $settings->profile);
        $this->assertSame('Europe/Paris', $settings->timezone);
        $this->assertSame('11:15', $settings->news_time);
        $this->assertFalse($settings->learning_enabled);
        $this->assertNull($settings->onboarded_at);
        $this->assertSame('Other reader', $other->fresh()->profile);
    }

    public static function profiles(): array
    {
        return ['nullable' => [null], 'maximum length' => [str_repeat('a', 2000)]];
    }

    public function test_about_accepts_an_omitted_profile(): void
    {
        $user = $this->onboardingUser();

        $this->actingAs($user)->from('/onboarding')->put('/onboarding/about', ['timezone' => 'UTC'])
            ->assertRedirect('/onboarding')->assertSessionHasNoErrors();

        $this->assertSame('UTC', $user->digestSettings()->sole()->timezone);
        $this->assertNull($user->digestSettings()->sole()->profile);
    }

    public function test_ai_keys_are_appended_and_upserted_per_user_and_provider_with_fresh_consent(): void
    {
        $this->freezeSecond();
        HealthCheck::fake()->preventStrayPrompts();
        $user = $this->onboardingUser();
        $existing = AiKey::factory()->for($user)->provider(AiProvider::Groq)->create(['position' => 8]);
        $other = AiKey::factory()->create(['position' => 99, 'api_key' => 'fake-other-key']);
        foreach ([AiProvider::Anthropic, AiProvider::OpenAi, AiProvider::Groq, AiProvider::OpenRouter] as $provider) {
            AiKey::factory()->for($other->user)->provider($provider)->create(['position' => 100]);
        }

        $this->actingAs($user)->from('/onboarding')->post('/onboarding/ai-key', [
            'provider' => 'gemini', 'model' => str_repeat('m', 100), 'api_key' => str_repeat('k', 496).'abcd', 'consent' => 'yes',
        ])->assertRedirect('/onboarding')->assertSessionHasNoErrors();

        $key = $user->aiKeys()->where('provider', 'gemini')->sole();
        $this->assertSame(9, $key->position);
        $this->assertSame(str_repeat('m', 100), $key->model);
        $this->assertSame(str_repeat('k', 496).'abcd', $key->api_key);
        $this->assertSame('abcd', $key->key_suffix);
        $this->assertTrue($key->consented_at->equalTo(now()));
        $this->assertNull($user->digestSettings()->sole()->onboarded_at);
        $id = $key->id;
        $this->travel(10)->seconds();

        $this->post('/onboarding/ai-key', [
            'provider' => 'gemini', 'model' => 'm', 'api_key' => 'x', 'consent' => true,
        ])->assertRedirect('/onboarding')->assertSessionHasNoErrors();

        $key->refresh();
        $this->assertSame($id, $key->id);
        $this->assertSame(9, $key->position);
        $this->assertSame('m', $key->model);
        $this->assertSame('x', $key->api_key);
        $this->assertSame('x', $key->key_suffix);
        $this->assertTrue($key->consented_at->equalTo(now()));
        $this->assertSame(2, $user->aiKeys()->count());
        $this->assertSame(8, $existing->fresh()->position);
        $this->assertSame('fake-other-key', $other->fresh()->api_key);
        HealthCheck::assertNeverPrompted();
    }

    public function test_a_sixth_provider_is_rejected_but_an_existing_key_can_be_updated_at_the_limit(): void
    {
        $user = $this->onboardingUser();
        foreach ([AiProvider::Gemini, AiProvider::Anthropic, AiProvider::OpenAi, AiProvider::Groq, AiProvider::OpenRouter] as $index => $provider) {
            AiKey::factory()->for($user)->provider($provider)->create(['position' => $index + 1]);
        }
        $this->actingAs($user)->from('/onboarding')->post('/onboarding/ai-key', [
            'provider' => 'zai', 'model' => 'glm-4.7-flash', 'api_key' => 'fake-sixth-key', 'consent' => true,
        ])->assertRedirect('/onboarding')->assertSessionHasErrors();
        $this->assertSame(5, $user->aiKeys()->count());
        $this->assertDatabaseMissing('ai_keys', ['user_id' => $user->id, 'provider' => 'zai']);

        $this->post('/onboarding/ai-key', [
            'provider' => 'gemini', 'model' => 'custom-model', 'api_key' => 'fake-updated-key', 'consent' => true,
        ])->assertRedirect('/onboarding')->assertSessionHasNoErrors();
        $this->assertSame(5, $user->aiKeys()->count());
        $this->assertSame('fake-updated-key', $user->aiKeys()->where('provider', 'gemini')->sole()->api_key);
    }

    #[DataProvider('providers')]
    public function test_each_supported_provider_can_be_saved_as_the_first_key(string $provider): void
    {
        $this->freezeSecond();
        $user = $this->onboardingUser();

        $this->actingAs($user)->from('/onboarding')->post('/onboarding/ai-key', [
            'provider' => $provider, 'model' => 'custom-model', 'api_key' => 'fake-key-abcd', 'consent' => true,
        ])->assertRedirect('/onboarding')->assertSessionHasNoErrors();

        $key = $user->aiKeys()->sole();
        $this->assertSame($provider, $key->provider->value);
        $this->assertSame('custom-model', $key->model);
        $this->assertSame('fake-key-abcd', $key->api_key);
        $this->assertSame('abcd', $key->key_suffix);
        $this->assertTrue($key->consented_at->equalTo(now()));
        $this->assertNull($user->digestSettings()->sole()->onboarded_at);
    }

    public static function providers(): array
    {
        return [
            'gemini' => ['gemini'], 'anthropic' => ['anthropic'], 'openai' => ['openai'],
            'groq' => ['groq'], 'openrouter' => ['openrouter'], 'zai' => ['zai'],
        ];
    }

    #[DataProvider('emailChannelStartingStates')]
    public function test_completion_creates_or_reenables_email_delivery_and_marks_only_the_user_onboarded(?bool $enabled): void
    {
        $this->freezeSecond();
        $user = $this->onboardingUser();
        $user->interests()->attach(Interest::factory()->create(['status' => InterestStatus::Live]));
        $otherSettings = DigestSettings::factory()->create();
        $otherChannel = DeliveryChannel::factory()->for($otherSettings->user)->email()->create(['enabled' => false]);
        $inApp = DeliveryChannel::factory()->for($user)->inApp()->create();
        $channel = $enabled === null ? null : DeliveryChannel::factory()->for($user)->email()->create([
            'enabled' => $enabled, 'name' => 'Old email', 'kinds' => ['news'], 'settings' => ['address' => 'old@example.test'],
        ]);

        $this->actingAs($user)->post('/onboarding/complete', [
            'email_delivery' => true, 'email' => 'reader@example.test',
        ])->assertRedirect('/')->assertSessionHasNoErrors();

        $saved = $user->deliveryChannels()->where('type', DeliveryChannelType::Email)->sole();
        if ($channel !== null) {
            $this->assertSame($channel->id, $saved->id);
        }
        $this->assertTrue($saved->enabled);
        $this->assertSame('Email', $saved->name);
        $this->assertSame(['news', 'learning'], $saved->kinds);
        $this->assertSame(['address' => 'reader@example.test'], $saved->settings);
        $this->assertTrue($user->digestSettings()->sole()->onboarded_at->equalTo(now()));
        $this->assertNull($otherSettings->fresh()->onboarded_at);
        $this->assertFalse($otherChannel->fresh()->enabled);
        $this->assertTrue($inApp->fresh()->enabled);
        $this->assertSame(0, $user->aiKeys()->count());
    }

    public static function emailChannelStartingStates(): array
    {
        return ['new' => [null], 'disabled' => [false], 'enabled' => [true]];
    }

    public function test_completion_without_email_or_ai_keys_preserves_in_app_delivery(): void
    {
        $this->freezeSecond();
        $user = $this->onboardingUser();
        $user->interests()->attach(Interest::factory()->create(['status' => InterestStatus::Live]));
        $inApp = DeliveryChannel::factory()->for($user)->inApp()->create();

        $this->actingAs($user)->post('/onboarding/complete', ['email_delivery' => false])
            ->assertRedirect('/')->assertSessionHasNoErrors();

        $this->assertTrue($user->digestSettings()->sole()->onboarded_at->equalTo(now()));
        $this->assertTrue($inApp->fresh()->enabled);
        $this->assertSame(0, $user->deliveryChannels()->where('type', DeliveryChannelType::Email)->count());
        $this->assertSame(0, $user->aiKeys()->count());
    }

    public function test_completion_without_an_interest_does_not_save_a_channel_or_mark_onboarded(): void
    {
        $user = $this->onboardingUser();

        $this->actingAs($user)->from('/onboarding')->post('/onboarding/complete', [
            'email_delivery' => true, 'email' => 'reader@example.test',
        ])->assertRedirect('/onboarding')->assertSessionHasErrors();

        $this->assertNull($user->digestSettings()->sole()->onboarded_at);
        $this->assertSame(0, $user->deliveryChannels()->count());
    }

    #[DataProvider('invalidSteps')]
    public function test_step_validation_rejects_invalid_input_without_persisting_changes(string $method, string $path, array $payload, string $field): void
    {
        $user = $this->onboardingUser();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $user->interests()->attach($interest);
        $draft = Interest::factory()->create(['status' => InterestStatus::Draft]);
        if (($payload['interests'][0] ?? null) === 'draft-slug') {
            $payload['interests'][0] = $draft->slug;
        }
        $before = $user->digestSettings()->sole()->getAttributes();

        $this->actingAs($user)->from('/onboarding')->call($method, $path, $payload)
            ->assertRedirect('/onboarding')->assertSessionHasErrors($field);

        $this->assertSame($before, $user->digestSettings()->sole()->getAttributes());
        $this->assertSame([$interest->id], $user->interests()->pluck('interests.id')->all());
        $this->assertSame(0, $user->sourceSubscriptions()->count());
        $this->assertSame(0, $user->aiKeys()->count());
        $this->assertSame(0, $user->deliveryChannels()->count());
    }

    public static function invalidSteps(): array
    {
        $cases = [
            'interests required' => ['POST', '/onboarding/interests', [], 'interests'],
            'interests array' => ['POST', '/onboarding/interests', ['interests' => 'technology'], 'interests'],
            'interests minimum' => ['POST', '/onboarding/interests', ['interests' => []], 'interests'],
            'interest unknown' => ['POST', '/onboarding/interests', ['interests' => ['unknown-slug']], 'interests.0'],
            'interest draft' => ['POST', '/onboarding/interests', ['interests' => ['draft-slug']], 'interests.0'],
            'interest slug string' => ['POST', '/onboarding/interests', ['interests' => [123]], 'interests.0'],
            'subscriptions required' => ['PUT', '/onboarding/sources', [], 'subscriptions'],
            'subscriptions array' => ['PUT', '/onboarding/sources', ['subscriptions' => 'invalid'], 'subscriptions'],
            'subscription boolean' => ['PUT', '/onboarding/sources', ['subscriptions' => [123 => 'invalid']], 'subscriptions.123'],
            'subscription null' => ['PUT', '/onboarding/sources', ['subscriptions' => [123 => null]], 'subscriptions.123'],
            'timezone required' => ['PUT', '/onboarding/about', ['profile' => null], 'timezone'],
            'timezone string' => ['PUT', '/onboarding/about', ['timezone' => ['UTC']], 'timezone'],
            'timezone IANA' => ['PUT', '/onboarding/about', ['timezone' => 'Unknown/Timezone'], 'timezone'],
            'profile string' => ['PUT', '/onboarding/about', ['timezone' => 'UTC', 'profile' => ['invalid']], 'profile'],
            'profile maximum' => ['PUT', '/onboarding/about', ['timezone' => 'UTC', 'profile' => str_repeat('p', 2001)], 'profile'],
            'email delivery required' => ['POST', '/onboarding/complete', [], 'email_delivery'],
            'email delivery boolean' => ['POST', '/onboarding/complete', ['email_delivery' => 'invalid'], 'email_delivery'],
            'email required when enabled' => ['POST', '/onboarding/complete', ['email_delivery' => true], 'email'],
            'email null when enabled' => ['POST', '/onboarding/complete', ['email_delivery' => true, 'email' => null], 'email'],
            'email valid' => ['POST', '/onboarding/complete', ['email_delivery' => true, 'email' => 'invalid'], 'email'],
            'email string' => ['POST', '/onboarding/complete', ['email_delivery' => true, 'email' => ['reader@example.test']], 'email'],
        ];
        foreach (self::invalidAiKeys() as $name => [$payload, $field]) {
            $cases['ai key '.$name] = ['POST', '/onboarding/ai-key', $payload, $field];
        }

        return $cases;
    }

    public static function invalidAiKeys(): array
    {
        $valid = ['provider' => 'gemini', 'model' => 'custom-model', 'api_key' => 'fake-key', 'consent' => true];
        $invalid = [
            'provider required' => ['provider', null],
            'provider enum' => ['provider', 'unknown'],
            'provider scalar' => ['provider', ['gemini']],
            'model required' => ['model', null],
            'model minimum' => ['model', ''],
            'model string' => ['model', ['invalid']],
            'model maximum' => ['model', str_repeat('m', 101)],
            'api key required' => ['api_key', null],
            'api key minimum' => ['api_key', ''],
            'api key string' => ['api_key', ['invalid']],
            'api key maximum' => ['api_key', str_repeat('k', 501)],
            'consent required' => ['consent', null],
            'consent declined' => ['consent', false],
            'consent invalid' => ['consent', 'no'],
        ];
        $cases = [];
        foreach ($invalid as $name => [$field, $value]) {
            $cases[$name] = [[...$valid, $field => $value], $field];
        }
        foreach (['provider', 'model', 'api_key', 'consent'] as $field) {
            $payload = $valid;
            unset($payload[$field]);
            $cases[$field.' missing'] = [$payload, $field];
        }

        return $cases;
    }

    private function onboardingUser(): User
    {
        $user = User::factory()->create();
        DigestSettings::factory()->for($user)->create();

        return $user;
    }
}
