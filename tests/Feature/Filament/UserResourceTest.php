<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\AiKey;
use App\Models\DeliveryChannel;
use App\Models\DigestSettings;
use App\Models\Interest;
use App\Models\Post;
use App\Models\Source;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\NotificationMailbox;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserResourceTest extends TestCase
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

    public function test_only_the_list_page_is_registered_without_create_edit_delete_or_relations(): void
    {
        $this->assertContains(UserResource::class, Filament::getPanel('admin')->getResources());
        $this->assertSame(User::class, UserResource::getModel());
        $this->assertSame(['index'], array_keys(UserResource::getPages()));
        $this->assertSame(ListUsers::class, UserResource::getPages()['index']->getPage());
        $this->assertSame([], UserResource::getRelations());
        $user = User::factory()->create();
        $page = $this->listPage()->assertActionDoesNotExist('create')
            ->assertActionDoesNotExist(TestAction::make('create')->table())
            ->assertActionDoesNotExist(TestAction::make('edit')->table($user))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($user));
        $this->assertSame([], $page->instance()->getTable()->getBulkActions());
        $this->assertEqualsCanonicalizing(
            ['verifyEmail', 'makeAdmin', 'removeAdmin', 'allowInstanceAi', 'stopInstanceAi'],
            array_keys($page->instance()->getTable()->getFlatRecordActions()),
        );
        $url = UserResource::getUrl('index', panel: 'admin');
        $this->get($url.'/create')->assertNotFound();
        $this->get($url.'/'.$user->id.'/edit')->assertNotFound();
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())
            ->get(UserResource::getUrl('index', panel: 'admin'))->assertForbidden();
    }

    public function test_users_are_sorted_newest_first_and_email_is_searchable(): void
    {
        $older = User::factory()->create(['created_at' => now()->subDays(3)]);
        $newer = User::factory()->create(['created_at' => now()->subDay(), 'email' => 'find-reader@example.test']);
        $this->listPage()->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertTableColumnExists('created_at', fn (TextColumn $column): bool => $column->isSortable())
            ->sortTable('created_at', 'asc')->assertCanSeeTableRecords([$older, $newer], inOrder: true)
            ->searchTable('find-reader@example.test')->assertCanSeeTableRecords([$newer])
            ->assertCanNotSeeTableRecords([$older])->assertCountTableRecords(1);
    }

    public function test_table_exposes_account_facts_without_private_reader_data(): void
    {
        $user = User::factory()->onboarded()->create();
        $user->digestSettings->update(['profile' => 'Private reader profile marker']);
        $interest = Interest::factory()->create(['name' => 'Private interest marker']);
        $user->interests()->attach($interest);
        $source = Source::factory()->for($user)->create(['name' => 'Private source marker']);
        $key = AiKey::factory()->for($user)->create(['api_key' => 'private-key-marker']);
        $destination = DeliveryChannel::factory()->for($user)->email()->create(['name' => 'Private destination marker']);
        Post::factory()->for($user)->create(['rendered' => ['title' => 'Private digest marker']]);

        $page = $this->listPage()->assertCanSeeTableRecords([$user])
            ->assertTableColumnStateSet('id', $user->id, $user)
            ->assertTableColumnStateSet('name', $user->name, $user)
            ->assertTableColumnStateSet('email', $user->email, $user)
            ->assertTableColumnStateSet('is_admin', false, $user)
            ->assertTableColumnStateSet('uses_instance_ai', false, $user);
        foreach (['Private reader profile marker', $interest->name, $source->name, $source->url, $key->api_key, $destination->name, 'Private digest marker'] as $private) {
            $page->assertDontSee($private);
        }
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'email', 'created_at', 'email_verified_at', 'digestSettings.onboarded_at', 'is_admin', 'uses_instance_ai'],
            array_keys($page->instance()->getTable()->getColumns()),
        );
    }

    public function test_verification_and_onboarding_columns_reflect_completed_missing_and_unfinished_settings(): void
    {
        $completed = User::factory()->onboarded()->create();
        $missing = User::factory()->unverified()->create();
        $unfinished = User::factory()->has(DigestSettings::factory(), 'digestSettings')->create();
        $page = $this->listPage()->assertCanSeeTableRecords([$completed, $missing, $unfinished]);
        foreach (['email_verified_at', 'digestSettings.onboarded_at', 'is_admin', 'uses_instance_ai'] as $name) {
            $page->assertTableColumnExists($name, fn (IconColumn $column): bool => $column->isBoolean());
        }
        foreach ([$completed, $missing, $unfinished] as $user) {
            $page->assertTableColumnStateSet('email_verified_at', $user->email_verified_at, $user)
                ->assertTableColumnStateSet('digestSettings.onboarded_at', $user->digestSettings?->onboarded_at, $user);
        }
    }

    #[DataProvider('booleanFilters')]
    public function test_filters_select_matching_account_facts(string $filter, string $attribute, bool $value): void
    {
        $match = User::factory()->create();
        $other = User::factory()->create();
        $match->forceFill([$attribute => $attribute === 'email_verified_at' ? ($value ? now() : null) : $value])->save();
        $other->forceFill([$attribute => $attribute === 'email_verified_at' ? ($value ? null : now()) : ! $value])->save();
        $this->listPage()->filterTable($filter, $value ? '1' : '0')
            ->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
    }

    #[DataProvider('accountStates')]
    public function test_row_action_visibility_matches_account_state(bool $verified, bool $admin, bool $instanceAi): void
    {
        $user = User::factory()->create();
        $user->forceFill(['email_verified_at' => $verified ? now() : null, 'is_admin' => $admin, 'uses_instance_ai' => $instanceAi])->save();
        $page = $this->listPage();
        foreach (['verifyEmail' => ! $verified, 'makeAdmin' => ! $admin && $verified, 'removeAdmin' => $admin, 'allowInstanceAi' => ! $instanceAi, 'stopInstanceAi' => $instanceAi] as $name => $visible) {
            $action = TestAction::make($name)->table($user);
            if ($visible) {
                $page->assertActionVisible($action);
            } else {
                $page->assertActionHidden($action);
            }
        }
    }

    #[DataProvider('accountActions')]
    public function test_actions_require_confirmation_update_only_the_account_fact_and_notify_success(string $name, string $attribute, bool $beforeValue, bool $afterValue): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $user->forceFill([$attribute => $attribute === 'email_verified_at' ? null : $beforeValue])->save();
        $before = $user->fresh()->getAttributes();
        $action = TestAction::make($name)->table($user);
        $page = $this->listPage()->assertActionExists($action, fn (Action $action): bool => $action->isConfirmationRequired())
            ->mountAction($action)->assertActionMounted($action);
        $this->assertSame($before, $user->fresh()->getAttributes());
        $page->callMountedAction()->assertHasNoActionErrors();
        $user->refresh();
        if ($attribute === 'email_verified_at') {
            $this->assertTrue(now()->equalTo($user->email_verified_at));
        } else {
            $this->assertSame($afterValue, $user->{$attribute});
        }
        $this->assertSame(array_diff_key($before, array_flip([$attribute, 'updated_at'])), array_diff_key($user->getAttributes(), array_flip([$attribute, 'updated_at'])));
        $mailbox = app(NotificationMailbox::class);
        $mailbox->flush();
        $this->assertContains('success', array_column($mailbox->pull(), 'status'));
    }

    public function test_last_admin_cannot_be_removed_and_receives_a_danger_notification(): void
    {
        $admin = User::query()->where('is_admin', true)->sole();
        $before = $admin->fresh()->getAttributes();
        $this->listPage()->assertActionVisible(TestAction::make('removeAdmin')->table($admin))
            ->callAction(TestAction::make('removeAdmin')->table($admin))->assertHasNoActionErrors();
        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertSame(1, User::query()->where('is_admin', true)->count());
        $mailbox = app(NotificationMailbox::class);
        $mailbox->flush();
        $notifications = $mailbox->pull();
        $this->assertContains('danger', array_column($notifications, 'status'));
        $this->assertNotContains('success', array_column($notifications, 'status'));
    }

    public function test_last_verified_admin_cannot_be_removed_until_another_admin_is_verified(): void
    {
        $this->freezeSecond();
        $admin = User::query()->where('is_admin', true)->sole();
        $other = User::factory()->unverified()->create();
        $other->forceFill(['is_admin' => true])->save();
        $before = $admin->fresh()->getAttributes();
        $beforeOther = $other->fresh()->getAttributes();

        $this->listPage()->callAction(TestAction::make('removeAdmin')->table($admin))->assertHasNoActionErrors();

        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertSame($beforeOther, $other->fresh()->getAttributes());
        $mailbox = app(NotificationMailbox::class);
        $mailbox->flush();
        $notifications = $mailbox->pull();
        $this->assertContains('danger', array_column($notifications, 'status'));
        $this->assertNotContains('success', array_column($notifications, 'status'));

        $other->forceFill(['email_verified_at' => now()])->save();
        $beforeOther = $other->fresh()->getAttributes();

        $this->listPage()->callAction(TestAction::make('removeAdmin')->table($admin))->assertHasNoActionErrors();

        $this->assertFalse($admin->refresh()->is_admin);
        $this->assertSame(array_diff_key($before, array_flip(['is_admin', 'updated_at'])), array_diff_key($admin->getAttributes(), array_flip(['is_admin', 'updated_at'])));
        $this->assertSame($beforeOther, $other->fresh()->getAttributes());
        $this->assertTrue($other->canAccessPanel(Filament::getPanel('admin')));
        $mailbox->flush();
        $notifications = $mailbox->pull();
        $this->assertContains('success', array_column($notifications, 'status'));
        $this->assertNotContains('danger', array_column($notifications, 'status'));
    }

    public function test_last_admin_guard_is_rechecked_when_a_confirmed_action_is_submitted(): void
    {
        $admin = User::query()->where('is_admin', true)->sole();
        $other = User::factory()->create();
        $other->forceFill(['is_admin' => true])->save();
        $page = $this->listPage()->mountAction(TestAction::make('removeAdmin')->table($admin));
        User::query()->whereKey($other->id)->update(['is_admin' => false]);
        $before = $admin->fresh()->getAttributes();

        $page->callMountedAction()->assertHasNoActionErrors();

        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertSame(1, User::query()->where('is_admin', true)->count());
        $mailbox = app(NotificationMailbox::class);
        $mailbox->flush();
        $this->assertContains('danger', array_column($mailbox->pull(), 'status'));
    }

    #[DataProvider('sortableColumns')]
    public function test_account_columns_are_sortable_both_ways(string $column): void
    {
        User::factory()->create();
        $this->listPage()->assertTableColumnExists($column, fn (Column $c): bool => $c->isSortable())
            ->sortTable($column)->assertSuccessful()->sortTable($column, 'desc')->assertSuccessful();
    }

    #[DataProvider('searchableColumns')]
    public function test_text_columns_are_searchable(string $column, bool $searchable): void
    {
        $this->listPage()->assertTableColumnExists($column, fn (Column $c): bool => $c->isSearchable() === $searchable);
    }

    public function test_admin_column_sorts_admins_after_readers_and_back(): void
    {
        $admin = User::query()->sole();
        $reader = User::factory()->create(['created_at' => now()->subDay()]);
        $this->listPage()->sortTable('is_admin')->assertCanSeeTableRecords([$reader, $admin], inOrder: true)
            ->sortTable('is_admin', 'desc')->assertCanSeeTableRecords([$admin, $reader], inOrder: true);
    }

    public function test_name_search_finds_only_the_matching_account(): void
    {
        $match = User::factory()->create(['name' => 'Searchable Reader']);
        $other = User::factory()->create(['name' => 'Someone Else']);
        $this->listPage()->searchTable('Searchable')->assertCanSeeTableRecords([$match])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_icon_button_row_actions_show_their_label_as_tooltip(): void
    {
        $user = User::factory()->unverified()->create();
        $page = $this->listPage();
        foreach (['verifyEmail', 'allowInstanceAi', 'removeAdmin', 'makeAdmin', 'stopInstanceAi'] as $name) {
            $page->assertActionExists(TestAction::make($name)->table($user), fn (Action $action): bool => ! $action->isIconButton()
                || ($action->getTooltip() === $action->getLabel() && ! array_key_exists('title', $action->getExtraAttributes())));
        }
    }

    private function listPage(): Testable
    {
        return Livewire::test(UserResource::getPages()['index']->getPage());
    }

    /** @return array<string, array{string, string, bool}> */
    public static function booleanFilters(): array
    {
        return [
            'verified' => ['verified', 'email_verified_at', true],
            'unverified' => ['verified', 'email_verified_at', false],
            'admin' => ['is_admin', 'is_admin', true],
            'reader' => ['is_admin', 'is_admin', false],
            'instance AI allowed' => ['uses_instance_ai', 'uses_instance_ai', true],
            'instance AI stopped' => ['uses_instance_ai', 'uses_instance_ai', false],
        ];
    }

    /** @return array<string, array{bool, bool, bool}> */
    public static function accountStates(): array
    {
        $states = [];
        foreach ([false, true] as $verified) {
            foreach ([false, true] as $admin) {
                foreach ([false, true] as $instanceAi) {
                    $states[(int) $verified.' '.(int) $admin.' '.(int) $instanceAi] = [$verified, $admin, $instanceAi];
                }
            }
        }

        return $states;
    }

    /** @return array<string, array{string, string, bool, bool}> */
    public static function accountActions(): array
    {
        return [
            'verify email' => ['verifyEmail', 'email_verified_at', false, true],
            'make admin' => ['makeAdmin', 'is_admin', false, true],
            'remove admin' => ['removeAdmin', 'is_admin', true, false],
            'allow instance AI' => ['allowInstanceAi', 'uses_instance_ai', false, true],
            'stop instance AI' => ['stopInstanceAi', 'uses_instance_ai', true, false],
        ];
    }

    /** @return array<string, array{string}> */
    public static function sortableColumns(): array
    {
        return [
            'id' => ['id'], 'name' => ['name'], 'email' => ['email'], 'created_at' => ['created_at'],
            'verified' => ['email_verified_at'], 'onboarded' => ['digestSettings.onboarded_at'],
            'admin' => ['is_admin'], 'instance AI' => ['uses_instance_ai'],
        ];
    }

    /** @return array<string, array{string, bool}> */
    public static function searchableColumns(): array
    {
        return ['name' => ['name', true], 'email' => ['email', true], 'created_at' => ['created_at', false]];
    }
}
