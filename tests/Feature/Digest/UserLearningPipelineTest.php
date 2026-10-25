<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Ai\Agents\LearningWriter;
use App\Digest\Ai\AiUnavailableException;
use App\Digest\Delivery\PostDeliverer;
use App\Digest\Learning\LearningPipeline;
use App\Digest\Learning\LearningUnavailableException;
use App\Digest\Learning\LinkChecker;
use App\Digest\Learning\TopicPicker;
use App\Digest\Support\PublicUrl;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\AiKey;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\LearningTopic;
use App\Models\LearningTopicUsage;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class UserLearningPipelineTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    private User $user;

    private LearningTopic $topic;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-16 07:30:00');
        $this->today = CarbonImmutable::parse('2026-10-16');
        config([
            'digest.ai_providers' => 'gemini:instance-model',
            'digest.profile' => 'Instance profile that must stay private.',
            'digest.max_attempts' => 2,
        ]);
        $this->user = $this->learningUser();
        $this->topic = LearningTopic::factory()->create(['title' => 'Idempotency keys', 'category' => 'distributed-systems']);
        $this->app->instance(LinkChecker::class, new LinkChecker(new PublicUrl(fn () => ['93.184.216.34'])));
        Http::preventStrayRequests();
        Http::fake([
            'ok.test/*' => Http::response('', 200),
            'dead.test/*' => Http::response('', 404),
        ]);
    }

    public function test_unavailable_learning_throws_before_creating_a_post_or_picking_a_topic(): void
    {
        $this->user->interests()->detach();
        LearningWriter::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');

        try {
            app(LearningPipeline::class)->runFor($this->user, $this->today);
            $this->fail('Expected LearningUnavailableException');
        } catch (LearningUnavailableException) {
        }

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('learning_topic_usages', 0);
        $this->assertDatabaseCount('learning_posts', 0);
        LearningWriter::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_an_interest_without_learning_does_not_make_learning_available_even_for_a_dry_run(): void
    {
        $this->user->interests()->sync([Interest::factory()->create(['has_learning' => false])->id]);
        LearningWriter::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');

        try {
            app(LearningPipeline::class)->runFor($this->user, $this->today, dryRun: true);
            $this->fail('Expected LearningUnavailableException');
        } catch (LearningUnavailableException) {
        }

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('learning_topic_usages', 0);
        LearningWriter::assertNeverPrompted();
    }

    public function test_generates_stores_renders_and_delivers_the_users_tip_and_topic(): void
    {
        LearningWriter::fake([$this->answer()])->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()
            ->withArgs(function (Post $post): bool {
                $this->assertSame($this->user->id, $post->user_id);
                $this->assertSame(PostStatus::Generated, $post->status);
                $this->assertNotNull($post->fresh()->rendered);
                $this->assertSame(2, $post->learningPosts()->count());

                return true;
            })->andReturn(0);

        $post = app(LearningPipeline::class)->runFor($this->user, $this->today);

        $this->assertSame($this->user->id, $post->user_id);
        $this->assertSame('2026-10-16', $post->date->toDateString());
        $this->assertSame(PostKind::Learning, $post->kind);
        $this->assertSame(PostStatus::Generated, $post->refresh()->status);
        $this->assertSame("user_{$this->user->id}_gemini:user-model", $post->model_used);
        $tip = $post->learningPosts()->where('kind', LearningKind::Tip)->firstOrFail();
        $topic = $post->learningPosts()->where('kind', LearningKind::Topic)->firstOrFail();
        $this->assertSame('Trace a slow query', $tip->title);
        $this->assertSame('Use an execution plan.', $tip->body);
        $this->assertSame(['databases'], $tip->tags()->pluck('slug')->all());
        $this->assertSame('Idempotency keys', $topic->title);
        $this->assertSame($this->topic->id, $topic->learning_topic_id);
        $this->assertSame("Retries can be safe.\n\n**In practice:** Add a unique constraint in Django.", $topic->body);
        $this->assertSame(['engineering'], $topic->tags()->pluck('slug')->all());
        $this->assertSame([['title' => 'Alive', 'url' => 'https://ok.test/a']], $topic->links);
        $this->assertSame('🧠 Tip & topic — Fri 16 Oct', $post->rendered['title']);
        $text = implode("\n", $post->rendered['messages']);
        $this->assertStringContainsString("## 💡 Trace a slow query\nUse an execution plan.", $text);
        $this->assertStringContainsString('## 📚 Idempotency keys', $text);
        $this->assertStringContainsString('**In practice:** Add a unique constraint in Django.', $text);
        $this->assertStringContainsString('- Alive <https://ok.test/a>', $text);
        $this->assertStringNotContainsString('dead.test', $text);
        $this->assertDatabaseHas('learning_topic_usages', [
            'user_id' => $this->user->id, 'learning_topic_id' => $this->topic->id, 'used_on' => '2026-10-16',
        ]);
    }

    public function test_prompt_uses_the_users_profile_own_last_sixty_tip_titles_existing_tags_and_ai_chain(): void
    {
        $other = $this->learningUser('A Rust reader whose profile is private.');
        for ($day = 0; $day < 61; $day++) {
            $this->tipHistory($this->user, $this->today->subDays(61 - $day), "Own tip number {$day}.");
        }
        $this->tipHistory($other, $this->today->subDay(), 'Foreign tip that must stay private.');
        $history = Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-01-01', 'kind' => PostKind::Learning]);
        $history->learningPosts()->create(['kind' => LearningKind::Topic, 'title' => 'Own topic excluded from tips.', 'body' => '…', 'links' => []]);
        Tag::factory()->create(['slug' => 'existing-tag']);
        LearningWriter::fake([$this->answer()])->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        app(LearningPipeline::class)->runFor($this->user, $this->today);

        LearningWriter::assertPrompted(function (AgentPrompt $prompt): bool {
            $instructions = (string) $prompt->agent->instructions();
            $this->assertStringContainsString('A Python and Django developer.', $instructions);
            $this->assertStringNotContainsString('Instance profile that must stay private.', $instructions);
            $this->assertStringNotContainsString('A Rust reader whose profile is private.', $instructions);
            $this->assertStringNotContainsString('Foreign tip that must stay private.', $instructions);
            $this->assertStringNotContainsString('Own tip number 0.', $instructions);
            $this->assertStringNotContainsString('Own topic excluded from tips.', $instructions);
            foreach (range(1, 60) as $number) {
                $this->assertStringContainsString("Own tip number {$number}.", $instructions);
            }
            $this->assertStringContainsString('existing-tag', $instructions);
            $this->assertSame("user_{$this->user->id}_gemini", $prompt->provider->name());
            $this->assertSame('user-model', $prompt->model);

            return $prompt->contains('Idempotency keys') && $prompt->contains('distributed-systems');
        });
    }

    public function test_blank_profile_and_empty_stack_angle_do_not_add_an_instance_profile_or_practice_section(): void
    {
        $this->user->digestSettings()->update(['profile' => null]);
        $answer = $this->answer();
        $answer['topic']['stack_angle'] = '';
        LearningWriter::fake([$answer])->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        $post = app(LearningPipeline::class)->runFor($this->user, $this->today);

        $this->assertSame('Retries can be safe.', $post->learningPosts()->where('kind', LearningKind::Topic)->value('body'));
        $this->assertStringNotContainsString('**In practice:**', implode("\n", $post->rendered['messages']));
        LearningWriter::assertPrompted(fn (AgentPrompt $prompt) => ! str_contains((string) $prompt->agent->instructions(), 'Reader profile:'));
    }

    public function test_rendered_post_is_reused_and_delivered_without_generation_or_a_new_usage(): void
    {
        $rendered = ['title' => 'Saved', 'messages' => ['Saved content'], 'tags' => []];
        $existing = Post::factory()->create([
            'user_id' => $this->user->id, 'date' => $this->today, 'kind' => PostKind::Learning,
            'status' => PostStatus::Generated, 'rendered' => $rendered, 'model_used' => 'saved:model',
        ]);
        LearningTopicUsage::factory()->create(['user_id' => $this->user->id, 'learning_topic_id' => $this->topic->id, 'used_on' => $this->today]);
        LearningWriter::fake()->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()
            ->withArgs(fn (Post $post) => $post->is($existing))->andReturn(0);

        $post = app(LearningPipeline::class)->runFor($this->user, $this->today);

        $this->assertTrue($post->is($existing));
        $this->assertSame($rendered, $post->rendered);
        $this->assertSame('saved:model', $post->model_used);
        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseCount('learning_topic_usages', 1);
        $this->assertDatabaseCount('learning_posts', 0);
        LearningWriter::assertNeverPrompted();
        Http::assertNothingSent();
    }

    public function test_delivery_failures_leave_the_generated_post_and_usage_unchanged_and_retry_without_ai(): void
    {
        LearningWriter::fake([$this->answer()])->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->twice()->andReturn(1, 0);

        $post = app(LearningPipeline::class)->runFor($this->user, $this->today);

        $this->assertSame(PostStatus::Generated, $post->refresh()->status);
        $this->assertSame(0, $post->attempts);
        $this->assertNull($post->last_error);
        $rendered = $post->rendered;
        $retried = app(LearningPipeline::class)->runFor($this->user, $this->today);

        $this->assertTrue($retried->is($post));
        $this->assertSame($rendered, $retried->rendered);
        $this->assertSame(PostStatus::Generated, $retried->status);
        $this->assertDatabaseCount('learning_posts', 2);
        $this->assertDatabaseCount('learning_topic_usages', 1);
        LearningWriter::assertPromptedTimes(1);
    }

    public function test_generation_failure_records_the_error_and_retry_keeps_the_same_post_and_topic(): void
    {
        LearningTopic::factory()->create(['title' => 'Mutation testing', 'category' => 'testing']);
        LearningWriter::fake(fn () => throw new ProviderOverloadedException('overloaded'))->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->once()->andReturn(0);

        try {
            app(LearningPipeline::class)->runFor($this->user, $this->today);
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException $exception) {
            $this->assertStringContainsString('overloaded', $exception->getMessage());
            $error = $exception->getMessage();
        }

        $failed = Post::query()->firstOrFail();
        $this->assertSame(PostStatus::Failed, $failed->status);
        $this->assertSame(1, $failed->attempts);
        $this->assertSame($error, $failed->last_error);
        $this->assertNull($failed->rendered);
        $this->assertDatabaseCount('learning_posts', 0);
        $usage = $this->user->learningTopicUsages()->sole();
        $this->assertSame($this->topic->id, $usage->learning_topic_id);
        LearningWriter::fake([$this->answer()])->preventStrayPrompts();

        $post = app(LearningPipeline::class)->runFor($this->user, $this->today);

        $this->assertTrue($post->is($failed));
        $this->assertSame(PostStatus::Generated, $post->status);
        $this->assertSame($this->topic->id, $post->learningPosts()->where('kind', LearningKind::Topic)->value('learning_topic_id'));
        $this->assertSame($usage->id, $this->user->learningTopicUsages()->sole()->id);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_final_generation_failure_only_deletes_this_users_usage_for_today(): void
    {
        $other = $this->learningUser();
        $otherUsage = LearningTopicUsage::factory()->create(['user_id' => $other->id, 'learning_topic_id' => $this->topic->id, 'used_on' => $this->today]);
        $pastUsage = LearningTopicUsage::factory()->create(['user_id' => $this->user->id, 'learning_topic_id' => $this->topic->id, 'used_on' => $this->today->subDay()]);
        LearningWriter::fake(fn () => throw new ProviderOverloadedException('overloaded'))->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');

        foreach ([1, 2] as $attempt) {
            try {
                app(LearningPipeline::class)->runFor($this->user, $this->today);
                $this->fail('Expected AiUnavailableException');
            } catch (AiUnavailableException $exception) {
                $this->assertStringContainsString('overloaded', $exception->getMessage());
            }

            $post = Post::query()->where('user_id', $this->user->id)->sole();
            $this->assertSame(PostStatus::Failed, $post->status);
            $this->assertSame($attempt, $post->attempts);
            $this->assertSame($attempt === 1 ? 1 : 0, $this->user->learningTopicUsages()->whereDate('used_on', $this->today)->count());
            $this->assertModelExists($otherUsage);
            $this->assertModelExists($pastUsage);
        }

        $picked = app(TopicPicker::class)->nextFor($this->user, $this->today->addDay());
        $this->assertTrue($picked->is($this->topic));
        $this->assertDatabaseHas('learning_topic_usages', ['user_id' => $this->user->id, 'learning_topic_id' => $this->topic->id, 'used_on' => '2026-10-17']);
        $this->assertDatabaseCount('learning_posts', 0);
    }

    public function test_dry_run_returns_rendered_content_but_rolls_back_posts_usages_and_learning_posts(): void
    {
        LearningWriter::fake([$this->answer()])->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');

        $post = app(LearningPipeline::class)->runFor($this->user, $this->today, dryRun: true);

        $this->assertSame($this->user->id, $post->user_id);
        $this->assertSame(PostStatus::Generated, $post->status);
        $this->assertStringContainsString('**In practice:** Add a unique constraint in Django.', implode("\n", $post->rendered['messages']));
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('learning_topic_usages', 0);
        $this->assertDatabaseCount('learning_posts', 0);
    }

    public function test_failed_dry_run_rethrows_and_rolls_back_the_post_and_usage(): void
    {
        LearningWriter::fake(fn () => throw new ProviderOverloadedException('overloaded'))->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldNotReceive('deliver');

        try {
            app(LearningPipeline::class)->runFor($this->user, $this->today, dryRun: true);
            $this->fail('Expected AiUnavailableException');
        } catch (AiUnavailableException) {
        }

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('learning_topic_usages', 0);
        $this->assertDatabaseCount('learning_posts', 0);
    }

    public function test_two_users_get_separate_posts_and_usages_on_the_same_day(): void
    {
        $other = $this->learningUser();
        $news = Post::factory()->create(['user_id' => $this->user->id, 'date' => $this->today, 'kind' => PostKind::News]);
        LearningWriter::fake([$this->answer(), $this->answer()])->preventStrayPrompts();
        $this->mock(PostDeliverer::class)->shouldReceive('deliver')->twice()
            ->withArgs(fn (Post $post) => in_array($post->user_id, [$this->user->id, $other->id], true))->andReturn(0);

        $first = app(LearningPipeline::class)->runFor($this->user, $this->today);
        $second = app(LearningPipeline::class)->runFor($other, $this->today);

        $this->assertFalse($first->is($second));
        $this->assertSame($this->user->id, $first->user_id);
        $this->assertSame($other->id, $second->user_id);
        $this->assertSame(2, $first->learningPosts()->count());
        $this->assertSame(2, $second->learningPosts()->count());
        foreach ([$this->user, $other] as $user) {
            $this->assertDatabaseHas('learning_topic_usages', ['user_id' => $user->id, 'learning_topic_id' => $this->topic->id, 'used_on' => '2026-10-16']);
            LearningWriter::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider->name() === "user_{$user->id}_gemini");
        }
        $this->assertSame(PostStatus::Pending, $news->refresh()->status);
        $this->assertDatabaseCount('posts', 3);
        $this->assertDatabaseCount('learning_topic_usages', 2);
    }

    private function learningUser(string $profile = 'A Python and Django developer.'): User
    {
        $user = User::factory()->create();
        $user->interests()->attach(Interest::factory()->create(['has_learning' => true]));
        DigestSettings::factory()->create(['user_id' => $user->id, 'profile' => $profile]);
        AiKey::factory()->create(['user_id' => $user->id, 'model' => 'user-model']);

        return $user;
    }

    private function tipHistory(User $user, CarbonImmutable $date, string $title): void
    {
        $post = Post::factory()->create(['user_id' => $user->id, 'date' => $date, 'kind' => PostKind::Learning, 'status' => PostStatus::Generated]);
        $post->learningPosts()->create(['kind' => LearningKind::Tip, 'title' => $title, 'body' => '…', 'links' => []]);
    }

    /**
     * @return array<string, mixed>
     */
    private function answer(): array
    {
        return [
            'tip' => ['title' => 'Trace a slow query', 'body' => 'Use an execution plan.', 'tags' => ['databases']],
            'topic' => [
                'body' => 'Retries can be safe.',
                'stack_angle' => 'Add a unique constraint in Django.',
                'links' => [['title' => 'Alive', 'url' => 'https://ok.test/a'], ['title' => 'Dead', 'url' => 'https://dead.test/b']],
                'tags' => ['engineering'],
            ],
        ];
    }
}
