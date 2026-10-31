<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Digest\DueDigest;
use App\Digest\TickScheduler;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Models\AiKey;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\Post;
use App\Models\User;
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
        config(['digest.max_attempts' => 4, 'digest.timezone' => 'UTC', 'digest.news_time' => '00:00', 'digest.learning_time' => '00:00']);
    }

    #[DataProvider('ticks')]
    public function test_uses_the_users_times_including_summer_and_winter(string $now, array $kinds): void
    {
        $user = $this->eligibleUser();
        $due = (new TickScheduler)->due(CarbonImmutable::parse($now, 'UTC'));

        $this->assertCount(count($kinds), $due);
        foreach ($due as $index => $digest) {
            $this->assertInstanceOf(DueDigest::class, $digest);
            $this->assertTrue($digest->user->is($user));
            $this->assertSame($kinds[$index], $digest->kind);
            $this->assertSame(substr($now, 0, 10).' 00:00:00', $digest->date->toDateTimeString());
            $this->assertSame('Europe/Paris', $digest->date->timezoneName);
        }
    }

    public function test_returns_users_in_id_order_with_news_before_learning_and_each_local_date(): void
    {
        $tokyo = $this->eligibleUser(['timezone' => 'Asia/Tokyo', 'news_time' => '00:00', 'learning_time' => '00:00']);
        $paris = $this->eligibleUser();
        $due = (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 16:00', 'UTC'));

        $this->assertCount(4, $due);
        foreach ($due as $index => $digest) {
            $this->assertInstanceOf(DueDigest::class, $digest);
            $this->assertSame($index < 2 ? $tokyo->id : $paris->id, $digest->user->id);
            $this->assertSame($index % 2 === 0 ? PostKind::News : PostKind::Learning, $digest->kind);
            $this->assertSame($index < 2 ? '2026-07-02 00:00:00' : '2026-07-01 00:00:00', $digest->date->toDateTimeString());
            $this->assertSame($index < 2 ? 'Asia/Tokyo' : 'Europe/Paris', $digest->date->timezoneName);
        }
    }

    #[DataProvider('ineligibleUsers')]
    public function test_skips_ineligible_users_without_affecting_an_eligible_user(string $reason): void
    {
        $excluded = $this->eligibleUser();
        match ($reason) {
            'unverified' => $excluded->forceFill(['email_verified_at' => null])->save(),
            'not onboarded' => $excluded->digestSettings->forceFill(['onboarded_at' => null])->save(),
            'no settings' => $excluded->digestSettings()->delete(),
            'no AI' => $excluded->aiKeys()->delete(),
        };
        $eligible = $this->eligibleUser();
        $due = (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 12:00', 'UTC'));

        $this->assertCount(2, $due);
        foreach ($due as $digest) {
            $this->assertInstanceOf(DueDigest::class, $digest);
            $this->assertTrue($digest->user->is($eligible));
        }
    }

    public function test_instance_ai_is_enough_without_a_personal_key(): void
    {
        $user = $this->eligibleUser();
        $user->aiKeys()->delete();
        $user->forceFill(['uses_instance_ai' => true])->save();
        $due = (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 12:00', 'UTC'));

        $this->assertCount(2, $due);
        $this->assertInstanceOf(DueDigest::class, $due[0]);
        $this->assertTrue($due[0]->user->is($user));
    }

    #[DataProvider('toggles')]
    public function test_respects_each_toggle_and_learning_availability(bool $news, bool $learning, bool $available, array $expected): void
    {
        $user = $this->eligibleUser(['news_enabled' => $news, 'learning_enabled' => $learning]);
        if (! $available) {
            $user->interests()->detach();
            $user->interests()->attach(Interest::factory()->create(['has_learning' => false]));
        }
        $due = (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 12:00', 'UTC'));

        $this->assertCount(count($expected), $due);
        foreach ($due as $index => $digest) {
            $this->assertInstanceOf(DueDigest::class, $digest);
            $this->assertSame($expected[$index], $digest->kind);
        }
    }

    #[DataProvider('postStates')]
    public function test_generation_depends_on_rendered_and_attempts_for_each_kind(PostKind $kind, PostStatus $status, ?array $rendered, int $attempts, bool $expected): void
    {
        $user = $this->eligibleUser();
        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-07-01', 'kind' => $kind, 'status' => $status, 'rendered' => $rendered, 'attempts' => $attempts]);
        $due = (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 12:00', 'UTC'));

        $this->assertCount($expected ? 2 : 1, $due);
        foreach ($due as $digest) {
            $this->assertInstanceOf(DueDigest::class, $digest);
        }
        $this->assertSame($expected, in_array($kind, array_map(fn (DueDigest $digest) => $digest->kind, $due), true));
    }

    public function test_other_users_posts_and_other_days_do_not_suppress_a_users_digest(): void
    {
        $user = $this->eligibleUser();
        foreach ([User::factory()->create()->id] as $owner) {
            Post::factory()->create(['user_id' => $owner, 'date' => '2026-07-01', 'kind' => PostKind::News, 'rendered' => ['title' => 'Done', 'messages' => ['Done']]]);
        }
        Post::factory()->create(['user_id' => $user->id, 'date' => '2026-06-30', 'kind' => PostKind::News, 'attempts' => 4]);
        $due = (new TickScheduler)->due(CarbonImmutable::parse('2026-07-01 12:00', 'UTC'));

        $this->assertCount(2, $due);
        $this->assertInstanceOf(DueDigest::class, $due[0]);
        $this->assertTrue($due[0]->user->is($user));
        $this->assertSame(PostKind::News, $due[0]->kind);
    }

    /** @return array<string, array{string, list<PostKind>}> */
    public static function ticks(): array
    {
        return [
            'winter before news' => ['2026-12-01 07:59:59', []],
            'winter news boundary' => ['2026-12-01 08:00:00', [PostKind::News]],
            'winter before learning' => ['2026-12-01 08:29:59', [PostKind::News]],
            'winter learning boundary' => ['2026-12-01 08:30:00', [PostKind::News, PostKind::Learning]],
            'summer news boundary' => ['2026-07-01 07:00:00', [PostKind::News]],
            'summer learning boundary' => ['2026-07-01 07:30:00', [PostKind::News, PostKind::Learning]],
            'DST switch before news' => ['2026-10-25 07:00:00', []],
            'DST switch at news' => ['2026-10-25 08:00:00', [PostKind::News]],
        ];
    }

    /** @return array<string, array{string}> */
    public static function ineligibleUsers(): array
    {
        return ['unverified' => ['unverified'], 'not onboarded' => ['not onboarded'], 'no settings' => ['no settings'], 'no AI' => ['no AI']];
    }

    /** @return array<string, array{bool, bool, bool, list<PostKind>}> */
    public static function toggles(): array
    {
        return [
            'news disabled' => [false, true, true, [PostKind::Learning]],
            'learning disabled' => [true, false, true, [PostKind::News]],
            'both disabled' => [false, false, true, []],
            'no learning interest' => [true, true, false, [PostKind::News]],
        ];
    }

    /** @return array<string, array{PostKind, PostStatus, array<string, mixed>|null, int, bool}> */
    public static function postStates(): array
    {
        $states = [];
        foreach (PostKind::cases() as $kind) {
            foreach ([
                'pending' => [PostStatus::Pending, null, 0, true],
                'retry below limit' => [PostStatus::Failed, null, 3, true],
                'at limit' => [PostStatus::Failed, null, 4, false],
                'above limit' => [PostStatus::Failed, null, 5, false],
                'generated' => [PostStatus::Generated, ['title' => 'Done', 'messages' => ['Done']], 0, false],
                'delivery failed' => [PostStatus::Failed, ['title' => 'Done', 'messages' => ['Done']], 1, false],
            ] as $name => $state) {
                $states[$kind->value.' '.$name] = [$kind, ...$state];
            }
        }

        return $states;
    }

    /** @param  array<string, mixed>  $settings */
    private function eligibleUser(array $settings = []): User
    {
        $user = User::factory()->create();
        DigestSettings::factory()->create(['user_id' => $user->id, 'timezone' => 'Europe/Paris', 'onboarded_at' => now(), ...$settings]);
        AiKey::factory()->create(['user_id' => $user->id]);
        $user->interests()->attach(Interest::factory()->create(['has_learning' => true]));

        return $user;
    }
}
