<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\DiscordGuild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeliveryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['services.discord.token' => 'fake-bot-token', 'services.discord.client_id' => '123']);
    }

    public function test_page_groups_only_own_destinations_and_exposes_latest_delivery_without_secrets(): void
    {
        $user = User::factory()->onboarded()->create();
        $guild = DiscordGuild::factory()->for($user)->create(['guild_id' => '456', 'name' => 'Developers']);
        $bot = DeliveryChannel::factory()->for($user)->discordBot()->create(['settings' => ['guild_id' => '456', 'channel_id' => '789']]);
        $webhook = DeliveryChannel::factory()->for($user)->discordWebhook()->create(['settings' => ['webhook_url' => 'https://discord.com/api/webhooks/123456789/fake-private-token']]);
        $email = DeliveryChannel::factory()->for($user)->email()->create(['settings' => ['address' => 'reader@example.test']]);
        Delivery::factory()->for($webhook, 'channel')->create(['status' => DeliveryStatus::Delivered, 'delivered_at' => now()->subDay()]);
        $latest = Delivery::factory()->for($webhook, 'channel')->create(['status' => DeliveryStatus::Failed, 'last_error' => 'Missing access']);
        DiscordGuild::factory()->create();
        DeliveryChannel::factory()->discordWebhook()->create();
        DeliveryChannel::factory()->for($user)->inApp()->create();
        Http::fake(['https://discord.com/api/v10/guilds/456/channels' => Http::response([
            ['id' => '789', 'name' => 'Digest', 'type' => 15], ['id' => '123', 'name' => 'Chat', 'type' => 0],
        ])]);
        $this->assertSame(url('/settings/delivery'), route('delivery.index'));
        $response = $this->actingAs($user)->get('/settings/delivery')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/Delivery', false)->has('guilds', 1)->where('guilds.0.id', $guild->id)
            ->where('guilds.0.forums.0.id', '789')->has('guilds.0.forums', 1)
            ->has('guilds.0.destinations', 1)->where('guilds.0.destinations.0.id', $bot->id)
            ->has('webhooks', 1)->where('webhooks.0.id', $webhook->id)
            ->where('webhooks.0.latest_delivery.id', $latest->id)->where('webhooks.0.latest_delivery.last_error', 'Missing access')
            ->missing('webhooks.0.settings')->where('email.id', $email->id)->where('email.latest_delivery', null)
            ->where('email.target.address', 'reader@example.test')->where('account_email', $user->email)
            ->where('install_url', route('discord.install')));
        $this->assertStringNotContainsString('fake-private-token', $response->getContent());
        $this->assertStringNotContainsString('fake-bot-token', $response->getContent());
        Http::assertSentCount(1);
    }

    public function test_forums_are_cached_for_five_minutes_per_guild(): void
    {
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        foreach (['456', '457'] as $id) {
            DiscordGuild::factory()->for($user)->create(['guild_id' => $id]);
        }
        Http::fake(['https://discord.com/api/v10/guilds/*/channels' => Http::response([['id' => '789', 'name' => 'Digest', 'type' => 15]])]);
        $this->actingAs($user)->get('/settings/delivery')->assertOk();
        $this->travel(299)->seconds();
        $this->get('/settings/delivery')->assertOk();
        Http::assertSentCount(2);
        $this->travel(2)->seconds();
        $this->get('/settings/delivery')->assertOk();
        Http::assertSentCount(4);
    }

    public function test_discord_failure_keeps_destinations_visible_with_empty_forums_and_an_error_flag(): void
    {
        $user = User::factory()->onboarded()->create();
        DiscordGuild::factory()->for($user)->create(['guild_id' => '456']);
        $bot = DeliveryChannel::factory()->for($user)->discordBot()->create(['settings' => ['guild_id' => '456', 'channel_id' => '789']]);
        Http::fake(['https://discord.com/api/v10/guilds/456/channels' => Http::response(['message' => 'Missing access'], 403)]);
        $this->actingAs($user)->get('/settings/delivery')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/Delivery', false)->where('guilds.0.forums', [])->where('guilds.0.forums_error', true)
            ->where('guilds.0.destinations.0.id', $bot->id));
        $this->assertTrue($bot->fresh()->enabled);
    }

    public function test_unconfigured_install_and_empty_destinations_have_explicit_null_props(): void
    {
        config(['services.discord.client_id' => null]);
        $this->actingAs(User::factory()->onboarded()->create())->get('/settings/delivery')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/Delivery', false)->where('guilds', [])->where('webhooks', [])->where('email', null)->where('install_url', null));
        Http::assertNothingSent();
    }
}
