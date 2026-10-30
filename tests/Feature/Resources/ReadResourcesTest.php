<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Enums\DeliveryChannelType;
use App\Enums\DeliveryStatus;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Http\Resources\AiKeyResource;
use App\Http\Resources\DayResource;
use App\Http\Resources\DeliveryChannelResource;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\DigestSettingsResource;
use App\Http\Resources\DiscordGuildResource;
use App\Http\Resources\InterestResource;
use App\Http\Resources\LearningPostResource;
use App\Http\Resources\PostItemResource;
use App\Http\Resources\SourceResource;
use App\Http\Resources\TagResource;
use App\Models\AiKey;
use App\Models\Delivery;
use App\Models\DeliveryChannel;
use App\Models\DigestSettings;
use App\Models\DiscordGuild;
use App\Models\Interest;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Reading\DayQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadResourcesTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/123456789/secret-token-ABCD';

    public function test_tag_has_only_public_fields(): void
    {
        $tag = Tag::factory()->create();
        $data = $this->resolved(new TagResource($tag));

        $this->assertKeys(['id', 'slug', 'name'], $data);
        $this->assertSame(['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name], $data);
    }

    #[DataProvider('selectedStates')]
    public function test_interest_selected_is_included_only_when_the_attribute_exists(?bool $selected): void
    {
        $interest = Interest::factory()->create(['has_learning' => true]);
        if ($selected !== null) {
            $interest->setAttribute('selected', $selected);
        }
        $data = $this->resolved(new InterestResource($interest));
        $keys = ['id', 'slug', 'name', 'category', 'icon', 'position', 'has_learning', 'status'];
        if ($selected !== null) {
            $keys[] = 'selected';
            $this->assertSame($selected, $data['selected']);
        }
        $this->assertKeys($keys, $data);
        $this->assertSame($interest->category->value, $data['category']);
        $this->assertSame($interest->status->value, $data['status']);
        $this->assertSame($interest->position, $data['position']);
        $this->assertTrue($data['has_learning']);
    }

    public static function selectedStates(): array
    {
        return ['absent' => [null], 'selected' => [true], 'unselected' => [false]];
    }

    #[DataProvider('sourceStates')]
    public function test_source_health_and_conditional_interests(?int $hoursAgo, ?string $error, string $health, bool $loaded, bool $custom): void
    {
        $this->freezeSecond();
        $source = Source::factory()->create([
            'user_id' => $custom ? User::factory()->create()->id : null,
            'credentials' => ['api_key' => 'resource-secret'],
            'last_fetched_at' => now(),
            'last_success_at' => $hoursAgo === null ? null : now()->subHours($hoursAgo),
            'last_error' => $error,
            'site_url' => 'https://example.test',
            'is_default' => true,
        ]);
        $interest = Interest::factory()->create();
        $source->interests()->attach($interest);
        if ($loaded) {
            $source->load('interests');
        }
        $data = $this->resolved(new SourceResource($source));
        $keys = ['id', 'slug', 'name', 'type', 'url', 'site_url', 'is_custom', 'is_default', 'enabled', 'last_fetched_at', 'last_success_at', 'last_error', 'health', 'has_key'];
        if ($loaded) {
            $keys[] = 'interests';
            $this->assertCount(1, $data['interests']);
            $this->assertKeys(['id', 'slug', 'name', 'category', 'icon', 'position', 'has_learning', 'status'], $data['interests'][0]);
            $this->assertSame($interest->id, $data['interests'][0]['id']);
        }
        $this->assertKeys($keys, $data);
        $this->assertSame($health, $data['health']);
        $this->assertSame($custom, $data['is_custom']);
        $this->assertSame($source->type->value, $data['type']);
        $this->assertSame($source->url, $data['url']);
        $this->assertSame($source->site_url, $data['site_url']);
        $this->assertTrue($data['is_default']);
        $this->assertTrue($data['enabled']);
        $this->assertSame($error, $data['last_error']);
        $this->assertTimestamp($source->last_fetched_at, $data['last_fetched_at']);
        if ($source->last_success_at === null) {
            $this->assertNull($data['last_success_at']);
        } else {
            $this->assertTimestamp($source->last_success_at, $data['last_success_at']);
        }
    }

    public static function sourceStates(): array
    {
        return [
            'recent' => [47, null, 'ok', true, false],
            'stale' => [49, null, 'unknown', false, true],
            'never fetched' => [null, null, 'unknown', false, false],
            'recent failure' => [1, 'HTTP 500', 'failing', true, true],
            'never succeeded failure' => [null, 'HTTP 500', 'failing', false, true],
        ];
    }

    #[DataProvider('sourceVisibility')]
    public function test_post_item_source_uses_the_lowest_visible_id_without_foreign_custom_names(string $mode): void
    {
        $post = Post::factory()->create(['date' => '2026-10-29']);
        $item = PostItem::factory()->create(['post_id' => $post->id, 'summary' => null]);
        $foreign = Source::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign private source']);
        $this->actingAs($foreign->user);
        $catalog = Source::factory()->create(['name' => 'Catalog source']);
        $custom = Source::factory()->create(['user_id' => $post->user_id, 'name' => 'Owned source']);
        $ids = match ($mode) {
            'mixed' => [$custom->id, $catalog->id, $foreign->id],
            'custom' => [$custom->id, $foreign->id],
            'foreign only' => [$foreign->id],
            default => [],
        };
        $item->item->sources()->attach($ids, ['first_seen_at' => now()]);
        $tag = Tag::factory()->create();
        $item->tags()->attach($tag);
        $loaded = $item->fresh(['post', 'item.sources', 'tags']);
        $loaded->item->setRelation('sources', $loaded->item->sources->sortByDesc('id')->values());
        $data = $this->resolved(new PostItemResource($loaded));

        $this->assertKeys(['id', 'title', 'summary', 'position', 'url', 'date', 'source', 'tags'], $data);
        $this->assertSame($item->id, $data['id']);
        $this->assertSame($item->title, $data['title']);
        $this->assertNull($data['summary']);
        $this->assertSame($item->position, $data['position']);
        $this->assertSame($item->item->url, $data['url']);
        $this->assertSame('2026-10-29', $data['date']);
        $this->assertSame(match ($mode) {
            'mixed' => $catalog->name, 'custom' => $custom->name, default => null
        }, $data['source']);
        $this->assertSame([['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name]], $data['tags']);
        $this->assertStringNotContainsString($foreign->name, json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function sourceVisibility(): array
    {
        return ['mixed' => ['mixed'], 'owned custom' => ['custom'], 'foreign only' => ['foreign only'], 'none' => ['none']];
    }

    public function test_post_item_date_is_omitted_without_the_post_relation(): void
    {
        $item = PostItem::factory()->create();
        $data = $this->resolved(new PostItemResource($item->fresh(['item.sources', 'tags'])));

        $this->assertKeys(['id', 'title', 'summary', 'position', 'url', 'source', 'tags'], $data);
    }

    #[DataProvider('learningResourceStates')]
    public function test_learning_post_preserves_markdown_but_renders_safe_html(bool $hasTopic, bool $loadedPost, bool $hasLinks): void
    {
        $post = Post::factory()->create(['kind' => PostKind::Learning, 'date' => '2026-10-29']);
        $body = "**Safe** <script>alert('x')</script> <img src=x onerror=alert(1)> [bad](javascript:alert%281%29) [good](https://example.test)";
        $links = $hasLinks ? [['title' => 'Docs', 'url' => 'https://example.test/docs']] : [];
        $entry = LearningPost::query()->create(['post_id' => $post->id, 'kind' => LearningKind::Topic, 'learning_topic_id' => $hasTopic ? LearningTopic::factory()->create(['category' => 'testing'])->id : null, 'title' => 'Safe learning', 'body' => $body, 'links' => $links]);
        $tag = Tag::factory()->create();
        $entry->tags()->attach($tag);
        $entry = $entry->fresh($loadedPost ? ['post', 'topic', 'tags'] : ['topic', 'tags']);
        $data = $this->resolved(new LearningPostResource($entry));
        $keys = ['id', 'kind', 'title', 'body', 'body_html', 'category', 'links', 'tags'];
        if ($loadedPost) {
            $keys[] = 'date';
            $this->assertSame('2026-10-29', $data['date']);
        }
        $this->assertKeys($keys, $data);
        $this->assertSame($entry->id, $data['id']);
        $this->assertSame('topic', $data['kind']);
        $this->assertSame($entry->title, $data['title']);
        $this->assertSame($body, $data['body']);
        $this->assertSame(Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]), $data['body_html']);
        $this->assertStringNotContainsString('<script', $data['body_html']);
        $this->assertStringNotContainsString('<img', $data['body_html']);
        $this->assertStringNotContainsString('href="javascript:', $data['body_html']);
        $this->assertStringContainsString('<strong>Safe</strong>', $data['body_html']);
        $this->assertStringContainsString('href="https://example.test"', $data['body_html']);
        $this->assertSame($hasTopic ? 'testing' : null, $data['category']);
        $this->assertSame($links, $data['links']);
        $this->assertSame([['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name]], $data['tags']);
    }

    public static function learningResourceStates(): array
    {
        return ['full' => [true, true, true], 'no topic or links' => [false, true, false], 'post not loaded' => [false, false, false]];
    }

    #[DataProvider('channelTargets')]
    public function test_delivery_channel_projects_only_the_target_fields(DeliveryChannelType $type, array $settings, array $expected): void
    {
        $channel = DeliveryChannel::factory()->create(['type' => $type, 'settings' => $settings + ['api_key' => 'resource-secret'], 'verified_at' => '2026-10-29 12:00:00']);
        $data = $this->resolved(new DeliveryChannelResource($channel));

        $this->assertKeys(['id', 'type', 'name', 'kinds', 'enabled', 'verified_at', 'target'], $data);
        $this->assertSame($channel->id, $data['id']);
        $this->assertSame($type->value, $data['type']);
        $this->assertSame($channel->name, $data['name']);
        $this->assertSame($channel->kinds, $data['kinds']);
        $this->assertSame($channel->enabled, $data['enabled']);
        $this->assertTimestamp($channel->verified_at, $data['verified_at']);
        $this->assertSame($expected, $data['target']);
    }

    public static function channelTargets(): array
    {
        return [
            'bot' => [DeliveryChannelType::DiscordBot, ['guild_id' => '123', 'channel_id' => '456', 'token' => 'resource-secret'], ['guild_id' => '123', 'channel_id' => '456']],
            'email' => [DeliveryChannelType::Email, ['address' => 'reader@example.test'], ['address' => 'reader@example.test']],
            'webhook' => [DeliveryChannelType::DiscordWebhook, ['webhook_url' => self::WEBHOOK], ['webhook' => '…ABCD']],
            'in app' => [DeliveryChannelType::InApp, [], []],
        ];
    }

    public function test_delivery_has_only_public_status_fields_and_iso_timestamps(): void
    {
        $this->freezeSecond();
        $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Failed, 'attempts' => 3, 'last_error' => 'HTTP 500', 'delivered_at' => now(), 'external_id' => 'private-external-id', 'progress' => 4]);
        $data = $this->resolved(new DeliveryResource($delivery));

        $this->assertKeys(['id', 'status', 'attempts', 'last_error', 'delivered_at', 'updated_at'], $data);
        $this->assertSame($delivery->id, $data['id']);
        $this->assertSame('failed', $data['status']);
        $this->assertSame(3, $data['attempts']);
        $this->assertSame('HTTP 500', $data['last_error']);
        $this->assertTimestamp($delivery->delivered_at, $data['delivered_at']);
        $this->assertTimestamp($delivery->updated_at, $data['updated_at']);
        $empty = $this->resolved(new DeliveryResource(Delivery::factory()->create()));
        $this->assertNull($empty['delivered_at']);
        $this->assertNull($empty['last_error']);
    }

    public function test_latest_delivery_relation_uses_the_latest_record_and_a_resource_allowlist(): void
    {
        $channel = DeliveryChannel::factory()->create();
        Delivery::factory()->create(['delivery_channel_id' => $channel->id]);
        $latest = Delivery::factory()->create(['delivery_channel_id' => $channel->id, 'status' => DeliveryStatus::Failed]);
        Delivery::factory()->create();
        $data = $this->resolved(new DeliveryChannelResource($channel->fresh(['latestDelivery'])));

        $this->assertKeys(['id', 'type', 'name', 'kinds', 'enabled', 'verified_at', 'target', 'latest_delivery'], $data);
        $this->assertKeys(['id', 'status', 'attempts', 'last_error', 'delivered_at', 'updated_at'], $data['latest_delivery']);
        $this->assertSame($latest->id, $data['latest_delivery']['id']);
        $this->assertSame('failed', $data['latest_delivery']['status']);
        $empty = DeliveryChannel::factory()->create()->load('latestDelivery');
        $this->assertNull($this->resolved(new DeliveryChannelResource($empty))['latest_delivery']);
    }

    public function test_latest_delivery_attribute_is_serialized_when_present(): void
    {
        $channel = DeliveryChannel::factory()->create();
        $delivery = Delivery::factory()->create(['delivery_channel_id' => $channel->id]);
        $channel->setAttribute('latestDelivery', $delivery);
        $data = $this->resolved(new DeliveryChannelResource($channel));

        $this->assertKeys(['id', 'type', 'name', 'kinds', 'enabled', 'verified_at', 'target', 'latest_delivery'], $data);
        $this->assertSame($delivery->id, $data['latest_delivery']['id']);
    }

    public function test_discord_guild_excludes_ownership_and_extra_attributes(): void
    {
        $guild = DiscordGuild::factory()->create();
        $data = $this->resolved(new DiscordGuildResource($guild));

        $this->assertKeys(['id', 'guild_id', 'name'], $data);
        $this->assertSame(['id' => $guild->id, 'guild_id' => $guild->guild_id, 'name' => $guild->name], $data);
    }

    public function test_ai_key_exposes_only_suffix_and_public_metadata(): void
    {
        $this->freezeSecond();
        $key = AiKey::factory()->create(['api_key' => 'resource-secret-ABCD']);
        $data = $this->resolved(new AiKeyResource($key));

        $this->assertKeys(['id', 'provider', 'model', 'key_suffix', 'position', 'consented_at', 'created_at'], $data);
        $this->assertSame($key->id, $data['id']);
        $this->assertSame($key->provider->value, $data['provider']);
        $this->assertSame($key->model, $data['model']);
        $this->assertSame('ABCD', $data['key_suffix']);
        $this->assertSame($key->position, $data['position']);
        $this->assertTimestamp($key->consented_at, $data['consented_at']);
        $this->assertTimestamp($key->created_at, $data['created_at']);
    }

    #[DataProvider('learningAvailability')]
    public function test_digest_settings_reports_the_owners_learning_availability(bool $available): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->interests()->attach(Interest::factory()->create(['has_learning' => $available]));
        User::factory()->create()->interests()->attach(Interest::factory()->create(['has_learning' => ! $available]));
        $settings = DigestSettings::factory()->create(['user_id' => $user->id, 'profile' => 'Fictional reader', 'timezone' => 'Europe/Paris', 'news_enabled' => true, 'learning_enabled' => false, 'max_items' => 7, 'onboarded_at' => '2026-10-29 12:00:00']);
        $data = $this->resolved(new DigestSettingsResource($settings));

        $this->assertKeys(['timezone', 'profile', 'news_enabled', 'news_time', 'learning_enabled', 'learning_time', 'max_items', 'onboarded_at', 'learning_available'], $data);
        $this->assertSame('Europe/Paris', $data['timezone']);
        $this->assertSame('Fictional reader', $data['profile']);
        $this->assertTrue($data['news_enabled']);
        $this->assertFalse($data['learning_enabled']);
        $this->assertSame(substr($settings->news_time, 0, 5), substr($data['news_time'], 0, 5));
        $this->assertSame(substr($settings->learning_time, 0, 5), substr($data['learning_time'], 0, 5));
        $this->assertSame(7, $data['max_items']);
        $this->assertSame($available, $data['learning_available']);
        $this->assertTimestamp($settings->onboarded_at, $data['onboarded_at']);
    }

    public static function learningAvailability(): array
    {
        return ['available' => [true], 'unavailable' => [false]];
    }

    public function test_day_resource_serializes_nested_items_learning_and_statuses_without_secrets(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $news = Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-29', 'status' => PostStatus::Generated]);
        $item = PostItem::factory()->create(['post_id' => $news->id]);
        $foreign = Source::factory()->create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign private source', 'credentials' => ['api_key' => 'resource-secret']]);
        $item->item->sources()->attach($foreign, ['first_seen_at' => now()]);
        $learning = Post::factory()->create(['user_id' => $user->id, 'date' => '2026-10-29', 'kind' => PostKind::Learning, 'status' => PostStatus::Generated]);
        $tip = LearningPost::query()->create(['post_id' => $learning->id, 'kind' => LearningKind::Tip, 'title' => 'Tip', 'body' => '**Tip**', 'links' => []]);
        $data = $this->resolved(new DayResource(app(DayQuery::class)->for($user, '2026-10-29')));

        $this->assertKeys(['date', 'previous_date', 'next_date', 'news', 'learning'], $data);
        $this->assertSame('2026-10-29', $data['date']);
        $this->assertNull($data['previous_date']);
        $this->assertNull($data['next_date']);
        $this->assertKeys(['status', 'item_count', 'sections'], $data['news']);
        $this->assertSame('generated', $data['news']['status']);
        $this->assertSame(1, $data['news']['item_count']);
        $this->assertCount(1, $data['news']['sections']);
        $section = $data['news']['sections'][0];
        $this->assertKeys(['slug', 'name', 'items'], $section);
        $this->assertSame('general', $section['slug']);
        $this->assertSame(Tag::displayName('general'), $section['name']);
        $this->assertCount(1, $section['items']);
        $this->assertSame($item->id, $section['items'][0]['id']);
        $this->assertNull($section['items'][0]['source']);
        $itemKeys = ['id', 'title', 'summary', 'position', 'url', 'source', 'tags'];
        if (array_key_exists('date', $section['items'][0])) {
            $itemKeys[] = 'date';
            $this->assertSame('2026-10-29', $section['items'][0]['date']);
        }
        $this->assertKeys($itemKeys, $section['items'][0]);
        $this->assertKeys(['status', 'tip', 'topic'], $data['learning']);
        $this->assertSame('generated', $data['learning']['status']);
        $this->assertSame($tip->id, $data['learning']['tip']['id']);
        $this->assertNull($data['learning']['topic']);
        $learningKeys = ['id', 'kind', 'title', 'body', 'body_html', 'category', 'links', 'tags'];
        if (array_key_exists('date', $data['learning']['tip'])) {
            $learningKeys[] = 'date';
            $this->assertSame('2026-10-29', $data['learning']['tip']['date']);
        }
        $this->assertKeys($learningKeys, $data['learning']['tip']);
        $this->assertStringNotContainsString($foreign->name, json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function test_day_resource_keeps_null_posts_and_quiet_sections(): void
    {
        $news = Post::factory()->create(['date' => '2026-10-29', 'status' => PostStatus::Generated]);
        $quiet = $this->resolved(new DayResource(app(DayQuery::class)->for($news->user, '2026-10-29')));
        $this->assertKeys(['status', 'item_count', 'sections'], $quiet['news']);
        $this->assertSame('generated', $quiet['news']['status']);
        $this->assertSame(0, $quiet['news']['item_count']);
        $this->assertSame([], $quiet['news']['sections']);
        $this->assertNull($quiet['learning']);
        $post = Post::factory()->create(['date' => '2026-10-29', 'kind' => PostKind::Learning, 'status' => PostStatus::Pending]);
        $pending = $this->resolved(new DayResource(app(DayQuery::class)->for($post->user, '2026-10-29')));
        $this->assertNull($pending['news']);
        $this->assertKeys(['status', 'tip', 'topic'], $pending['learning']);
        $this->assertSame('pending', $pending['learning']['status']);
        $this->assertNull($pending['learning']['tip']);
        $this->assertNull($pending['learning']['topic']);
    }

    private function resolved(JsonResource $resource): array
    {
        $data = json_decode(json_encode($resource->resolve(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $this->assertNoSecrets($data);

        return $data;
    }

    private function assertKeys(array $expected, array $data): void
    {
        $actual = array_keys($data);
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    private function assertNoSecrets(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->assertNotContains($key, ['credentials', 'settings', 'api_key', 'webhook_url', 'user_id', 'is_admin']);
            if (is_array($value)) {
                $this->assertNoSecrets($value);
            }
        }
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->assertStringNotContainsString('resource-secret', $json);
        $this->assertStringNotContainsString(self::WEBHOOK, $json);
        $this->assertStringNotContainsString('secret-token', $json);
    }

    private function assertTimestamp(CarbonImmutable $expected, mixed $actual): void
    {
        $this->assertIsString($actual);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $actual);
        $this->assertTrue($expected->equalTo(CarbonImmutable::parse($actual)));
    }
}
