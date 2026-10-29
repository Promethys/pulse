<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Digest\Ai\AiChainResolver;
use App\Enums\AiProvider;
use App\Models\AiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\AiManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('providers')]
    public function test_providers_offer_key_links_and_configured_default_models(string $value, string $label, string $url, array $models): void
    {
        $provider = AiProvider::from($value);

        $this->assertSame($label, $provider->label());
        $this->assertSame($url, $provider->keyUrl());
        $this->assertSame($models, $provider->suggestedModels());
        $this->assertSame($models, config("digest.suggested_models.$value"));

        config(["digest.suggested_models.$value" => ['custom-model', 'second-model']]);

        $this->assertSame(['custom-model', 'second-model'], $provider->suggestedModels());
    }

    public static function providers(): array
    {
        return [
            'gemini' => ['gemini', 'Gemini', 'https://aistudio.google.com/apikey', ['gemini-3.8-flash']],
            'anthropic' => ['anthropic', 'Anthropic', 'https://console.anthropic.com/settings/keys', ['claude-haiku-5-5', 'claude-sonnet-5-5']],
            'openai' => ['openai', 'OpenAI', 'https://platform.openai.com/api-keys', ['gpt-5-mini']],
            'groq' => ['groq', 'Groq', 'https://console.groq.com/keys', ['llama-3.3-70b-versatile']],
            'openrouter' => ['openrouter', 'OpenRouter', 'https://openrouter.ai/keys', ['deepseek/deepseek-chat']],
            'zai' => ['zai', 'Z.AI', 'https://z.ai/manage-apikey/apikey-list', ['glm-4.7-flash']],
        ];
    }

    public function test_zai_user_keys_build_a_chain_from_the_configured_compatible_provider(): void
    {
        config(['ai.providers.zai.key' => 'fake-instance-zai-key']);
        $configured = config('ai.providers.zai');
        $user = User::factory()->create();
        AiKey::factory()->provider(AiProvider::ZAi)->create([
            'user_id' => $user->id,
            'model' => 'glm-4.7-flash',
            'api_key' => 'fake-user-zai-key',
        ]);

        $this->assertSame(["user_{$user->id}_zai" => 'glm-4.7-flash'], app(AiChainResolver::class)->for($user));
        $built = app(AiManager::class)->getInstanceConfig("user_{$user->id}_zai");
        $this->assertSame('openai-compatible', $built['driver']);
        $this->assertSame($configured['url'], $built['url']);
        $this->assertSame('fake-user-zai-key', $built['key']);
        $this->assertSame($configured, config('ai.providers.zai'));
    }
}
