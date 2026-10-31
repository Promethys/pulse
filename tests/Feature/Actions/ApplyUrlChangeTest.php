<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\ApplyUrlChange;
use App\Enums\InterestStatus;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalStatus;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\SourceSubscription;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ApplyUrlChangeTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('sourceStates')]
    public function test_url_change_clears_error_restores_only_retired_sources_and_preserves_subscriptions(bool $enabled, bool $retired): void
    {
        $source = Source::factory()->create([
            'enabled' => $enabled,
            'retired_at' => $retired ? now()->subDay() : null,
            'last_error' => 'HTTP 410',
            'last_fetched_at' => now()->subHours(2),
            'last_success_at' => now()->subMonth(),
        ]);
        $interest = Interest::factory()->create();
        $source->interests()->attach($interest);
        SourceSubscription::factory()->for($source)->create(['enabled' => true]);
        SourceSubscription::factory()->for($source)->create(['enabled' => false]);
        $subscriptions = SourceSubscription::query()->orderBy('id')->get()->map->getAttributes()->all();
        $before = $source->fresh()->getAttributes();
        $proposal = SourceProposal::factory()->for($source)->create(['kind' => SourceProposalKind::UrlChange]);

        $result = (new ApplyUrlChange)->handle($proposal);

        $this->assertTrue($result->is($source));
        $this->assertSame($proposal->url, $source->refresh()->url);
        $this->assertNull($source->last_error);
        $this->assertSame($retired || $enabled, $source->enabled);
        $this->assertNull($source->retired_at);
        $this->assertSame(
            array_diff_key($before, array_flip(['url', 'last_error', 'enabled', 'retired_at', 'updated_at'])),
            array_diff_key($source->getAttributes(), array_flip(['url', 'last_error', 'enabled', 'retired_at', 'updated_at'])),
        );
        $this->assertSame($subscriptions, SourceSubscription::query()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame([$interest->id], $source->interests()->pluck('interests.id')->all());
        $this->assertSame(SourceProposalStatus::Approved, $proposal->refresh()->status);
        $this->assertSame($source->id, $proposal->source_id);
        $this->assertDatabaseCount('sources', 1);
    }

    public function test_restoring_a_retired_default_source_refreshes_interest_statuses_without_subscribing_users(): void
    {
        $interest = Interest::factory()->create();
        $interest->sources()->attach(Source::factory()->count(2)->create(['is_default' => true])->modelKeys());
        $source = Source::factory()->create(['is_default' => true, 'enabled' => false, 'retired_at' => now()]);
        $interest->sources()->attach($source);
        $unrelated = Interest::factory()->create(['status' => InterestStatus::Live]);
        $proposal = SourceProposal::factory()->for($source)->create(['kind' => SourceProposalKind::UrlChange]);

        (new ApplyUrlChange)->handle($proposal);

        $this->assertSame(InterestStatus::Live, $interest->refresh()->status);
        $this->assertSame(InterestStatus::Draft, $unrelated->refresh()->status);
        $this->assertDatabaseCount('source_subscriptions', 0);
    }

    #[DataProvider('invalidProposals')]
    public function test_invalid_proposals_throw_without_changing_or_creating_anything(SourceProposalStatus $status, SourceProposalKind $kind): void
    {
        $source = Source::factory()->create(['enabled' => false, 'retired_at' => now(), 'last_error' => 'HTTP 410']);
        $subscription = SourceSubscription::factory()->for($source)->create(['enabled' => false]);
        $interest = Interest::factory()->create(['status' => InterestStatus::Live]);
        $proposal = SourceProposal::factory()->for($source)->create(['status' => $status, 'kind' => $kind]);
        $beforeSource = $source->fresh()->getAttributes();
        $beforeProposal = $proposal->fresh()->getAttributes();
        $beforeSubscription = $subscription->fresh()->getAttributes();

        try {
            (new ApplyUrlChange)->handle($proposal);
            $this->fail('Expected DomainException for an invalid proposal.');
        } catch (DomainException) {
            $this->assertSame($beforeSource, $source->fresh()->getAttributes());
            $this->assertSame($beforeProposal, $proposal->fresh()->getAttributes());
            $this->assertSame($beforeSubscription, $subscription->fresh()->getAttributes());
            $this->assertSame(InterestStatus::Live, $interest->refresh()->status);
            $this->assertDatabaseCount('sources', 1);
            $this->assertDatabaseCount('source_subscriptions', 1);
            $this->assertDatabaseCount('interest_source', 0);
        }
    }

    public function test_failure_to_save_approval_rolls_back_the_url_change_and_retirement(): void
    {
        $source = Source::factory()->create(['enabled' => false, 'retired_at' => now(), 'last_error' => 'HTTP 410']);
        $proposal = SourceProposal::factory()->for($source)->create(['kind' => SourceProposalKind::UrlChange]);
        $beforeSource = $source->fresh()->getAttributes();
        $beforeProposal = $proposal->fresh()->getAttributes();
        SourceProposal::updating(fn () => throw new RuntimeException('Approval save failed'));

        try {
            (new ApplyUrlChange)->handle($proposal);
            $this->fail('Expected the approval save to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Approval save failed', $exception->getMessage());
            $this->assertSame($beforeSource, $source->fresh()->getAttributes());
            $this->assertSame($beforeProposal, $proposal->fresh()->getAttributes());
            $this->assertDatabaseCount('sources', 1);
        }
    }

    /** @return array<string, array{bool, bool}> */
    public static function sourceStates(): array
    {
        return [
            'enabled' => [true, false],
            'manually disabled' => [false, false],
            'retired' => [false, true],
        ];
    }

    /** @return array<string, array{SourceProposalStatus, SourceProposalKind}> */
    public static function invalidProposals(): array
    {
        return [
            'approved' => [SourceProposalStatus::Approved, SourceProposalKind::UrlChange],
            'rejected' => [SourceProposalStatus::Rejected, SourceProposalKind::UrlChange],
            'wrong kind' => [SourceProposalStatus::Pending, SourceProposalKind::NewSource],
            'approved wrong kind' => [SourceProposalStatus::Approved, SourceProposalKind::NewSource],
            'rejected wrong kind' => [SourceProposalStatus::Rejected, SourceProposalKind::NewSource],
        ];
    }
}
