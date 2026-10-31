<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\InterestStatus;
use App\Enums\SourceProposalKind;
use App\Enums\SourceProposalOrigin;
use App\Enums\SourceProposalStatus;
use App\Filament\Resources\CatalogResource;
use App\Filament\Resources\CatalogResource\Pages\ListCatalog;
use App\Jobs\ImportProposal;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceProposal;
use App\Models\SourceSubscription;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogResourceTest extends TestCase
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
    }

    public function test_resource_is_registered_as_catalog_and_has_only_a_list_page_without_mutation_actions(): void
    {
        $this->assertContains(CatalogResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(SourceProposal::class, CatalogResource::getModel());
        $this->assertSame('Catalog', CatalogResource::getNavigationLabel());
        $this->assertSame(['index'], array_keys(CatalogResource::getPages()));

        $proposal = SourceProposal::factory()->create();
        $page = $this->listPage()
            ->assertCanSeeTableRecords([$proposal])
            ->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist(TestAction::make('create')->table())
            ->assertActionDoesNotExist(TestAction::make('edit')->table($proposal))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($proposal));

        $this->assertSame(['bulkApprove', 'bulkReject'], array_map(fn ($action) => $action->getName(), $page->instance()->getTable()->getBulkActions()));
        $url = CatalogResource::getUrl('index', panel: 'admin');
        $this->get($url.'/create')->assertNotFound();
        $this->get($url.'/'.$proposal->id.'/edit')->assertNotFound();
    }

    public function test_non_admin_cannot_access_the_resource_url(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(CatalogResource::getUrl('index', panel: 'admin'))
            ->assertForbidden();
    }

    public function test_default_filter_shows_only_pending_proposals_newest_first_and_created_at_is_sortable(): void
    {
        $oldest = SourceProposal::factory()->create(['created_at' => now()->subDays(2)]);
        $newest = SourceProposal::factory()->create(['created_at' => now()]);
        $approved = SourceProposal::factory()->create(['status' => SourceProposalStatus::Approved]);
        $rejected = SourceProposal::factory()->create(['status' => SourceProposalStatus::Rejected]);

        $this->listPage()
            ->assertCanSeeTableRecords([$newest, $oldest], inOrder: true)
            ->assertCanNotSeeTableRecords([$approved, $rejected])
            ->assertCountTableRecords(2)
            ->sortTable('created_at', 'asc')
            ->assertCanSeeTableRecords([$oldest, $newest], inOrder: true);
    }

    public function test_table_exposes_only_proposal_columns_with_copyable_truncated_url_and_interest_name(): void
    {
        $interest = Interest::factory()->create(['name' => 'Catalog interest']);
        $proposal = SourceProposal::factory()->for($interest)->create([
            'url' => 'https://catalog.test/'.str_repeat('long-path/', 15).'feed.xml',
        ]);

        $page = $this->listPage()
            ->assertCanSeeTableRecords([$proposal])
            ->assertTableColumnStateSet('name', $proposal->name, $proposal)
            ->assertTableColumnStateSet('url', $proposal->url, $proposal)
            ->assertTableColumnStateSet('kind', SourceProposalKind::NewSource, $proposal)
            ->assertTableColumnStateSet('origin', SourceProposalOrigin::Scout, $proposal)
            ->assertTableColumnStateSet('status', SourceProposalStatus::Pending, $proposal)
            ->assertTableColumnStateSet('interest.name', $interest->name, $proposal)
            ->assertTableColumnExists('created_at', fn (TextColumn $column): bool => $column->isSortable())
            ->assertTableColumnExists('url', fn (TextColumn $column): bool => $column->isCopyable($proposal->url)
                && $column->getCharacterLimit($proposal->url) > 0
                && $column->getCharacterLimit($proposal->url) < strlen($proposal->url));

        $this->assertEqualsCanonicalizing(
            ['name', 'url', 'kind', 'origin', 'status', 'interest.name', 'created_at'],
            array_keys($page->instance()->getTable()->getColumns()),
        );
    }

    #[DataProvider('filters')]
    public function test_filters_select_matching_proposals(string $filter, string $value, string $other): void
    {
        $match = SourceProposal::factory()->create([$filter => $value]);
        $excluded = SourceProposal::factory()->create([$filter => $other]);

        $this->listPage()
            ->filterTable($filter, $value)
            ->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$excluded])
            ->assertCountTableRecords(1);
    }

    #[DataProvider('proposalStates')]
    public function test_review_actions_are_visible_only_for_pending_proposals_of_the_matching_kind(SourceProposalStatus $status, SourceProposalKind $kind): void
    {
        $proposal = SourceProposal::factory()->for(Source::factory())->create(['status' => $status, 'kind' => $kind]);
        $page = $this->listPage()->filterTable('status', $status->value)->assertCanSeeTableRecords([$proposal]);

        foreach (['approve' => SourceProposalKind::NewSource, 'applyUrlChange' => SourceProposalKind::UrlChange, 'reject' => null] as $name => $requiredKind) {
            $action = TestAction::make($name)->table($proposal);
            if ($status === SourceProposalStatus::Pending && ($requiredKind === null || $kind === $requiredKind)) {
                $page->assertActionVisible($action);
            } else {
                $page->assertActionHidden($action);
            }
        }
    }

    public function test_approval_form_prefills_interest_and_default_toggle_and_offers_live_and_draft_interests(): void
    {
        $draft = Interest::factory()->create(['status' => InterestStatus::Draft, 'name' => 'Draft interest']);
        $live = Interest::factory()->create(['status' => InterestStatus::Live, 'name' => 'Live interest']);
        $proposal = SourceProposal::factory()->for($draft)->create();

        $this->listPage()
            ->mountAction(TestAction::make('approve')->table($proposal))
            ->assertActionMounted(TestAction::make('approve')->table($proposal))
            ->assertActionDataSet(['interests' => [$draft->id], 'is_default' => true])
            ->assertFormFieldExists('interests', function (Select $field) use ($draft, $live): bool {
                $options = collect($field->getOptions())
                    ->union($field->getSearchResults('Draft interest'))
                    ->union($field->getSearchResults('Live interest'));

                return $field->isMultiple()
                    && $field->isRequired()
                    && $options->get($draft->id) === $draft->name
                    && $options->get($live->id) === $live->name;
            });
    }

    public function test_approval_requires_at_least_one_interest_and_does_not_write_on_validation_failure(): void
    {
        $proposal = SourceProposal::factory()->create();

        $this->listPage()
            ->callAction(TestAction::make('approve')->table($proposal), ['interests' => [], 'is_default' => true])
            ->assertHasActionErrors(['interests' => 'required']);

        $this->assertSame(SourceProposalStatus::Pending, $proposal->refresh()->status);
        $this->assertNull($proposal->source_id);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('interest_source', 0);
        $this->assertDatabaseCount('source_subscriptions', 0);
    }

    #[DataProvider('defaultChoices')]
    public function test_approve_action_passes_selected_interests_and_default_choice_to_catalog_approval(bool $isDefault): void
    {
        $interests = Interest::factory()->count(2)->create();
        $user = User::factory()->create();
        $user->interests()->attach($interests->first());
        $proposal = SourceProposal::factory()->create();

        $this->listPage()
            ->callAction(TestAction::make('approve')->table($proposal), ['interests' => $interests->modelKeys(), 'is_default' => $isDefault])
            ->assertHasNoActionErrors()
            ->assertCanNotSeeTableRecords([$proposal]);

        $this->assertSame(SourceProposalStatus::Approved, $proposal->refresh()->status);
        $source = $proposal->source;
        $this->assertNotNull($source);
        $this->assertNull($source->user_id);
        $this->assertSame($isDefault, $source->is_default);
        $this->assertEqualsCanonicalizing($interests->modelKeys(), $source->interests()->pluck('interests.id')->all());
        $this->assertSame($isDefault ? [$user->id] : [], $source->subscriptions()->pluck('user_id')->all());
    }

    public function test_url_change_requires_confirmation_showing_both_urls_and_applies_the_change(): void
    {
        $source = Source::factory()->create(['url' => 'https://old.test/feed', 'enabled' => false, 'retired_at' => now(), 'last_error' => 'HTTP 410']);
        $proposal = SourceProposal::factory()->for($source)->create(['kind' => SourceProposalKind::UrlChange, 'url' => 'https://new.test/feed']);
        $subscription = SourceSubscription::factory()->for($source)->create(['enabled' => false]);
        $before = $subscription->fresh()->getAttributes();

        $page = $this->listPage()
            ->assertActionExists(TestAction::make('applyUrlChange')->table($proposal), fn (Action $action): bool => $action->isConfirmationRequired())
            ->mountAction(TestAction::make('applyUrlChange')->table($proposal))
            ->assertMountedActionModalSee([$source->url, $proposal->url]);

        $this->assertSame(SourceProposalStatus::Pending, $proposal->refresh()->status);
        $this->assertSame('https://old.test/feed', $source->refresh()->url);

        $page->callMountedAction()->assertHasNoActionErrors()->assertCanNotSeeTableRecords([$proposal]);

        $this->assertSame(SourceProposalStatus::Approved, $proposal->refresh()->status);
        $this->assertSame($proposal->url, $source->refresh()->url);
        $this->assertTrue($source->enabled);
        $this->assertNull($source->retired_at);
        $this->assertNull($source->last_error);
        $this->assertSame($before, $subscription->fresh()->getAttributes());
    }

    #[DataProvider('kinds')]
    public function test_reject_requires_confirmation_and_rejects_without_changing_catalog_or_subscriptions(SourceProposalKind $kind): void
    {
        $source = Source::factory()->create();
        $subscription = SourceSubscription::factory()->for($source)->create(['enabled' => false]);
        $proposal = SourceProposal::factory()->create([
            'kind' => $kind,
            'source_id' => $kind === SourceProposalKind::UrlChange ? $source->id : null,
        ]);
        $beforeSource = $source->fresh()->getAttributes();
        $beforeSubscription = $subscription->fresh()->getAttributes();
        $beforeProposal = $proposal->fresh()->getAttributes();

        $page = $this->listPage()
            ->assertActionExists(TestAction::make('reject')->table($proposal), fn (Action $action): bool => $action->isConfirmationRequired())
            ->mountAction(TestAction::make('reject')->table($proposal));

        $this->assertSame(SourceProposalStatus::Pending, $proposal->refresh()->status);

        $page->callMountedAction()->assertHasNoActionErrors()->assertCanNotSeeTableRecords([$proposal]);

        $this->assertSame(SourceProposalStatus::Rejected, $proposal->refresh()->status);
        $this->assertSame(
            array_diff_key($beforeProposal, array_flip(['status', 'updated_at'])),
            array_diff_key($proposal->getAttributes(), array_flip(['status', 'updated_at'])),
        );
        $this->assertSame($beforeSource, $source->fresh()->getAttributes());
        $this->assertSame($beforeSubscription, $subscription->fresh()->getAttributes());
        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('source_subscriptions', 1);
        $this->assertDatabaseCount('interest_source', 0);
    }

    #[DataProvider('kinds')]
    public function test_view_shows_review_evidence_and_current_url_without_disclosing_user_data(SourceProposalKind $kind): void
    {
        $user = User::factory()->create(['name' => 'Private Proposer', 'email' => 'private-proposer@example.test']);
        $privateInterest = Interest::factory()->create(['name' => 'Private user interest']);
        $user->interests()->attach($privateInterest);
        $interest = Interest::factory()->create(['name' => 'Proposal interest']);
        $source = Source::factory()->create(['url' => 'https://current.test/feed']);
        $proposal = SourceProposal::factory()->for($user)->for($interest)->create([
            'kind' => $kind,
            'source_id' => $kind === SourceProposalKind::UrlChange ? $source->id : null,
            'reason' => 'The publisher moved the feed',
            'checks' => ['http_status' => 200, 'feed_title' => 'Verified feed title'],
        ]);
        $before = $proposal->fresh()->getAttributes();

        $page = $this->listPage()
            ->assertDontSee($privateInterest->name)
            ->assertDontSee($user->email)
            ->assertDontSee($user->name)
            ->mountAction(TestAction::make('view')->table($proposal))
            ->assertActionMounted(TestAction::make('view')->table($proposal))
            ->assertMountedActionModalSee([$proposal->reason, 'Http status', '200', 'Feed title', 'Verified feed title'])
            ->assertMountedActionModalDontSee([$privateInterest->name, $user->email, $user->name]);

        if ($kind === SourceProposalKind::UrlChange) {
            $page->assertMountedActionModalSee($source->url);
        }
        $this->assertSame($before, $proposal->fresh()->getAttributes());
    }

    private function listPage(): Testable
    {
        return Livewire::test(CatalogResource::getPages()['index']->getPage());
    }

    /** @return array<string, array{string, string, string}> */
    public static function filters(): array
    {
        return [
            'approved status' => ['status', 'approved', 'rejected'],
            'rejected status' => ['status', 'rejected', 'pending'],
            'new source kind' => ['kind', 'new_source', 'url_change'],
            'url change kind' => ['kind', 'url_change', 'new_source'],
            'scout origin' => ['origin', 'scout', 'user'],
            'user origin' => ['origin', 'user', 'check'],
            'check origin' => ['origin', 'check', 'scout'],
        ];
    }

    /** @return array<string, array{SourceProposalStatus, SourceProposalKind}> */
    public static function proposalStates(): array
    {
        $states = [];
        foreach (SourceProposalStatus::cases() as $status) {
            foreach (SourceProposalKind::cases() as $kind) {
                $states[$status->value.' '.$kind->value] = [$status, $kind];
            }
        }

        return $states;
    }

    /** @return array<string, array{bool}> */
    public static function defaultChoices(): array
    {
        return ['default' => [true], 'non default' => [false]];
    }

    /** @return array<string, array{SourceProposalKind}> */
    public static function kinds(): array
    {
        return ['new source' => [SourceProposalKind::NewSource], 'URL change' => [SourceProposalKind::UrlChange]];
    }

    public function test_review_actions_notify_the_admin(): void
    {
        $page = $this->catalogPageForTest();
        $proposal = SourceProposal::factory()->create();

        $page->callAction(TestAction::make('reject')->table($proposal))->assertNotified();
    }

    public function test_an_already_handled_proposal_is_left_untouched_by_a_stale_action(): void
    {
        $page = $this->catalogPageForTest();
        $proposal = SourceProposal::factory()->create();
        $action = TestAction::make('reject')->table($proposal);
        $page->mountAction($action);

        SourceProposal::query()->whereKey($proposal->id)->update(['status' => SourceProposalStatus::Approved]);

        $page->callMountedAction()->assertHasNoErrors();
        $this->assertSame(SourceProposalStatus::Approved, $proposal->fresh()?->status);
    }

    public function test_copying_gives_the_full_url_and_the_view_shows_it(): void
    {
        $page = $this->catalogPageForTest();
        $url = 'https://catalog.test/'.str_repeat('long-path/', 15).'feed.xml';
        $proposal = SourceProposal::factory()->create(['url' => $url]);

        $column = $page->instance()->getTable()->getColumn('url');
        $this->assertNotNull($column);
        $column->record($proposal);
        $this->assertSame($url, $column->getCopyableState($url));

        $page->mountAction(TestAction::make('view')->table($proposal))->assertMountedActionModalSee($url);
    }

    public function test_labels_and_empty_values_read_well(): void
    {
        $this->assertSame('New source', SourceProposalKind::NewSource->getLabel());
        $this->assertSame('URL change', SourceProposalKind::UrlChange->getLabel());
        $this->assertSame('Pending', SourceProposalStatus::Pending->getLabel());
        $this->assertSame('Scout', SourceProposalOrigin::Scout->getLabel());
        $this->assertFalse(CatalogResource::canGloballySearch());

        $page = $this->catalogPageForTest();
        $this->assertSame('No proposals', $page->instance()->getTable()->getEmptyStateHeading());
    }

    public function test_search_covers_names_urls_and_interests(): void
    {
        $interest = Interest::factory()->create(['name' => 'Astronomy']);
        $byName = SourceProposal::factory()->create(['name' => 'Findable feed', 'url' => 'https://a.test/feed']);
        $byUrl = SourceProposal::factory()->create(['name' => 'Other', 'url' => 'https://findable.test/feed']);
        $byInterest = SourceProposal::factory()->create(['name' => 'Third', 'url' => 'https://c.test/feed', 'interest_id' => $interest->id]);

        $this->catalogPageForTest()->searchTable('findable')->assertCanSeeTableRecords([$byName, $byUrl])->assertCanNotSeeTableRecords([$byInterest]);
        $this->catalogPageForTest()->searchTable('astronomy')->assertCanSeeTableRecords([$byInterest])->assertCanNotSeeTableRecords([$byName, $byUrl]);
    }

    public function test_it_filters_by_interest_and_every_column_can_be_toggled(): void
    {
        $interest = Interest::factory()->create();
        $match = SourceProposal::factory()->create(['interest_id' => $interest->id]);
        $other = SourceProposal::factory()->create();
        $page = $this->catalogPageForTest();

        $page->filterTable('interest', $interest->id)->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);

        foreach ($page->instance()->getTable()->getColumns() as $column) {
            $this->assertTrue($column->isToggleable(), $column->getName());
        }
    }

    public function test_bulk_approve_uses_each_proposal_and_skips_what_it_cannot_approve(): void
    {
        $interest = Interest::factory()->create();
        $feed = SourceProposal::factory()->create(['interest_id' => $interest->id, 'name' => 'Bulk feed', 'url' => 'https://bulk.test/feed']);
        $noInterest = SourceProposal::factory()->create(['interest_id' => null]);
        $source = Source::factory()->create(['url' => 'https://old.test/feed']);
        $move = SourceProposal::factory()->for($source)->create(['kind' => SourceProposalKind::UrlChange, 'url' => 'https://new.test/feed']);

        $this->catalogPageForTest()
            ->selectTableRecords([$feed->id, $noInterest->id, $move->id])
            ->callAction(TestAction::make('bulkApprove')->table()->bulk(), ['is_default' => false])
            ->assertNotified();

        $this->assertSame(SourceProposalStatus::Approved, $feed->fresh()?->status);
        $this->assertSame(SourceProposalStatus::Pending, $noInterest->fresh()?->status);
        $this->assertSame(SourceProposalStatus::Approved, $move->fresh()?->status);
        $this->assertSame('https://new.test/feed', $source->fresh()?->url);
        $this->assertFalse(Source::query()->where('url', 'https://bulk.test/feed')->sole()->is_default);
    }

    public function test_bulk_reject_only_changes_pending_proposals(): void
    {
        $pending = SourceProposal::factory()->create();
        $approved = SourceProposal::factory()->create(['status' => SourceProposalStatus::Approved]);

        $this->catalogPageForTest()
            ->removeTableFilters()
            ->selectTableRecords([$pending->id, $approved->id])
            ->callAction(TestAction::make('bulkReject')->table()->bulk())
            ->assertNotified();

        $this->assertSame(SourceProposalStatus::Rejected, $pending->fresh()?->status);
        $this->assertSame(SourceProposalStatus::Approved, $approved->fresh()?->status);
    }

    public function test_import_queues_valid_rows_and_reports_rejected_ones(): void
    {
        Bus::fake();
        Interest::factory()->create(['slug' => 'pets']);
        $rows = [
            ['interest' => 'pets', 'name' => 'Dogs', 'url' => 'https://dogs.test/feed', 'site_url' => null, 'reason' => 'Regular posts.'],
            ['interest' => 'nope', 'name' => 'Cats', 'url' => 'https://cats.test/feed', 'site_url' => null, 'reason' => 'Regular posts.'],
        ];

        $this->catalogPageForTest()
            ->callAction('import', ['json' => json_encode($rows, JSON_THROW_ON_ERROR)])
            ->assertHasNoFormErrors()
            ->assertNotified();

        Bus::assertDispatchedTimes(ImportProposal::class, 1);
        Bus::assertDispatched(ImportProposal::class, fn (ImportProposal $job) => $job->row['url'] === 'https://dogs.test/feed');
    }

    public function test_import_of_broken_json_queues_nothing(): void
    {
        Bus::fake();

        $this->catalogPageForTest()->callAction('import', ['json' => 'not json'])->assertNotified();

        Bus::assertNothingDispatched();
    }

    private function catalogPageForTest(): Testable
    {
        return Livewire::test(ListCatalog::class);
    }

    public function test_extreme_urls_are_truncated_in_the_tooltip_and_the_confirmation(): void
    {
        $page = $this->catalogPageForTest();
        $source = Source::factory()->create(['url' => 'https://old.test/feed']);
        $url = 'https://catalog.test/'.str_repeat('long-path/', 40).'feed.xml';
        $proposal = SourceProposal::factory()->for($source)->create(['kind' => SourceProposalKind::UrlChange, 'url' => $url]);

        $column = $page->instance()->getTable()->getColumn('url');
        $this->assertNotNull($column);
        $this->assertLessThanOrEqual(123, mb_strlen((string) $column->record($proposal)->getTooltip($url)));

        $page->mountAction(TestAction::make('applyUrlChange')->table($proposal))
            ->assertMountedActionModalDontSee($url);
    }
}
