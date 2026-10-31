<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\NewsCurator;
use App\Digest\Ai\AiNotConfiguredException;
use App\Digest\Delivery\PostDeliverer;
use App\Digest\News\NewsPipeline;
use App\Digest\Rendering\RenderedPost;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceFetcher;
use App\Digest\Sources\UrlNormalizer;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\AiKey;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\Item;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class UserNewsPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CarbonImmutable $date;

    /** @var array<string, list<Candidate>|Throwable> */
    private array $responses = [];

    /** @var list<string> */
    private array $fetched = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->travelTo(CarbonImmutable::parse('2026-10-16 07:00:00'));
        $this->date = CarbonImmutable::parse('2026-10-16');
        config(['digest.ai_providers' => 'gemini:instance-model', 'digest.profile' => 'Wrong instance profile', 'digest.max_items' => 20]);
        $this->user = $this->reader();

        $this->app->instance(FeedFetcher::class, new class($this) implements SourceFetcher
        {
            public function __construct(private UserNewsPipelineTest $test) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                return $this->test->fetched($source);
            }
        });
    }

    /** @return list<Candidate> */
    public function fetched(Source $source): array
    {
        $this->fetched[] = $source->slug;
        $response = $this->responses[$source->slug] ?? [];

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function test_collects_for_the_reader_persists_ordered_picks_and_delivers_the_generated_post(): void
    {
        $source = $this->source($this->user, 'astronomy', tag: 'space');
        $other = $this->source($this->reader(), 'other-reader');
        $this->responses[$source->slug] = [
            $this->candidate($source, 'https://example.com/one', 'Original one'),
            $this->candidate($source, 'https://example.com/two', 'Original two'),
        ];
        NewsCurator::fake(fn () => ['items' => [
            $this->pick(Item::query()->where('url', 'https://example.com/two')->firstOrFail(), 'Second story', ['space', 'science']),
            $this->pick(Item::query()->where('url', 'https://example.com/one')->firstOrFail(), 'First story', []),
        ]])->preventStrayPrompts();
        $delivered = null;
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->with(Mockery::on(function (Post $post) use (&$delivered): bool {
            $delivered = $post->id;

            return $post->user_id === $this->user->id && $post->status === PostStatus::Generated
                && $post->rendered !== null && $post->postItems()->count() === 2;
        }))->andReturn(0);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        $this->assertSame($post->id, $delivered);
        $this->assertSame([$source->slug], $this->fetched);
        $this->assertNull($other->fresh()->last_fetched_at);
        $this->assertSame($this->user->id, $post->user_id);
        $this->assertSame('2026-10-16', $post->date->toDateString());
        $this->assertSame(PostKind::News, $post->kind);
        $this->assertSame(PostStatus::Generated, $post->fresh()->status);
        $this->assertSame("user_{$this->user->id}_gemini:reader-model", $post->model_used);
        $picks = $post->postItems()->get();
        $this->assertSame(['Second story', 'First story'], $picks->pluck('title')->all());
        $this->assertSame([1, 2], $picks->pluck('position')->all());
        $this->assertSame(['Selected summary.', 'Selected summary.'], $picks->pluck('summary')->all());
        $this->assertEqualsCanonicalizing(['space', 'science'], $picks[0]->tags()->pluck('slug')->all());
        $this->assertSame(['space'], $picks[1]->tags()->pluck('slug')->all());
        $this->assertSame(['https://example.com/two', 'https://example.com/one'], $picks->map(fn (PostItem $pick) => $pick->item->url)->all());
        $this->assertSame('📰 News digest — Fri 16 Oct', $post->rendered['title']);
        $text = $this->text($post);
        $this->assertStringContainsString('- **Second story**: Selected summary. <https://example.com/two>', $text);
        $this->assertStringContainsString('- **First story**: Selected summary. <https://example.com/one>', $text);
        $this->assertLessThan(strpos($text, 'First story'), strpos($text, 'Second story'));
        foreach (Item::query()->get() as $item) {
            $this->assertStringStartsWith('Original ', $item->title);
        }
    }

    public function test_ai_selection_does_not_mutate_existing_shared_items_or_another_users_selection(): void
    {
        $other = $this->reader();
        $foreign = $this->source($other, 'foreign-original', position: 0, tag: 'wrong');
        $source = $this->source($this->user, 'reader-source', position: 1, tag: 'space');
        $otherPost = Post::factory()->create(['user_id' => $other->id, 'date' => '2026-10-15', 'kind' => PostKind::News]);
        $picked = $this->item($foreign, ['title' => 'Shared title']);
        $picked->sources()->attach($source, ['first_seen_at' => now()]);
        $ignored = $this->item($source);
        $tag = Tag::query()->create(['slug' => 'legacy', 'name' => 'Legacy']);
        $otherPick = PostItem::factory()->create(['post_id' => $otherPost->id, 'item_id' => $picked->id, 'summary' => 'Shared summary']);
        $otherPick->tags()->attach($tag);
        $otherBefore = $otherPick->fresh()->getAttributes();
        $before = [$picked->fresh()->getAttributes(), $ignored->fresh()->getAttributes()];
        NewsCurator::fake([['items' => [$this->pick($picked, 'Reader title', [])]]]);
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        $pick = $post->postItems()->sole();
        $this->assertSame($picked->id, $pick->item_id);
        $this->assertSame('Reader title', $pick->title);
        $this->assertSame('Selected summary.', $pick->summary);
        $this->assertSame(['space'], $pick->tags()->pluck('slug')->all());
        $this->assertEquals($before, [$picked->fresh()->getAttributes(), $ignored->fresh()->getAttributes()]);
        $this->assertSame([$tag->id], $otherPick->tags()->pluck('tags.id')->all());
        $this->assertEquals($otherBefore, $otherPick->fresh()->getAttributes());
        $this->assertStringContainsString('- **Reader title**: Selected summary. <'.$picked->url.'>', $this->text($post));
        $this->assertStringNotContainsString('Shared summary', $this->text($post));
    }

    public function test_prompt_uses_user_settings_interests_keys_and_first_subscribed_source(): void
    {
        $this->user->digestSettings()->update(['profile' => 'A reader of the night sky.', 'max_items' => 2]);
        $this->user->interests()->attach(Interest::factory()->create(['slug' => 'astronomy', 'name' => 'Astronomy', 'position' => 1])->id);
        $this->user->interests()->attach(Interest::factory()->create(['slug' => 'gardening', 'name' => 'Gardening', 'position' => 2])->id);
        Tag::query()->create(['slug' => 'space', 'name' => 'Space']);
        $foreign = $this->source($this->reader(), 'foreign', position: 0);
        $low = $this->source($this->user, 'low', position: 20);
        $high = $this->source($this->user, 'high', position: 1);
        $item = $this->item($foreign);
        foreach ([$low, $high] as $source) {
            $item->sources()->attach($source, ['first_seen_at' => now()]);
        }
        NewsCurator::fake([['items' => []]])->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        $this->assertSame(['Quiet night: nothing notable.'], $post->rendered['messages']);
        $this->assertDatabaseCount('post_items', 0);
        NewsCurator::assertPromptedTimes(1);
        NewsCurator::assertPrompted(function (AgentPrompt $prompt) use ($item, $high, $foreign, $low): bool {
            $instructions = (string) $prompt->agent->instructions();
            $this->assertStringContainsString('Reader profile: A reader of the night sky.', $instructions);
            $this->assertStringContainsString('Reader interests: Astronomy, Gardening', $instructions);
            $this->assertStringContainsString('at most 2 items', $instructions);
            $this->assertStringContainsString('space', $instructions);
            $this->assertStringNotContainsString('Wrong instance profile', $instructions);
            $this->assertDoesNotMatchRegularExpression('/\btech\b/i', $instructions);
            $rows = json_decode(substr($prompt->prompt, strpos($prompt->prompt, '[')), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount(1, $rows);
            $this->assertSame($item->id, $rows[0]['id']);
            $this->assertSame($high->slug, $rows[0]['source']);
            $this->assertFalse($prompt->contains('"source":"'.$foreign->slug.'"'));
            $this->assertFalse($prompt->contains('"source":"'.$low->slug.'"'));
            $this->assertSame("user_{$this->user->id}_gemini", $prompt->provider->name());
            $this->assertSame('reader-model', $prompt->model);

            return true;
        });
    }

    public function test_empty_candidates_render_quiet_night_without_ai_even_without_keys(): void
    {
        $this->user->aiKeys()->delete();
        $this->source($this->user, 'empty');
        NewsCurator::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->with(Mockery::on(fn (Post $post) => $post->user_id === $this->user->id && $post->status === PostStatus::Generated))->andReturn(0);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        NewsCurator::assertNeverPrompted();
        $this->assertSame(['Quiet night: nothing notable.'], $post->rendered['messages']);
        $this->assertSame(PostStatus::Generated, $post->status);
        $this->assertSame('none', $post->model_used);
        $this->assertDatabaseCount('post_items', 0);
    }

    public function test_existing_rendered_post_is_reused_and_only_delivery_is_retried(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id, 'date' => $this->date, 'kind' => PostKind::News, 'status' => PostStatus::Generated,
            'model_used' => 'previous:model', 'rendered' => (new RenderedPost('Saved title', ['Saved body'], []))->toArray()]);
        $pick = PostItem::factory()->create(['post_id' => $post->id]);
        $before = $pick->fresh()->getAttributes();
        $this->source($this->user, 'unused');
        NewsCurator::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->twice()->with(Mockery::on(fn (Post $saved) => $saved->is($post) && $saved->rendered === $post->rendered))->andReturn(1, 0);

        $first = app(NewsPipeline::class)->runFor($this->user, $this->date);
        $second = app(NewsPipeline::class)->runFor($this->user, $this->date);
        $dry = app(NewsPipeline::class)->runFor($this->user, $this->date, dryRun: true);

        $this->assertTrue($dry->is($post));
        $this->assertSame($post->rendered, $dry->rendered);
        $this->assertTrue($first->is($second));
        $this->assertSame('previous:model', $second->model_used);
        $this->assertSame(PostStatus::Generated, $second->fresh()->status);
        $this->assertSame(0, $second->attempts);
        $this->assertEquals($before, $pick->fresh()->getAttributes());
        $this->assertDatabaseCount('posts', 1);
        $this->assertSame([], $this->fetched);
        NewsCurator::assertNeverPrompted();
    }

    public function test_fallback_uses_user_limit_first_source_priority_then_recency_and_leaves_shared_items_untouched(): void
    {
        $this->user->digestSettings()->update(['max_items' => 3]);
        $other = $this->reader();
        $foreign = $this->source($other, 'foreign', position: 0, tag: 'wrong');
        $high = $this->source($this->user, 'high', position: 1, tag: 'space');
        $low = $this->source($this->user, 'low', position: 20, tag: 'garden');
        $old = $this->item($low, ['title' => 'Low old', 'published_at' => now()->subHours(6)]);
        $new = $this->item($low, ['title' => 'Low new', 'published_at' => now()->subHour()]);
        $best = $this->item($foreign, ['title' => 'High old', 'published_at' => now()->subHours(12)]);
        $best->sources()->attach($high, ['first_seen_at' => now()]);
        $omitted = $this->item($low, ['title' => 'Omitted', 'published_at' => now()->subHours(10)]);
        $tag = Tag::query()->create(['slug' => 'original', 'name' => 'Original']);
        $otherPost = Post::factory()->create(['user_id' => $other->id, 'date' => '2026-10-15', 'kind' => PostKind::News]);
        $otherPick = PostItem::factory()->create(['post_id' => $otherPost->id, 'item_id' => $best->id, 'summary' => 'Shared summary']);
        $otherPick->tags()->attach($tag);
        $otherBefore = $otherPick->fresh()->getAttributes();
        $before = Item::query()->get()->mapWithKeys(fn (Item $item) => [$item->id => $item->fresh()->getAttributes()])->all();
        NewsCurator::fake(fn () => throw new ProviderOverloadedException('overloaded'));
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(1);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        $picks = $post->postItems()->get();
        $this->assertSame([$best->id, $new->id, $old->id], $picks->pluck('item_id')->all());
        $this->assertSame([1, 2, 3], $picks->pluck('position')->all());
        $this->assertSame(['High old', 'Low new', 'Low old'], $picks->pluck('title')->all());
        $this->assertSame([null, null, null], $picks->pluck('summary')->all());
        $this->assertSame([['space'], ['garden'], ['garden']], $picks->map(fn (PostItem $pick) => $pick->tags()->pluck('slug')->all())->all());
        $this->assertSame('none', $post->model_used);
        $this->assertSame(PostStatus::Generated, $post->fresh()->status);
        $this->assertSame(0, $post->attempts);
        $this->assertStringContainsString('- **High old** <'.$best->url.'>', $this->text($post));
        $this->assertStringEndsWith('⚠️ Summaries unavailable today, headlines only.', $this->text($post));
        $this->assertStringNotContainsString('Shared summary', $this->text($post));
        $this->assertStringNotContainsString($omitted->url, $this->text($post));
        foreach (Item::query()->get() as $item) {
            $this->assertEquals($before[$item->id], $item->getAttributes());
        }
        $this->assertSame([$tag->id], $otherPick->tags()->pluck('tags.id')->all());
        $this->assertEquals($otherBefore, $otherPick->fresh()->getAttributes());
    }

    public function test_missing_ai_configuration_fails_the_post_increments_attempts_and_rethrows_without_delivery(): void
    {
        $this->user->aiKeys()->delete();
        $item = $this->item($this->source($this->user, 'configured-source'));
        $before = $item->fresh()->getAttributes();
        $post = Post::factory()->create(['user_id' => $this->user->id, 'date' => $this->date, 'kind' => PostKind::News, 'status' => PostStatus::Failed, 'attempts' => 2]);
        NewsCurator::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');

        try {
            app(NewsPipeline::class)->runFor($this->user, $this->date);
            $this->fail('Expected AiNotConfiguredException');
        } catch (AiNotConfiguredException $e) {
            $this->assertSame($e->getMessage(), $post->fresh()->last_error);
        }

        $this->assertSame(PostStatus::Failed, $post->fresh()->status);
        $this->assertSame(3, $post->fresh()->attempts);
        $this->assertNull($post->fresh()->rendered);
        $this->assertDatabaseCount('post_items', 0);
        $this->assertEquals($before, $item->fresh()->getAttributes());
        NewsCurator::assertNeverPrompted();
    }

    public function test_generation_persistence_errors_fail_the_post_and_are_rethrown(): void
    {
        $item = $this->item($this->source($this->user, 'feed'));
        NewsCurator::fake([['items' => [$this->pick($item, 'Chosen')]]]);
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');
        Event::listen('eloquent.creating: '.PostItem::class, fn () => throw new RuntimeException('Cannot save selection'));

        try {
            app(NewsPipeline::class)->runFor($this->user, $this->date);
            $this->fail('Expected generation failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Cannot save selection', $e->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.PostItem::class);
        }

        $post = Post::query()->where('user_id', $this->user->id)->sole();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertSame(1, $post->attempts);
        $this->assertSame('Cannot save selection', $post->last_error);
        $this->assertNull($post->rendered);
    }

    public function test_footer_uses_only_the_readers_failing_sources_including_cached_failures(): void
    {
        $broken = $this->source($this->user, 'broken', position: 1);
        $this->responses[$broken->slug] = new FetchException('HTTP 500');
        $cached = $this->source($this->user, 'cached', position: 2);
        $cached->update(['last_success_at' => now(), 'last_error' => 'HTTP 429']);
        $foreign = $this->source($this->reader(), 'foreign-broken');
        $foreign->update(['last_error' => 'private failure']);
        $disabled = $this->source($this->user, 'disabled');
        $disabled->subscriptions()->update(['enabled' => false]);
        $disabled->update(['last_error' => 'disabled failure']);
        $disabledSource = $this->source($this->user, 'disabled-source');
        $disabledSource->update(['enabled' => false, 'last_error' => 'disabled source failure']);
        NewsCurator::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        $this->assertSame(["Quiet night: nothing notable.\n\n⚠️ Not fetched: {$broken->name}, {$cached->name}"], $post->rendered['messages']);
        NewsCurator::assertNeverPrompted();
        $this->assertSame([$broken->slug], $this->fetched);
    }

    #[DataProvider('newsletterLinks')]
    public function test_newsletter_link_creates_or_reuses_a_shared_item_and_attaches_the_readers_first_source(bool $existing): void
    {
        $foreign = $this->source($this->reader(), 'foreign-newsletter', position: 0, tag: 'wrong');
        $source = $this->source($this->user, 'newsletter', position: 1, tag: 'space');
        $url = 'https://www.example.com/story/?utm_source=mail';
        $normalized = UrlNormalizer::normalize($url);
        $email = $this->item($foreign, ['url' => 'resend://email/one', 'url_normalized' => 'resend://email/one', 'links' => [$url]]);
        $email->sources()->attach($source, ['first_seen_at' => now()]);
        $shared = $existing ? Item::factory()->create(['url' => $normalized, 'url_normalized' => $normalized, 'title' => 'Shared title']) : null;
        $before = $shared?->fresh()?->getAttributes();
        $emailBefore = $email->fresh()->getAttributes();
        NewsCurator::fake([['items' => [['candidate_id' => $email->id, 'url' => $url, 'title' => 'Newsletter story', 'summary' => 'A new discovery.', 'tags' => []]]]]);
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        $linked = Item::query()->where('url_normalized', $normalized)->sole();
        $pick = $post->postItems()->sole();
        $this->assertSame($linked->id, $pick->item_id);
        $this->assertNotSame($email->id, $linked->id);
        $this->assertSame('Newsletter story', $pick->title);
        $this->assertSame('A new discovery.', $pick->summary);
        $this->assertSame(1, $pick->position);
        $this->assertSame(['space'], $pick->tags()->pluck('slug')->all());
        $this->assertDatabaseHas('item_source', ['item_id' => $linked->id, 'source_id' => $source->id]);
        $this->assertDatabaseMissing('item_source', ['item_id' => $linked->id, 'source_id' => $foreign->id]);
        $this->assertSame(2, Item::query()->count());
        $this->assertEquals($emailBefore, $email->fresh()->getAttributes());
        $this->assertStringContainsString('- **Newsletter story**: A new discovery. <'.$linked->url.'>', $this->text($post));
        if ($shared !== null) {
            $this->assertSame($shared->id, $linked->id);
            $this->assertEquals($before, $linked->getAttributes());
        }
    }

    /** @return array<string, array{bool}> */
    public static function newsletterLinks(): array
    {
        return ['new URL' => [false], 'existing normalized URL' => [true]];
    }

    #[DataProvider('historyWindows')]
    public function test_candidate_window_uses_latest_other_user_news_created_at_with_a_three_day_cap(?string $previous, string $boundary): void
    {
        if ($previous !== null) {
            Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-10-14', 'kind' => PostKind::News, 'created_at' => $previous, 'status' => PostStatus::Failed]);
            Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-10-13', 'kind' => PostKind::News, 'created_at' => '2026-10-01 07:00:00']);
        }
        Post::factory()->create(['user_id' => User::factory(), 'date' => '2026-10-15', 'kind' => PostKind::News, 'created_at' => now()->subMinute()]);
        Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-10-15', 'kind' => PostKind::Learning, 'created_at' => now()->subMinute()]);
        $current = Post::factory()->create(['user_id' => $this->user->id, 'date' => $this->date, 'kind' => PostKind::News, 'created_at' => now()]);
        $source = $this->source($this->user, 'history');
        $at = $this->item($source, seenAt: CarbonImmutable::parse($boundary));
        $before = $this->item($source, seenAt: CarbonImmutable::parse($boundary)->subSecond());
        NewsCurator::fake([['items' => [$this->pick($at, 'At boundary'), $this->pick($before, 'Too old')]]]);
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date);

        $this->assertTrue($post->is($current));
        $this->assertSame([$at->id], $post->postItems()->pluck('item_id')->all());
        NewsCurator::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains($at->url) && ! $prompt->contains($before->url));
    }

    /** @return array<string, array{?string, string}> */
    public static function historyWindows(): array
    {
        return [
            'no history uses 24 hours' => [null, '2026-10-15 07:00:00'],
            'latest news creation time even when failed' => ['2026-10-14 09:00:00', '2026-10-14 09:00:00'],
            'recent history uses less than 24 hours' => ['2026-10-16 01:00:00', '2026-10-16 01:00:00'],
            'exact three day boundary' => ['2026-10-13 07:00:00', '2026-10-13 07:00:00'],
            'old history capped at three days' => ['2026-10-10 07:00:00', '2026-10-13 07:00:00'],
        ];
    }

    public function test_two_users_have_independent_posts_candidates_and_history_for_shared_items(): void
    {
        $other = $this->reader();
        $sharedSource = $this->source($this->user, 'shared');
        SourceSubscription::factory()->create(['user_id' => $other->id, 'source_id' => $sharedSource->id]);
        $private = $this->source($other, 'private');
        $private->forceFill(['user_id' => $other->id])->save();
        $shared = $this->item($sharedSource);
        $secret = $this->item($private);
        NewsCurator::fake(fn () => ['items' => [$this->pick($shared, 'Shared pick'), $this->pick($secret, 'Private pick')]]);
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->times(3)->andReturn(0);

        $first = app(NewsPipeline::class)->runFor($this->user, $this->date);
        $second = app(NewsPipeline::class)->runFor($other, $this->date);
        $this->travelTo(CarbonImmutable::parse('2026-10-17 07:00:00'));
        $next = app(NewsPipeline::class)->runFor($this->user->fresh(), $this->date->addDay());

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($this->user->id, $first->user_id);
        $this->assertSame($other->id, $second->user_id);
        $this->assertSame([$shared->id], $first->postItems()->pluck('item_id')->all());
        $this->assertSame([$shared->id, $secret->id], $second->postItems()->pluck('item_id')->all());
        $this->assertSame(0, $next->postItems()->count());
        $this->assertSame(['Quiet night: nothing notable.'], $next->rendered['messages']);
        $this->assertStringNotContainsString($secret->url, $this->text($first));
        NewsCurator::assertPromptedTimes(2);
        NewsCurator::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider->name() === "user_{$this->user->id}_gemini"
            && $prompt->contains($shared->url) && ! $prompt->contains($secret->url));
        NewsCurator::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider->name() === "user_{$other->id}_gemini"
            && $prompt->contains($shared->url) && $prompt->contains($secret->url));
        $this->assertDatabaseCount('posts', 3);
    }

    public function test_dry_run_returns_rendered_selection_and_rolls_back_all_writes_without_delivery(): void
    {
        $source = $this->source($this->user, 'dry', tag: 'space');
        $this->responses[$source->slug] = [$this->candidate($source, 'https://example.com/dry')];
        NewsCurator::fake(fn () => ['items' => [$this->pick(Item::query()->where('url', 'https://example.com/dry')->sole(), 'Dry pick', ['new-tag'])]]);
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');
        $before = $source->fresh()->getAttributes();
        $level = DB::transactionLevel();

        $post = app(NewsPipeline::class)->runFor($this->user, $this->date, dryRun: true);

        $this->assertSame(PostStatus::Generated, $post->status);
        $this->assertStringContainsString('- **Dry pick**: Selected summary. <https://example.com/dry>', $this->text($post));
        $this->assertSame($level, DB::transactionLevel());
        foreach (['posts', 'post_items', 'post_item_tag', 'items', 'item_source', 'tags', 'deliveries'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertEquals($before, $source->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_failed_dry_run_rolls_back_the_failed_post_and_collection_too(): void
    {
        $source = $this->source($this->user, 'dry-failure');
        $this->user->aiKeys()->delete();
        $this->responses[$source->slug] = [$this->candidate($source, 'https://example.com/dry-failure')];
        NewsCurator::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');
        $level = DB::transactionLevel();

        try {
            app(NewsPipeline::class)->runFor($this->user, $this->date, dryRun: true);
            $this->fail('Expected AiNotConfiguredException');
        } catch (AiNotConfiguredException) {
        }

        $this->assertSame($level, DB::transactionLevel());
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_items', 0);
        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('item_source', 0);
        $this->assertNull($source->fresh()->last_fetched_at);
        NewsCurator::assertNeverPrompted();
    }

    private function reader(): User
    {
        $user = User::factory()->create();
        DigestSettings::factory()->create(['user_id' => $user->id]);
        AiKey::factory()->create(['user_id' => $user->id, 'model' => 'reader-model']);

        return $user;
    }

    private function source(User $user, string $slug, int $position = 10, string $tag = 'space'): Source
    {
        $source = Source::factory()->create(['slug' => $slug, 'name' => $slug, 'position' => $position, 'tag' => $tag]);
        SourceSubscription::factory()->create(['user_id' => $user->id, 'source_id' => $source->id]);

        return $source;
    }

    /** @param array<string, mixed> $attributes */
    private function item(Source $source, array $attributes = [], ?CarbonImmutable $seenAt = null): Item
    {
        $item = Item::factory()->create($attributes);
        $item->sources()->attach($source, ['first_seen_at' => $seenAt ?? now()]);

        return $item;
    }

    private function candidate(Source $source, string $url, string $title = 'Original title'): Candidate
    {
        return new Candidate($title, $url, 'Excerpt.', CarbonImmutable::now()->subHour(), [$url]);
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function pick(Item $item, string $title, array $tags = ['space']): array
    {
        return ['candidate_id' => $item->id, 'url' => $item->url, 'title' => $title, 'summary' => 'Selected summary.', 'tags' => $tags];
    }

    private function text(Post $post): string
    {
        return implode("\n", $post->rendered['messages'] ?? []);
    }
}
