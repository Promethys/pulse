<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\InterestStatus;
use App\Enums\SourceType;
use App\Filament\Resources\SourceResource;
use App\Filament\Resources\SourceResource\Pages\ListSources;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourceResourceTest extends TestCase
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

    public function test_only_a_list_page_is_registered_without_create_or_delete_actions(): void
    {
        $this->assertContains(SourceResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(Source::class, SourceResource::getModel());
        $this->assertSame(['index'], array_keys(SourceResource::getPages()));
        $this->assertSame(ListSources::class, SourceResource::getPages()['index']->getPage());
        $source = Source::factory()->create();
        $page = $this->listPage()->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist(TestAction::make('create')->table())
            ->assertActionDoesNotExist(TestAction::make('delete')->table($source));
        $this->assertSame([], $page->instance()->getTable()->getBulkActions());
        $this->get(SourceResource::getUrl('index', panel: 'admin').'/create')->assertNotFound();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(SourceResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_catalog_includes_disabled_sources_and_excludes_private_sources_even_during_search(): void
    {
        $enabled = Source::factory()->create(['name' => 'Catalog enabled']);
        $disabled = Source::factory()->create(['enabled' => false, 'retired_at' => now()]);
        $private = Source::factory()->for(User::factory())->create(['name' => 'Private source marker']);
        $this->listPage()->assertCanSeeTableRecords([$enabled, $disabled])
            ->assertCanNotSeeTableRecords([$private])->assertCountTableRecords(2)
            ->assertDontSee($private->name)->assertDontSee($private->url)
            ->searchTable($private->name)->assertCountTableRecords(0)
            ->assertCanNotSeeTableRecords([$private]);
        $this->listPage()->searchTable('Catalog enabled')->assertCanSeeTableRecords([$enabled])->assertCountTableRecords(1);
    }

    #[DataProvider('privateActions')]
    public function test_private_source_cannot_be_targeted_by_a_forged_table_action(string $name): void
    {
        $private = Source::factory()->for(User::factory())->create(['enabled' => $name !== 'enable']);
        $before = $private->fresh()->getAttributes();
        $this->listPage()->mountAction(TestAction::make($name)->table($private))->assertActionNotMounted();
        $this->assertSame($before, $private->fresh()->getAttributes());
    }

    public function test_table_shows_catalog_metadata_and_copies_the_full_truncated_url(): void
    {
        $interest = Interest::factory()->create();
        $source = Source::factory()->hasAttached($interest)->create([
            'url' => 'https://catalog.test/'.str_repeat('long-path/', 15).'feed.xml',
            'is_default' => true,
        ]);
        $page = $this->listPage()->assertCanSeeTableRecords([$source])
            ->assertTableColumnStateSet('name', $source->name, $source)
            ->assertTableColumnStateSet('type', SourceType::Feed, $source)
            ->assertTableColumnStateSet('url', $source->url, $source)
            ->assertTableColumnStateSet('interests.name', [$interest->name], $source)
            ->assertTableColumnStateSet('is_default', true, $source)
            ->assertTableColumnStateSet('enabled', true, $source)
            ->assertTableColumnExists('url', fn (TextColumn $column): bool => $column->isCopyable($source->url)
                && $column->getCharacterLimit($source->url) > 0
                && $column->getCharacterLimit($source->url) < strlen($source->url));
        $column = $page->instance()->getTable()->getColumn('url');
        $this->assertNotNull($column);
        $column->record($source);
        $this->assertSame($source->url, $column->getCopyableState($source->url));
    }

    #[DataProvider('booleanFilters')]
    public function test_boolean_filters_include_only_matching_catalog_sources(string $filter, bool $value): void
    {
        $match = Source::factory()->create([$filter => $value]);
        $other = Source::factory()->create([$filter => ! $value]);
        $private = Source::factory()->for(User::factory())->create([$filter => $value]);
        $this->listPage()->filterTable($filter, $value ? '1' : '0')->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$other, $private])->assertCountTableRecords(1);
    }

    public function test_interest_and_type_filters_select_matching_catalog_sources(): void
    {
        $interest = Interest::factory()->create();
        $match = Source::factory()->hasAttached($interest)->create(['type' => SourceType::Feed]);
        $otherInterest = Source::factory()->create(['type' => SourceType::Feed]);
        $otherType = Source::factory()->hasAttached($interest)->create(['type' => SourceType::GithubTrending]);
        $private = Source::factory()->for(User::factory())->hasAttached($interest)->create(['type' => SourceType::Feed]);
        $this->listPage()->filterTable('interest', (string) $interest->id)
            ->assertCanSeeTableRecords([$match, $otherType])->assertCanNotSeeTableRecords([$otherInterest, $private])
            ->filterTable('type', SourceType::Feed->value)->assertCanSeeTableRecords([$match])
            ->assertCanNotSeeTableRecords([$otherType, $otherInterest, $private])->assertCountTableRecords(1);
    }

    #[DataProvider('enabledStates')]
    public function test_enable_disable_visibility_and_changes_preserve_source_and_subscriptions(bool $enabled): void
    {
        $source = Source::factory()->create(['enabled' => $enabled, 'retired_at' => $enabled ? null : now()]);
        $subscription = SourceSubscription::factory()->for($source)->create(['enabled' => false]);
        $beforeSubscription = $subscription->fresh()->getAttributes();
        $name = $enabled ? 'disable' : 'enable';
        $this->listPage()->assertActionVisible(TestAction::make($name)->table($source))
            ->assertActionHidden(TestAction::make($enabled ? 'enable' : 'disable')->table($source))
            ->callAction(TestAction::make($name)->table($source))->assertHasNoActionErrors();
        $this->assertSame(! $enabled, $source->refresh()->enabled);
        $this->assertDatabaseHas('sources', ['id' => $source->id]);
        $this->assertSame($beforeSubscription, $subscription->fresh()->getAttributes());
        $this->listPage()->assertActionHidden(TestAction::make($name)->table($source))
            ->assertActionVisible(TestAction::make($enabled ? 'enable' : 'disable')->table($source));
    }

    public function test_edit_form_prefills_metadata_and_offers_live_and_draft_interests(): void
    {
        $draft = Interest::factory()->create(['name' => 'Draft interest', 'status' => InterestStatus::Draft]);
        $live = Interest::factory()->create(['name' => 'Live interest', 'status' => InterestStatus::Live]);
        $source = Source::factory()->hasAttached($draft)->create();
        $this->listPage()->mountAction(TestAction::make('edit')->table($source))
            ->assertActionDataSet(['name' => $source->name, 'interests' => [$draft->id], 'enabled' => true, 'is_default' => false])
            ->assertFormFieldExists('url', fn (TextInput $field): bool => $field->isDisabled() || $field->isReadOnly())
            ->assertFormFieldDisabled('type')
            ->assertFormFieldExists('interests', function (Select $field) use ($draft, $live): bool {
                $options = collect($field->getOptions())->union($field->getSearchResults('Draft interest'))->union($field->getSearchResults('Live interest'));

                return $field->isMultiple() && $options->get($draft->id) === $draft->name && $options->get($live->id) === $live->name;
            });
    }

    public function test_edit_persists_metadata_and_relationships_but_ignores_forged_url_and_type(): void
    {
        $oldInterest = Interest::factory()->create();
        $interests = Interest::factory()->count(2)->create();
        $source = Source::factory()->hasAttached($oldInterest)->create();
        $before = $source->fresh()->getAttributes();
        $this->listPage()->callAction(TestAction::make('edit')->table($source), [
            'name' => 'Updated catalog source', 'interests' => $interests->modelKeys(), 'is_default' => true, 'enabled' => false,
            'url' => 'https://forged.test/feed', 'type' => SourceType::GithubTrending->value,
        ])->assertHasNoActionErrors();
        $source->refresh();
        $this->assertSame('Updated catalog source', $source->name);
        $this->assertTrue($source->is_default);
        $this->assertFalse($source->enabled);
        $this->assertEqualsCanonicalizing($interests->modelKeys(), $source->interests()->pluck('interests.id')->all());
        $this->assertSame(array_diff_key($before, array_flip(['name', 'is_default', 'enabled', 'updated_at'])), array_diff_key($source->getAttributes(), array_flip(['name', 'is_default', 'enabled', 'updated_at'])));
    }

    public function test_edit_enabling_a_retired_source_clears_its_retirement(): void
    {
        $this->freezeSecond();
        $interest = Interest::factory()->create();
        $source = Source::factory()->hasAttached($interest)->create(['enabled' => false, 'retired_at' => now()->subDay()]);
        $subscription = SourceSubscription::factory()->for($source)->create(['enabled' => false]);
        $before = $source->fresh()->getAttributes();
        $beforeSubscription = $subscription->fresh()->getAttributes();

        $this->listPage()->callAction(TestAction::make('edit')->table($source), [
            'name' => $source->name, 'interests' => [$interest->id], 'is_default' => $source->is_default, 'enabled' => true,
        ])->assertHasNoActionErrors();

        $this->assertTrue($source->refresh()->enabled);
        $this->assertNull($source->retired_at);
        $this->assertSame(array_diff_key($before, array_flip(['enabled', 'retired_at', 'updated_at'])), array_diff_key($source->getAttributes(), array_flip(['enabled', 'retired_at', 'updated_at'])));
        $this->assertSame([$interest->id], $source->interests()->pluck('interests.id')->all());
        $this->assertSame($beforeSubscription, $subscription->fresh()->getAttributes());
    }

    public function test_edit_requires_a_name_without_changing_source_or_interests(): void
    {
        $interest = Interest::factory()->create();
        $source = Source::factory()->hasAttached($interest)->create();
        $before = $source->fresh()->getAttributes();
        $this->listPage()->callAction(TestAction::make('edit')->table($source), [
            'name' => '', 'interests' => [], 'is_default' => true, 'enabled' => false,
        ])->assertHasActionErrors(['name' => 'required']);
        $this->assertSame($before, $source->fresh()->getAttributes());
        $this->assertSame([$interest->id], $source->interests()->pluck('interests.id')->all());
    }

    #[DataProvider('sortableColumns')]
    public function test_column_sortability_follows_the_table_rules(string $column, bool $sortable): void
    {
        Source::factory()->hasAttached(Interest::factory())->count(2)->create();
        $page = $this->listPage()->assertTableColumnExists($column, fn (Column $c): bool => $c->isSortable() === $sortable);
        if ($sortable) {
            $page->sortTable($column)->assertSuccessful()->sortTable($column, 'desc')->assertSuccessful();
        }
    }

    #[DataProvider('searchableColumns')]
    public function test_column_searchability_follows_the_table_rules(string $column, bool $searchable): void
    {
        $this->listPage()->assertTableColumnExists($column, fn (Column $c): bool => $c->isSearchable() === $searchable);
    }

    public function test_url_sort_orders_catalog_sources_both_ways(): void
    {
        $alphaUrl = Source::factory()->create(['name' => 'Zulu', 'url' => 'https://alpha.test/feed']);
        $zuluUrl = Source::factory()->create(['name' => 'Alpha', 'url' => 'https://zulu.test/feed']);
        $this->listPage()->sortTable('url')->assertCanSeeTableRecords([$alphaUrl, $zuluUrl], inOrder: true)
            ->sortTable('url', 'desc')->assertCanSeeTableRecords([$zuluUrl, $alphaUrl], inOrder: true);
    }

    public function test_search_matches_url_and_interest_name_without_leaking_private_sources(): void
    {
        $interest = Interest::factory()->create(['name' => 'Quantum Gardening']);
        $byUrl = Source::factory()->create(['url' => 'https://needle-feed.test/rss']);
        $byInterest = Source::factory()->hasAttached($interest)->create();
        $other = Source::factory()->create();
        $private = Source::factory()->for(User::factory())->hasAttached($interest)->create(['url' => 'https://needle-feed.test/private']);
        $this->listPage()->searchTable('needle-feed')->assertCanSeeTableRecords([$byUrl])
            ->assertCanNotSeeTableRecords([$byInterest, $other, $private])->assertCountTableRecords(1)
            ->searchTable('Quantum Gardening')->assertCanSeeTableRecords([$byInterest])
            ->assertCanNotSeeTableRecords([$byUrl, $other, $private])->assertCountTableRecords(1);
    }

    #[DataProvider('iconActions')]
    public function test_icon_actions_show_their_label_as_tooltip(string $name, string $label, bool $enabled): void
    {
        $source = Source::factory()->create(['enabled' => $enabled]);
        $this->listPage()->assertActionVisible(TestAction::make($name)->table($source))
            ->assertActionExists(TestAction::make($name)->table($source), fn (Action $action): bool => $action->isIconButton()
                && $action->getTooltip() === $label && ! array_key_exists('title', $action->getExtraAttributes()));
    }

    private function listPage(): Testable
    {
        return Livewire::test(SourceResource::getPages()['index']->getPage());
    }

    /** @return array<string, array{string}> */
    public static function privateActions(): array
    {
        return ['edit' => ['edit'], 'enable' => ['enable'], 'disable' => ['disable']];
    }

    /** @return array<string, array{string, bool}> */
    public static function booleanFilters(): array
    {
        return ['enabled' => ['enabled', true], 'disabled' => ['enabled', false], 'default' => ['is_default', true], 'not default' => ['is_default', false]];
    }

    /** @return array<string, array{bool}> */
    public static function enabledStates(): array
    {
        return ['enabled' => [true], 'disabled and retired' => [false]];
    }

    /** @return array<string, array{string, bool}> */
    public static function sortableColumns(): array
    {
        return [
            'name' => ['name', true], 'type' => ['type', true], 'url' => ['url', true],
            'default' => ['is_default', true], 'enabled' => ['enabled', true], 'interests' => ['interests.name', false],
        ];
    }

    /** @return array<string, array{string, bool}> */
    public static function searchableColumns(): array
    {
        return ['name' => ['name', true], 'url' => ['url', true], 'interests' => ['interests.name', true], 'type' => ['type', false]];
    }

    /** @return array<string, array{string, string, bool}> */
    public static function iconActions(): array
    {
        return ['edit' => ['edit', 'Edit', true], 'enable' => ['enable', 'Enable', false], 'disable' => ['disable', 'Disable', true]];
    }
}
