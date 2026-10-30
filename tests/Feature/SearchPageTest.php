<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_route_returns_plain_results_filters_options_and_pagination(): void
    {
        $user = User::factory()->onboarded()->create();
        $post = Post::factory()->for($user)->create(['date' => '2026-10-28']);
        $item = PostItem::factory()->create(['post_id' => $post->id, 'title' => 'Compiler release', 'summary' => null]);
        $tag = Tag::factory()->create();
        $source = Source::factory()->create();
        $item->tags()->attach($tag);
        $item->item->sources()->attach($source, ['first_seen_at' => now()]);
        PostItem::factory()->create(['title' => 'Compiler private']);
        $filters = ['q' => 'compiler', 'tags' => [$tag->slug], 'source' => $source->slug, 'from' => '2026-10-28', 'to' => '2026-10-28'];

        $this->assertSame(url('/search'), route('search.index'));
        $this->actingAs($user)->get(route('search.index', $filters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Search', false)
                ->where('results', [[
                    'id' => $item->id, 'title' => 'Compiler release', 'summary' => null, 'position' => 1,
                    'url' => $item->item->url, 'date' => '2026-10-28', 'source' => $source->name,
                    'tags' => [['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name]],
                ]])
                ->where('filters', $filters)
                ->where('options', ['tags' => [['slug' => $tag->slug, 'name' => $tag->name]], 'sources' => [['slug' => $source->slug, 'name' => $source->name]]])
                ->where('pagination', ['current_page' => 1, 'last_page' => 1, 'total' => 1, 'prev_url' => null, 'next_url' => null])
                ->missing('results.data')->missing('results.0.search'));
    }

    public function test_no_digests_returns_empty_arrays_and_normalized_empty_filters(): void
    {
        PostItem::factory()->create();
        $this->actingAs(User::factory()->onboarded()->create())->get('/search')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Search', false)
                ->where('results', [])
                ->where('filters', ['q' => null, 'tags' => [], 'source' => null, 'from' => null, 'to' => null])
                ->where('options', ['tags' => [], 'sources' => []])
                ->where('pagination', ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'prev_url' => null, 'next_url' => null]));
    }

    #[DataProvider('invalidFilters')]
    public function test_rejects_invalid_filters(array $params, string $field): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->from('/search')
            ->get('/search?'.http_build_query($params))->assertRedirect('/search')->assertSessionHasErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'long query' => [['q' => str_repeat('a', 201)], 'q'],
            'array query' => [['q' => ['compiler']], 'q'],
            'scalar tags' => [['tags' => 'php'], 'tags'],
            'nested tag' => [['tags' => [['php']]], 'tags.0'],
            'array source' => [['source' => ['feed']], 'source'],
            'invalid from' => [['from' => '2026-02-30'], 'from'],
            'wrong date format' => [['to' => '30/10/2026'], 'to'],
            'inverted dates' => [['from' => '2026-10-29', 'to' => '2026-10-28'], 'to'],
        ];
    }

    #[DataProvider('validFilters')]
    public function test_accepts_boundary_query_and_optional_dates(array $params): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->get('/search?'.http_build_query($params))
            ->assertOk()->assertSessionHasNoErrors();
    }

    public static function validFilters(): array
    {
        return [
            'max query' => [['q' => str_repeat('a', 200)]],
            'from only' => [['from' => '2026-10-28']],
            'to only' => [['to' => '2026-10-28']],
            'equal dates' => [['from' => '2026-10-28', 'to' => '2026-10-28']],
            'empty values' => [['q' => '', 'source' => '', 'from' => '', 'to' => '']],
        ];
    }

    public function test_pagination_and_redirect_preserve_all_filters(): void
    {
        $user = User::factory()->onboarded()->create();
        $post = Post::factory()->for($user)->create(['date' => '2026-10-28']);
        $tag = Tag::factory()->create();
        $source = Source::factory()->create();
        foreach (range(1, 31) as $position) {
            $item = PostItem::factory()->create(['post_id' => $post->id, 'position' => $position, 'title' => 'Compiler release']);
            $item->tags()->attach($tag);
            $item->item->sources()->attach($source, ['first_seen_at' => now()]);
        }
        $filters = ['q' => 'compiler', 'tags' => [$tag->slug], 'source' => $source->slug, 'from' => '2026-10-28', 'to' => '2026-10-28'];
        $this->actingAs($user)->get(route('search.index', $filters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Search', false)->has('results', 30)
                ->where('pagination.total', 31)->where('pagination.prev_url', null)
                ->where('pagination.next_url', fn (string $url) => $this->hasQuery($url, [...$filters, 'page' => '2'])));
        $this->get(route('search.index', [...$filters, 'page' => 2]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Search', false)->has('results', 1)
                ->where('pagination.current_page', 2)->where('pagination.next_url', null)
                ->where('pagination.prev_url', fn (string $url) => $this->hasQuery($url, [...$filters, 'page' => '1'])));
        $response = $this->get(route('search.index', [...$filters, 'page' => 99]))->assertRedirect();
        $this->assertTrue($this->hasQuery($response->headers->get('Location'), [...$filters, 'page' => '2']));
    }

    public function test_out_of_range_empty_search_redirects_to_page_one_with_query(): void
    {
        $response = $this->actingAs(User::factory()->onboarded()->create())->get('/search?q=missing&page=99')->assertRedirect();
        $this->assertTrue($this->hasQuery($response->headers->get('Location'), ['q' => 'missing', 'page' => '1']));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/search')->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification(): void
    {
        $this->actingAs(User::factory()->onboarded()->unverified()->create())->get('/search')->assertRedirect(route('verification.notice'));
    }

    public function test_users_who_have_not_onboarded_are_redirected_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())->get('/search')->assertRedirect('/onboarding');
    }

    private function hasQuery(string $url, array $expected): bool
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $actual);

        return parse_url($url, PHP_URL_PATH) === '/search' && $actual == $expected;
    }

    public function test_inverted_dates_get_a_readable_message(): void
    {
        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)->from('/search')
            ->get('/search?from=2026-10-29&to=2026-10-28')
            ->assertSessionHasErrors(['to' => 'The end date must be on or after the start date.']);
    }

    public function test_an_invalid_link_stays_on_search_whatever_page_came_before(): void
    {
        $user = User::factory()->onboarded()->create();

        $this->actingAs($user)->from('/archive')
            ->get('/search?from=2026-10-29&to=2026-10-28')
            ->assertRedirect(route('search.index'));
    }
}
