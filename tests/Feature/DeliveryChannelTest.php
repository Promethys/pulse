<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryChannelType;
use App\Enums\PostKind;
use App\Models\DeliveryChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_encrypted_and_read_back_as_an_array(): void
    {
        $settings = ['webhook_url' => 'https://discord.com/api/webhooks/test-id/private-token'];
        $channel = DeliveryChannel::factory()->discordWebhook()->create(['settings' => $settings]);
        $stored = DB::table('delivery_channels')->where('id', $channel->id)->value('settings');

        $this->assertIsString($stored);
        $this->assertNotSame(json_encode($settings), $stored);
        $this->assertStringNotContainsString($settings['webhook_url'], $stored);
        $this->assertStringNotContainsString('private-token', $stored);
        $this->assertSame($settings, $channel->fresh()->settings);
    }

    public function test_settings_are_hidden_from_array_and_json_serialization(): void
    {
        $channel = DeliveryChannel::factory()->discordWebhook()->create([
            'settings' => ['webhook_url' => 'https://discord.com/api/webhooks/test-id/private-token'],
        ])->refresh();

        $this->assertArrayNotHasKey('settings', $channel->toArray());
        $this->assertArrayNotHasKey('settings', json_decode($channel->toJson(), true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('private-token', $channel->toJson());
    }

    public function test_channel_attributes_can_be_filled_and_are_cast_after_reloading(): void
    {
        $user = User::factory()->create();
        $channel = new DeliveryChannel;
        $channel->fill([
            'user_id' => $user->id,
            'type' => 'discord_bot',
            'name' => 'News forum',
            'kinds' => ['news'],
            'enabled' => 0,
            'settings' => ['guild_id' => '123', 'channel_id' => '456'],
            'verified_at' => '2026-10-25 10:00:00',
        ]);
        $channel->save();
        $channel->refresh();

        $this->assertSame($user->id, $channel->user_id);
        $this->assertSame(DeliveryChannelType::DiscordBot, $channel->type);
        $this->assertSame('discord_bot', $channel->getRawOriginal('type'));
        $this->assertSame('News forum', $channel->name);
        $this->assertSame(['news'], $channel->kinds);
        $this->assertFalse($channel->enabled);
        $this->assertSame(['guild_id' => '123', 'channel_id' => '456'], $channel->settings);
        $this->assertInstanceOf(CarbonImmutable::class, $channel->verified_at);
        $this->assertSame('2026-10-25 10:00:00', $channel->verified_at->format('Y-m-d H:i:s'));
    }

    public function test_the_default_factory_creates_an_enabled_in_app_channel_for_both_kinds(): void
    {
        $channel = DeliveryChannel::factory()->create()->refresh();

        $this->assertSame(DeliveryChannelType::InApp, $channel->type);
        $this->assertSame('in_app', $channel->getRawOriginal('type'));
        $this->assertTrue($channel->enabled);
        $this->assertEqualsCanonicalizing(['news', 'learning'], $channel->kinds);
        $this->assertNull($channel->settings);
        $this->assertNull($channel->verified_at);
    }

    /** @param list<string> $settingsKeys */
    #[DataProvider('channelStates')]
    public function test_factory_states_create_the_expected_channel_type_and_settings(string $state, string $type, array $settingsKeys): void
    {
        $channel = DeliveryChannel::factory()->{$state}()->create()->refresh();

        $this->assertSame(DeliveryChannelType::from($type), $channel->type);
        $this->assertSame($type, $channel->getRawOriginal('type'));
        if ($settingsKeys === []) {
            $this->assertNull($channel->settings);
        } else {
            $this->assertIsArray($channel->settings);
            $this->assertEqualsCanonicalizing($settingsKeys, array_keys($channel->settings));
            foreach ($settingsKeys as $key) {
                $this->assertIsString($channel->settings[$key]);
                $this->assertNotSame('', $channel->settings[$key]);
            }
        }
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function channelStates(): array
    {
        return [
            'Discord bot' => ['discordBot', 'discord_bot', ['guild_id', 'channel_id']],
            'Discord webhook' => ['discordWebhook', 'discord_webhook', ['webhook_url']],
            'email' => ['email', 'email', ['address']],
            'in app' => ['inApp', 'in_app', []],
        ];
    }

    /** @param list<string> $kinds */
    #[DataProvider('channelKinds')]
    public function test_takes_checks_each_kind_including_empty_and_disabled_channels(array $kinds, bool $news, bool $learning): void
    {
        $channel = DeliveryChannel::factory()->disabled()->create(['kinds' => $kinds]);

        $this->assertFalse($channel->enabled);
        $this->assertSame($news, $channel->takes(PostKind::News));
        $this->assertSame($learning, $channel->takes(PostKind::Learning));
    }

    /** @return array<string, array{list<string>, bool, bool}> */
    public static function channelKinds(): array
    {
        return [
            'news only' => [['news'], true, false],
            'learning only' => [['learning'], false, true],
            'both kinds' => [['news', 'learning'], true, true],
            'neither kind' => [[], false, false],
        ];
    }

    #[DataProvider('receivingKinds')]
    public function test_receiving_returns_only_enabled_channels_taking_the_requested_kind(string $kind, string $otherKind): void
    {
        $matching = DeliveryChannel::factory()->create(['enabled' => true, 'kinds' => [$kind]]);
        $both = DeliveryChannel::factory()->create(['enabled' => true, 'kinds' => ['news', 'learning']]);
        DeliveryChannel::factory()->create(['enabled' => true, 'kinds' => [$otherKind]]);
        DeliveryChannel::factory()->create(['enabled' => true, 'kinds' => []]);
        DeliveryChannel::factory()->disabled()->create(['kinds' => [$kind]]);
        DeliveryChannel::factory()->disabled()->create(['kinds' => ['news', 'learning']]);

        $this->assertEqualsCanonicalizing(
            [$matching->id, $both->id],
            DeliveryChannel::query()->receiving(PostKind::from($kind))->get()->modelKeys(),
        );
    }

    /** @return array<string, array{string, string}> */
    public static function receivingKinds(): array
    {
        return [
            'news' => ['news', 'learning'],
            'learning' => ['learning', 'news'],
        ];
    }

    public function test_a_user_can_have_several_discord_bot_channels_and_relations_keep_other_users_separate(): void
    {
        $user = User::factory()->create();
        $first = DeliveryChannel::factory()->discordBot()->create([
            'user_id' => $user->id,
            'settings' => ['guild_id' => '123', 'channel_id' => '456'],
        ]);
        $second = DeliveryChannel::factory()->discordBot()->create([
            'user_id' => $user->id,
            'settings' => ['guild_id' => '123', 'channel_id' => '789'],
        ]);
        DeliveryChannel::factory()->discordBot()->create();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $user->deliveryChannels->modelKeys());
        $this->assertTrue($first->user->is($user));
        $this->assertTrue($second->user->is($user));
    }

    public function test_deleting_a_user_deletes_its_channels_and_keeps_other_users_channels(): void
    {
        $user = User::factory()->create();
        DeliveryChannel::factory()->count(2)->create(['user_id' => $user->id]);
        $other = DeliveryChannel::factory()->create();

        $user->delete();

        $this->assertDatabaseMissing('delivery_channels', ['user_id' => $user->id]);
        $this->assertModelExists($other);
    }
}
