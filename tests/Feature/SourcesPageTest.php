<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InterestCategory;
use App\Enums\InterestStatus;
use App\Enums\SourceType;
use App\Http\Resources\InterestResource;
use App\Http\Resources\SourceResource;
use App\Models\Interest;
use App\Models\Source;
use App\Models\SourceSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SourcesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_props_group_subscribed_catalog_by_selected_interest_in_category_and_position_order(): void
    {
        $user = User::factory()->onboarded()->create();
        $life = Interest::factory()->create(['category' => InterestCategory::Life, 'position' => 1]);
        $later = Interest::factory()->create(['category' => InterestCategory::Technology, 'position' => 9]);
        $first = Interest::factory()->create(['category' => InterestCategory::Technology, 'position' => 1, 'status' => InterestStatus::Live]);
        $unselected = Interest::factory()->create(['category' => InterestCategory::Technology, 'position' => 2]);
        $user->interests()->attach([$life->id, $later->id, $first->id]);
        $alpha = Source::factory()->create(['name' => 'Alpha', 'last_success_at' => now()]);
        $zulu = Source::factory()->create(['name' => 'Zulu', 'last_error' => 'HTTP 503']);
        $alpha->interests()->attach([$first->id, $later->id]);
        $zulu->interests()->attach($first);
        SourceSubscription::factory()->for($user)->for($alpha)->create(['enabled' => false]);
        SourceSubscription::factory()->for($user)->for($zulu)->create();
        $hidden = Source::factory()->create();
        $hidden->interests()->attach($first);
        SourceSubscription::factory()->for($hidden)->create();
        $outside = Source::factory()->create();
        $outside->interests()->attach($unselected);
        SourceSubscription::factory()->for($user)->for($outside)->create();
        $custom = Source::factory()->for($user)->create(['name' => 'Custom']);
        $custom->interests()->attach($first);
        SourceSubscription::factory()->for($user)->for($custom)->create();

        $this->assertSame(url('/sources'), route('sources.index'));
        $response = $this->actingAs($user)->get('/sources')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Sources', false)
            ->has('catalog', 3)->has('custom', 1)
            ->where('categories', array_map(fn (InterestCategory $category) => [
                'value' => $category->value, 'label' => $category->label(),
            ], InterestCategory::cases())));
        $groups = $response->inertiaProps('catalog');
        $this->assertSame([$first->id, $later->id, $life->id], array_column(array_column($groups, 'interest'), 'id'));
        $this->assertSame([$alpha->id, $zulu->id], array_column($groups[0]['sources'], 'id'));
        $this->assertSame([false, true], array_column($groups[0]['sources'], 'subscription_enabled'));
        $this->assertSame(['ok', 'failing'], array_column($groups[0]['sources'], 'health'));
        $this->assertSame([$alpha->id], array_column($groups[1]['sources'], 'id'));
        $this->assertSame([], $groups[2]['sources']);
        $this->assertSame($first->slug, $groups[0]['interest']['slug']);
        $this->assertSame($first->category->value, $groups[0]['interest']['category']);
        foreach ((new InterestResource($first))->resolve() as $key => $value) {
            $this->assertSame($value, $groups[0]['interest'][$key]);
        }
        foreach ((new SourceResource($alpha->load('interests')))->resolve() as $key => $value) {
            $this->assertSame($value, $groups[0]['sources'][0][$key]);
        }
        $all = $response->inertiaProps('interests');
        $this->assertSame([$first->id, $unselected->id, $later->id, $life->id], array_column($all, 'id'));
        $this->assertSame([true, false, true, true], array_column($all, 'selected'));
        $this->assertSame($unselected->status->value, $all[1]['status']);
    }

    public function test_custom_sources_are_owned_ordered_and_masked_and_counts_do_not_duplicate_grouped_sources(): void
    {
        $user = User::factory()->onboarded()->create();
        $interests = Interest::factory()->count(2)->create();
        $user->interests()->attach($interests->modelKeys());
        $catalog = Source::factory()->create(['credentials' => ['api_key' => 'fake-catalog-secret'], 'last_error' => 'HTTP 500']);
        $catalog->interests()->attach($interests->modelKeys());
        SourceSubscription::factory()->for($user)->for($catalog)->create();
        $zulu = Source::factory()->for($user)->create(['name' => 'Zulu', 'type' => SourceType::ResendInbox, 'url' => null, 'credentials' => ['api_key' => 'fake-newsletter-secret']]);
        $alpha = Source::factory()->for($user)->create(['name' => 'Alpha', 'type' => SourceType::ResendInbox, 'url' => null, 'credentials' => null]);
        $zulu->interests()->attach($interests[0]);
        SourceSubscription::factory()->for($user)->for($zulu)->create(['enabled' => false]);
        SourceSubscription::factory()->for($user)->for($alpha)->create();
        $other = Source::factory()->for(User::factory())->create(['last_error' => 'Private error']);
        $other->interests()->attach($interests[0]);
        SourceSubscription::factory()->for($user)->for($other)->create();

        $response = $this->actingAs($user)->get('/sources')->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Sources', false)
            ->where('counts', ['total' => 3, 'failing' => 1])
            ->where('custom.0.id', $alpha->id)->where('custom.0.has_key', false)
            ->where('custom.1.id', $zulu->id)->where('custom.1.has_key', true)
            ->where('custom.1.subscription_enabled', false)
            ->where('custom.1.interests.0.id', $interests[0]->id)
            ->missing('custom.0.credentials')->missing('custom.1.credentials')
            ->missing('catalog.0.sources.0.credentials'));
        $serialized = json_encode($response->inertiaProps(), JSON_THROW_ON_ERROR);
        foreach (['fake-catalog-secret', 'fake-newsletter-secret', 'Private error', '"credentials":', '"api_key":'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
    }

    public function test_empty_account_has_zero_counts_and_empty_groups(): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->get('/sources')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sources', false)
                ->where('catalog', [])->where('custom', [])->where('interests', [])
                ->where('counts', ['total' => 0, 'failing' => 0]));
    }

    #[DataProvider('gates')]
    public function test_all_endpoints_require_authentication_verification_and_onboarding(string $method, string $path): void
    {
        $source = Source::factory()->create();
        $path = str_replace('999', (string) $source->id, $path);
        $this->call($method, $path)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->onboarded()->unverified()->create())->call($method, $path)
            ->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->create())->call($method, $path)->assertRedirect('/onboarding');
    }

    public static function gates(): array
    {
        return [
            ['GET', '/sources'], ['PUT', '/sources/subscriptions/999'], ['PUT', '/sources/interests'],
            ['POST', '/sources'], ['PUT', '/sources/999'], ['POST', '/sources/test'],
        ];
    }
}
