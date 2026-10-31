<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\ApproveInterestProposal;
use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Enums\SourceProposalStatus;
use App\Jobs\ScoutInterest;
use App\Models\Interest;
use App\Models\InterestProposal;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ApproveInterestProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_creates_an_edited_draft_after_the_last_interest_in_its_category_and_links_the_proposal(): void
    {
        Bus::fake();
        Interest::factory()->create(['category' => InterestCategory::Technology, 'position' => 7]);
        Interest::factory()->create(['category' => InterestCategory::Life, 'position' => 90]);
        $proposal = InterestProposal::factory()->create(['name' => 'Original name', 'category' => InterestCategory::Life]);

        $interest = (new ApproveInterestProposal)->handle($proposal, 'Space Science', InterestCategory::Technology);

        $this->assertModelExists($interest);
        $this->assertSame('Space Science', $interest->name);
        $this->assertSame('space-science', $interest->slug);
        $this->assertSame(InterestCategory::Technology, $interest->category);
        $this->assertSame(InterestStatus::Draft, $interest->status);
        $this->assertFalse($interest->has_learning);
        $this->assertSame(8, $interest->position);
        $this->assertMatchesRegularExpression('/^[a-z]+(?:-[a-z0-9]+)*$/', $interest->icon);
        $this->assertSame(SourceProposalStatus::Approved, $proposal->refresh()->status);
        $this->assertSame($interest->id, $proposal->interest_id);
        $this->assertDatabaseCount('interest_user', 0);
        $this->assertDatabaseCount('source_proposals', 0);
    }

    public function test_first_interest_in_a_category_has_position_one(): void
    {
        Bus::fake();
        $interest = (new ApproveInterestProposal)->handle(InterestProposal::factory()->create(), 'Space Science', InterestCategory::Technology);
        $this->assertSame(1, $interest->position);
    }

    public function test_scout_is_queued_once_only_after_the_outer_transaction_commits(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false]);
        $proposal = InterestProposal::factory()->create();
        DB::beginTransaction();
        $interest = (new ApproveInterestProposal)->handle($proposal, 'Space Science', InterestCategory::Technology);
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertDatabaseCount('jobs', 1);
        $payload = json_decode(DB::table('jobs')->sole()->payload, true, flags: JSON_THROW_ON_ERROR);
        $job = unserialize($payload['data']['command']);
        $this->assertInstanceOf(ScoutInterest::class, $job);
        $this->assertSame($interest->id, $job->interest->id);
    }

    public function test_rolling_back_the_outer_transaction_discards_the_interest_and_scout(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false]);
        $proposal = InterestProposal::factory()->create();
        DB::beginTransaction();
        (new ApproveInterestProposal)->handle($proposal, 'Space Science', InterestCategory::Technology);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseMissing('interests', ['slug' => 'space-science']);
        $this->assertSame(SourceProposalStatus::Pending, $proposal->refresh()->status);
        $this->assertNull($proposal->interest_id);
    }

    public function test_a_taken_slug_changes_nothing_and_dispatches_nothing(): void
    {
        Bus::fake();
        $existing = Interest::factory()->create(['slug' => 'space-science']);
        $proposal = InterestProposal::factory()->create();
        $before = $proposal->fresh()->getAttributes();
        $existingBefore = $existing->fresh()->getAttributes();
        try {
            (new ApproveInterestProposal)->handle($proposal, 'SPACE Science', InterestCategory::Technology);
            $this->fail('A taken slug must be refused.');
        } catch (DomainException) {
            $this->assertSame($before, $proposal->fresh()->getAttributes());
            $this->assertSame($existingBefore, $existing->fresh()->getAttributes());
            $this->assertDatabaseCount('interests', 1);
            Bus::assertNothingDispatched();
        }
    }

    #[DataProvider('handledStatuses')]
    public function test_handled_proposals_cannot_be_approved(SourceProposalStatus $status): void
    {
        Bus::fake();
        $proposal = InterestProposal::factory()->create(['status' => $status]);
        $before = $proposal->fresh()->getAttributes();
        try {
            (new ApproveInterestProposal)->handle($proposal, 'Space Science', InterestCategory::Technology);
            $this->fail('Only pending proposals can be approved.');
        } catch (DomainException) {
            $this->assertSame($before, $proposal->fresh()->getAttributes());
            $this->assertDatabaseCount('interests', 0);
            Bus::assertNothingDispatched();
        }
    }

    public function test_a_stale_pending_model_cannot_approve_a_proposal_already_handled_in_the_database(): void
    {
        Bus::fake();
        $proposal = InterestProposal::factory()->create();
        InterestProposal::query()->whereKey($proposal->id)->update(['status' => SourceProposalStatus::Rejected]);
        try {
            (new ApproveInterestProposal)->handle($proposal, 'Space Science', InterestCategory::Technology);
            $this->fail('The stored status must be checked again.');
        } catch (DomainException) {
            $this->assertSame(SourceProposalStatus::Rejected, $proposal->fresh()->status);
            $this->assertDatabaseCount('interests', 0);
            Bus::assertNothingDispatched();
        }
    }

    public function test_a_failure_saving_the_proposal_rolls_back_the_interest_and_dispatches_nothing(): void
    {
        Bus::fake();
        $proposal = InterestProposal::factory()->create();
        InterestProposal::updating(function (): void {
            throw new RuntimeException('Cannot save approval');
        });
        try {
            (new ApproveInterestProposal)->handle($proposal, 'Space Science', InterestCategory::Technology);
            $this->fail('The save failure must escape.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cannot save approval', $exception->getMessage());
            $this->assertDatabaseCount('interests', 0);
            $this->assertSame(SourceProposalStatus::Pending, $proposal->fresh()->status);
            Bus::assertNothingDispatched();
        }
    }

    public static function handledStatuses(): array
    {
        return [[SourceProposalStatus::Approved], [SourceProposalStatus::Rejected]];
    }

    public function test_the_proposal_keeps_the_approved_name_and_category(): void
    {
        $proposal = InterestProposal::factory()->create(['name' => 'Original', 'category' => InterestCategory::Technology]);

        app(ApproveInterestProposal::class)->handle($proposal, 'Edited name', InterestCategory::Media);

        $fresh = $proposal->fresh();
        $this->assertSame('Edited name', $fresh?->name);
        $this->assertSame(InterestCategory::Media, $fresh?->category);
    }
}
