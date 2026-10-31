<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\DiscoverDiscordForums;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoverDiscordForumsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.discord.token' => 'test-token']);
    }

    public function test_a_new_forum_shows_up_right_away_when_the_list_was_empty(): void
    {
        Http::fake(['discord.com/*' => Http::sequence()
            ->push([])
            ->push([['id' => '1', 'name' => 'News', 'type' => 15, 'position' => 0]])]);
        $action = app(DiscoverDiscordForums::class);

        $this->assertSame([], $action->handle('42')['forums']);
        $this->assertSame([['id' => '1', 'name' => 'News']], $action->handle('42')['forums']);
    }

    public function test_a_found_list_is_cached(): void
    {
        Http::fake(['discord.com/*' => Http::sequence()->push([['id' => '1', 'name' => 'News', 'type' => 15, 'position' => 0]])]);
        $action = app(DiscoverDiscordForums::class);

        $action->handle('42');
        $action->handle('42');

        Http::assertSentCount(1);
    }

    public function test_a_failure_is_reported_as_an_error(): void
    {
        Http::fake(['discord.com/*' => Http::response([], 403)]);

        $this->assertTrue(app(DiscoverDiscordForums::class)->handle('42')['forums_error']);
    }
}
