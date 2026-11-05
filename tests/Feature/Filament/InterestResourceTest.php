<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Filament\Resources\InterestResource;
use App\Filament\Resources\InterestResource\Pages\ListInterests;
use App\Models\Interest;
use App\Models\Source;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InterestResourceTest extends TestCase
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

    public function test_resource_registers_only_the_list_page_with_modal_create_edit_and_no_delete(): void
    {
        $this->assertContains(InterestResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(Interest::class, InterestResource::getModel());
        $this->assertSame(['index'], array_keys(InterestResource::getPages()));
        $this->assertSame(ListInterests::class, InterestResource::getPages()['index']->getPage());
        $interest = Interest::factory()->create();
        $page = $this->listPage()->assertActionVisible('create')
            ->assertActionVisible(TestAction::make('edit')->table($interest))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($interest));
        $this->assertSame([], $page->instance()->getTable()->getBulkActions());
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(InterestResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_table_lists_draft_and_live_interests_with_loaded_source_and_reader_counts(): void
    {
        $draft = Interest::factory()->create(['status' => InterestStatus::Draft]);
        $live = Interest::factory()->create(['status' => InterestStatus::Live]);
        $sources = Source::factory()->count(2)->create();
        $readers = User::factory()->count(3)->create();
        $live->sources()->attach($sources->modelKeys());
        $live->users()->attach($readers->modelKeys());
        $page = $this->listPage()->assertCanSeeTableRecords([$draft, $live])->assertCountTableRecords(2)
            ->assertTableColumnStateSet('name', $live->name, $live)
            ->assertTableColumnStateSet('slug', $live->slug, $live)
            ->assertTableColumnStateSet('status', InterestStatus::Live, $live)
            ->assertTableColumnStateSet('sources_count', 2, $live)
            ->assertTableColumnStateSet('users_count', 3, $live)
            ->assertTableColumnStateSet('sources_count', 0, $draft)
            ->assertTableColumnStateSet('users_count', 0, $draft);
        foreach ($page->instance()->getTableRecords() as $record) {
            $this->assertArrayHasKey('sources_count', $record->getAttributes());
            $this->assertArrayHasKey('users_count', $record->getAttributes());
        }
    }

    #[DataProvider('statuses')]
    public function test_publish_and_draft_actions_match_status_and_change_only_status(InterestStatus $status): void
    {
        $interest = Interest::factory()->create(['status' => $status]);
        $source = Source::factory()->create();
        $reader = User::factory()->create();
        $interest->sources()->attach($source);
        $interest->users()->attach($reader);
        $before = $interest->fresh()->getAttributes();
        $name = $status === InterestStatus::Draft ? 'publish' : 'draft';
        $opposite = $status === InterestStatus::Draft ? 'draft' : 'publish';
        $this->listPage()->assertActionVisible(TestAction::make($name)->table($interest))
            ->assertActionHidden(TestAction::make($opposite)->table($interest))
            ->callAction(TestAction::make($name)->table($interest))->assertHasNoActionErrors();
        $interest->refresh();
        $this->assertSame($status === InterestStatus::Draft ? InterestStatus::Live : InterestStatus::Draft, $interest->status);
        $this->assertSame(array_diff_key($before, array_flip(['status', 'updated_at'])), array_diff_key($interest->getAttributes(), array_flip(['status', 'updated_at'])));
        $this->assertSame([$source->id], $interest->sources()->pluck('sources.id')->all());
        $this->assertSame([$reader->id], $interest->users()->pluck('users.id')->all());
        $this->listPage()->assertActionHidden(TestAction::make($name)->table($interest))
            ->assertActionVisible(TestAction::make($opposite)->table($interest));
    }

    #[DataProvider('statuses')]
    public function test_create_persists_all_editable_fields(InterestStatus $status): void
    {
        $data = $this->interestData($status);
        $this->listPage()->callAction('create', $data)->assertHasNoActionErrors();
        $interest = Interest::query()->sole();
        $this->assertSame($data['name'], $interest->name);
        $this->assertSame($data['slug'], $interest->slug);
        $this->assertSame(InterestCategory::Technology, $interest->category);
        $this->assertSame($data['icon'], $interest->icon);
        $this->assertSame($data['position'], $interest->position);
        $this->assertTrue($interest->has_learning);
        $this->assertSame($status, $interest->status);
    }

    public function test_edit_prefills_and_persists_all_editable_fields_without_changing_relationships(): void
    {
        $interest = Interest::factory()->create();
        $source = Source::factory()->create();
        $reader = User::factory()->create();
        $interest->sources()->attach($source);
        $interest->users()->attach($reader);
        $data = $this->interestData(InterestStatus::Live);
        $this->listPage()->mountAction(TestAction::make('edit')->table($interest))
            ->assertActionDataSet([
                'name' => $interest->name, 'slug' => $interest->slug, 'category' => $interest->category->value,
                'icon' => $interest->icon, 'position' => $interest->position,
                'has_learning' => $interest->has_learning, 'status' => $interest->status->value,
            ])->setActionData($data)->callMountedAction()->assertHasNoActionErrors();
        $interest->refresh();
        $this->assertSame($data['name'], $interest->name);
        $this->assertSame($data['slug'], $interest->slug);
        $this->assertSame(InterestCategory::Technology, $interest->category);
        $this->assertSame($data['icon'], $interest->icon);
        $this->assertSame($data['position'], $interest->position);
        $this->assertTrue($interest->has_learning);
        $this->assertSame(InterestStatus::Live, $interest->status);
        $this->assertSame([$source->id], $interest->sources()->pluck('sources.id')->all());
        $this->assertSame([$reader->id], $interest->users()->pluck('users.id')->all());
    }

    public function test_edit_can_keep_its_own_slug_and_turn_learning_off(): void
    {
        $interest = Interest::factory()->create(['has_learning' => true]);
        $this->listPage()->callAction(TestAction::make('edit')->table($interest), [
            ...$this->interestData(InterestStatus::Draft), 'slug' => $interest->slug, 'has_learning' => false,
        ])->assertHasNoActionErrors();
        $this->assertFalse($interest->refresh()->has_learning);
    }

    #[DataProvider('formActions')]
    public function test_duplicate_slug_is_rejected_without_writes(string $action): void
    {
        $existing = Interest::factory()->create(['slug' => 'existing-topic']);
        $target = $action === 'edit' ? Interest::factory()->create() : null;
        $before = Interest::query()->orderBy('id')->get()->map->getAttributes()->all();
        $data = [...$this->interestData(InterestStatus::Live), 'slug' => $existing->slug];
        $this->listPage()->callAction($target === null ? 'create' : TestAction::make('edit')->table($target), $data)
            ->assertHasActionErrors(['slug' => 'unique']);
        $this->assertSame($before, Interest::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    #[DataProvider('invalidForms')]
    public function test_invalid_create_and_edit_forms_do_not_write(string $action, string $field, string $value, string $rule): void
    {
        $target = $action === 'edit' ? Interest::factory()->create() : null;
        $before = Interest::query()->get()->map->getAttributes()->all();
        $data = [...$this->interestData(InterestStatus::Live), $field => $value];
        $this->listPage()->callAction($target === null ? 'create' : TestAction::make('edit')->table($target), $data)
            ->assertHasActionErrors([$field => $rule]);
        $this->assertSame($before, Interest::query()->get()->map->getAttributes()->all());
    }

    #[DataProvider('sortableColumns')]
    public function test_interest_columns_are_sortable_both_ways(string $column): void
    {
        Interest::factory()->count(2)->create();
        $this->listPage()->assertTableColumnExists($column, fn (Column $c): bool => $c->isSortable())
            ->sortTable($column)->assertSuccessful()->sortTable($column, 'desc')->assertSuccessful();
    }

    #[DataProvider('searchableColumns')]
    public function test_column_searchability_follows_the_table_rules(string $column, bool $searchable): void
    {
        $this->listPage()->assertTableColumnExists($column, fn (Column $c): bool => $c->isSearchable() === $searchable);
    }

    public function test_name_sort_orders_interests_both_ways_instead_of_by_position(): void
    {
        $alpha = Interest::factory()->create(['name' => 'Alpha', 'position' => 2]);
        $zulu = Interest::factory()->create(['name' => 'Zulu', 'position' => 1]);
        $this->listPage()->assertCanSeeTableRecords([$zulu, $alpha], inOrder: true)
            ->sortTable('name')->assertCanSeeTableRecords([$alpha, $zulu], inOrder: true)
            ->sortTable('name', 'desc')->assertCanSeeTableRecords([$zulu, $alpha], inOrder: true);
    }

    #[DataProvider('iconActions')]
    public function test_icon_actions_show_their_label_as_tooltip(string $name, string $label, InterestStatus $status): void
    {
        $interest = Interest::factory()->create(['status' => $status]);
        $this->listPage()->assertActionVisible(TestAction::make($name)->table($interest))
            ->assertActionExists(TestAction::make($name)->table($interest), fn (Action $action): bool => $action->isIconButton()
                && $action->getTooltip() === $label && ! array_key_exists('title', $action->getExtraAttributes()));
    }

    private function listPage(): Testable
    {
        return Livewire::test(InterestResource::getPages()['index']->getPage());
    }

    /** @return array{name: string, slug: string, category: string, icon: string, position: int, has_learning: bool, status: string} */
    private function interestData(InterestStatus $status): array
    {
        return [
            'name' => 'Space Science', 'slug' => 'space-science', 'category' => InterestCategory::Technology->value,
            'icon' => 'rocket', 'position' => 42, 'has_learning' => true, 'status' => $status->value,
        ];
    }

    /** @return array<string, array{InterestStatus}> */
    public static function statuses(): array
    {
        return ['draft' => [InterestStatus::Draft], 'live' => [InterestStatus::Live]];
    }

    /** @return array<string, array{string}> */
    public static function formActions(): array
    {
        return ['create' => ['create'], 'edit' => ['edit']];
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function invalidForms(): array
    {
        $forms = [];
        foreach (['create', 'edit'] as $action) {
            foreach (['name', 'slug', 'category', 'status'] as $field) {
                $forms[$action.' missing '.$field] = [$action, $field, '', 'required'];
            }
        }

        return $forms;
    }

    /** @return array<string, array{string}> */
    public static function sortableColumns(): array
    {
        return ['name' => ['name'], 'slug' => ['slug'], 'status' => ['status'], 'sources' => ['sources_count'], 'readers' => ['users_count']];
    }

    /** @return array<string, array{string, bool}> */
    public static function searchableColumns(): array
    {
        return ['name' => ['name', true], 'slug' => ['slug', true], 'status' => ['status', false]];
    }

    /** @return array<string, array{string, string, InterestStatus}> */
    public static function iconActions(): array
    {
        return [
            'edit' => ['edit', 'Edit', InterestStatus::Live],
            'publish' => ['publish', 'Publish', InterestStatus::Draft],
            'draft' => ['draft', 'Move to draft', InterestStatus::Live],
        ];
    }
}
