<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\LearningWriter;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Discord\DiscordException;
use App\Digest\Learning\LearningPipeline;
use App\Digest\Learning\LinkChecker;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class LearningPipelineTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    private LearningTopic $topic;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-16 07:30:00');
        $this->today = CarbonImmutable::parse('2026-10-16');
        config([
            'digest.ai_providers' => 'gemini:gemini-test-flash',
            'digest.profile' => 'A Laravel developer.',
            'services.discord.token' => 'bot-token',
            'services.discord.learning_forum' => 'forum-learning',
        ]);
        $this->topic = LearningTopic::query()->create(['title' => 'Idempotency keys', 'category' => 'distributed-systems']);
        $this->app->instance(LinkChecker::class, new LinkChecker(fn () => ['93.184.216.34']));
    }

    public function test_posts_tip_and_topic(): void
    {
        LearningWriter::fake([$this->answer()]);
        $this->fakeHttp();

        $post = app(LearningPipeline::class)->run($this->today);

        $this->assertSame(PostStatus::Posted, $post->status);
        $this->assertSame(PostKind::Learning, $post->kind);
        $this->assertSame('gemini:gemini-test-flash', $post->model_used);
        $tip = $post->learningPosts()->where('kind', LearningKind::Tip)->firstOrFail();
        $topic = $post->learningPosts()->where('kind', LearningKind::Topic)->firstOrFail();
        $this->assertSame('git bisect run with a test filter', $tip->title);
        $this->assertSame('Idempotency keys', $topic->title);
        $this->assertSame($this->topic->id, $topic->learning_topic_id);
        $this->assertStringContainsString('**In practice:** Store the key with a unique index.', $topic->body);
        $this->assertSame(['devtools'], $tip->tags()->pluck('slug')->all());

        $text = implode("\n", $post->rendered['messages'] ?? []);
        $this->assertSame('🧠 Tip & topic — Fri 16 Oct', $post->rendered['title'] ?? null);
        $this->assertStringContainsString("## 💡 git bisect run with a test filter\nUse `git bisect run php artisan test --filter=X`.", $text);
        $this->assertStringContainsString('## 📚 Idempotency keys', $text);
        $this->assertStringContainsString("**Suggested reading:**\n- Stripe: idempotent requests <https://ok.test/stripe>", $text);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/channels/forum-learning/threads'));
    }

    public function test_dead_links_are_removed(): void
    {
        LearningWriter::fake([$this->answer(links: [['title' => 'Alive', 'url' => 'https://ok.test/a'], ['title' => 'Dead', 'url' => 'https://dead.test/b']])]);
        $this->fakeHttp();

        $post = app(LearningPipeline::class)->run($this->today);

        $topic = $post->learningPosts()->where('kind', LearningKind::Topic)->firstOrFail();
        $this->assertSame([['title' => 'Alive', 'url' => 'https://ok.test/a']], $topic->links);
        $this->assertStringNotContainsString('dead.test', implode("\n", $post->rendered['messages'] ?? []));
    }

    public function test_prompt_contains_the_topic_recent_tips_and_the_level_rule(): void
    {
        $old = Post::query()->create(['date' => '2026-10-15', 'kind' => PostKind::Learning, 'status' => PostStatus::Posted]);
        LearningPost::query()->create(['post_id' => $old->id, 'kind' => LearningKind::Tip, 'title' => 'Use xdebug.mode=profile', 'body' => '…', 'links' => []]);
        LearningWriter::fake([$this->answer()]);
        $this->fakeHttp();

        app(LearningPipeline::class)->run($this->today);

        LearningWriter::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Idempotency keys')
            && $prompt->contains('distributed-systems')
            && str_contains((string) $prompt->agent->instructions(), 'Use xdebug.mode=profile')
            && str_contains((string) $prompt->agent->instructions(), 'never basics')
            && str_contains((string) $prompt->agent->instructions(), 'A Laravel developer.'));
    }

    public function test_ai_failure_marks_failed_and_the_retry_keeps_the_same_topic(): void
    {
        LearningTopic::query()->create(['title' => 'Mutation testing', 'category' => 'testing']);
        LearningWriter::fake(fn () => throw new ProviderOverloadedException('overloaded'));
        $this->fakeHttp();

        try {
            app(LearningPipeline::class)->run($this->today);
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException) {
        }

        $post = Post::query()->firstOrFail();
        $this->assertSame(PostStatus::Failed, $post->status);
        $this->assertSame(1, $post->attempts);
        $this->assertNull($post->rendered);
        $this->assertSame(0, LearningPost::query()->count());

        LearningWriter::fake([$this->answer()]);
        $post = app(LearningPipeline::class)->run($this->today);

        $this->assertSame(PostStatus::Posted, $post->status);
        $this->assertSame('Idempotency keys', $post->learningPosts()->where('kind', LearningKind::Topic)->value('title'));
    }

    public function test_last_failed_attempt_frees_the_topic_for_another_day(): void
    {
        config(['digest.max_attempts' => 2]);
        LearningWriter::fake(fn () => throw new ProviderOverloadedException('overloaded'));
        $this->fakeHttp();

        foreach ([1, 2] as $attempt) {
            try {
                app(LearningPipeline::class)->run($this->today);
                $this->fail('Expected AiUnavailableException');
            } catch (AiUnavailableException) {
            }

            $expected = $attempt === 1 ? '2026-10-16' : null;
            $this->assertSame($expected, $this->topic->refresh()->last_used_on?->toDateString());
        }
    }

    public function test_discord_failure_keeps_rendered_and_the_retry_only_publishes(): void
    {
        LearningWriter::fake([$this->answer()]);
        $this->fakeHttp(threadStatuses: [500, 200]);

        try {
            app(LearningPipeline::class)->run($this->today);
            $this->fail('Expected the Discord failure to be rethrown');
        } catch (DiscordException) {
        }

        $this->assertNotNull(Post::query()->firstOrFail()->rendered);

        $post = app(LearningPipeline::class)->run($this->today);

        $this->assertSame(PostStatus::Posted, $post->status);
        LearningWriter::assertPromptedTimes(1);
        $this->assertSame(2, LearningPost::query()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        LearningWriter::fake([$this->answer()]);
        $this->fakeHttp();

        $post = app(LearningPipeline::class)->run($this->today, dryRun: true);

        $this->assertNotNull($post->rendered);
        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, LearningPost::query()->count());
        $this->assertNull($this->topic->refresh()->last_used_on);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'discord.com'));
    }

    /**
     * @param  list<array{title: string, url: string}>|null  $links
     * @return array<string, mixed>
     */
    private function answer(?array $links = null): array
    {
        return [
            'tip' => ['title' => 'git bisect run with a test filter', 'body' => 'Use `git bisect run php artisan test --filter=X`.', 'tags' => ['devtools']],
            'topic' => [
                'body' => 'An idempotency key lets a client retry safely.',
                'stack_angle' => 'Store the key with a unique index.',
                'links' => $links ?? [['title' => 'Stripe: idempotent requests', 'url' => 'https://ok.test/stripe']],
                'tags' => ['engineering'],
            ],
        ];
    }

    /**
     * @param  list<int>  $threadStatuses
     */
    private function fakeHttp(array $threadStatuses = [200, 200, 200]): void
    {
        $threads = Http::sequence();
        foreach ($threadStatuses as $status) {
            $threads->push(['id' => 'thread-1'], $status);
        }

        Http::fake([
            'ok.test/*' => Http::response('', 200),
            'dead.test/*' => Http::response('', 404),
            'discord.com/api/v10/channels/forum-learning' => Http::response(['id' => 'forum-learning', 'available_tags' => []]),
            'discord.com/api/v10/channels/forum-learning/threads' => $threads,
            'discord.com/api/v10/channels/thread-1/messages' => Http::response(['id' => 'm']),
            'discord.com/api/v10/*' => Http::response(['id' => 'forum-learning', 'available_tags' => []]),
        ]);
    }
}
