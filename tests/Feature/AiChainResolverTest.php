<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Digest\Ai\AiChainResolver;
use App\Digest\Ai\AiConfigurationException;
use App\Digest\Ai\AiNotConfiguredException;
use App\Enums\AiProvider;
use App\Models\AiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AiManager;
use Tests\TestCase;

class AiChainResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_keys_build_providers_in_position_order_with_their_models_and_credentials(): void
    {
        config([
            'digest.ai_providers' => 'openai:instance-model',
            'ai.providers.gemini' => [
                'driver' => 'gemini',
                'key' => 'fake-instance-gemini-key',
                'url' => 'https://gemini.example.test/v1beta/',
            ],
            'ai.providers.anthropic.key' => 'fake-instance-anthropic-key',
        ]);
        $user = User::factory()->create();
        AiKey::factory()->provider(AiProvider::Anthropic)->create([
            'user_id' => $user->id,
            'model' => 'test-anthropic-model',
            'api_key' => 'fake-user-anthropic-key',
            'position' => 8,
        ]);
        AiKey::factory()->create([
            'user_id' => $user->id,
            'model' => 'gemini-3.8-flash',
            'api_key' => 'fake-user-gemini-key',
            'position' => 2,
        ]);

        $chain = app(AiChainResolver::class)->for($user);

        $this->assertSame([
            "user_{$user->id}_gemini" => 'gemini-3.8-flash',
            "user_{$user->id}_anthropic" => 'test-anthropic-model',
        ], $chain);
        $manager = app(AiManager::class);
        $gemini = $manager->getInstanceConfig("user_{$user->id}_gemini");
        $anthropic = $manager->getInstanceConfig("user_{$user->id}_anthropic");
        $this->assertSame('fake-user-gemini-key', $gemini['key']);
        $this->assertSame('gemini', $gemini['driver']);
        $this->assertSame('https://gemini.example.test/v1beta/', $gemini['url']);
        $this->assertSame('fake-user-anthropic-key', $anthropic['key']);
        $this->assertSame('anthropic', $anthropic['driver']);
        $this->assertSame('fake-instance-gemini-key', config('ai.providers.gemini.key'));
        $this->assertSame('fake-instance-anthropic-key', config('ai.providers.anthropic.key'));
    }

    public function test_resolving_two_users_keeps_their_chains_and_credentials_separate(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        AiKey::factory()->create([
            'user_id' => $first->id,
            'model' => 'first-user-model',
            'api_key' => 'fake-first-user-key',
        ]);
        AiKey::factory()->create([
            'user_id' => $second->id,
            'model' => 'second-user-model',
            'api_key' => 'fake-second-user-key',
        ]);
        $resolver = app(AiChainResolver::class);

        $this->assertSame(["user_{$first->id}_gemini" => 'first-user-model'], $resolver->for($first));
        $this->assertSame(["user_{$second->id}_gemini" => 'second-user-model'], $resolver->for($second));

        $manager = app(AiManager::class);
        $this->assertSame('fake-first-user-key', $manager->getInstanceConfig("user_{$first->id}_gemini")['key']);
        $this->assertSame('fake-second-user-key', $manager->getInstanceConfig("user_{$second->id}_gemini")['key']);
    }

    public function test_a_user_without_keys_or_the_flag_cannot_use_the_instance_chain_or_another_users_key(): void
    {
        config([
            'digest.ai_providers' => 'gemini:gemini-3.8-flash',
            'ai.default' => 'gemini',
            'ai.providers.gemini.key' => 'fake-instance-key',
        ]);
        AiKey::factory()->create();
        $user = User::factory()->create();
        $user->forceFill(['uses_instance_ai' => false])->save();
        $resolver = app(AiChainResolver::class);

        $this->expectException(AiNotConfiguredException::class);

        $resolver->for($user);
    }

    public function test_a_user_without_keys_or_the_flag_cannot_use_the_default_provider_when_the_instance_chain_is_empty(): void
    {
        config([
            'digest.ai_providers' => '',
            'ai.default' => 'gemini',
            'ai.providers.gemini.key' => 'fake-instance-key',
        ]);
        $user = User::factory()->create();
        $user->forceFill(['uses_instance_ai' => false])->save();
        $resolver = app(AiChainResolver::class);

        $this->expectException(AiNotConfiguredException::class);

        $resolver->for($user);
    }

    public function test_a_flagged_user_without_keys_gets_the_instance_chain(): void
    {
        config(['digest.ai_providers' => 'gemini:gemini-3.8-flash']);
        $user = User::factory()->create();
        $user->forceFill(['uses_instance_ai' => true])->save();
        AiKey::factory()->provider(AiProvider::Anthropic)->create();

        $this->assertSame(['gemini' => 'gemini-3.8-flash'], app(AiChainResolver::class)->for($user));
    }

    public function test_a_flagged_user_with_keys_gets_only_their_keys_instead_of_the_instance_chain(): void
    {
        config(['digest.ai_providers' => 'gemini:gemini-3.8-flash']);
        $user = User::factory()->create();
        $user->forceFill(['uses_instance_ai' => true])->save();
        AiKey::factory()->provider(AiProvider::Groq)->create([
            'user_id' => $user->id,
            'model' => 'test-groq-model',
            'api_key' => 'fake-user-groq-key',
        ]);

        $this->assertSame(
            ["user_{$user->id}_groq" => 'test-groq-model'],
            app(AiChainResolver::class)->for($user),
        );
        $config = app(AiManager::class)->getInstanceConfig("user_{$user->id}_groq");
        $this->assertSame('fake-user-groq-key', $config['key']);
        $this->assertSame('groq', $config['driver']);
    }

    public function test_a_flagged_user_without_keys_and_an_empty_instance_chain_gets_a_configuration_exception(): void
    {
        config(['digest.ai_providers' => '']);
        $user = User::factory()->create();
        $user->forceFill(['uses_instance_ai' => true])->save();
        $resolver = app(AiChainResolver::class);

        $this->expectException(AiConfigurationException::class);

        $resolver->for($user);
    }
}
