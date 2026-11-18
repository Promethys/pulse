<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Actions\RunDigestNow;
use App\Digest\Manual\RunEligibility;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Jobs\RunDigest;
use App\Models\AiKey;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManualRunTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('refusals')]
    public function test_eligibility_and_action_refuse_each_reason(string $reason): void
    {
        Queue::fake();
        $user = User::factory()->onboarded()->create();
        AiKey::factory()->for($user)->create();
        $kind = PostKind::News;
        match ($reason) {
            'not_onboarded' => $user->digestSettings->forceFill(['onboarded_at' => null])->save(),
            'not_verified' => $user->forceFill(['email_verified_at' => null])->save(),
            'no_ai' => $user->aiKeys()->delete(),
            'paused' => $user->digestSettings->forceFill(['paused_at' => now()])->save(),
            'kind_disabled' => $user->digestSettings->update(['news_enabled' => false]),
            'unsupported_kind' => $kind = PostKind::Learning,
            'already_has_post' => Post::factory()->for($user)->create(['kind' => PostKind::News]),
        };
        $this->assertSame($reason, app(RunEligibility::class)->check($user, $kind));
        try {
            app(RunDigestNow::class)->handle($user, $kind);
            $this->fail('Expected an eligibility error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('kind', $exception->errors());
        }
        Queue::assertNothingPushed();
    }

    public static function refusals(): array
    {
        return array_map(fn (string $reason) => [$reason], array_combine(
            ['not_onboarded', 'not_verified', 'no_ai', 'paused', 'kind_disabled', 'unsupported_kind', 'already_has_post'],
            ['not_onboarded', 'not_verified', 'no_ai', 'paused', 'kind_disabled', 'unsupported_kind', 'already_has_post'],
        ));
    }

    #[DataProvider('aiModes')]
    public function test_creates_pending_post_and_dispatches_for_local_today(bool $instance): void
    {
        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 15:00:00', 'UTC'));
        $user = User::factory()->onboarded()->create(['uses_instance_ai' => $instance]);
        $user->digestSettings->update(['timezone' => 'Asia/Tokyo']);
        if (! $instance) {
            AiKey::factory()->for($user)->create();
        }
        $this->assertNull(app(RunEligibility::class)->check($user, PostKind::News));
        app(RunDigestNow::class)->handle($user, PostKind::News);
        $this->assertDatabaseHas('posts', ['user_id' => $user->id, 'date' => '2026-11-16', 'kind' => 'news', 'status' => PostStatus::Pending->value]);
        Queue::assertPushed(RunDigest::class, fn (RunDigest $job) => $job->user->is($user) && $job->kind === PostKind::News && $job->date->toDateString() === '2026-11-16');
        Queue::assertPushed(RunDigest::class, 1);
    }

    public static function aiModes(): array
    {
        return ['personal key' => [false], 'instance AI' => [true]];
    }

    public function test_endpoint_requires_authentication_and_valid_kind(): void
    {
        $this->post('/digest/run', ['kind' => 'news'])->assertRedirect(route('login'));
        $user = User::factory()->onboarded()->create();
        $this->actingAs($user)->post('/digest/run', [])->assertSessionHasErrors('kind');
        $other = User::factory()->onboarded()->create();
        $this->actingAs($other)->post('/digest/run', ['kind' => 'invalid'])->assertSessionHasErrors('kind');
    }

    public function test_endpoint_redirects_without_a_toast_then_throttles(): void
    {
        Queue::fake();
        $user = User::factory()->onboarded()->create(['uses_instance_ai' => true]);
        $this->actingAs($user)->from('/dashboard')->post('/digest/run', ['kind' => 'news'])
            ->assertRedirect('/dashboard')->assertInertiaFlashMissing('toast');
        $this->from('/dashboard')->post('/digest/run', ['kind' => 'news'])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['kind' => 'Please wait 10 minutes before trying again.']);
        Queue::assertPushed(RunDigest::class, 1);
    }

    public function test_endpoint_refuses_existing_post_and_today_exposes_eligibility(): void
    {
        $user = User::factory()->onboarded()->create(['uses_instance_ai' => true]);
        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('can_run_first_digest', true));
        Post::factory()->for($user)->create(['kind' => PostKind::News]);
        $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('can_run_first_digest', false));
        $this->post('/digest/run', ['kind' => 'news'])
            ->assertSessionHasErrors(['kind' => 'Your digest has already been started.']);
    }
}
