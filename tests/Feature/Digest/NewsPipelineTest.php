<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\NewsCurator;
use App\Digest\Discord\DiscordException;
use App\Digest\News\NewsPipeline;
use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\SourceFetcher;
use App\Enums\ItemStatus;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Post;
use App\Models\Source;
use App\Models\Tag;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class NewsPipelineTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Candidate> */
    private array $feed = [];

    private ?CarbonImmutable $fetchedSince = null;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        CarbonImmutable::setTestNow('2026-10-16 07:00:00');
        $this->today = CarbonImmutable::parse('2026-10-16');
        config([
            'digest.ai_providers' => 'gemini:gemini-test-flash',
            'services.discord.token' => 'bot-token',
            'services.discord.news_forum' => 'forum-news',
        ]);
        Source::query()->create(['slug' => 'laravel-news', 'name' => 'Laravel News', 'type' => SourceType::Feed, 'url' => 'https://laravel-news.test/feed', 'tag' => 'laravel', 'max_items' => 10, 'position' => 1]);
        Tag::query()->create(['slug' => 'laravel', 'name' => 'Laravel']);

        $this->app->instance(FeedFetcher::class, new class($this) implements SourceFetcher
        {
            public function __construct(private NewsPipelineTest $test) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                return $this->test->fetched($since);
            }
        });
    }

    /**
     * @return list<Candidate>
     */
    public function fetched(CarbonImmutable $since): array
    {
        $this->fetchedSince = $since;

        return $this->feed;
    }

    public function test_happy_path_posts_and_marks_posted(): void
    {
        $this->feed = [$this->candidate('https://laravel-news.com/a')];
        NewsCurator::fake(fn () => ['items' => [['candidate_id' => Item::query()->value('id'), 'url' => 'https://laravel-news.com/a', 'title' => 'Laravel 13.18', 'summary' => 'Deferred batches.', 'tags' => ['laravel']]]]);
        $this->fakeDiscord();

        $post = app(NewsPipeline::class)->run($this->today);

        $this->assertSame(PostStatus::Posted, $post->status);
        $this->assertSame(PostKind::News, $post->kind);
        $this->assertSame('gemini:gemini-test-flash', $post->model_used);
        $this->assertSame('📰 News digest — Fri 16 Oct', $post->rendered['title']);
        $this->assertStringContainsString('## Laravel', implode("\n", $post->rendered['messages']));
        $this->assertSame('thread-1', $post->discord_thread_id);
        $this->assertSame(ItemStatus::Selected, Item::query()->firstOrFail()->status);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/channels/forum-news/threads') && str_contains($r['message']['content'], 'Laravel 13.18'));
    }

    public function test_already_posted_is_a_noop(): void
    {
        Post::query()->create(['date' => $this->today, 'kind' => PostKind::News, 'status' => PostStatus::Posted]);
        NewsCurator::fake();

        $post = app(NewsPipeline::class)->run($this->today);

        $this->assertSame(PostStatus::Posted, $post->status);
        $this->assertNull($this->fetchedSince);
        NewsCurator::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_ai_failure_posts_headlines_only(): void
    {
        $this->feed = [$this->candidate('https://laravel-news.com/a', 'A plain headline')];
        NewsCurator::fake(fn () => throw new ProviderOverloadedException('overloaded'));
        $this->fakeDiscord();

        $post = app(NewsPipeline::class)->run($this->today);

        $this->assertSame(PostStatus::Posted, $post->status);
        $this->assertSame('none', $post->model_used);
        $text = implode("\n", $post->rendered['messages'] ?? []);
        $this->assertStringContainsString('- **A plain headline** <https://laravel-news.com/a>', $text);
        $this->assertStringContainsString('headlines only', $text);
        $this->assertStringContainsString('## Laravel', $text);
        $item = Item::query()->firstOrFail();
        $this->assertSame(ItemStatus::Selected, $item->status);
        $this->assertSame($post->id, $item->post_id);
        $this->assertSame(['laravel'], $item->tags()->pluck('slug')->all());
    }

    public function test_discord_failure_keeps_rendered_and_the_retry_only_publishes(): void
    {
        $this->feed = [$this->candidate('https://laravel-news.com/a')];
        NewsCurator::fake([['items' => []]]);
        $this->fakeDiscord(threadStatuses: [500, 200]);

        try {
            app(NewsPipeline::class)->run($this->today);
            $this->fail('Expected the Discord failure to be rethrown');
        } catch (DiscordException) {
        }

        $post = Post::query()->firstOrFail();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertSame(1, $post->attempts);
        $this->assertNotNull($post->rendered);
        $this->assertStringContainsString('HTTP 500', (string) $post->last_error);

        $this->feed = [];
        $post = app(NewsPipeline::class)->run($this->today);

        $this->assertSame(PostStatus::Posted, $post->status);
        NewsCurator::assertPromptedTimes(1);
    }

    public function test_zero_candidates_skips_the_ai_and_posts_quiet_night(): void
    {
        NewsCurator::fake();
        $this->fakeDiscord();

        $post = app(NewsPipeline::class)->run($this->today);

        NewsCurator::assertNeverPrompted();
        $this->assertSame(['Quiet night: nothing notable.'], $post->rendered['messages'] ?? null);
        $this->assertSame(PostStatus::Posted, $post->status);
    }

    public function test_since_is_the_last_posted_news_or_the_last_24_hours(): void
    {
        NewsCurator::fake();
        $this->fakeDiscord();

        app(NewsPipeline::class)->run($this->today);
        $this->assertSame('2026-10-15 07:00:00', $this->fetchedSince?->utc()->toDateTimeString());

        CarbonImmutable::setTestNow('2026-10-17 07:30:00');
        app(NewsPipeline::class)->run($this->today->addDay());
        $this->assertSame('2026-10-16 07:00:00', $this->fetchedSince?->utc()->toDateTimeString());
    }

    public function test_dry_run_writes_nothing_and_sends_nothing_to_discord(): void
    {
        $this->feed = [$this->candidate('https://laravel-news.com/a')];
        NewsCurator::fake(fn () => ['items' => []]);

        $post = app(NewsPipeline::class)->run($this->today, dryRun: true);

        $this->assertNotNull($post->rendered);
        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, Item::query()->count());
        $this->assertNull(Source::query()->firstOrFail()->last_fetched_at);
        Http::assertNothingSent();
    }

    public function test_command_dry_run_prints_the_post(): void
    {
        NewsCurator::fake();

        $this->artisan('digest:news', ['--dry-run' => true])
            ->expectsOutputToContain('📰 News digest — Fri 16 Oct')
            ->expectsOutputToContain('Quiet night: nothing notable.')
            ->assertExitCode(0);
    }

    public function test_command_force_reposts_today_with_the_same_items(): void
    {
        $this->feed = [$this->candidate('https://laravel-news.com/a')];
        NewsCurator::fake(fn () => ['items' => [['candidate_id' => Item::query()->value('id'), 'url' => 'https://laravel-news.com/a', 'title' => 'Laravel 13.18', 'summary' => 'Deferred batches.', 'tags' => ['laravel']]]]);
        $this->fakeDiscord();
        app(NewsPipeline::class)->run($this->today);

        $this->artisan('digest:news', ['--force' => true])->assertExitCode(0);

        NewsCurator::assertPromptedTimes(2);
        NewsCurator::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('https://laravel-news.com/a'));
        Http::assertSentCount(4);
        $this->assertSame(1, Post::query()->count());
    }

    private function candidate(string $url, string $title = 'A title'): Candidate
    {
        return new Candidate('laravel-news', $title, $url, 'Excerpt.', CarbonImmutable::parse('2026-10-16 05:00'), [$url]);
    }

    /**
     * @param  list<int>  $threadStatuses  one per thread creation attempt
     */
    private function fakeDiscord(array $threadStatuses = [200, 200, 200]): void
    {
        $threads = Http::sequence();
        foreach ($threadStatuses as $status) {
            $threads->push(['id' => 'thread-1'], $status);
        }

        Http::fake([
            'discord.com/api/v10/channels/forum-news' => Http::response(['id' => 'forum-news', 'available_tags' => [['id' => '7', 'name' => 'Laravel']]]),
            'discord.com/api/v10/channels/forum-news/threads' => $threads,
            'discord.com/api/v10/channels/thread-1/messages' => Http::response(['id' => 'm']),
        ]);
    }
}
