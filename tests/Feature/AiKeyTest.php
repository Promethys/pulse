<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AiProvider;
use App\Models\AiKey;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_key_is_encrypted_at_rest_and_read_back_decrypted(): void
    {
        $secret = 'fake-private-ai-key-a1b2';
        $key = AiKey::factory()->create(['api_key' => $secret]);
        $stored = DB::table('ai_keys')->where('id', $key->id)->value('api_key');

        $this->assertIsString($stored);
        $this->assertNotSame($secret, $stored);
        $this->assertStringNotContainsString($secret, $stored);
        $this->assertSame($secret, $key->fresh()->api_key);
    }

    public function test_api_key_is_hidden_from_array_and_json_serialization(): void
    {
        $key = AiKey::factory()->create(['api_key' => 'fake-private-ai-key-a1b2'])->refresh();

        $this->assertArrayNotHasKey('api_key', $key->toArray());
        $this->assertArrayNotHasKey('api_key', json_decode($key->toJson(), true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('fake-private-ai-key-a1b2', $key->toJson());
    }

    public function test_key_suffix_is_stored_and_updates_when_the_api_key_changes(): void
    {
        $key = AiKey::factory()->create(['api_key' => 'fake-private-ai-key-a1b2']);

        $this->assertSame('a1b2', DB::table('ai_keys')->where('id', $key->id)->value('key_suffix'));
        $this->assertSame('a1b2', $key->fresh()->toArray()['key_suffix']);

        $key->update(['api_key' => 'fake-replacement-key-c3d4']);

        $this->assertSame('c3d4', DB::table('ai_keys')->where('id', $key->id)->value('key_suffix'));
        $this->assertSame('c3d4', $key->fresh()->key_suffix);
        $this->assertSame('fake-replacement-key-c3d4', $key->fresh()->api_key);
    }

    public function test_key_attributes_can_be_filled_and_are_cast_after_reloading(): void
    {
        $user = User::factory()->create();
        $key = new AiKey;
        $key->fill([
            'user_id' => $user->id,
            'provider' => 'openai',
            'model' => 'test-openai-model',
            'api_key' => 'fake-openai-key-a1b2',
            'position' => '3',
            'consented_at' => '2026-10-25 10:00:00',
        ]);
        $key->save();
        $key->refresh();

        $this->assertSame($user->id, $key->user_id);
        $this->assertTrue($key->user->is($user));
        $this->assertSame(AiProvider::OpenAi, $key->provider);
        $this->assertSame('openai', $key->getRawOriginal('provider'));
        $this->assertSame('test-openai-model', $key->model);
        $this->assertSame('fake-openai-key-a1b2', $key->api_key);
        $this->assertSame(3, $key->position);
        $this->assertInstanceOf(CarbonImmutable::class, $key->consented_at);
        $this->assertSame('2026-10-25 10:00:00', $key->consented_at->format('Y-m-d H:i:s'));
    }

    public function test_the_default_factory_creates_a_consented_gemini_key_in_first_position(): void
    {
        $this->freezeSecond();

        $key = AiKey::factory()->create()->refresh();

        $this->assertSame(AiProvider::Gemini, $key->provider);
        $this->assertSame('gemini-3.8-flash', $key->model);
        $this->assertIsString($key->api_key);
        $this->assertNotSame('', $key->api_key);
        $this->assertSame(1, $key->position);
        $this->assertInstanceOf(CarbonImmutable::class, $key->consented_at);
        $this->assertTrue($key->consented_at->equalTo(now()));
    }

    #[DataProvider('providers')]
    public function test_factory_provider_state_is_persisted_and_cast(string $case, string $value, string $label): void
    {
        $provider = constant(AiProvider::class.'::'.$case);
        $key = AiKey::factory()->provider($provider)->create()->refresh();

        $this->assertSame($provider, $key->provider);
        $this->assertSame($value, $key->getRawOriginal('provider'));
        $this->assertSame($label, $key->provider->label());
    }

    /** @return array<string, array{string, string, string}> */
    public static function providers(): array
    {
        return [
            'Gemini' => ['Gemini', 'gemini', 'Gemini'],
            'Anthropic' => ['Anthropic', 'anthropic', 'Anthropic'],
            'OpenAI' => ['OpenAi', 'openai', 'OpenAI'],
            'Groq' => ['Groq', 'groq', 'Groq'],
            'OpenRouter' => ['OpenRouter', 'openrouter', 'OpenRouter'],
        ];
    }

    public function test_the_same_user_cannot_have_two_keys_for_the_same_provider(): void
    {
        $user = User::factory()->create();
        AiKey::factory()->create(['user_id' => $user->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        AiKey::factory()->create(['user_id' => $user->id]);
    }

    public function test_two_users_can_have_keys_for_the_same_provider(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $key = AiKey::factory()->create(['user_id' => $first->id]);
        $other = AiKey::factory()->create(['user_id' => $second->id]);

        $this->assertTrue($key->fresh()->user->is($first));
        $this->assertTrue($other->fresh()->user->is($second));
        $this->assertSame([$key->id], $first->aiKeys->modelKeys());
        $this->assertSame([$other->id], $second->aiKeys->modelKeys());
    }

    public function test_user_keys_are_ordered_by_position_and_exclude_other_users(): void
    {
        $user = User::factory()->create();
        $last = AiKey::factory()->provider(AiProvider::Anthropic)->create(['user_id' => $user->id, 'position' => 9]);
        $first = AiKey::factory()->create(['user_id' => $user->id, 'position' => 1]);
        $middle = AiKey::factory()->provider(AiProvider::Groq)->create(['user_id' => $user->id, 'position' => 4]);
        AiKey::factory()->create(['position' => 0]);

        $this->assertSame([$first->id, $middle->id, $last->id], $user->aiKeys->modelKeys());
    }

    public function test_deleting_a_user_cascades_to_their_keys_and_keeps_other_users_keys(): void
    {
        $user = User::factory()->create();
        AiKey::factory()->create(['user_id' => $user->id]);
        AiKey::factory()->provider(AiProvider::OpenAi)->create(['user_id' => $user->id]);
        $other = AiKey::factory()->create();

        DB::table('users')->where('id', $user->id)->delete();

        $this->assertDatabaseMissing('ai_keys', ['user_id' => $user->id]);
        $this->assertModelExists($other);
    }

    public function test_a_key_cannot_be_stored_without_consent(): void
    {
        $this->expectException(QueryException::class);

        AiKey::factory()->create(['consented_at' => null]);
    }
}
