<?php

declare(strict_types=1);

namespace Tests\Feature\Rules;

use App\Rules\DiscordWebhookUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiscordWebhookUrlTest extends TestCase
{
    #[DataProvider('urls')]
    public function test_accepts_only_discord_webhook_urls(string $url, bool $expected): void
    {
        Http::preventStrayRequests();

        $validator = Validator::make(['webhook_url' => $url], ['webhook_url' => [new DiscordWebhookUrl]]);

        $this->assertSame($expected, $validator->passes());
        if (! $expected) {
            $this->assertSame(['The webhook url must be a Discord webhook URL.'], $validator->errors()->get('webhook_url'));
        }
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, bool}> */
    public static function urls(): array
    {
        return [
            'discord' => ['https://discord.com/api/webhooks/123/AbC_09-token', true],
            'legacy' => ['https://discordapp.com/api/webhooks/123/token', true],
            'ptb' => ['https://ptb.discord.com/api/webhooks/123/token', true],
            'canary' => ['https://canary.discord.com/api/webhooks/123/token', true],
            'versioned discord' => ['https://discord.com/api/v10/webhooks/123/token', true],
            'versioned legacy' => ['https://discordapp.com/api/v10/webhooks/123/token', true],
            'versioned ptb' => ['https://ptb.discord.com/api/v10/webhooks/123/token', true],
            'versioned canary' => ['https://canary.discord.com/api/v10/webhooks/123/token', true],
            'http' => ['http://discord.com/api/webhooks/123/token', false],
            'other host' => ['https://example.test/api/webhooks/123/token', false],
            'host suffix attack' => ['https://discord.com.evil.test/api/webhooks/123/token', false],
            'userinfo attack' => ['https://discord.com@evil.test/api/webhooks/123/token', false],
            'unapproved subdomain' => ['https://www.discord.com/api/webhooks/123/token', false],
            'loopback' => ['http://127.0.0.1/api/webhooks/123/token', false],
            'extra path' => ['https://discord.com/api/webhooks/123/token/messages', false],
            'trailing slash' => ['https://discord.com/api/webhooks/123/token/', false],
            'query' => ['https://discord.com/api/webhooks/123/token?wait=true', false],
            'fragment' => ['https://discord.com/api/webhooks/123/token#secret', false],
            'missing token' => ['https://discord.com/api/webhooks/123', false],
            'empty token' => ['https://discord.com/api/webhooks/123/', false],
            'nonnumeric id' => ['https://discord.com/api/webhooks/webhook-1/token', false],
            'missing id' => ['https://discord.com/api/webhooks//token', false],
            'invalid token character' => ['https://discord.com/api/webhooks/123/token.secret', false],
            'encoded slash in token' => ['https://discord.com/api/webhooks/123/token%2Fsecret', false],
        ];
    }
}
