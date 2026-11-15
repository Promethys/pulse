<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Digest\Learning\LearningPipeline;
use App\Digest\Learning\LearningUnavailableException;
use App\Digest\News\NewsPipeline;
use App\Enums\PostKind;
use App\Jobs\RunDigest;
use App\Models\DigestSettings;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class RunDigestTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('pausedDays')]
    public function test_skips_both_pipelines_and_logs_only_the_user_id_on_a_paused_day(PostKind $kind, ?string $pausedUntil): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-05', 'UTC'));
        $user = User::factory()->create();
        DigestSettings::factory()->create(['user_id' => $user->id, 'paused_at' => '2026-07-01 12:00:00', 'paused_until' => $pausedUntil]);
        $date = CarbonImmutable::parse('2026-07-02', 'Asia/Tokyo');
        $news = $this->mock(NewsPipeline::class);
        $learning = $this->mock(LearningPipeline::class);
        $news->shouldNotReceive('run', 'runFor');
        $learning->shouldNotReceive('run', 'runFor');
        Log::shouldReceive('info')->once()->with('Digest skipped, account paused', ['user_id' => $user->id]);

        (new RunDigest($user, $kind, $date))->handle($news, $learning);
    }

    #[DataProvider('kinds')]
    public function test_runs_normally_the_day_after_the_pause_ends(PostKind $kind): void
    {
        $user = User::factory()->create();
        DigestSettings::factory()->create(['user_id' => $user->id, 'paused_at' => '2026-07-01 12:00:00', 'paused_until' => '2026-07-01']);
        $date = CarbonImmutable::parse('2026-07-02', 'Asia/Tokyo');
        $news = $this->mock(NewsPipeline::class);
        $learning = $this->mock(LearningPipeline::class);
        ($kind === PostKind::News ? $news : $learning)->shouldReceive('runFor')->once()->with($user, $date)->andReturn(new Post);
        ($kind === PostKind::News ? $learning : $news)->shouldNotReceive('run', 'runFor');

        (new RunDigest($user, $kind, $date))->handle($news, $learning);
    }

    #[DataProvider('kinds')]
    public function test_respects_a_pause_saved_after_the_job_was_built_with_cached_settings(PostKind $kind): void
    {
        $user = User::factory()->create();
        $settings = DigestSettings::factory()->create(['user_id' => $user->id]);
        $user->load('digestSettings');
        $date = CarbonImmutable::parse('2026-07-02', 'Asia/Tokyo');
        $job = new RunDigest($user, $kind, $date);
        $settings->forceFill(['paused_at' => '2026-07-01 12:00:00', 'paused_until' => null])->save();
        $news = $this->mock(NewsPipeline::class);
        $learning = $this->mock(LearningPipeline::class);
        $news->shouldNotReceive('run', 'runFor');
        $learning->shouldNotReceive('run', 'runFor');
        Log::shouldReceive('info')->once()->with('Digest skipped, account paused', ['user_id' => $user->id]);

        $job->handle($news, $learning);
    }

    #[DataProvider('kinds')]
    public function test_calls_only_the_matching_per_user_pipeline(PostKind $kind): void
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::parse('2026-07-02', 'Asia/Tokyo');
        $news = $this->mock(NewsPipeline::class);
        $learning = $this->mock(LearningPipeline::class);
        $selected = $kind === PostKind::News ? $news : $learning;
        $other = $kind === PostKind::News ? $learning : $news;
        $selected->shouldNotReceive('run');
        $other->shouldNotReceive('run', 'runFor');
        $selected->shouldReceive('runFor')->once()->withArgs(fn (User $actual, CarbonImmutable $day, bool $dryRun = false) => $actual->is($user)
            && $day->equalTo($date) && $day->timezoneName === 'Asia/Tokyo' && ! $dryRun)->andReturn(new Post);

        $job = unserialize(serialize(new RunDigest($user, $kind, $date)));

        $job->handle($news, $learning);
    }

    public function test_queue_uniqueness_is_scoped_to_user_kind_and_local_date_for_fifteen_minutes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $date = CarbonImmutable::parse('2026-07-02', 'Asia/Tokyo');
        $job = new RunDigest($user, PostKind::News, $date);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame($user->id.':news:2026-07-02', $job->uniqueId());
        $this->assertSame(900, $job->uniqueFor);
        $this->assertSame(1, $job->tries);
        $this->assertSame($job->uniqueId(), (new RunDigest($user, PostKind::News, $date->setTime(12, 0)))->uniqueId());
        foreach ([new RunDigest($other, PostKind::News, $date), new RunDigest($user, PostKind::Learning, $date), new RunDigest($user, PostKind::News, $date->addDay())] as $different) {
            $this->assertNotSame($job->uniqueId(), $different->uniqueId());
        }
    }

    public function test_timeout_covers_a_slow_ai_call_and_stays_below_the_queue_retry_window(): void
    {
        $job = new RunDigest(User::factory()->create(), PostKind::News, CarbonImmutable::parse('2026-07-02', 'UTC'));

        $this->assertSame(300, $job->timeout);
        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
    }

    public function test_learning_unavailable_is_logged_and_swallowed(): void
    {
        $user = User::factory()->create();
        $date = CarbonImmutable::parse('2026-07-01', 'UTC');
        $news = $this->mock(NewsPipeline::class);
        $news->shouldNotReceive('run', 'runFor');
        $learning = $this->mock(LearningPipeline::class);
        $learning->shouldReceive('runFor')->once()->andThrow(new LearningUnavailableException('Learning is unavailable for this user.'));
        $messages = [];
        Log::listen(function (MessageLogged $event) use (&$messages): void {
            $messages[] = $event->message.' '.json_encode($event->context);
        });

        (new RunDigest($user, PostKind::Learning, $date))->handle($news, $learning);

        $this->assertTrue(collect($messages)->contains(fn (string $message) => str_contains($message, 'Learning is unavailable for this user.')));
    }

    #[DataProvider('kinds')]
    public function test_other_exceptions_propagate_to_the_queue(PostKind $kind): void
    {
        $news = $this->mock(NewsPipeline::class);
        $learning = $this->mock(LearningPipeline::class);
        $failure = new RuntimeException('Provider unavailable');
        ($kind === PostKind::News ? $news : $learning)->shouldReceive('runFor')->once()->andThrow($failure);
        ($kind === PostKind::News ? $learning : $news)->shouldNotReceive('runFor');
        $job = new RunDigest(User::factory()->create(), $kind, CarbonImmutable::parse('2026-07-01', 'UTC'));
        $this->expectExceptionObject($failure);

        $job->handle($news, $learning);
    }

    /** @return array<string, array{PostKind, string|null}> */
    public static function pausedDays(): array
    {
        $days = [];
        foreach (PostKind::cases() as $kind) {
            foreach (['indefinite' => null, 'last paused day' => '2026-07-02', 'before last paused day' => '2026-07-03'] as $name => $pausedUntil) {
                $days[$kind->value.' '.$name] = [$kind, $pausedUntil];
            }
        }

        return $days;
    }

    /** @return array<string, array{PostKind}> */
    public static function kinds(): array
    {
        return ['news' => [PostKind::News], 'learning' => [PostKind::Learning]];
    }
}
