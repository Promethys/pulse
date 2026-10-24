<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Concerns\DigestSettingsValidationRules;
use App\Models\DigestSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DigestSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_default_digest_settings(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
        $user = User::query()->sole();
        $settings = $user->digestSettings;

        $this->assertNotNull($settings);
        $this->assertDatabaseCount('digest_settings', 1);
        $this->assertSame($user->id, $settings->user_id);
        $this->assertTrue($settings->user->is($user));
        $this->assertSame('UTC', $settings->timezone);
        $this->assertNull($settings->profile);
        $this->assertTrue($settings->news_enabled);
        $this->assertSame('09:00', $settings->news_time);
        $this->assertTrue($settings->learning_enabled);
        $this->assertSame('09:30', $settings->learning_time);
        $this->assertSame(20, $settings->max_items);
        $this->assertNull($settings->onboarded_at);
    }

    public function test_registration_ignores_privileged_flags(): void
    {
        $this->skipUnlessFortifyHas(Features::registration());

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'is_admin' => 1,
            'uses_instance_ai' => 1,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $user = User::query()->sole();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->uses_instance_ai);
    }

    public function test_privileged_flags_cannot_be_mass_assigned(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'is_admin' => true,
            'uses_instance_ai' => true,
        ])->refresh();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->uses_instance_ai);
    }

    public function test_privileged_flags_are_hidden_from_serialization(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'uses_instance_ai' => true,
        ])->refresh();

        $this->assertTrue($user->is_admin);
        $this->assertTrue($user->uses_instance_ai);
        $this->assertArrayNotHasKey('is_admin', $user->toArray());
        $this->assertArrayNotHasKey('uses_instance_ai', $user->toArray());
    }

    public function test_deleting_a_user_deletes_its_digest_settings(): void
    {
        $settings = DigestSettings::factory()->create();

        $settings->user->delete();

        $this->assertModelMissing($settings);
    }

    public function test_validation_accepts_valid_settings(): void
    {
        $this->assertTrue(Validator::make($this->validSettings(), $this->rules())->passes());
        $this->assertTrue(Validator::make([
            ...$this->validSettings(),
            'profile' => null,
            'news_enabled' => false,
            'news_time' => '00:00',
            'learning_enabled' => false,
            'learning_time' => '23:45',
            'max_items' => 1,
        ], $this->rules())->passes());
    }

    #[DataProvider('invalidSettings')]
    public function test_validation_rejects_invalid_settings(string $field, mixed $value): void
    {
        $validator = Validator::make([
            ...$this->validSettings(),
            $field => $value,
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has($field));
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidSettings(): array
    {
        return [
            'unknown timezone' => ['timezone', 'Unknown/Timezone'],
            'off quarter hour' => ['news_time', '09:10'],
            'hour out of range' => ['news_time', '24:00'],
            'learning off quarter hour' => ['learning_time', '09:10'],
            'learning hour out of range' => ['learning_time', '24:00'],
            'too few items' => ['max_items', 0],
            'too many items' => ['max_items', 21],
            'profile too long' => ['profile', str_repeat('a', 2001)],
        ];
    }

    /** @return array<string, mixed> */
    private function validSettings(): array
    {
        return [
            'timezone' => 'Europe/Paris',
            'profile' => str_repeat('a', 2000),
            'news_enabled' => true,
            'news_time' => '09:15',
            'learning_enabled' => true,
            'learning_time' => '09:30',
            'max_items' => 20,
        ];
    }

    /** @return array<string, array<int, string>> */
    private function rules(): array
    {
        $rules = new class
        {
            use DigestSettingsValidationRules {
                digestSettingsRules as public;
            }
        };

        return $rules->digestSettingsRules();
    }
}
