<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryChannelType;
use App\Enums\DeliveryStatus;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Enums\SourceType;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\DigestSettings;
use App\Models\DiscordGuild;
use App\Models\Interest;
use App\Models\LearningTopic;
use App\Models\LearningTopicUsage;
use App\Models\Post;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PulseAdoptCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0));
        Http::preventStrayRequests();
        config([
            'digest.timezone' => 'Europe/Paris',
            'digest.news_time' => '08:15',
            'digest.learning_time' => '18:45',
            'digest.max_items' => 7,
            'digest.profile' => 'A fictional reader interested in testing.',
            'services.discord.token' => 'bot-token',
            'services.discord.news_forum' => null,
            'services.discord.learning_forum' => null,
            'services.resend.key' => 'test-resend-key',
        ]);
    }

    public function test_unknown_email_fails_without_changing_data(): void
    {
        $this->mvpData();
        $before = $this->databaseSnapshot();

        $this->artisan('pulse:adopt', ['email' => 'missing@example.test'])
            ->expectsOutputToContain('missing@example.test')
            ->assertExitCode(1);

        $this->assertSame($before, $this->databaseSnapshot());
        Http::assertNothingSent();
    }

    #[DataProvider('settingsStates')]
    public function test_imports_config_and_enables_both_digests_while_preserving_onboarding(?string $state): void
    {
        $owner = User::factory()->create();
        $existing = $state === 'missing' ? null : DigestSettings::factory()->create([
            'user_id' => $owner->id,
            'timezone' => 'UTC',
            'profile' => 'Old fictional profile',
            'news_enabled' => false,
            'learning_enabled' => false,
            'onboarded_at' => $state,
        ]);

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $settings = $owner->digestSettings()->sole();
        if ($existing !== null) {
            $this->assertSame($existing->id, $settings->id);
        }
        $this->assertSame('Europe/Paris', $settings->timezone);
        $this->assertSame('08:15', substr($settings->news_time, 0, 5));
        $this->assertSame('18:45', substr($settings->learning_time, 0, 5));
        $this->assertSame(7, $settings->max_items);
        $this->assertSame(config('digest.profile'), $settings->profile);
        $this->assertTrue($settings->news_enabled);
        $this->assertTrue($settings->learning_enabled);
        $this->assertSame($state === null || $state === 'missing' ? now()->toDateTimeString() : $state, $settings->onboarded_at?->toDateTimeString());
    }

    /** @return array<string, array{?string}> */
    public static function settingsStates(): array
    {
        return ['missing' => ['missing'], 'not onboarded' => [null], 'already onboarded' => ['2026-09-01 10:00:00']];
    }

    public function test_chooses_enabled_catalog_interests_and_subscribes_to_defaults_without_reenabling_opt_outs(): void
    {
        $owner = User::factory()->create();
        $chosen = Interest::factory()->count(2)->create();
        $default = Source::factory()->create(['is_default' => true]);
        $optedOut = Source::factory()->create(['is_default' => true]);
        $optional = Source::factory()->create();
        $disabled = Source::factory()->create(['enabled' => false, 'is_default' => true]);
        $custom = Source::factory()->for(User::factory())->create(['is_default' => true]);
        $chosen[0]->sources()->attach([$default->id, $optedOut->id, $disabled->id]);
        $chosen[1]->sources()->attach($optional);
        Interest::factory()->create()->sources()->attach($disabled);
        Interest::factory()->create()->sources()->attach($custom);
        $subscription = SourceSubscription::factory()->create(['user_id' => $owner->id, 'source_id' => $optedOut->id, 'enabled' => false]);

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $this->assertEqualsCanonicalizing($chosen->modelKeys(), $owner->interests()->pluck('interests.id')->all());
        $this->assertEqualsCanonicalizing([$default->id, $optedOut->id], $owner->sourceSubscriptions()->pluck('source_id')->all());
        $this->assertFalse($subscription->fresh()->enabled);
        $this->assertDatabaseHas('source_subscriptions', ['user_id' => $owner->id, 'source_id' => $default->id, 'enabled' => true]);
    }

    /**
     * @param  array<string, string>|null  $credentials
     * @param  array<string, string>|null  $expected
     */
    #[DataProvider('newsletterCredentials')]
    public function test_newsletters_becomes_a_subscribed_custom_source_without_overwriting_credentials(?array $credentials, ?string $key, ?array $expected): void
    {
        $owner = User::factory()->create();
        $source = Source::factory()->create(['slug' => 'newsletters', 'type' => SourceType::ResendInbox, 'is_default' => true, 'credentials' => $credentials]);
        config(['services.resend.key' => $key]);

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $source->refresh();
        $this->assertSame($owner->id, $source->user_id);
        $this->assertFalse($source->is_default);
        $this->assertSame($expected, $source->credentials);
        $this->assertDatabaseHas('source_subscriptions', ['user_id' => $owner->id, 'source_id' => $source->id, 'enabled' => true]);
    }

    /** @return array<string, array{?array<string, string>, ?string, ?array<string, string>}> */
    public static function newsletterCredentials(): array
    {
        return [
            'null credentials' => [null, 'new-key', ['api_key' => 'new-key']],
            'empty credentials' => [[], 'new-key', ['api_key' => 'new-key']],
            'existing credentials' => [['api_key' => 'keep-key'], 'new-key', ['api_key' => 'keep-key']],
            'missing key' => [null, null, null],
            'empty key' => [null, '', null],
        ];
    }

    public function test_missing_newsletters_source_is_skipped(): void
    {
        $owner = User::factory()->create();

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('source_subscriptions', 0);
    }

    public function test_creates_verified_discord_destinations_and_updates_the_existing_guild(): void
    {
        $owner = User::factory()->create();
        $guild = DiscordGuild::factory()->create(['user_id' => $owner->id, 'guild_id' => '456', 'name' => 'Old name']);
        $this->fakeDiscord();

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $this->assertDatabaseCount('discord_guilds', 1);
        $this->assertSame('Test guild', $guild->fresh()->name);
        $channels = $owner->deliveryChannels()->where('type', DeliveryChannelType::DiscordBot)->get();
        $this->assertCount(2, $channels);
        foreach (['news' => '111', 'learning' => '222'] as $kind => $id) {
            $channel = $channels->sole(fn (DeliveryChannel $channel) => $channel->settings['channel_id'] === $id);
            $this->assertSame('Discord '.$kind, $channel->name);
            $this->assertSame([$kind], $channel->kinds);
            $this->assertSame(['guild_id' => '456', 'channel_id' => $id], $channel->settings);
            $this->assertTrue($channel->enabled);
            $this->assertTrue($channel->verified_at?->equalTo(now()));
        }
    }

    public function test_equal_forum_ids_create_one_destination_for_both_kinds(): void
    {
        $owner = User::factory()->create();
        $this->fakeDiscord();
        config(['services.discord.learning_forum' => '111']);
        foreach (PostKind::cases() as $kind) {
            Post::factory()->create(['user_id' => null, 'kind' => $kind, 'status' => PostStatus::Posted, 'discord_thread_id' => 'thread-'.$kind->value]);
        }

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $channel = $owner->deliveryChannels()->where('type', DeliveryChannelType::DiscordBot)->sole();
        $this->assertSame('Discord', $channel->name);
        $this->assertEqualsCanonicalizing(['news', 'learning'], $channel->kinds);
        $this->assertSame('111', $channel->settings['channel_id']);
        $this->assertSame(2, $channel->deliveries()->count());
        $this->assertDatabaseCount('discord_guilds', 1);
    }

    #[DataProvider('missingForums')]
    public function test_missing_forum_warns_and_keeps_the_configured_destination(string $missing, string $remaining): void
    {
        $owner = User::factory()->create();
        $this->fakeDiscord();
        config(['services.discord.'.$missing.'_forum' => null]);

        $this->artisan('pulse:adopt', ['email' => $owner->email])
            ->expectsOutputToContain($missing)
            ->assertExitCode(0);

        $this->assertSame([$remaining], $owner->deliveryChannels()->where('type', DeliveryChannelType::DiscordBot)->sole()->kinds);
    }

    /** @return array<string, array{string, string}> */
    public static function missingForums(): array
    {
        return ['news missing' => ['news', 'learning'], 'learning missing' => ['learning', 'news']];
    }

    public function test_assigns_only_unowned_posts_and_sets_owner_flags(): void
    {
        $owner = User::factory()->create(['is_admin' => false, 'uses_instance_ai' => false]);
        $posts = Post::factory()->count(3)->create(['user_id' => null]);
        $owned = Post::factory()->create(['user_id' => $owner->id]);
        $other = User::factory()->create(['is_admin' => false, 'uses_instance_ai' => false]);
        $otherPost = Post::factory()->create(['user_id' => $other->id]);

        $this->artisan('pulse:adopt', ['email' => $owner->email])
            ->expectsOutputToContain('Assigned 3 posts')
            ->assertExitCode(0);

        $this->assertEqualsCanonicalizing([...$posts->modelKeys(), $owned->id], $owner->posts()->pluck('id')->all());
        $this->assertSame($other->id, $otherPost->fresh()->user_id);
        $this->assertTrue($owner->fresh()->is_admin);
        $this->assertTrue($owner->fresh()->uses_instance_ai);
        $this->assertFalse($other->fresh()->is_admin);
        $this->assertFalse($other->fresh()->uses_instance_ai);
    }

    public function test_imports_topic_history_once_per_date_without_replacing_existing_usage(): void
    {
        $owner = User::factory()->create();
        $first = LearningTopic::factory()->create(['last_used_on' => '2026-10-20']);
        LearningTopic::factory()->create(['last_used_on' => '2026-10-20']);
        $second = LearningTopic::factory()->create(['last_used_on' => '2026-10-21']);
        LearningTopic::factory()->create(['last_used_on' => '2026-10-22']);
        LearningTopic::factory()->create(['last_used_on' => null]);
        $existing = LearningTopicUsage::factory()->create(['user_id' => $owner->id, 'used_on' => '2026-10-22']);
        $other = LearningTopicUsage::factory()->create(['learning_topic_id' => $first->id, 'used_on' => '2026-10-20']);

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $this->assertSame(3, $owner->learningTopicUsages()->count());
        $this->assertSame(1, $owner->learningTopicUsages()->whereDate('used_on', '2026-10-20')->count());
        $this->assertDatabaseHas('learning_topic_usages', ['user_id' => $owner->id, 'learning_topic_id' => $second->id, 'used_on' => '2026-10-21']);
        $this->assertSame($existing->learning_topic_id, $existing->fresh()->learning_topic_id);
        $this->assertModelExists($other);
    }

    public function test_backfills_deliveries_only_for_owner_posted_threads_into_the_matching_kind(): void
    {
        $owner = User::factory()->create();
        $this->fakeDiscord();
        $posts = [
            Post::factory()->create(['user_id' => null, 'kind' => PostKind::News, 'status' => PostStatus::Posted, 'discord_thread_id' => 'news-thread', 'messages_sent' => 4, 'attempts' => 2, 'posted_at' => now()->subDays(2)]),
            Post::factory()->create(['user_id' => $owner->id, 'kind' => PostKind::Learning, 'status' => PostStatus::Posted, 'discord_thread_id' => 'learning-thread', 'messages_sent' => 3, 'attempts' => 1, 'posted_at' => now()->subDay()]),
        ];
        foreach ([PostStatus::Pending, PostStatus::Generated, PostStatus::Failed] as $status) {
            Post::factory()->create(['user_id' => null, 'status' => $status, 'discord_thread_id' => 'unfinished']);
        }
        Post::factory()->create(['user_id' => null, 'status' => PostStatus::Posted, 'discord_thread_id' => null]);
        Post::factory()->create(['user_id' => User::factory()->create()->id, 'status' => PostStatus::Posted, 'discord_thread_id' => 'other-thread']);

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $this->assertDatabaseCount('deliveries', 2);
        foreach ($posts as $post) {
            $delivery = $post->deliveries()->sole();
            $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
            $this->assertSame($post->discord_thread_id, $delivery->external_id);
            $this->assertSame($post->messages_sent, $delivery->progress);
            $this->assertSame($post->attempts, $delivery->attempts);
            $this->assertTrue($delivery->delivered_at?->equalTo($post->posted_at));
            $this->assertSame($owner->id, $delivery->channel->user_id);
            $this->assertSame(DeliveryChannelType::DiscordBot, $delivery->channel->type);
            $this->assertSame([$post->kind->value], $delivery->channel->kinds);
        }
    }

    public function test_creates_an_in_app_channel_for_the_owner_even_when_another_user_has_one(): void
    {
        $owner = User::factory()->create();
        DeliveryChannel::factory()->inApp()->create();

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $this->assertSame(DeliveryChannelType::InApp, $owner->deliveryChannels()->sole()->type);
    }

    public function test_keeps_an_existing_in_app_channel(): void
    {
        $owner = User::factory()->create();
        $channel = DeliveryChannel::factory()->inApp()->create(['user_id' => $owner->id]);

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        $this->assertSame($channel->id, $owner->deliveryChannels()->sole()->id);
    }

    public function test_running_twice_does_not_duplicate_adopted_rows(): void
    {
        $owner = $this->mvpData();
        $this->fakeDiscord();

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);
        $tables = ['digest_settings', 'interest_user', 'source_subscriptions', 'discord_guilds', 'delivery_channels', 'learning_topic_usages', 'deliveries'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
            $this->assertGreaterThan(0, $before[$table]);
        }
        $channelIds = $owner->deliveryChannels()->pluck('id')->all();
        $deliveryIds = Delivery::query()->pluck('id')->all();

        $this->artisan('pulse:adopt', ['email' => $owner->email])->assertExitCode(0);

        foreach ($before as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($channelIds, $owner->deliveryChannels()->pluck('id')->all());
        $this->assertSame($deliveryIds, Delivery::query()->pluck('id')->all());
    }

    #[DataProvider('discordFailures')]
    public function test_discord_errors_fail_and_roll_back_every_write(string $path): void
    {
        $owner = $this->mvpData();
        config(['services.discord.news_forum' => '111', 'services.discord.learning_forum' => '222']);
        Http::fake(['https://discord.com/api/v10/'.$path => Http::response(['message' => 'Missing Access'], 403)] + [
            'https://discord.com/api/v10/channels/*' => Http::response(['guild_id' => '456']),
            'https://discord.com/api/v10/guilds/456' => Http::response(['id' => '456', 'name' => 'Test guild']),
        ]);
        $before = $this->databaseSnapshot();

        $this->artisan('pulse:adopt', ['email' => $owner->email])
            ->expectsOutputToContain('Missing Access')
            ->assertExitCode(1);

        $this->assertSame($before, $this->databaseSnapshot());
    }

    /** @return array<string, array{string}> */
    public static function discordFailures(): array
    {
        return ['channel lookup' => ['channels/222'], 'guild lookup' => ['guilds/456']];
    }

    public function test_dry_run_prints_assignment_summary_and_rolls_back_every_write(): void
    {
        $owner = $this->mvpData();
        Post::factory()->count(2)->create(['user_id' => null]);
        $this->fakeDiscord();
        $before = $this->databaseSnapshot();

        $this->artisan('pulse:adopt', ['email' => $owner->email, '--dry-run' => true])
            ->expectsOutputToContain('Assigned 3 posts')
            ->assertExitCode(0);

        $this->assertSame($before, $this->databaseSnapshot());
    }

    private function mvpData(): User
    {
        $owner = User::factory()->create(['is_admin' => false, 'uses_instance_ai' => false]);
        DigestSettings::factory()->create(['user_id' => $owner->id, 'news_enabled' => false, 'learning_enabled' => false]);
        $source = Source::factory()->create(['is_default' => true]);
        Interest::factory()->create()->sources()->attach($source);
        Source::factory()->create(['slug' => 'newsletters', 'type' => SourceType::ResendInbox, 'is_default' => true]);
        Post::factory()->create(['user_id' => null, 'status' => PostStatus::Posted, 'discord_thread_id' => 'old-thread', 'messages_sent' => 2, 'attempts' => 1, 'posted_at' => now()->subDay()]);
        LearningTopic::factory()->create(['last_used_on' => '2026-10-24']);

        return $owner;
    }

    private function fakeDiscord(): void
    {
        config(['services.discord.news_forum' => '111', 'services.discord.learning_forum' => '222']);
        Http::fake([
            'https://discord.com/api/v10/channels/111' => Http::response(['id' => '111', 'guild_id' => '456']),
            'https://discord.com/api/v10/channels/222' => Http::response(['id' => '222', 'guild_id' => '456']),
            'https://discord.com/api/v10/guilds/456' => Http::response(['id' => '456', 'name' => 'Test guild']),
        ]);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function databaseSnapshot(): array
    {
        $snapshot = [];
        foreach (['users', 'digest_settings', 'sources', 'source_subscriptions', 'posts', 'learning_topics', 'learning_topic_usages', 'discord_guilds', 'delivery_channels', 'deliveries'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all();
        }
        $snapshot['interest_user'] = DB::table('interest_user')->orderBy('user_id')->orderBy('interest_id')->get()->map(fn (object $row) => (array) $row)->all();

        return $snapshot;
    }
}
