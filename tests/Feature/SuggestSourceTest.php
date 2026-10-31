<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Digest\Sources\Candidate;
use App\Digest\Sources\FeedFetcher;
use App\Digest\Sources\FetchException;
use App\Digest\Sources\SourceFetcher;
use App\Digest\Support\PublicUrl;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Enums\SourceType;
use App\Models\Interest;
use App\Models\InterestProposal;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\SourceSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SuggestSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['93.184.216.34']));
        $this->fakeFeed();
    }

    public function test_suggestion_copies_evidence_to_a_pending_proposal_without_changing_the_custom_source(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create(['url' => 'https://community.test/feed', 'site_url' => 'https://community.test']);
        $interest = Interest::factory()->create();
        $source->interests()->attach($interest);
        $subscription = SourceSubscription::factory()->for($user)->for($source)->create();
        $before = $source->fresh()->getAttributes();
        $beforeSubscription = $subscription->fresh()->getAttributes();

        $this->assertSame(url("/sources/{$source->id}/suggest"), route('sources.suggest', $source));
        $this->actingAs($user)->from('/sources?tab=mine')->post("/sources/{$source->id}/suggest", [
            'interest' => $interest->slug, 'reason' => 'Useful reporting for everyone.',
            'source_id' => $source->id, 'status' => 'approved', 'user_id' => User::factory()->create()->id,
        ])->assertRedirect('/sources?tab=mine')->assertSessionHasNoErrors()->assertInertiaFlash('toast.type', 'success');

        $proposal = SourceProposal::query()->sole();
        $this->assertSame(SourceProposalStatus::Pending, $proposal->status);
        $this->assertSame(SourceProposalOrigin::User, $proposal->origin);
        $this->assertSame(SourceProposalKind::NewSource, $proposal->kind);
        $this->assertSame($user->id, $proposal->user_id);
        $this->assertSame($interest->id, $proposal->interest_id);
        $this->assertNull($proposal->source_id);
        foreach (['url', 'site_url', 'name'] as $field) {
            $this->assertSame($source->$field, $proposal->$field);
        }
        $this->assertSame('Useful reporting for everyone.', $proposal->reason);
        $this->assertSame(['public_url' => true, 'not_in_catalog' => true, 'feed_parses' => true, 'recent_posts' => true], $proposal->checks);
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertSame($beforeSubscription, $subscription->fresh()->getAttributes());
        $this->assertSame([$interest->id], $source->interests()->pluck('interests.id')->all());
        $source->delete();
        $this->assertModelExists($proposal);
    }

    public function test_other_users_sources_and_catalog_sources_are_forbidden(): void
    {
        $this->actingAs(User::factory()->onboarded()->create());
        foreach ([Source::factory()->for(User::factory())->create(), Source::factory()->create()] as $source) {
            $this->postJson("/sources/{$source->id}/suggest", $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('source_proposals', 0);
    }

    public function test_resend_inboxes_are_unprocessable(): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create(['type' => SourceType::ResendInbox, 'url' => null]);
        $this->actingAs($user)->postJson("/sources/{$source->id}/suggest", $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('source_proposals', 0);
    }

    #[DataProvider('duplicateProposals')]
    public function test_a_url_proposed_by_any_user_in_any_status_is_refused(SourceProposalStatus $status, SourceProposalKind $kind): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create();
        SourceProposal::factory()->for(User::factory())->create(['url' => $source->url, 'status' => $status, 'kind' => $kind]);
        $this->actingAs($user)->postJson("/sources/{$source->id}/suggest", $this->payload())->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseCount('source_proposals', 1);
    }

    #[DataProvider('failedChecks')]
    public function test_failed_checks_store_nothing_and_return_readable_reason_errors(string $check, string $messagePattern): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create(['url' => 'https://community.test/feed']);
        $before = $source->fresh()->getAttributes();
        if ($check === 'public_url') {
            $this->app->instance(PublicUrl::class, new PublicUrl(fn (string $host) => ['127.0.0.1']));
        } elseif ($check === 'not_in_catalog') {
            Source::factory()->create(['url' => 'https://community.test/another-feed']);
        } else {
            $this->fakeFeed($check === 'feed_parses' ? -1 : 2);
        }
        $response = $this->actingAs($user)->postJson("/sources/{$source->id}/suggest", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $message = $response->json('errors.reason.0');
        $this->assertIsString($message);
        $this->assertMatchesRegularExpression($messagePattern, $message);
        $this->assertStringNotContainsString('Private parser details', $message);
        foreach (array_keys(self::failedChecks()) as $rawKey) {
            $this->assertStringNotContainsString($rawKey, $message);
        }
        $this->assertDatabaseCount('source_proposals', 0);
        $this->assertSame($before, $source->fresh()->getAttributes());
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_store_nothing(string $field, mixed $value): void
    {
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create();
        $this->actingAs($user)->postJson("/sources/{$source->id}/suggest", array_replace($this->payload(), [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('source_proposals', 0);
    }

    #[DataProvider('openCounts')]
    public function test_ten_open_proposals_across_both_tables_prevent_another_source(int $sources, int $interests): void
    {
        $user = User::factory()->onboarded()->create();
        SourceProposal::factory()->count($sources)->for($user)->create();
        InterestProposal::factory()->count($interests)->for($user)->create();
        $source = Source::factory()->for($user)->create();
        $this->actingAs($user)->postJson("/sources/{$source->id}/suggest", $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseCount('source_proposals', $sources);
        $this->assertDatabaseCount('interest_proposals', $interests);
    }

    #[DataProvider('reasonLengths')]
    public function test_the_tenth_proposal_is_allowed_and_handled_and_other_users_proposals_do_not_count(int $length): void
    {
        $user = User::factory()->onboarded()->create();
        SourceProposal::factory()->count(4)->for($user)->create();
        InterestProposal::factory()->count(5)->for($user)->create();
        foreach ([SourceProposalStatus::Approved, SourceProposalStatus::Rejected] as $status) {
            SourceProposal::factory()->count(6)->for($user)->create(['status' => $status]);
            InterestProposal::factory()->count(6)->for($user)->create(['status' => $status]);
        }
        SourceProposal::factory()->count(10)->for(User::factory())->create();
        InterestProposal::factory()->count(10)->for(User::factory())->create();
        $source = Source::factory()->for($user)->create();
        $this->actingAs($user)->from('/sources')->post("/sources/{$source->id}/suggest", array_replace($this->payload(), ['reason' => str_repeat('a', $length)]))
            ->assertRedirect('/sources')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('source_proposals', ['url' => $source->url, 'user_id' => $user->id, 'status' => 'pending']);
    }

    public function test_authentication_verification_and_onboarding_are_required(): void
    {
        $user = User::factory()->create();
        $source = Source::factory()->for($user)->create();
        $url = "/sources/{$source->id}/suggest";
        $this->post($url, $this->payload())->assertRedirect('/login');
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->post($url, $this->payload())->assertRedirect(route('verification.notice'));
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->post($url, $this->payload())->assertRedirect('/onboarding');
        $this->assertDatabaseCount('source_proposals', 0);
    }

    public function test_seventh_request_is_throttled_and_the_window_expires(): void
    {
        $this->freezeSecond();
        $user = User::factory()->onboarded()->create();
        $source = Source::factory()->for($user)->create();
        $url = "/sources/{$source->id}/suggest";
        $this->actingAs($user);
        foreach (range(1, 6) as $attempt) {
            $this->postJson($url, [])->assertUnprocessable();
        }
        $this->postJson($url, [])->assertTooManyRequests();
        $other = User::factory()->onboarded()->create();
        $otherSource = Source::factory()->for($other)->create();
        $this->actingAs($other)->postJson("/sources/{$otherSource->id}/suggest", [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->actingAs($user)->postJson($url, [])->assertUnprocessable();
        $this->assertDatabaseCount('source_proposals', 0);
    }

    private function payload(): array
    {
        return ['interest' => Interest::factory()->create()->slug, 'reason' => 'Useful reporting for everyone.'];
    }

    private function fakeFeed(int $count = 3): void
    {
        $this->app->instance(FeedFetcher::class, new class($count) implements SourceFetcher
        {
            public function __construct(private int $count) {}

            public function fetch(Source $source, CarbonImmutable $since): array
            {
                if ($this->count < 0) {
                    throw new FetchException('Private parser details');
                }

                return array_map(fn (int $index) => new Candidate('Post '.$index, 'https://community.test/post-'.$index, '', CarbonImmutable::now(), []), range(1, $this->count));
            }
        });
    }

    public static function duplicateProposals(): array
    {
        $cases = [];
        foreach (SourceProposalStatus::cases() as $status) {
            foreach (SourceProposalKind::cases() as $kind) {
                $cases[$status->value.' '.$kind->value] = [$status, $kind];
            }
        }

        return $cases;
    }

    public static function failedChecks(): array
    {
        return [
            'public_url' => ['public_url', '/public|private|accessible/i'],
            'not_in_catalog' => ['not_in_catalog', '/catalog|already/i'],
            'feed_parses' => ['feed_parses', '/feed|read|parse/i'],
            'recent_posts' => ['recent_posts', '/recent|posts|30/i'],
        ];
    }

    public static function invalidFields(): array
    {
        return [
            'missing interest' => ['interest', null], 'unknown interest' => ['interest', 'unknown'],
            'interest id' => ['interest', 123], 'missing reason' => ['reason', null],
            'short reason' => ['reason', '123456789'], 'long reason' => ['reason', str_repeat('a', 501)],
        ];
    }

    public static function openCounts(): array
    {
        return ['sources only' => [10, 0], 'mixed' => [5, 5], 'interests only' => [0, 10], 'already over limit' => [5, 6]];
    }

    public static function reasonLengths(): array
    {
        return ['minimum' => [10], 'maximum' => [500]];
    }

    public function test_testing_feeds_does_not_use_up_the_suggestion_limit(): void
    {
        $user = User::factory()->onboarded()->create();
        $this->actingAs($user);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/sources/test', []);
        }

        $this->postJson('/sources/test', [])->assertTooManyRequests();
        $this->postJson('/interest-proposals', [])->assertUnprocessable();
    }
}
