<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\TickScheduler;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TickSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['digest.timezone' => 'Europe/Paris', 'digest.news_time' => '09:00', 'digest.learning_time' => '09:30', 'digest.max_attempts' => 4]);
    }

    /**
     * @return array<string, array{string, list<PostKind>}>
     */
    public static function ticks(): array
    {
        return [
            'winter 07:00 UTC = 08:00 Paris' => ['2026-12-01 07:00', []],
            'winter 07:30 UTC = 08:30 Paris' => ['2026-12-01 07:30', []],
            'winter 08:00 UTC = 09:00 Paris' => ['2026-12-01 08:00', [PostKind::News]],
            'winter 08:30 UTC = 09:30 Paris' => ['2026-12-01 08:30', [PostKind::News, PostKind::Learning]],
            'summer 07:00 UTC = 09:00 Paris' => ['2026-07-01 07:00', [PostKind::News]],
            'summer 07:30 UTC = 09:30 Paris' => ['2026-07-01 07:30', [PostKind::News, PostKind::Learning]],
            'summer 08:30 UTC = 10:30 Paris' => ['2026-07-01 08:30', [PostKind::News, PostKind::Learning]],
            'EU switch day 07:00 UTC = 08:00 Paris' => ['2026-10-25 07:00', []],
            'EU switch day 08:00 UTC = 09:00 Paris' => ['2026-10-25 08:00', [PostKind::News]],
        ];
    }

    /**
     * @param  list<PostKind>  $expected
     */
    #[DataProvider('ticks')]
    public function test_due_kinds(string $nowUtc, array $expected): void
    {
        $this->assertSame($expected, (new TickScheduler)->due(CarbonImmutable::parse($nowUtc, 'UTC')));
    }

    public function test_posted_is_not_due(): void
    {
        Post::query()->create(['date' => '2026-07-01', 'kind' => PostKind::News, 'status' => PostStatus::Posted]);

        $this->assertSame([PostKind::Learning], (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 07:30', 'UTC')));
    }

    public function test_a_posted_post_from_another_day_does_not_count(): void
    {
        Post::query()->create(['date' => '2026-06-30', 'kind' => PostKind::News, 'status' => PostStatus::Posted]);

        $this->assertSame([PostKind::News], (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 07:00', 'UTC')));
    }

    public function test_stops_after_max_attempts(): void
    {
        Post::query()->create(['date' => '2026-07-01', 'kind' => PostKind::News, 'status' => PostStatus::Failed, 'attempts' => 4]);
        Post::query()->create(['date' => '2026-07-01', 'kind' => PostKind::Learning, 'status' => PostStatus::Failed, 'attempts' => 3]);

        $this->assertSame([PostKind::Learning], (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 07:30', 'UTC')));
    }
}
