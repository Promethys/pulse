<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\DiscordGuild;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DeliveryActionsTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/123456789/fake-private-token';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        Sleep::fake();
        Cache::flush();
        config(['services.discord.token' => 'fake-bot-token']);
    }

    #[DataProvider('destinations')]
    public function test_store_creates_an_enabled_owned_destination_with_encrypted_settings(string $type, array $fields, array $settings): void
    {
        $user = $this->readerWithGuild();
        $this->actingAs($user)->from('/settings/delivery')->post('/settings/delivery', [
            'type' => $type, 'kinds' => ['news'], ...$fields, 'user_id' => 999999,
        ])->assertRedirect('/settings/delivery')->assertSessionHasNoErrors();
        $channel = $user->deliveryChannels()->sole();
        $this->assertSame($type, $channel->type->value);
        $this->assertSame(['news'], $channel->kinds);
        $this->assertTrue($channel->enabled);
        $this->assertSame($settings, $channel->settings);
        foreach ($settings as $value) {
            $this->assertStringNotContainsString($value, $channel->getRawOriginal('settings'));
        }
        Mail::assertNothingSent();
    }

    public static function destinations(): array
    {
        return [
            ['discord_bot', ['guild_id' => '456', 'channel_id' => '789'], ['guild_id' => '456', 'channel_id' => '789']],
            ['discord_webhook', ['name' => 'Digest hook', 'webhook_url' => self::WEBHOOK], ['webhook_url' => self::WEBHOOK]],
            ['email', ['address' => 'reader@example.test'], ['address' => 'reader@example.test']],
        ];
    }

    #[DataProvider('invalidDestinations')]
    public function test_invalid_destination_is_not_saved(array $payload, string $field): void
    {
        $this->actingAs($this->readerWithGuild())->postJson('/settings/delivery', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('delivery_channels', 0);
        Mail::assertNothingSent();
    }

    public static function invalidDestinations(): array
    {
        $webhook = ['type' => 'discord_webhook', 'name' => 'Digest', 'webhook_url' => self::WEBHOOK, 'kinds' => ['news']];
        $cases = [];
        foreach ([['type', 'in_app'], ['type', 'invalid'], ['name', ''], ['name', str_repeat('n', 101)],
            ['webhook_url', 'https://example.test/hook'], ['webhook_url', self::WEBHOOK.'?redirect=1'],
            ['webhook_url', 'http://discord.com/api/webhooks/123/token'], ['kinds', []], ['kinds', ['unknown']], ['kinds', 'news']] as [$field, $value]) {
            $cases[] = [[...$webhook, $field => $value], $field === 'kinds' && is_array($value) && $value !== [] ? 'kinds.0' : $field];
        }
        $cases[] = [['type' => 'email', 'address' => 'invalid', 'kinds' => ['news']], 'address'];
        $cases[] = [['type' => 'discord_bot', 'guild_id' => '456', 'channel_id' => 'text-channel', 'kinds' => ['news']], 'channel_id'];
        $cases[] = [['type' => 'discord_bot', 'guild_id' => 'missing', 'channel_id' => '789', 'kinds' => ['news']], 'guild_id'];

        return $cases;
    }

    public function test_bot_cannot_use_another_readers_guild_even_if_the_forum_exists(): void
    {
        $user = User::factory()->onboarded()->create();
        DiscordGuild::factory()->create(['guild_id' => '456']);
        $this->actingAs($user)->postJson('/settings/delivery', ['type' => 'discord_bot', 'guild_id' => '456', 'channel_id' => '789', 'kinds' => ['news']])
            ->assertUnprocessable()->assertJsonValidationErrors('guild_id');
        $this->assertDatabaseCount('delivery_channels', 0);
        Http::assertNothingSent();
    }

    public function test_only_one_email_is_allowed_per_user(): void
    {
        $user = User::factory()->onboarded()->create();
        DeliveryChannel::factory()->email()->create();
        $payload = ['type' => 'email', 'address' => 'reader@example.test', 'kinds' => ['news', 'learning']];
        $this->actingAs($user)->postJson('/settings/delivery', $payload)->assertRedirect();
        $this->postJson('/settings/delivery', $payload)->assertUnprocessable();
        $this->assertSame(1, $user->deliveryChannels()->count());
    }

    #[DataProvider('discordTypes')]
    public function test_twentieth_discord_destination_is_allowed_but_twenty_first_is_rejected(string $type): void
    {
        $user = $this->readerWithGuild();
        DeliveryChannel::factory()->for($user)->discordWebhook()->count(18)->create();
        DeliveryChannel::factory()->for($user)->discordBot()->create();
        DeliveryChannel::factory()->for($user)->email()->create();
        DeliveryChannel::factory()->for($user)->inApp()->create();
        DeliveryChannel::factory()->discordWebhook()->count(20)->create();
        $payload = ['type' => $type, 'name' => 'Digest', 'webhook_url' => self::WEBHOOK, 'guild_id' => '456', 'channel_id' => '789', 'kinds' => ['news']];
        $this->actingAs($user)->postJson('/settings/delivery', $payload)->assertRedirect();
        $this->postJson('/settings/delivery', $payload)->assertUnprocessable();
        $this->assertSame(22, $user->deliveryChannels()->count());
    }

    public static function discordTypes(): array
    {
        return [['discord_bot'], ['discord_webhook']];
    }

    public function test_update_changes_email_webhook_and_bot_fields_without_changing_owner_or_type(): void
    {
        $user = $this->readerWithGuild();
        $email = DeliveryChannel::factory()->for($user)->email()->create();
        $hook = DeliveryChannel::factory()->for($user)->discordWebhook()->create(['settings' => ['webhook_url' => self::WEBHOOK]]);
        $bot = DeliveryChannel::factory()->for($user)->discordBot()->create(['settings' => ['guild_id' => '456', 'channel_id' => 'old']]);
        $this->actingAs($user)->from('/settings/delivery');
        foreach ([[$email, ['address' => 'updated@example.test']], [$hook, ['name' => 'Renamed']], [$bot, ['channel_id' => '789']]] as [$channel, $fields]) {
            $this->put("/settings/delivery/{$channel->id}", ['enabled' => false, 'kinds' => ['learning'], ...$fields, 'user_id' => 999999, 'type' => 'in_app'])
                ->assertRedirect('/settings/delivery')->assertSessionHasNoErrors();
            $this->assertFalse($channel->fresh()->enabled);
            $this->assertSame(['learning'], $channel->fresh()->kinds);
            $this->assertSame($user->id, $channel->fresh()->user_id);
            $this->assertSame($channel->type, $channel->fresh()->type);
        }
        $this->assertSame('updated@example.test', $email->fresh()->settings['address']);
        $this->assertSame('Renamed', $hook->fresh()->name);
        $this->assertSame(self::WEBHOOK, $hook->fresh()->settings['webhook_url']);
        $this->assertSame('789', $bot->fresh()->settings['channel_id']);
        $replacement = 'https://discord.com/api/webhooks/987654321/fake-replacement-token';
        $this->put("/settings/delivery/{$hook->id}", ['enabled' => true, 'kinds' => ['news'], 'name' => 'New hook', 'webhook_url' => $replacement])
            ->assertSessionHasNoErrors();
        $this->assertSame($replacement, $hook->fresh()->settings['webhook_url']);
    }

    public function test_invalid_updates_leave_all_fields_unchanged(): void
    {
        $user = $this->readerWithGuild();
        $email = DeliveryChannel::factory()->for($user)->email()->create();
        $before = $email->fresh()->getAttributes();
        $this->actingAs($user)->putJson("/settings/delivery/{$email->id}", ['enabled' => 'wrong', 'kinds' => [], 'address' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['enabled', 'kinds', 'address']);
        $this->assertSame($before, $email->fresh()->getAttributes());
        $bot = DeliveryChannel::factory()->for($user)->discordBot()->create(['settings' => ['guild_id' => '456', 'channel_id' => '789']]);
        $botBefore = $bot->fresh()->getAttributes();
        $this->putJson("/settings/delivery/{$bot->id}", ['enabled' => true, 'kinds' => ['news'], 'channel_id' => 'text-channel'])
            ->assertUnprocessable()->assertJsonValidationErrors('channel_id');
        $this->assertSame($botBefore, $bot->fresh()->getAttributes());
    }

    #[DataProvider('protectedChannels')]
    public function test_every_model_action_forbids_other_users_and_in_app_channels(bool $inApp): void
    {
        $user = User::factory()->onboarded()->create();
        $channel = $inApp ? DeliveryChannel::factory()->for($user)->inApp()->create() : DeliveryChannel::factory()->discordWebhook()->create();
        $before = $channel->fresh()->getAttributes();
        $this->actingAs($user)->putJson("/settings/delivery/{$channel->id}", ['enabled' => false, 'kinds' => ['news']])->assertForbidden();
        $this->delete("/settings/delivery/{$channel->id}")->assertForbidden();
        $this->postJson("/settings/delivery/{$channel->id}/test")->assertForbidden();
        $this->assertSame($before, $channel->fresh()->getAttributes());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function protectedChannels(): array
    {
        return [[false], [true]];
    }

    public function test_delete_cascades_deliveries_but_preserves_posts_and_other_destinations(): void
    {
        $user = User::factory()->onboarded()->create();
        $channel = DeliveryChannel::factory()->for($user)->email()->create();
        $delivery = Delivery::factory()->for($channel, 'channel')->create();
        $post = $delivery->post;
        $other = DeliveryChannel::factory()->create();
        $this->actingAs($user)->from('/settings/delivery')->delete("/settings/delivery/{$channel->id}")->assertRedirect('/settings/delivery');
        $this->assertModelMissing($channel);
        $this->assertModelMissing($delivery);
        $this->assertModelExists($post);
        $this->assertModelExists($other);
    }

    public function test_disconnect_removes_only_owned_guild_and_its_bot_channels_without_leaving_discord(): void
    {
        $user = User::factory()->onboarded()->create();
        $guild = DiscordGuild::factory()->for($user)->create(['guild_id' => '456']);
        $otherGuild = DiscordGuild::factory()->create(['guild_id' => '456']);
        $bot = DeliveryChannel::factory()->for($user)->discordBot()->create(['settings' => ['guild_id' => '456', 'channel_id' => '789']]);
        $otherBot = DeliveryChannel::factory()->for($otherGuild->user)->discordBot()->create(['settings' => ['guild_id' => '456', 'channel_id' => '789']]);
        $unrelated = DeliveryChannel::factory()->for($user)->discordBot()->create(['settings' => ['guild_id' => '457', 'channel_id' => '790']]);
        $hook = DeliveryChannel::factory()->for($user)->discordWebhook()->create();
        $this->actingAs($user)->delete("/settings/delivery/guilds/{$otherGuild->id}")->assertForbidden();
        $this->from('/settings/delivery')->delete("/settings/delivery/guilds/{$guild->id}")->assertRedirect('/settings/delivery');
        $this->assertModelMissing($guild);
        $this->assertModelMissing($bot);
        foreach ([$otherGuild, $otherBot, $unrelated, $hook] as $model) {
            $this->assertModelExists($model);
        }
        Http::assertNothingSent();
    }

    public function test_bot_test_creates_a_pulse_test_forum_post_without_persisting_deliveries(): void
    {
        $user = User::factory()->onboarded()->create();
        $channel = DeliveryChannel::factory()->for($user)->discordBot()->create(['settings' => ['guild_id' => '456', 'channel_id' => '789']]);
        Http::fake(['https://discord.com/api/v10/channels/789/threads' => Http::response(['id' => 'thread-1'])]);
        $this->actingAs($user)->postJson("/settings/delivery/{$channel->id}/test")->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['message']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['name'] === 'Pulse test'
            && $request['message']['allowed_mentions'] === ['parse' => []] && $request['message']['content'] !== '');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_delivery_tests_are_limited_through_the_hour(): void
    {
        $user = User::factory()->onboarded()->create();
        $channel = DeliveryChannel::factory()->for($user)->discordWebhook()->create(['settings' => ['webhook_url' => self::WEBHOOK]]);
        Http::fake([self::WEBHOOK.'*' => Http::response(['id' => 'message-1', 'channel_id' => 'thread-1'])]);
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->travel(13)->seconds();
            $this->postJson("/settings/delivery/{$channel->id}/test")->assertOk();
        }

        $this->travel(13)->seconds();
        $this->postJson("/settings/delivery/{$channel->id}/test")->assertTooManyRequests();
    }

    public function test_webhook_tests_send_a_new_thread_and_are_throttled_without_mutating_the_channel(): void
    {
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        $channel = DeliveryChannel::factory()->for($user)->discordWebhook()->create(['settings' => ['webhook_url' => self::WEBHOOK]]);
        $before = $channel->fresh()->getAttributes();
        Http::fake([self::WEBHOOK.'*' => Http::response(['id' => 'message-1', 'channel_id' => 'thread-1'])]);
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson("/settings/delivery/{$channel->id}/test")->assertOk()->assertJsonPath('ok', true);
        }
        $this->postJson("/settings/delivery/{$channel->id}/test")->assertTooManyRequests();
        Http::assertSentCount(6);
        Http::assertSent(fn (Request $request) => $request['thread_name'] === 'Pulse test' && $request['content'] !== ''
            && $request['allowed_mentions'] === ['parse' => []]);
        $this->travel(61)->seconds();
        $this->postJson("/settings/delivery/{$channel->id}/test")->assertOk();
        $this->assertSame($before, $channel->fresh()->getAttributes());
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_email_test_sends_only_to_the_configured_destination(): void
    {
        $user = User::factory()->onboarded()->create();
        $channel = DeliveryChannel::factory()->for($user)->email()->create(['settings' => ['address' => 'destination@example.test']]);
        $this->actingAs($user)->postJson("/settings/delivery/{$channel->id}/test")->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['message']);
        Mail::assertSentCount(1);
        Mail::assertSent(Mailable::class, fn (Mailable $mail) => $mail->hasTo('destination@example.test'));
        $this->assertDatabaseCount('deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_discord_test_failure_returns_a_friendly_message_and_logs_without_webhook_secrets(): void
    {
        Log::spy();
        $user = User::factory()->onboarded()->create();
        $channel = DeliveryChannel::factory()->for($user)->discordWebhook()->create(['settings' => ['webhook_url' => self::WEBHOOK]]);
        Http::fake([self::WEBHOOK.'*' => Http::response(['message' => 'raw error '.self::WEBHOOK], 403)]);
        $response = $this->actingAs($user)->postJson("/settings/delivery/{$channel->id}/test")
            ->assertOk()->assertJsonPath('ok', false)->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('fake-private-token', $response->getContent());
        $this->assertStringNotContainsString('raw error', $response->getContent());
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => str_contains(json_encode($context), 'raw error')
            && ! str_contains(json_encode($context), 'fake-private-token') && ! str_contains(json_encode($context), self::WEBHOOK));
    }

    public function test_discord_discovery_failure_does_not_create_a_bot_destination(): void
    {
        $user = User::factory()->onboarded()->create();
        DiscordGuild::factory()->for($user)->create(['guild_id' => '456']);
        Http::fake(['https://discord.com/api/v10/guilds/456/channels' => Http::response(['message' => 'Missing access'], 403)]);
        $this->actingAs($user)->postJson('/settings/delivery', ['type' => 'discord_bot', 'guild_id' => '456', 'channel_id' => '789', 'kinds' => ['news']])
            ->assertUnprocessable()->assertJsonValidationErrors('channel_id');
        $this->assertDatabaseCount('delivery_channels', 0);
    }

    public function test_email_transport_failure_returns_a_friendly_result_and_logs_the_error(): void
    {
        Log::spy();
        $user = User::factory()->onboarded()->create();
        $channel = DeliveryChannel::factory()->for($user)->email()->create(['settings' => ['address' => 'destination@example.test']]);
        $pending = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with('destination@example.test')->andReturn($pending);
        $pending->shouldReceive('send')->once()->with(Mockery::type(Mailable::class))->andThrow(new RuntimeException('Transport unavailable'));
        $response = $this->actingAs($user)->postJson("/settings/delivery/{$channel->id}/test")
            ->assertOk()->assertJsonPath('ok', false)->assertJsonStructure(['message']);
        $this->assertStringNotContainsString('Transport unavailable', $response->getContent());
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => str_contains(json_encode($context), 'Transport unavailable'));
        $this->assertDatabaseCount('deliveries', 0);
        Http::assertNothingSent();
    }

    private function readerWithGuild(): User
    {
        $user = User::factory()->onboarded()->create();
        DiscordGuild::factory()->for($user)->create(['guild_id' => '456']);
        Http::fake(['https://discord.com/api/v10/guilds/456/channels' => Http::response([
            ['id' => '789', 'name' => 'Digest', 'type' => 15], ['id' => 'text-channel', 'name' => 'Chat', 'type' => 0],
        ])]);

        return $user;
    }
}
