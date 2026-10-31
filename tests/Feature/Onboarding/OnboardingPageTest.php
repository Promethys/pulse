<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Enums\AiProvider;
use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Http\Resources\AiKeyResource;
use App\Http\Resources\DigestSettingsResource;
use App\Http\Resources\SourceResource;
use App\Models\AiKey;
use App\Models\DeliveryChannel;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OnboardingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_interests_include_drafts_and_selection_in_enum_category_then_position_order(): void
    {
        $user = User::factory()->create();
        DigestSettings::factory()->for($user)->create();
        $expected = [];
        foreach (array_reverse(InterestCategory::cases()) as $category) {
            $last = Interest::factory()->create(['category' => $category, 'position' => 9, 'status' => InterestStatus::Draft]);
            $first = Interest::factory()->create(['category' => $category, 'position' => 1, 'status' => InterestStatus::Live]);
            $expected[$category->value] = [$first, $last];
        }
        $selected = $expected[InterestCategory::Technology->value][0];
        $user->interests()->attach($selected);
        $other = User::factory()->create();
        $other->interests()->attach($expected[InterestCategory::Life->value][0]);
        $ordered = [];
        foreach (InterestCategory::cases() as $category) {
            foreach ($expected[$category->value] as $interest) {
                $ordered[] = [
                    'id' => $interest->id,
                    'slug' => $interest->slug,
                    'name' => $interest->name,
                    'category' => $category->value,
                    'icon' => $interest->icon,
                    'position' => $interest->position,
                    'has_learning' => $interest->has_learning,
                    'status' => $interest->status->value,
                    'selected' => $interest->id === $selected->id,
                ];
            }
        }

        $this->actingAs($user)->get('/onboarding')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Onboarding', false)
                ->where('interests', $ordered)
                ->where('categories', array_map(fn (InterestCategory $category) => [
                    'value' => $category->value, 'label' => $category->label(),
                ], InterestCategory::cases())));
    }

    public function test_sources_are_grouped_by_selected_interest_and_scoped_to_subscribed_catalog_defaults(): void
    {
        $user = User::factory()->create();
        DigestSettings::factory()->for($user)->create();
        $interests = Interest::factory()->count(2)->create(['status' => InterestStatus::Live]);
        $user->interests()->attach($interests->modelKeys());
        $unselected = Interest::factory()->create();
        $alpha = Source::factory()->create(['name' => 'Alpha', 'is_default' => true]);
        $zulu = Source::factory()->create(['name' => 'Zulu', 'is_default' => true]);
        $interests[0]->sources()->attach([$zulu->id, $alpha->id]);
        $interests[1]->sources()->attach($alpha);
        SourceSubscription::factory()->for($user)->for($alpha)->create(['enabled' => false]);
        SourceSubscription::factory()->for($user)->for($zulu)->create();
        $excluded = [
            Source::factory()->create(['is_default' => true]),
            Source::factory()->create(),
            Source::factory()->for($user)->create(['is_default' => true]),
            Source::factory()->for(User::factory())->create(['is_default' => true]),
        ];
        foreach ($excluded as $source) {
            $interests[0]->sources()->attach($source);
        }
        foreach (array_slice($excluded, 1) as $source) {
            SourceSubscription::factory()->for($user)->for($source)->create();
        }
        SourceSubscription::factory()->for($excluded[0])->create();
        $hidden = Source::factory()->create(['is_default' => true]);
        $unselected->sources()->attach($hidden);
        SourceSubscription::factory()->for($user)->for($hidden)->create();

        $response = $this->actingAs($user)->get('/onboarding')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Onboarding', false)->has('sources', 2));
        $groups = collect($response->inertiaProps('sources'))->keyBy('interest.slug');
        $this->assertEqualsCanonicalizing($interests->pluck('slug')->all(), $groups->keys()->all());
        $this->assertSame(['slug' => $interests[0]->slug, 'name' => $interests[0]->name], $groups[$interests[0]->slug]['interest']);
        $firstSources = $groups[$interests[0]->slug]['sources'];
        $this->assertSame([$alpha->id, $zulu->id], array_column($firstSources, 'id'));
        $this->assertSame([false, true], array_column($firstSources, 'subscription_enabled'));
        foreach ([$alpha, $zulu] as $index => $source) {
            foreach ((new SourceResource($source))->resolve() as $key => $value) {
                $this->assertSame($value, $firstSources[$index][$key]);
            }
        }
        $this->assertSame([$alpha->id], array_column($groups[$interests[1]->slug]['sources'], 'id'));
    }

    public function test_resume_props_include_settings_timezones_providers_and_only_the_users_masked_keys(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $settings = DigestSettings::factory()->for($user)->create(['profile' => 'A curious reader', 'timezone' => 'Europe/Paris']);
        $last = AiKey::factory()->for($user)->provider(AiProvider::Groq)->create(['position' => 8]);
        $first = AiKey::factory()->for($user)->create(['position' => 2]);
        AiKey::factory()->create();

        $this->actingAs($user)->get('/onboarding')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Onboarding', false)
                ->where('settings', (new DigestSettingsResource($settings))->resolve())
                ->where('interests', [])
                ->where('sources', [])
                ->where('timezones', DateTimeZone::listIdentifiers())
                ->where('providers', array_map(fn (AiProvider $provider) => [
                    'value' => $provider->value,
                    'label' => $provider->label(),
                    'key_url' => $provider->keyUrl(),
                    'suggested_models' => $provider->suggestedModels(),
                ], AiProvider::cases()))
                ->where('ai_keys', [(new AiKeyResource($first))->resolve(), (new AiKeyResource($last))->resolve()])
                ->where('email', $user->email)
                ->where('email_delivery', false));
    }

    #[DataProvider('emailChannelStates')]
    public function test_email_delivery_props_resume_the_users_channel_without_exposing_secrets(bool $enabled): void
    {
        $user = User::factory()->create();
        DigestSettings::factory()->for($user)->create();
        AiKey::factory()->for($user)->create(['api_key' => 'fake-private-ai-secret-abcd']);
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $user->interests()->attach($interest);
        $source = Source::factory()->create(['is_default' => true, 'credentials' => ['token' => 'fake-source-secret']]);
        $interest->sources()->attach($source);
        SourceSubscription::factory()->for($user)->for($source)->create();
        DeliveryChannel::factory()->for($user)->email()->create([
            'enabled' => $enabled, 'settings' => ['address' => 'delivery@example.test', 'token' => 'fake-channel-secret'],
        ]);
        DeliveryChannel::factory()->email()->create();
        DeliveryChannel::factory()->for($user)->discordWebhook()->create([
            'settings' => ['webhook_url' => 'https://discord.com/api/webhooks/123/fake-webhook-secret'],
        ]);

        $response = $this->actingAs($user)->get('/onboarding')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Onboarding', false)
            ->where('email_delivery', $enabled)
            ->has('email')
            ->where('ai_keys.0.key_suffix', 'abcd')
            ->missing('ai_keys.0.api_key')
            ->missing('sources.0.sources.0.credentials')
            ->missing('delivery_channels'));
        if ($enabled) {
            $this->assertSame('delivery@example.test', $response->inertiaProps('email'));
        }
        $props = $response->inertiaProps();
        $serialized = json_encode($props, JSON_THROW_ON_ERROR);
        foreach (['fake-private-ai-secret-abcd', 'fake-source-secret', 'fake-channel-secret', 'fake-webhook-secret', '"api_key":', '"credentials":'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        unset($props['settings']);
        $this->assertStringNotContainsString('"settings":', json_encode($props, JSON_THROW_ON_ERROR));
    }

    public static function emailChannelStates(): array
    {
        return ['enabled' => [true], 'disabled' => [false]];
    }
}
