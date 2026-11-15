<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\TestSource;
use App\Digest\Sources\Imap\ImapMessage;
use App\Digest\Sources\Imap\Mailbox;
use App\Digest\Sources\Imap\SignInFailedException;
use App\Digest\Support\PublicUrl;
use App\Enums\InterestStatus;
use App\Enums\SourceType;
use App\Http\Resources\SourceResource;
use App\Models\Interest;
use App\Models\Item;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SourcesActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['93.184.216.34']));
    }

    public function test_toggle_changes_only_the_users_subscription_and_redirects_back(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->create();
        $own = SourceSubscription::factory()->for($user)->for($source)->create();
        $other = SourceSubscription::factory()->for($source)->create();
        $sourceBefore = $source->fresh()->getAttributes();
        $otherBefore = $other->fresh()->getAttributes();
        $this->assertSame(url("/sources/subscriptions/{$source->id}"), route('sources.subscription', $source));
        foreach ([false, true] as $enabled) {
            $this->actingAs($user)->from('/sources')->put("/sources/subscriptions/{$source->id}", ['enabled' => $enabled])
                ->assertRedirect('/sources')->assertSessionHasNoErrors();
            $this->assertSame($enabled, $own->fresh()->enabled);
            $this->assertSame($otherBefore, $other->fresh()->getAttributes());
            $this->assertSame($sourceBefore, $source->fresh()->getAttributes());
        }
    }

    public function test_toggle_refuses_a_retired_source(): void
    {
        $subscription = SourceSubscription::factory()->for(User::factory()->onboarded())->create(['enabled' => false]);
        $subscription->source->update(['enabled' => false, 'retired_at' => now()]);

        $this->actingAs($subscription->user)
            ->put("/sources/subscriptions/{$subscription->source_id}", ['enabled' => true])
            ->assertForbidden();

        $this->assertFalse($subscription->fresh()->enabled);
    }

    public function test_toggle_without_own_subscription_is_404_and_never_creates_one(): void
    {
        $user = User::factory()->onboarded()->create();
        $other = SourceSubscription::factory()->create();
        $before = $other->fresh()->getAttributes();
        $this->assertSame(url("/sources/subscriptions/{$other->source_id}"), route('sources.subscription', $other->source_id));
        $this->actingAs($user)->put("/sources/subscriptions/{$other->source_id}", ['enabled' => false])->assertNotFound();
        $this->put('/sources/subscriptions/999999', ['enabled' => true])->assertNotFound();
        $this->assertSame($before, $other->fresh()->getAttributes());
        $this->assertSame(0, $user->sourceSubscriptions()->count());
    }

    #[DataProvider('invalidEnabled')]
    public function test_toggle_requires_a_boolean(array $payload): void
    {
        $subscription = SourceSubscription::factory()->for(User::factory()->onboarded())->create();
        $this->actingAs($subscription->user)->from('/sources')->put("/sources/subscriptions/{$subscription->source_id}", $payload)
            ->assertRedirect('/sources')->assertSessionHasErrors('enabled');
        $this->assertTrue($subscription->fresh()->enabled);
    }

    public static function invalidEnabled(): array
    {
        return [[[]], [['enabled' => null]], [['enabled' => 'wrong']], [['enabled' => []]]];
    }

    public function test_interests_syncs_only_the_user_and_reconciles_catalog_subscriptions(): void
    {
        $user = User::factory()->onboarded()->create();
        $old = Interest::factory()->create(['status' => InterestStatus::Live]);
        $new = Interest::factory()->create(['status' => InterestStatus::Live]);
        $user->interests()->attach($old);
        $oldSource = Source::factory()->create(['is_default' => true]);
        $oldSource->interests()->attach($old);
        $newSource = Source::factory()->create(['is_default' => true]);
        $newSource->interests()->attach($new);
        SourceSubscription::factory()->for($user)->for($oldSource)->create();
        $custom = SourceSubscription::factory()->for($user)->for(Source::factory()->for($user))->create();
        $other = SourceSubscription::factory()->for($oldSource)->create();
        $otherBefore = $other->fresh()->getAttributes();
        $this->assertSame(url('/sources/interests'), route('sources.interests'));
        $this->actingAs($user)->from('/sources')->put('/sources/interests', ['interests' => [$new->slug]])
            ->assertRedirect('/sources')->assertSessionHasNoErrors();
        $this->assertSame([$new->id], $user->interests()->pluck('interests.id')->all());
        $this->assertDatabaseHas('source_subscriptions', ['user_id' => $user->id, 'source_id' => $newSource->id, 'enabled' => true]);
        $this->assertDatabaseMissing('source_subscriptions', ['user_id' => $user->id, 'source_id' => $oldSource->id]);
        $this->assertTrue($custom->fresh()->enabled);
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
    }

    #[DataProvider('invalidInterests')]
    public function test_interests_rejects_invalid_selection_without_changes(array $payload, string $field): void
    {
        $user = User::factory()->onboarded()->create();
        $selected = Interest::factory()->create(['status' => InterestStatus::Live]);
        Interest::factory()->create(['slug' => 'draft', 'status' => InterestStatus::Draft]);
        $user->interests()->attach($selected);
        $this->actingAs($user)->from('/sources')->put('/sources/interests', $payload)
            ->assertRedirect('/sources')->assertSessionHasErrors($field);
        $this->assertSame([$selected->id], $user->interests()->pluck('interests.id')->all());
        $this->assertSame(0, $user->sourceSubscriptions()->count());
    }

    public static function invalidInterests(): array
    {
        return [[[], 'interests'], [['interests' => []], 'interests'], [['interests' => 'php'], 'interests'],
            [['interests' => ['missing']], 'interests.0'], [['interests' => ['draft']], 'interests.0'], [['interests' => [123]], 'interests.0']];
    }

    #[DataProvider('sourceTypes')]
    public function test_store_creates_owned_source_interests_unique_slug_and_enabled_subscription(string $type): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $payload = $this->payload($interest, $type);
        $this->assertSame(url('/sources'), route('sources.store'));
        foreach (range(1, 2) as $attempt) {
            $this->actingAs($user)->from('/sources')->post('/sources', [...$payload, 'user_id' => 999, 'is_default' => true, 'enabled' => false])
                ->assertRedirect('/sources')->assertSessionHasNoErrors();
        }
        $sources = Source::query()->where('user_id', $user->id)->orderBy('id')->get();
        $this->assertCount(2, $sources);
        $this->assertNotSame($sources[0]->slug, $sources[1]->slug);
        foreach ($sources as $source) {
            $this->assertMatchesRegularExpression('/^custom-'.$user->id.'-my-source-[a-zA-Z0-9]{4}$/', $source->slug);
            $this->assertSame($type, $source->type->value);
            $this->assertSame('My source', $source->name);
            $this->assertFalse($source->is_default);
            $this->assertTrue($source->enabled);
            $this->assertSame($type === 'feed' ? $payload['url'] : null, $source->url);
            if ($type === 'resend_inbox') {
                $this->assertSame(['api_key' => 'fake-resend-key', 'address' => null], $source->credentials);
                $this->assertStringNotContainsString('fake-resend-key', $source->getRawOriginal('credentials'));
            }
            $this->assertSame([$interest->id], $source->interests()->pluck('interests.id')->all());
            $this->assertDatabaseHas('source_subscriptions', ['user_id' => $user->id, 'source_id' => $source->id, 'enabled' => true]);
        }
        Http::assertNothingSent();
    }

    public static function sourceTypes(): array
    {
        return [['feed'], ['resend_inbox']];
    }

    #[DataProvider('invalidSources')]
    public function test_store_validation_persists_nothing(string $type, string $field, mixed $value, string $error): void
    {
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $this->actingAs(User::factory()->onboarded()->create())->from('/sources')
            ->post('/sources', [...$this->payload($interest, $type), $field => $value])
            ->assertRedirect('/sources')->assertSessionHasErrors($error);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('source_subscriptions', 0);
        Http::assertNothingSent();
    }

    public static function invalidSources(): array
    {
        return [
            ['feed', 'type', null, 'type'], ['feed', 'type', 'github_trending', 'type'], ['feed', 'type', [], 'type'],
            ['feed', 'name', '', 'name'], ['feed', 'name', [], 'name'], ['feed', 'name', str_repeat('n', 101), 'name'],
            ['feed', 'url', null, 'url'], ['feed', 'url', [], 'url'], ['feed', 'url', 'not-url', 'url'],
            ['feed', 'url', 'ftp://example.test/feed', 'url'], ['feed', 'url', 'https://example.test/'.str_repeat('a', 2000), 'url'],
            ['feed', 'url', 'http://127.0.0.1/feed', 'url'], ['feed', 'url', 'http://10.0.0.1/feed', 'url'],
            ['feed', 'url', 'http://169.254.169.254/latest/meta-data', 'url'], ['feed', 'url', 'http://[::1]/feed', 'url'],
            ['resend_inbox', 'api_key', null, 'api_key'], ['resend_inbox', 'api_key', '', 'api_key'],
            ['resend_inbox', 'api_key', [], 'api_key'], ['resend_inbox', 'api_key', str_repeat('k', 201), 'api_key'],
            ['feed', 'interests', [], 'interests'], ['feed', 'interests', 'php', 'interests'],
            ['feed', 'interests', ['missing'], 'interests.0'], ['feed', 'interests', [123], 'interests.0'],
        ];
    }

    public function test_private_dns_is_rejected_without_http(): void
    {
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['10.0.0.5']));
        $interest = Interest::factory()->create();
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources', $this->payload($interest))
            ->assertUnprocessable()->assertJsonValidationErrors('url');
        $this->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])
            ->assertUnprocessable()->assertJsonValidationErrors('url');
        $this->assertDatabaseCount('sources', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('sourceTypes')]
    public function test_thirtieth_source_is_allowed_but_thirty_first_is_rejected_per_user(string $type): void
    {
        $user = User::factory()->onboarded()->create();
        Source::factory()->for($user)->count(29)->create();
        Source::factory()->for(User::factory())->count(30)->create();
        Source::factory()->count(2)->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $payload = $this->payload($interest, $type);
        $this->actingAs($user)->from('/sources')->post('/sources', $payload)->assertRedirect('/sources')->assertSessionHasNoErrors();
        $this->post('/sources', $payload)->assertRedirect('/sources')->assertSessionHasErrors($type === 'feed' ? 'url' : 'name');
        $this->assertSame(30, Source::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, $user->sourceSubscriptions()->count());
    }

    public function test_update_own_feed_changes_fields_and_interests_without_changing_subscription_or_slug(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create();
        $old = Interest::factory()->create();
        $new = Interest::factory()->create(['status' => InterestStatus::Live]);
        $source->interests()->attach($old);
        $subscription = SourceSubscription::factory()->for($user)->for($source)->create(['enabled' => false]);
        $before = $subscription->fresh()->getAttributes();
        $this->assertSame(url("/sources/{$source->id}"), route('sources.update', $source));
        $this->actingAs($user)->from('/sources')->put("/sources/{$source->id}", $this->payload($new))
            ->assertRedirect('/sources')->assertSessionHasNoErrors();
        $saved = $source->fresh();
        $this->assertSame('My source', $saved->name);
        $this->assertSame('https://feeds.test/feed', $saved->url);
        $this->assertSame($source->slug, $saved->slug);
        $this->assertSame($user->id, $saved->user_id);
        $this->assertSame([$new->id], $saved->interests()->pluck('interests.id')->all());
        $this->assertSame($before, $subscription->fresh()->getAttributes());
    }

    public function test_update_forbids_catalog_and_other_users_custom_sources_without_mutation(): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        foreach ([Source::factory()->create(), Source::factory()->for(User::factory())->create()] as $source) {
            $before = $source->fresh()->getAttributes();
            $this->actingAs($user)->put("/sources/{$source->id}", $this->payload($interest))->assertForbidden();
            $this->assertSame($before, $source->fresh()->getAttributes());
            $this->assertSame(0, $source->interests()->count());
        }
    }

    public function test_destroy_deletes_the_users_custom_source_and_its_subscription(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create();
        $user->sourceSubscriptions()->create(['source_id' => $source->id, 'enabled' => true]);
        $item = Item::factory()->create();
        $item->sources()->attach($source, ['first_seen_at' => now()]);

        $this->actingAs($user)->from('/sources?tab=mine')->delete("/sources/{$source->id}")
            ->assertRedirect('/sources?tab=mine');

        $this->assertModelMissing($source);
        $this->assertSame(0, $user->sourceSubscriptions()->count());
        $this->assertNotNull($item->fresh());
    }

    public function test_destroy_forbids_catalog_and_other_users_sources(): void
    {
        $user = User::factory()->onboarded()->create();

        foreach ([Source::factory()->create(), Source::factory()->for(User::factory())->create()] as $source) {
            $this->actingAs($user)->delete("/sources/{$source->id}")->assertForbidden();
            $this->assertNotNull($source->fresh());
        }
    }

    public function test_newsletter_update_keeps_empty_or_omitted_key_and_replaces_it_only_when_given(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create(['type' => SourceType::ResendInbox, 'url' => null, 'credentials' => ['api_key' => 'fake-old-key']]);
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $payload = $this->payload($interest, 'resend_inbox');
        unset($payload['api_key']);
        foreach ([$payload, [...$payload, 'api_key' => ''], [...$payload, 'api_key' => 'fake-new-key']] as $index => $input) {
            $this->actingAs($user)->from('/sources')->put("/sources/{$source->id}", $input)
                ->assertRedirect('/sources')->assertSessionHasNoErrors();
            $this->assertSame($index === 2 ? 'fake-new-key' : 'fake-old-key', $source->fresh()->credentials['api_key']);
        }
    }

    public function test_update_cannot_change_type_even_with_a_valid_alternative_payload(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $this->actingAs($user)->from('/sources')->put("/sources/{$source->id}", $this->payload($interest, 'resend_inbox'))->assertRedirect('/sources');
        $this->assertSame(SourceType::Feed, $source->fresh()->type);
    }

    public function test_update_rejects_invalid_fields_without_mutating_source_or_interests(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $source->interests()->attach($interest);
        $before = $source->fresh()->getAttributes();
        $this->actingAs($user)->from('/sources')->put("/sources/{$source->id}", [...$this->payload($interest), 'name' => '', 'url' => 'http://127.0.0.1/feed', 'interests' => []])
            ->assertRedirect('/sources')->assertSessionHasErrors(['name', 'url', 'interests']);
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertSame([$interest->id], $source->interests()->pluck('interests.id')->all());
    }

    public function test_test_endpoint_returns_five_recent_headlines_from_a_thirty_day_window_without_storage(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30)->startOfDay());
        $xml = '<rss version="2.0"><channel>';
        foreach (range(1, 7) as $day) {
            $xml .= '<item><title>Post '.$day.'</title><link>https://example.test/'.$day.'</link><pubDate>'.now()->subDays($day + 23)->toRfc2822String().'</pubDate></item>';
        }
        $xml .= '<item><title>Old post</title><link>https://example.test/old</link><pubDate>'.now()->subDays(31)->toRfc2822String().'</pubDate></item></channel></rss>';
        Http::fake(['https://feeds.test/feed' => Http::response($xml)]);
        $source = Source::factory()->create();
        $before = $source->fresh()->getAttributes();
        $this->assertSame(url('/sources/test'), route('sources.test'));
        $response = $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonCount(5, 'headlines');
        $this->assertSame(['Post 1', 'Post 2', 'Post 3', 'Post 4', 'Post 5'], $response->json('headlines'));
        $this->assertSame('Found 7 recent posts.', $response->json('message'));
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('source_subscriptions', 0);
        $this->assertDatabaseCount('items', 0);
        Http::assertSentCount(1);
    }

    public function test_test_endpoint_reports_no_recent_posts(): void
    {
        Http::fake(['*' => Http::response('<rss version="2.0"><channel><item><title>Old</title><link>https://example.test/old</link><pubDate>'.now()->subDays(31)->toRfc2822String().'</pubDate></item></channel></rss>')]);
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])
            ->assertOk()->assertExactJson(['ok' => true, 'message' => 'No recent posts found.', 'headlines' => []]);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('items', 0);
    }

    public function test_newsletter_test_uses_submitted_key_without_returning_logging_or_storing_it(): void
    {
        Log::spy();
        Http::fake([
            'https://api.resend.com/emails/receiving?*' => Http::response(['data' => [['id' => 'mail-1', 'created_at' => now()->subDays(20)->toIso8601String()]], 'has_more' => false]),
            'https://api.resend.com/emails/receiving/mail-1' => Http::response(['id' => 'mail-1', 'subject' => 'Newsletter issue', 'text' => 'Read this issue', 'created_at' => now()->subDays(20)->toIso8601String()]),
        ]);
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'resend_inbox', 'api_key' => 'fake-submitted-key'])
            ->assertOk()->assertExactJson(['ok' => true, 'message' => 'Found 1 recent posts.', 'headlines' => ['Newsletter issue']]);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer fake-submitted-key'));
        Log::shouldNotHaveReceived('warning');
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('source_subscriptions', 0);
        $this->assertDatabaseCount('items', 0);
    }

    public function test_failure_returns_friendly_json_and_logs_redacted_raw_error(): void
    {
        Log::spy();
        $key = 'fake-key/with-secret';
        $address = 'news+private@example.test';
        $raw = 'Upstream refused '.$key.' '.rawurlencode($key).' '.$address.' '.rawurlencode($address);
        Http::fake(fn () => throw new RuntimeException($raw));
        $response = $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'resend_inbox', 'api_key' => $key, 'address' => $address])
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('headlines', [])
            ->assertJsonPath('message', 'Could not read this source. Check its address or key and try again.');
        $this->assertStringNotContainsString('Upstream refused', $response->getContent());
        $this->assertStringNotContainsString($key, $response->getContent());
        $this->assertStringNotContainsString(rawurlencode($key), $response->getContent());
        $this->assertStringNotContainsString($address, $response->getContent());
        $this->assertStringNotContainsString(rawurlencode($address), $response->getContent());
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) use ($key, $address): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            return str_contains($serialized, 'Upstream refused')
                && ! str_contains($serialized, $key) && ! str_contains($serialized, rawurlencode($key))
                && ! str_contains($serialized, $address) && ! str_contains($serialized, rawurlencode($address));
        });
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('items', 0);
    }

    #[DataProvider('invalidTestInputs')]
    public function test_test_endpoint_validates_before_fetching(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        Http::assertNothingSent();
        $this->assertDatabaseCount('sources', 0);
    }

    public static function invalidTestInputs(): array
    {
        return [[[], 'type'], [['type' => 'github_trending'], 'type'], [['type' => []], 'type'], [['type' => 'feed'], 'url'],
            [['type' => 'feed', 'url' => []], 'url'], [['type' => 'feed', 'url' => 'not-url'], 'url'],
            [['type' => 'feed', 'url' => 'ftp://feeds.test/feed'], 'url'],
            [['type' => 'feed', 'url' => 'http://127.0.0.1/feed'], 'url'],
            [['type' => 'feed', 'url' => 'https://feeds.test/'.str_repeat('x', 2000)], 'url'],
            [['type' => 'resend_inbox'], 'api_key'], [['type' => 'resend_inbox', 'api_key' => []], 'api_key'],
            [['type' => 'resend_inbox', 'api_key' => ''], 'api_key'], [['type' => 'resend_inbox', 'api_key' => str_repeat('x', 201)], 'api_key']];
    }

    public function test_test_endpoint_blocks_redirects_to_private_hosts(): void
    {
        Http::fake(['https://feeds.test/feed' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('headlines', []);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('sources', 0);
    }

    public function test_seventh_test_request_is_throttled(): void
    {
        $this->freezeSecond();
        Http::fake(['*' => Http::response('<rss version="2.0"><channel></channel></rss>')]);
        $user = User::factory()->onboarded()->create();
        $this->actingAs($user);
        foreach (range(1, 6) as $attempt) {
            $this->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])->assertOk();
        }
        $this->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])->assertTooManyRequests();
        Http::assertSentCount(6);
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])->assertOk();
        $this->travel(61)->seconds();
        $this->actingAs($user)->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])->assertOk();
        Http::assertSentCount(8);
        $this->assertDatabaseCount('sources', 0);
    }

    #[DataProvider('sourceTypes')]
    public function test_store_accepts_maximum_name_url_and_key_lengths(string $type): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $url = 'https://feeds.test/'.str_repeat('a', 2000 - strlen('https://feeds.test/'));
        $payload = [...$this->payload($interest, $type), 'name' => str_repeat('n', 100)];
        $payload[$type === 'feed' ? 'url' : 'api_key'] = $type === 'feed' ? $url : str_repeat('k', 200);
        $this->actingAs($user)->from('/sources')->post('/sources', $payload)
            ->assertRedirect('/sources')->assertSessionHasNoErrors();
        $source = Source::query()->where('user_id', $user->id)->sole();
        $this->assertSame($payload['name'], $source->name);
        if ($type === 'feed') {
            $this->assertSame($url, $source->url);
        } else {
            $this->assertSame($payload['api_key'], $source->credentials['api_key']);
        }
    }

    #[DataProvider('failedFeeds')]
    public function test_feed_failure_returns_friendly_json_and_does_not_store_health_or_items(string $body, int $status): void
    {
        Log::spy();
        Http::fake(['https://feeds.test/feed' => Http::response($body, $status)]);
        $source = Source::factory()->create();
        $before = $source->fresh()->getAttributes();
        $response = $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'feed', 'url' => 'https://feeds.test/feed'])
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('headlines', [])
            ->assertJsonPath('message', 'Could not read this source. Check its address or key and try again.');
        $this->assertStringNotContainsString('raw-upstream-error', $response->getContent());
        Log::shouldHaveReceived('warning')->once();
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('source_subscriptions', 0);
        $this->assertDatabaseCount('items', 0);
    }

    public static function failedFeeds(): array
    {
        return [['raw-upstream-error', 503], ['<html>raw-upstream-error</html>', 200]];
    }

    public function test_newsletter_address_is_stored_encrypted_and_returned_only_for_its_owner(): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $payload = [...$this->payload($interest, 'resend_inbox'), 'address' => 'news@example.test'];

        $this->actingAs($user)->from('/sources')->post('/sources', $payload)
            ->assertRedirect('/sources')->assertSessionHasNoErrors();

        $source = Source::query()->where('user_id', $user->id)->sole();
        $this->assertSame(['api_key' => 'fake-resend-key', 'address' => 'news@example.test'], $source->credentials);
        $this->assertStringNotContainsString('news@example.test', $source->getRawOriginal('credentials'));
        $own = $this->get('/sources')->assertOk();
        $own->assertInertia(fn ($page) => $page->where('custom.0.address', 'news@example.test')->where('custom.0.has_key', true)
            ->missing('custom.0.api_key')->missing('custom.0.credentials'));
        $this->assertStringNotContainsString('fake-resend-key', $own->getContent());
        $this->actingAs(User::factory()->onboarded()->create())->get('/sources')->assertOk()
            ->assertInertia(fn ($page) => $page->has('custom', 0));
        $resource = (new SourceResource($source))->resolve(request());
        $this->assertNull($resource['address']);
    }

    #[DataProvider('newsletterAddressUpdates')]
    public function test_newsletter_address_can_be_set_changed_or_cleared_while_retaining_the_key(?string $before, ?string $address, ?string $key): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $source = Source::factory()->for($user)->create([
            'type' => SourceType::ResendInbox, 'url' => null,
            'credentials' => ['api_key' => 'old-key', 'address' => $before],
        ]);

        $this->actingAs($user)->from('/sources')->put('/sources/'.$source->id, [
            ...$this->payload($interest, 'resend_inbox'), 'api_key' => $key, 'address' => $address,
        ])->assertRedirect('/sources')->assertSessionHasNoErrors();

        $this->assertSame(['api_key' => $key ?: 'old-key', 'address' => $address ?: null], $source->fresh()->credentials);
    }

    public static function newsletterAddressUpdates(): array
    {
        return [
            'set with empty key' => [null, 'news@example.test', ''],
            'change without new key' => ['old@example.test', 'news@example.test', null],
            'clear with empty string' => ['old@example.test', '', null],
            'clear with null' => ['old@example.test', null, null],
            'replace key and address' => ['old@example.test', 'news@example.test', 'new-key'],
        ];
    }

    public function test_feed_sources_ignore_the_newsletter_address(): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $this->actingAs($user)->from('/sources')->post('/sources', [...$this->payload($interest), 'address' => 'not-an-email'])
            ->assertRedirect('/sources')->assertSessionHasNoErrors();

        $source = Source::query()->where('user_id', $user->id)->sole();
        $this->assertNull($source->credentials);
        $this->get('/sources')->assertOk()->assertInertia(fn ($page) => $page->where('custom.0.address', null));
    }

    #[DataProvider('invalidNewsletterAddresses')]
    public function test_invalid_newsletter_addresses_are_rejected_on_create_update_and_test(mixed $address): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $payload = [...$this->payload($interest, 'resend_inbox'), 'address' => $address];
        $this->actingAs($user)->postJson('/sources', $payload)->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->assertDatabaseCount('sources', 0);
        $source = Source::factory()->for($user)->create(['type' => SourceType::ResendInbox, 'credentials' => ['api_key' => 'old-key']]);
        $before = $source->fresh()->getAttributes();

        $this->putJson('/sources/'.$source->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('address');
        $this->postJson('/sources/test', $payload)->assertUnprocessable()->assertJsonValidationErrors('address');

        $this->assertSame($before, $source->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_validation_messages_use_readable_field_names(): void
    {
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $this->actingAs(User::factory()->onboarded()->create());

        $this->postJson('/sources', [...$this->payload($interest, 'resend_inbox'), 'address' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonPath('errors.address.0', 'The receiving address field must be a valid email address.');
        $this->postJson('/sources', [...$this->payload($interest, 'feed'), 'url' => 'http://127.0.0.1/feed'])
            ->assertUnprocessable()->assertJsonPath('errors.url.0', 'The feed URL must be a public http or https URL.');
        $this->postJson('/sources/test', [...$this->payload($interest, 'feed'), 'url' => 'http://127.0.0.1/feed'])
            ->assertUnprocessable()->assertJsonPath('errors.url.0', 'The feed URL must be a public http or https URL.');
        $this->postJson('/sources/test', [...$this->payload($interest, 'resend_inbox'), 'address' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonPath('errors.address.0', 'The receiving address field must be a valid email address.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('sources', 0);
    }

    public static function invalidNewsletterAddresses(): array
    {
        return ['invalid email' => ['not-an-email'], 'array' => [[]], 'too long' => [str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 62)]];
    }

    public function test_newsletter_test_endpoint_filters_forwarded_emails_by_receiving_address(): void
    {
        $this->freezeSecond();
        $api = 'https://api.resend.com/emails/receiving';
        $date = now()->subDay()->toIso8601String();
        Http::fake([
            $api.'?limit=100' => Http::response(['data' => [
                ['id' => 'other', 'created_at' => $date], ['id' => 'matching', 'created_at' => $date],
            ], 'has_more' => false]),
            $api.'/other' => Http::response(['received_for' => ['other@example.test']]),
            $api.'/matching' => Http::response(['id' => 'matching', 'created_at' => $date, 'subject' => 'Matching issue', 'received_for' => ['news@example.test']]),
        ]);

        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', [
            'type' => 'resend_inbox', 'api_key' => 'test-key', 'address' => 'news@example.test',
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('headlines', ['Matching issue']);

        Http::assertSentCount(3);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('items', 0);
    }

    public function test_imap_source_stores_encrypted_credentials_and_exposes_settings_only_to_its_owner(): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);

        $this->actingAs($user)->from('/sources')->post('/sources', [
            ...$this->imapPayload(), 'name' => 'Mail', 'interests' => [$interest->slug],
        ])->assertRedirect('/sources')->assertSessionHasNoErrors();

        $source = Source::query()->where('user_id', $user->id)->sole();
        $this->assertSame(SourceType::ImapInbox, $source->type);
        $this->assertNull($source->url);
        $this->assertSame(20, $source->max_items);
        $this->assertSame(['host' => 'mail.example.test', 'username' => 'reader@example.test', 'password' => 'app-secret', 'folder' => 'INBOX'], $source->credentials);
        $this->assertStringNotContainsString('app-secret', $source->getRawOriginal('credentials'));
        $this->assertStringNotContainsString('reader@example.test', $source->getRawOriginal('credentials'));
        $this->assertSame([$interest->id], $source->interests()->pluck('interests.id')->all());
        $this->assertDatabaseHas('source_subscriptions', ['source_id' => $source->id, 'user_id' => $user->id, 'enabled' => true]);
        $this->get('/sources')->assertOk()->assertInertia(fn ($page) => $page
            ->where('custom.0.imap', ['host' => 'mail.example.test', 'username' => 'reader@example.test', 'folder' => 'INBOX'])
            ->where('custom.0.has_key', true)->missing('custom.0.imap.password')->missing('custom.0.credentials'));
        $this->actingAs(User::factory()->onboarded()->create());
        $this->assertNull((new SourceResource($source))->resolve(request())['imap']);
    }

    #[DataProvider('imapPasswords')]
    public function test_imap_updates_settings_and_keeps_an_empty_password(?string $password): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $source = Source::factory()->for($user)->create(['type' => SourceType::ImapInbox, 'credentials' => [
            'host' => 'old.example.test', 'username' => 'old', 'password' => 'stored-secret', 'folder' => 'INBOX',
        ]]);

        $this->actingAs($user)->from('/sources')->put('/sources/'.$source->id, [
            ...$this->imapPayload(), 'name' => 'Renamed', 'interests' => [$interest->slug],
            'imap_password' => $password, 'imap_folder' => 'Newsletters',
        ])->assertRedirect('/sources')->assertSessionHasNoErrors();

        $this->assertSame(['host' => 'mail.example.test', 'username' => 'reader@example.test',
            'password' => $password ?: 'stored-secret', 'folder' => 'Newsletters'], $source->fresh()->credentials);
        $this->assertSame('Renamed', $source->fresh()->name);
    }

    public static function imapPasswords(): array
    {
        return [[''], [null], ['replacement']];
    }

    public function test_imap_required_fields_have_readable_validation_messages(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', ['type' => 'imap_inbox'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.imap_host.0', 'The mail server field is required when type is imap_inbox.')
            ->assertJsonPath('errors.imap_username.0', 'The username field is required when type is imap_inbox.')
            ->assertJsonPath('errors.imap_password.0', 'The app password field is required when type is imap_inbox.');

        $this->assertDatabaseCount('sources', 0);
    }

    #[DataProvider('invalidImapFields')]
    public function test_imap_fields_are_validated_on_create_update_and_test(string $field, mixed $value): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $payload = [...$this->imapPayload(), 'name' => 'Mail', 'interests' => [$interest->slug], $field => $value];
        $this->actingAs($user)->postJson('/sources', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('sources', 0);
        $source = Source::factory()->for($user)->create(['type' => SourceType::ImapInbox]);
        $before = $source->fresh()->getAttributes();

        $this->putJson('/sources/'.$source->id, $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->postJson('/sources/test', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame($before, $source->fresh()->getAttributes());
    }

    public static function invalidImapFields(): array
    {
        return [
            ['imap_host', '93.184.216.34'], ['imap_host', 'localhost'], ['imap_host', []], ['imap_host', str_repeat('a', 254)],
            ['imap_username', ''], ['imap_username', []], ['imap_username', str_repeat('a', 255)],
            ['imap_password', []], ['imap_password', str_repeat('a', 201)],
            ['imap_folder', []], ['imap_folder', str_repeat('a', 201)],
        ];
    }

    public function test_imap_private_dns_is_rejected_with_a_readable_message(): void
    {
        $this->app->instance(PublicUrl::class, new PublicUrl(fn () => ['10.0.0.1']));
        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', $this->imapPayload())
            ->assertUnprocessable()->assertJsonPath('errors.imap_host.0', 'The mail server must be a public mail server name.');
    }

    public function test_imap_test_endpoint_reads_recent_headlines_without_storing_anything(): void
    {
        $this->freezeSecond();
        $mailbox = $this->mock(Mailbox::class);
        $mailbox->shouldReceive('messagesSince')->once()->with('mail.example.test', '93.184.216.34', 'reader@example.test', 'app-secret', 'INBOX',
            \Mockery::on(fn ($since) => $since->equalTo(now()->subDays(30))), PHP_INT_MAX)
            ->andReturn(array_map(fn ($index) => new ImapMessage(
                'issue-'.$index, $index, 'Issue '.$index, '', '', now()->subMinutes($index), '42'), range(1, 6)));

        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', $this->imapPayload())
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('message', 'Found 6 recent posts.')
            ->assertJsonPath('headlines', ['Issue 1', 'Issue 2', 'Issue 3', 'Issue 4', 'Issue 5']);

        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('items', 0);
    }

    public function test_imap_unexpected_errors_keep_the_generic_message_and_redact_the_log(): void
    {
        Log::spy();
        $this->app->instance(PublicUrl::class, new PublicUrl(fn () => throw new RuntimeException('reader@example.test app-secret')));

        $result = $this->app->make(TestSource::class)->handle(new User, SourceType::ImapInbox, '', '',
            imapHost: 'mail.example.test', imapUsername: 'reader@example.test', imapPassword: 'app-secret');

        $this->assertSame([
            'ok' => false, 'message' => 'Could not read this source. Check its address or key and try again.', 'headlines' => [],
        ], $result);
        Log::shouldHaveReceived('warning')->once()->with('Source test failed.', [
            'type' => 'imap_inbox', 'error' => '[redacted] [redacted]',
        ]);
    }

    public function test_imap_test_endpoint_returns_the_specific_sign_in_failure(): void
    {
        Log::spy();
        $this->mock(Mailbox::class)->shouldReceive('messagesSince')->once()
            ->andThrow(new SignInFailedException('reader@example.test app-secret'));

        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', $this->imapPayload())
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('headlines', [])
            ->assertJsonPath('message', 'Could not sign in to the mail server. Check the username and app password.');

        Log::shouldHaveReceived('warning')->once()->with('Source test failed.', [
            'type' => 'imap_inbox',
            'error' => 'Could not sign in to the mail server. Check the username and app password.',
        ]);
    }

    public function test_imap_failure_logs_do_not_expose_the_username_or_password(): void
    {
        Log::spy();
        $this->mock(Mailbox::class)->shouldReceive('messagesSince')->once()
            ->andThrow(new RuntimeException('reader@example.test app-secret'));

        $response = $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources/test', $this->imapPayload())
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('message', 'The mail server refused the request.');

        $this->assertStringNotContainsString('reader@example.test', $response->getContent());
        $this->assertStringNotContainsString('app-secret', $response->getContent());
        Log::shouldHaveReceived('warning')->twice()->withArgs(function (string $message, array $context): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return ! str_contains($serialized, 'reader@example.test') && ! str_contains($serialized, 'app-secret');
        });
    }

    public function test_imap_sources_count_toward_the_custom_source_cap(): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        Source::factory()->count(30)->for($user)->create(['type' => SourceType::ImapInbox]);

        $this->actingAs($user)->postJson('/sources', [...$this->imapPayload(), 'name' => 'Mail', 'interests' => [$interest->slug]])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'You may save up to 30 custom sources.');

        $this->assertSame(30, Source::query()->where('user_id', $user->id)->count());
    }

    public function test_imap_create_requires_mail_server_username_and_password(): void
    {
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);

        $this->actingAs(User::factory()->onboarded()->create())->postJson('/sources', [
            'type' => 'imap_inbox', 'name' => 'Mail', 'interests' => [$interest->slug],
        ])->assertUnprocessable()->assertJsonValidationErrors(['imap_host', 'imap_username', 'imap_password']);

        $this->assertDatabaseCount('sources', 0);
    }

    public function test_imap_updates_keep_an_omitted_password_and_default_an_empty_folder(): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $source = Source::factory()->for($user)->create(['type' => SourceType::ImapInbox, 'credentials' => [
            'host' => 'mail.example.test', 'username' => 'reader@example.test', 'password' => 'stored-secret', 'folder' => 'Newsletters',
        ]]);
        $payload = $this->imapPayload();
        unset($payload['imap_password']);

        $this->actingAs($user)->putJson('/sources/'.$source->id, [
            ...$payload, 'name' => 'Mail', 'interests' => [$interest->slug], 'imap_folder' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('stored-secret', $source->fresh()->credentials['password']);
        $this->assertSame('INBOX', $source->fresh()->credentials['folder']);
    }

    public function test_other_source_types_exclude_imap_credentials(): void
    {
        $user = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);

        $this->actingAs($user)->postJson('/sources', [
            ...$this->payload($interest), 'imap_host' => 'localhost', 'imap_username' => [], 'imap_password' => [], 'imap_folder' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(Source::query()->where('user_id', $user->id)->sole()->credentials);
    }

    public function test_imap_sources_cannot_be_updated_by_another_reader(): void
    {
        $owner = User::factory()->onboarded()->create();
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $source = Source::factory()->for($owner)->create(['type' => SourceType::ImapInbox]);
        $before = $source->fresh()->getAttributes();

        $this->actingAs(User::factory()->onboarded()->create())->putJson('/sources/'.$source->id, [
            ...$this->imapPayload(), 'name' => 'Mail', 'interests' => [$interest->slug],
        ])->assertForbidden();

        $this->assertSame($before, $source->fresh()->getAttributes());
    }

    public function test_imap_settings_are_hidden_from_guests_and_missing_password_has_no_key(): void
    {
        $owner = User::factory()->create();
        $source = Source::factory()->for($owner)->create(['type' => SourceType::ImapInbox, 'credentials' => [
            'host' => 'mail.example.test', 'username' => 'reader@example.test', 'folder' => 'INBOX',
        ]]);
        $request = \Illuminate\Http\Request::create('/sources');

        $resource = (new SourceResource($source))->resolve($request);

        $this->assertNull($resource['imap']);
        $this->assertFalse($resource['has_key']);
        $this->assertArrayNotHasKey('credentials', $resource);
    }

    private function imapPayload(): array
    {
        return ['type' => 'imap_inbox', 'imap_host' => 'mail.example.test', 'imap_username' => 'reader@example.test', 'imap_password' => 'app-secret'];
    }

    private function payload(Interest $interest, string $type = 'feed'): array
    {
        return ['type' => $type, 'name' => 'My source', 'interests' => [$interest->slug], ...($type === 'feed'
            ? ['url' => 'https://feeds.test/feed'] : ['api_key' => 'fake-resend-key'])];
    }
}
