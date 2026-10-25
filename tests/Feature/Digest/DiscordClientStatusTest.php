<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Discord\DiscordClient;
use App\Digest\Discord\DiscordException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DiscordClientStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        config(['services.discord.token' => 'bot-token']);
    }

    #[DataProvider('httpStatuses')]
    public function test_http_errors_expose_the_response_status(int $status): void
    {
        Http::fake(['https://discord.com/api/v10/channels/forum-1' => Http::response(['message' => 'Discord failure'], $status)]);

        try {
            app(DiscordClient::class)->channel('forum-1');
            $this->fail('Expected DiscordException');
        } catch (DiscordException $e) {
            $this->assertSame($status, $e->status);
            $this->assertStringContainsString('Discord failure', $e->getMessage());
        }
    }

    public function test_connection_errors_have_no_http_status_and_keep_the_previous_exception(): void
    {
        Http::fake(['https://discord.com/api/v10/channels/forum-1' => Http::failedConnection('Connection refused')]);

        try {
            app(DiscordClient::class)->channel('forum-1');
            $this->fail('Expected DiscordException');
        } catch (DiscordException $e) {
            $this->assertNull($e->status);
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
            $this->assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    public function test_non_http_discord_exceptions_default_to_a_null_status(): void
    {
        $this->assertNull((new DiscordException('Missing thread id'))->status);
    }

    /** @return array<string, array{int}> */
    public static function httpStatuses(): array
    {
        return ['forbidden' => [403], 'not found' => [404], 'server error' => [500], 'rate limited after retry' => [429]];
    }
}
