<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DigestSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_exposes_only_the_readers_settings_and_learning_availability(): void
    {
        $user = User::factory()->onboarded()->create();
        $user->digestSettings()->update(['profile' => 'A curious reader', 'news_time' => '10:15']);
        DigestSettings::factory()->create(['profile' => 'Other profile']);
        $this->assertSame(url('/settings/digest'), route('digest.edit'));
        $this->actingAs($user)->get('/settings/digest')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/Digest', false)
            ->where('settings.profile', 'A curious reader')->where('settings.news_time', '10:15')
            ->where('settings.learning_available', false)->missing('settings.user_id')
            ->where('timezones', fn ($zones) => in_array('UTC', $zones->all(), true) && in_array('Europe/Paris', $zones->all(), true)));
    }

    #[DataProvider('boundaries')]
    public function test_save_updates_only_the_current_reader_and_preserves_onboarding(?string $profile, int $maxItems): void
    {
        $user = User::factory()->onboarded()->create();
        $user->interests()->attach(Interest::factory()->create(['has_learning' => true]));
        $other = DigestSettings::factory()->create();
        $otherBefore = $other->fresh()->getAttributes();
        $onboarded = $user->digestSettings()->sole()->onboarded_at->toIso8601String();
        $payload = [...$this->payload(), 'profile' => $profile, 'max_items' => $maxItems, 'learning_enabled' => true];
        $this->assertSame(url('/settings/digest'), route('digest.update'));
        $this->actingAs($user)->from('/settings/digest')->put('/settings/digest', [...$payload, 'user_id' => $other->user_id, 'onboarded_at' => null])
            ->assertRedirect('/settings/digest')->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');
        $saved = $user->digestSettings()->sole()->fresh();
        foreach ($payload as $field => $value) {
            $this->assertSame($value, $saved->{$field});
        }
        $this->assertSame($onboarded, $saved->onboarded_at->toIso8601String());
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
    }

    public static function boundaries(): array
    {
        return [[null, 1], [str_repeat('a', 2000), 20]];
    }

    #[DataProvider('invalidSettings')]
    public function test_invalid_settings_leave_the_saved_values_unchanged(string $field, mixed $value): void
    {
        $user = User::factory()->onboarded()->create();
        $before = $user->digestSettings()->sole()->fresh()->getAttributes();
        $this->actingAs($user)->putJson('/settings/digest', [...$this->payload(), $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $user->digestSettings()->sole()->fresh()->getAttributes());
    }

    public static function invalidSettings(): array
    {
        return [
            ['timezone', null], ['timezone', 'Mars/Olympus'], ['profile', str_repeat('p', 2001)], ['profile', []],
            ['news_enabled', null], ['news_enabled', 'yes'], ['learning_enabled', null], ['learning_enabled', 'yes'],
            ['news_time', '09:01'], ['news_time', '24:00'], ['news_time', null],
            ['learning_time', '9:30'], ['learning_time', '09:60'], ['learning_time', null],
            ['max_items', 0], ['max_items', 21], ['max_items', 1.5], ['max_items', null],
        ];
    }

    public function test_learning_cannot_be_enabled_without_a_learning_interest(): void
    {
        $user = User::factory()->onboarded()->create();
        $user->interests()->attach(Interest::factory()->create(['has_learning' => false]));
        $user->digestSettings()->update(['learning_enabled' => false]);
        $before = $user->digestSettings()->sole()->fresh()->getAttributes();
        $this->actingAs($user)->putJson('/settings/digest', [...$this->payload(), 'learning_enabled' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('learning_enabled');
        $this->assertSame($before, $user->digestSettings()->sole()->fresh()->getAttributes());
    }

    #[DataProvider('pages')]
    public function test_new_pages_require_authentication_verification_and_onboarding(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
        $this->actingAs(User::factory()->unverified()->create())->get($path)->assertRedirect('/email/verify');
        $this->actingAs(User::factory()->create())->get($path)->assertRedirect('/onboarding');
    }

    public static function pages(): array
    {
        return [['/settings/digest'], ['/settings/ai-keys'], ['/settings/delivery']];
    }

    private function payload(): array
    {
        return ['timezone' => 'Europe/Paris', 'profile' => 'Reader profile', 'news_enabled' => false,
            'news_time' => '00:00', 'learning_enabled' => false, 'learning_time' => '23:45', 'max_items' => 12];
    }
}
