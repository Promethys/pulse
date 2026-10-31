<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\Learning\LearningPipeline;
use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Delivery;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\LearningTopicUsage;
use App\Models\Post;
use App\Models\PostItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DigestLearningCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-07-01 16:00:00', 'UTC'));
        $this->freezeSecond();
        config(['digest.timezone' => 'Europe/Paris']);
        $this->user = User::factory()->create();
        DigestSettings::factory()->create(['user_id' => $this->user->id, 'timezone' => 'Asia/Tokyo']);
        $this->user->interests()->attach(Interest::factory()->create(['has_learning' => true]));
    }

    public function test_user_option_is_required_and_prints_an_error(): void
    {
        $this->mock(LearningPipeline::class)->shouldNotReceive('run', 'runFor');

        $this->artisan('digest:learning')
            ->expectsOutputToContain('--user')
            ->assertExitCode(1);
    }

    public function test_unknown_user_fails_without_running_the_pipeline(): void
    {
        $this->mock(LearningPipeline::class)->shouldNotReceive('run', 'runFor');

        $this->artisan('digest:learning', ['--user' => 'missing@example.test'])
            ->expectsOutput()
            ->assertExitCode(1);
    }

    public function test_runs_for_the_selected_user_and_their_local_today(): void
    {
        $pipeline = $this->mock(LearningPipeline::class);
        $pipeline->shouldNotReceive('run');
        $pipeline->shouldReceive('runFor')->once()->withArgs(fn (User $user, CarbonImmutable $date, bool $dryRun = false) => $user->is($this->user)
            && $date->toDateTimeString() === '2026-07-02 00:00:00'
            && $date->timezoneName === 'Asia/Tokyo' && ! $dryRun)
            ->andReturn(new Post(['status' => PostStatus::Generated]));

        $this->artisan('digest:learning', ['--user' => $this->user->email])->assertExitCode(0);
    }

    public function test_dry_run_prints_the_title_and_all_messages(): void
    {
        $pipeline = $this->mock(LearningPipeline::class);
        $pipeline->shouldNotReceive('run');
        $pipeline->shouldReceive('runFor')->once()->withArgs(fn (User $user, CarbonImmutable $date, bool $dryRun = false) => $user->is($this->user)
            && $date->toDateTimeString() === '2026-07-02 00:00:00'
            && $date->timezoneName === 'Asia/Tokyo' && $dryRun)
            ->andReturn(new Post(['rendered' => ['title' => 'Learning for Tokyo', 'messages' => ['First message', 'Second message']]]));

        $this->artisan('digest:learning', ['--user' => $this->user->email, '--dry-run' => true])
            ->expectsOutput('Learning for Tokyo')
            ->expectsOutput('---')
            ->expectsOutput('First message')
            ->expectsOutput('---')
            ->expectsOutput('Second message')
            ->assertExitCode(0);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_pipeline_failure_prints_the_reason_and_fails(): void
    {
        $this->mock(LearningPipeline::class)->shouldReceive('runFor')->once()->andThrow(new RuntimeException('Provider unavailable'));

        $this->artisan('digest:learning', ['--user' => $this->user->email])
            ->expectsOutput('Provider unavailable')
            ->assertExitCode(1);
    }

    public function test_force_deletes_only_the_users_local_today_post_and_its_children_before_running(): void
    {
        $target = Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-07-02', 'kind' => PostKind::Learning, 'status' => PostStatus::Generated, 'rendered' => ['title' => 'Old', 'messages' => ['Old']]]);
        $pick = PostItem::factory()->create(['post_id' => $target->id]);
        $delivery = Delivery::factory()->create(['post_id' => $target->id]);
        $kept = [
            Post::factory()->create(['user_id' => User::factory(), 'date' => '2026-07-02', 'kind' => PostKind::Learning]),
            Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-07-01', 'kind' => PostKind::Learning]),
            Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-07-02', 'kind' => PostKind::News]),
        ];
        $otherPick = PostItem::factory()->create(['post_id' => $kept[0]->id]);
        $otherDelivery = Delivery::factory()->create(['post_id' => $kept[0]->id]);
        $usage = LearningTopicUsage::factory()->create(['user_id' => $this->user->id, 'used_on' => '2026-07-02']);
        $written = $target->learningPosts()->create(['kind' => LearningKind::Topic, 'learning_topic_id' => $usage->learning_topic_id, 'title' => 'Old topic', 'body' => 'Old body', 'links' => []]);
        $otherWritten = $kept[0]->learningPosts()->create(['kind' => LearningKind::Tip, 'title' => 'Keep tip', 'body' => 'Keep body', 'links' => []]);
        $usageBefore = $usage->fresh()->getAttributes();
        $this->mock(LearningPipeline::class)->shouldReceive('runFor')->once()->andReturnUsing(function (User $user, CarbonImmutable $date, bool $dryRun = false) use ($target, $pick, $delivery, $kept, $otherPick, $otherDelivery, $usage, $written, $otherWritten, $usageBefore) {
            $this->assertTrue($user->is($this->user));
            $this->assertSame('2026-07-02 00:00:00', $date->toDateTimeString());
            $this->assertSame('Asia/Tokyo', $date->timezoneName);
            $this->assertFalse($dryRun);
            foreach ([$target, $pick, $delivery] as $deleted) {
                $this->assertModelMissing($deleted);
            }
            foreach ([...$kept, $otherPick, $otherDelivery] as $retained) {
                $this->assertModelExists($retained);
            }
            $this->assertModelMissing($written);
            $this->assertModelExists($otherWritten);
            $this->assertEquals($usageBefore, $usage->fresh()->getAttributes());
            $this->assertTrue($user->learningTopicUsages()->whereDate('used_on', $date->toDateString())->sole()->is($usage));

            return Post::factory()->create(['user_id' => $user->id, 'date' => $date, 'kind' => PostKind::Learning]);
        });

        $this->artisan('digest:learning', ['--user' => $this->user->email, '--force' => true])->assertExitCode(0);

        $this->assertDatabaseCount('posts', 4);
        $this->assertModelMissing($target);
        $this->assertSame(1, $this->user->posts()->where('date', '2026-07-02')->where('kind', PostKind::Learning)->count());
    }

    public function test_force_with_dry_run_keeps_the_existing_post_and_children(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id, 'date' => '2026-07-02', 'kind' => PostKind::Learning]);
        $pick = PostItem::factory()->create(['post_id' => $post->id]);
        $delivery = Delivery::factory()->create(['post_id' => $post->id]);
        $before = $post->fresh()->getAttributes();
        $this->mock(LearningPipeline::class)->shouldReceive('runFor')->once()->withArgs(function (User $user, CarbonImmutable $date, bool $dryRun = false) use ($post, $pick, $delivery, $before) {
            $this->assertEquals($before, $post->fresh()->getAttributes());
            $this->assertModelExists($pick);
            $this->assertModelExists($delivery);

            return $user->is($this->user) && $date->toDateString() === '2026-07-02' && $dryRun;
        })->andReturn(new Post(['rendered' => ['title' => 'Preview', 'messages' => ['Body']]]));

        $this->artisan('digest:learning', ['--user' => $this->user->email, '--force' => true, '--dry-run' => true])
            ->expectsOutput('Preview')->expectsOutput('---')->expectsOutput('Body')->assertExitCode(0);

        $this->assertEquals($before, $post->fresh()->getAttributes());
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_user_without_a_learning_interest_prints_the_reason_and_fails(): void
    {
        $this->user->interests()->detach();
        $this->user->interests()->attach(Interest::factory()->create(['has_learning' => false]));
        $this->partialMock(LearningPipeline::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('run');
            $mock->shouldReceive('runFor')->once()->passthru();
        });

        $this->artisan('digest:learning', ['--user' => $this->user->email])
            ->expectsOutput('Learning is unavailable for this user.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('posts', 0);
    }
}
