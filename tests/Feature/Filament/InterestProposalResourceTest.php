<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Enums\SourceProposalStatus;
use App\Filament\Resources\InterestProposalResource;
use App\Models\Interest;
use App\Models\InterestProposal;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\NotificationMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InterestProposalResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->onboarded()->create();
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
        Bus::fake();
    }

    public function test_only_a_list_page_is_registered_with_no_create_edit_delete_or_bulk_actions(): void
    {
        $this->assertContains(InterestProposalResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(InterestProposal::class, InterestProposalResource::getModel());
        $this->assertSame('Interest proposals', InterestProposalResource::getNavigationLabel());
        $this->assertSame(['index'], array_keys(InterestProposalResource::getPages()));
        $proposal = InterestProposal::factory()->create();
        $page = $this->listPage()->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist(TestAction::make('create')->table())
            ->assertActionDoesNotExist(TestAction::make('edit')->table($proposal))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($proposal));
        $this->assertSame([], $page->instance()->getTable()->getBulkActions());
        $url = InterestProposalResource::getUrl('index', panel: 'admin');
        $this->get($url.'/create')->assertNotFound();
        $this->get($url.'/'.$proposal->id.'/edit')->assertNotFound();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(InterestProposalResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_default_pending_filter_and_newest_first_order_can_be_changed(): void
    {
        $older = InterestProposal::factory()->create(['created_at' => now()->subDays(2)]);
        $newer = InterestProposal::factory()->create();
        $approved = InterestProposal::factory()->create(['status' => SourceProposalStatus::Approved]);
        $rejected = InterestProposal::factory()->create(['status' => SourceProposalStatus::Rejected]);
        $this->listPage()->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertCanNotSeeTableRecords([$approved, $rejected])->assertCountTableRecords(2)
            ->sortTable('created_at', 'asc')->assertCanSeeTableRecords([$older, $newer], inOrder: true)
            ->filterTable('status', 'approved')->assertCanSeeTableRecords([$approved])->assertCountTableRecords(1)
            ->filterTable('status', 'rejected')->assertCanSeeTableRecords([$rejected])->assertCountTableRecords(1);
    }

    public function test_table_and_view_show_review_fields_without_user_data_and_wrap_long_reason(): void
    {
        $user = User::factory()->create(['name' => 'Private Proposer', 'email' => 'private@example.test']);
        $privateInterest = Interest::factory()->create(['name' => 'Private reading interest']);
        $user->interests()->attach($privateInterest);
        $reason = str_repeat('A useful subject with in depth reporting. ', 10);
        $proposal = InterestProposal::factory()->for($user)->create(['name' => str_repeat('Long name ', 6), 'category' => InterestCategory::Technology, 'reason' => $reason]);
        $before = $proposal->fresh()->getAttributes();
        $page = $this->listPage()->assertTableColumnExists('name')->assertTableColumnExists('category')->assertTableColumnExists('created_at')
            ->assertTableColumnStateSet('name', $proposal->name, $proposal)
            ->assertDontSee($user->name)->assertDontSee($user->email)->assertDontSee($privateInterest->name)
            ->mountAction(TestAction::make('view')->table($proposal))
            ->assertMountedActionModalSee([$proposal->name, 'Technology', $reason])
            ->assertMountedActionModalDontSee([$user->name, $user->email, $privateInterest->name]);
        foreach (array_keys($page->instance()->getTable()->getColumns()) as $column) {
            $this->assertStringNotContainsString('user', $column);
        }
        $page->assertMountedActionModalSeeHtml('overflow-wrap');
        $this->assertSame($before, $proposal->fresh()->getAttributes());
    }

    public function test_approval_form_prefills_name_and_category_and_uses_both_edited_values(): void
    {
        $proposal = InterestProposal::factory()->create(['name' => 'Original', 'category' => InterestCategory::Life]);
        $this->listPage()->mountAction(TestAction::make('approve')->table($proposal))
            ->assertActionDataSet(['name' => 'Original', 'category' => 'life'])
            ->fillForm(['name' => 'Space Science', 'category' => 'technology'])
            ->callMountedAction()
            ->assertHasNoActionErrors()->assertNotified()->assertCanNotSeeTableRecords([$proposal]);
        $interest = Interest::query()->sole();
        $this->assertSame('Space Science', $interest->name);
        $this->assertSame(InterestCategory::Technology, $interest->category);
        $this->assertSame(InterestStatus::Draft, $interest->status);
        $this->assertSame(SourceProposalStatus::Approved, $proposal->refresh()->status);
        $this->assertSame($interest->id, $proposal->interest_id);
    }

    public function test_taken_slug_notifies_with_warning_and_preserves_the_pending_proposal(): void
    {
        Interest::factory()->create(['slug' => 'space-science']);
        $proposal = InterestProposal::factory()->create();
        $before = $proposal->fresh()->getAttributes();
        $this->listPage()->callAction(TestAction::make('approve')->table($proposal), ['name' => 'Space Science', 'category' => 'technology'])
            ->assertHasNoErrors()->assertCanSeeTableRecords([$proposal]);
        $mailbox = app(NotificationMailbox::class);
        $mailbox->flush();
        $notifications = $mailbox->pull();
        $this->assertContains('warning', array_column($notifications, 'status'));
        $this->assertSame($before, $proposal->fresh()->getAttributes());
        $this->assertDatabaseCount('interests', 1);
        Bus::assertNothingDispatched();
    }

    public function test_reject_requires_confirmation_and_changes_only_status(): void
    {
        $proposal = InterestProposal::factory()->create();
        $before = $proposal->fresh()->getAttributes();
        $page = $this->listPage()->assertActionExists(TestAction::make('reject')->table($proposal), fn (Action $action): bool => $action->isConfirmationRequired())
            ->mountAction(TestAction::make('reject')->table($proposal));
        $this->assertSame(SourceProposalStatus::Pending, $proposal->refresh()->status);
        $page->callMountedAction()->assertHasNoActionErrors()->assertNotified()->assertCanNotSeeTableRecords([$proposal]);
        $this->assertSame(SourceProposalStatus::Rejected, $proposal->refresh()->status);
        $this->assertSame(array_diff_key($before, array_flip(['status', 'updated_at'])), array_diff_key($proposal->getAttributes(), array_flip(['status', 'updated_at'])));
        $this->assertDatabaseCount('interests', 0);
        Bus::assertNothingDispatched();
    }

    #[DataProvider('statuses')]
    public function test_only_pending_proposals_have_review_actions(SourceProposalStatus $status): void
    {
        $proposal = InterestProposal::factory()->create(['status' => $status]);
        $page = $this->listPage()->filterTable('status', $status->value);
        foreach (['approve', 'reject'] as $name) {
            $action = TestAction::make($name)->table($proposal);
            if ($status === SourceProposalStatus::Pending) {
                $page->assertActionVisible($action);
            } else {
                $page->assertActionHidden($action);
            }
        }
        $page->assertActionVisible(TestAction::make('view')->table($proposal));
    }

    public function test_long_names_have_a_limited_tooltip_but_the_view_preserves_full_text_and_actions_are_icons(): void
    {
        $proposal = InterestProposal::factory()->create(['name' => str_repeat('Long name ', 6)]);
        $page = $this->listPage();
        $column = $page->instance()->getTable()->getColumn('name');
        $this->assertNotNull($column);
        $this->assertGreaterThan(0, $column->getCharacterLimit($proposal->name));
        $this->assertLessThan(strlen($proposal->name), $column->getCharacterLimit($proposal->name));
        $this->assertLessThanOrEqual(123, mb_strlen((string) $column->record($proposal)->getTooltip($proposal->name)));
        foreach (['view', 'approve', 'reject'] as $name) {
            $page->assertActionExists(TestAction::make($name)->table($proposal), fn (Action $action): bool => $action->isIconButton());
        }
        $page->mountAction(TestAction::make('view')->table($proposal))->assertMountedActionModalSee($proposal->name);
    }

    public function test_approval_requires_a_name_and_category_without_changing_the_proposal(): void
    {
        $proposal = InterestProposal::factory()->create();
        $this->listPage()->callAction(TestAction::make('approve')->table($proposal), ['name' => '', 'category' => null])
            ->assertHasActionErrors(['name' => 'required', 'category' => 'required']);
        $this->assertSame(SourceProposalStatus::Pending, $proposal->refresh()->status);
        $this->assertDatabaseCount('interests', 0);
        Bus::assertNothingDispatched();
    }

    public function test_a_stale_reject_does_not_overwrite_an_approval(): void
    {
        $proposal = InterestProposal::factory()->create();
        $page = $this->listPage()->mountAction(TestAction::make('reject')->table($proposal));
        InterestProposal::query()->whereKey($proposal->id)->update(['status' => SourceProposalStatus::Approved]);
        $page->callMountedAction()->assertHasNoErrors();
        $this->assertSame(SourceProposalStatus::Approved, $proposal->fresh()->status);
    }

    private function listPage(): Testable
    {
        return Livewire::test(InterestProposalResource::getPages()['index']->getPage());
    }

    public static function statuses(): array
    {
        return array_map(fn ($status) => [$status], SourceProposalStatus::cases());
    }
}
