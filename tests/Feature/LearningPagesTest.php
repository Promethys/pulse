<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Models\LearningPost;
use App\Models\LearningTopic;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_index_returns_plain_entries_filters_options_and_pagination(): void
    {
        $user = User::factory()->onboarded()->create();
        $tag = Tag::factory()->create(['slug' => 'php', 'name' => 'PHP']);
        $old = $this->entry($user, '2026-10-27', LearningKind::Tip, null, $tag);
        $topic = $this->entry($user, '2026-10-28', LearningKind::Topic, 'Databases', $tag);
        $tip = $this->entry($user, '2026-10-28', LearningKind::Tip, null, $tag);

        $this->assertSame(url('/learning'), route('learning.index'));
        $response = $this->actingAs($user)->get(route('learning.index'))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('learning/Index', false)
            ->has('entries', 3)
            ->where('entries.0', $this->entryProps($tip, '2026-10-28', null, $tag))
            ->where('entries.1', $this->entryProps($topic, '2026-10-28', 'Databases', $tag))
            ->where('filters', ['kind' => null, 'category' => null, 'tag' => null])
            ->where('options', ['categories' => ['Databases'], 'tags' => [['slug' => 'php', 'name' => 'PHP']]])
            ->where('pagination', ['current_page' => 1, 'last_page' => 1, 'total' => 3, 'prev_url' => null, 'next_url' => null])
            ->missing('entries.data'));
        $this->assertSame([$tip->id, $topic->id, $old->id], array_column($response->inertiaProps('entries'), 'id'));
    }

    #[DataProvider('filters')]
    public function test_filters_select_only_matching_owned_entries(array $filters, array $expected): void
    {
        $user = User::factory()->onboarded()->create();
        $php = Tag::factory()->create(['slug' => 'php']);
        $ai = Tag::factory()->create(['slug' => 'ai']);
        $entries = [
            $this->entry($user, '2026-10-29', LearningKind::Tip, 'testing', $php),
            $this->entry($user, '2026-10-28', LearningKind::Topic, 'testing', $php),
            $this->entry($user, '2026-10-27', LearningKind::Tip, 'architecture', $php),
            $this->entry($user, '2026-10-26', LearningKind::Tip, 'testing', $ai),
        ];
        $this->entry(User::factory()->create(), '2026-10-30', LearningKind::Tip, 'testing', $php);

        $response = $this->actingAs($user)->get('/learning?'.http_build_query($filters))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('learning/Index', false)
            ->where('filters', array_replace(['kind' => null, 'category' => null, 'tag' => null], $filters))
            ->where('pagination.total', count($expected)));
        $this->assertSame(array_map(fn (int $index) => $entries[$index]->id, $expected), array_column($response->inertiaProps('entries'), 'id'));
    }

    public static function filters(): array
    {
        return [
            'tips' => [['kind' => 'tip'], [0, 2, 3]],
            'topics' => [['kind' => 'topic'], [1]],
            'category' => [['category' => 'testing'], [0, 1, 3]],
            'tag' => [['tag' => 'php'], [0, 1, 2]],
            'combined' => [['kind' => 'tip', 'category' => 'testing', 'tag' => 'php'], [0]],
            'unknown category' => [['category' => 'missing'], []],
            'unknown tag' => [['tag' => 'missing'], []],
        ];
    }

    public function test_empty_index_does_not_leak_foreign_entries_categories_or_tags(): void
    {
        $this->entry(User::factory()->create(), '2026-10-30', LearningKind::Topic, 'Private', Tag::factory()->create());

        $this->actingAs(User::factory()->onboarded()->create())->get('/learning')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('learning/Index', false)
                ->where('entries', [])->where('options', ['categories' => [], 'tags' => []])
                ->where('pagination', ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'prev_url' => null, 'next_url' => null]));
    }

    public function test_filter_options_are_sorted_distinct_and_owned_even_with_active_filters(): void
    {
        $user = User::factory()->onboarded()->create();
        $alpha = Tag::factory()->create(['name' => 'Alpha']);
        $zulu = Tag::factory()->create(['name' => 'Zulu']);
        $this->entry($user, '2026-10-29', LearningKind::Tip, 'Testing', $zulu);
        $this->entry($user, '2026-10-28', LearningKind::Topic, 'Architecture', $alpha);
        $this->entry($user, '2026-10-27', LearningKind::Tip, 'Testing', $zulu);
        $this->entry(User::factory()->create(), '2026-10-30', LearningKind::Topic, 'Private', Tag::factory()->create());

        $this->actingAs($user)->get('/learning?kind=tip&category=Testing')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('learning/Index', false)
                ->where('options', ['categories' => ['Architecture', 'Testing'], 'tags' => [
                    ['slug' => $alpha->slug, 'name' => 'Alpha'],
                    ['slug' => $zulu->slug, 'name' => 'Zulu'],
                ]]));
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_redirect_to_learning_regardless_of_previous_page(array $params, string $field): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->from('/archive')
            ->get('/learning?'.http_build_query($params))->assertRedirect('/learning')->assertSessionHasErrors($field);
    }

    public static function invalidFilters(): array
    {
        return [
            'unknown kind' => [['kind' => 'news'], 'kind'],
            'array kind' => [['kind' => ['tip']], 'kind'],
            'array category' => [['category' => ['testing']], 'category'],
            'long category' => [['category' => str_repeat('a', 101)], 'category'],
            'array tag' => [['tag' => ['php']], 'tag'],
            'long tag' => [['tag' => str_repeat('a', 101)], 'tag'],
        ];
    }

    #[DataProvider('validFilters')]
    public function test_accepts_nullable_and_maximum_length_filters(array $params, array $normalized): void
    {
        $this->actingAs(User::factory()->onboarded()->create())->get('/learning?'.http_build_query($params))
            ->assertOk()->assertSessionHasNoErrors()
            ->assertInertia(fn (Assert $page) => $page->component('learning/Index', false)->where('filters', $normalized));
    }

    public static function validFilters(): array
    {
        return [
            'empty' => [['kind' => '', 'category' => '', 'tag' => ''], ['kind' => null, 'category' => null, 'tag' => null]],
            'maximum' => [['category' => str_repeat('a', 100), 'tag' => str_repeat('b', 100)], ['kind' => null, 'category' => str_repeat('a', 100), 'tag' => str_repeat('b', 100)]],
        ];
    }

    public function test_thirty_entries_per_page_and_out_of_range_redirect_preserve_filters(): void
    {
        $user = User::factory()->onboarded()->create();
        $tag = Tag::factory()->create(['slug' => 'php']);
        for ($offset = 0; $offset < 31; $offset++) {
            $this->entry($user, CarbonImmutable::parse('2026-10-30')->subDays($offset)->toDateString(), LearningKind::Tip, 'testing', $tag);
        }
        $filters = ['kind' => 'tip', 'category' => 'testing', 'tag' => 'php'];
        $this->actingAs($user)->get('/learning?'.http_build_query($filters))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('learning/Index', false)->has('entries', 30)
                ->where('pagination.current_page', 1)->where('pagination.last_page', 2)->where('pagination.total', 31)
                ->where('pagination.prev_url', null)
                ->where('pagination.next_url', fn (string $url) => $this->hasQuery($url, [...$filters, 'page' => '2'])));
        $this->get('/learning?'.http_build_query([...$filters, 'page' => 2]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('learning/Index', false)->has('entries', 1)
                ->where('pagination.current_page', 2)->where('pagination.next_url', null)
                ->where('pagination.prev_url', fn (string $url) => $this->hasQuery($url, [...$filters, 'page' => '1'])));
        $response = $this->get('/learning?'.http_build_query([...$filters, 'page' => 99]))->assertRedirect();
        $this->assertTrue($this->hasQuery($response->headers->get('Location'), [...$filters, 'page' => '2']));
    }

    public function test_out_of_range_empty_results_redirect_to_page_one_with_filters(): void
    {
        $response = $this->actingAs(User::factory()->onboarded()->create())->get('/learning?tag=missing&page=99')->assertRedirect();
        $this->assertTrue($this->hasQuery($response->headers->get('Location'), ['tag' => 'missing', 'page' => '1']));
    }

    #[DataProvider('kinds')]
    public function test_show_returns_the_owned_entry_with_full_resource_props(LearningKind $kind): void
    {
        $user = User::factory()->onboarded()->create();
        $tag = Tag::factory()->create();
        $entry = $this->entry($user, '2026-10-28', $kind, null, $tag);

        $this->assertSame(url('/learning/'.$entry->id), route('learning.show', $entry->id));
        $this->actingAs($user)->get(route('learning.show', $entry->id))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('learning/Show', false)
                ->where('entry', $this->entryProps($entry, '2026-10-28', null, $tag))->missing('entry.data'));
    }

    public static function kinds(): array
    {
        return ['tip' => [LearningKind::Tip], 'topic' => [LearningKind::Topic]];
    }

    public function test_show_returns_404_for_another_users_entry(): void
    {
        $owner = User::factory()->onboarded()->create();
        $entry = $this->entry($owner, '2026-10-28', LearningKind::Tip, null, Tag::factory()->create());
        $this->actingAs($owner)->get('/learning/'.$entry->id)->assertOk();
        $this->actingAs(User::factory()->onboarded()->create())->get('/learning/'.$entry->id)->assertNotFound();
    }

    #[DataProvider('missingIds')]
    public function test_show_returns_404_for_unknown_or_non_numeric_ids(string $id): void
    {
        $user = User::factory()->onboarded()->create();
        $entry = $this->entry($user, '2026-10-28', LearningKind::Tip, null, Tag::factory()->create());
        $this->actingAs($user)->get('/learning/'.$entry->id)->assertOk();
        $this->get('/learning/'.$id)->assertNotFound();
    }

    public static function missingIds(): array
    {
        return ['unknown' => ['999999999'], 'non numeric' => ['topic'], 'mixed' => ['123abc']];
    }

    #[DataProvider('endpoints')]
    public function test_guests_are_redirected_to_login(string $endpoint): void
    {
        $this->get($endpoint)->assertRedirect(route('login'));
    }

    #[DataProvider('endpoints')]
    public function test_unverified_users_are_redirected_to_verification(string $endpoint): void
    {
        $this->actingAs(User::factory()->onboarded()->unverified()->create())->get($endpoint)->assertRedirect(route('verification.notice'));
    }

    #[DataProvider('endpoints')]
    public function test_users_who_have_not_onboarded_are_redirected_to_onboarding(string $endpoint): void
    {
        $this->actingAs(User::factory()->create())->get($endpoint)->assertRedirect('/onboarding');
    }

    public static function endpoints(): array
    {
        return ['index' => ['/learning'], 'show' => ['/learning/1']];
    }

    private function entry(User $user, string $date, LearningKind $kind, ?string $category, Tag $tag): LearningPost
    {
        $post = Post::query()->firstOrCreate(['user_id' => $user->id, 'date' => $date, 'kind' => PostKind::Learning]);
        $entry = LearningPost::query()->create(['post_id' => $post->id, 'kind' => $kind, 'learning_topic_id' => $category === null ? null : LearningTopic::factory()->create(['category' => $category])->id, 'title' => 'Learning', 'body' => 'Use **EXPLAIN**.', 'links' => [['title' => 'Guide', 'url' => 'https://docs.example.test/guide']]]);
        $entry->tags()->attach($tag);

        return $entry;
    }

    private function entryProps(LearningPost $entry, string $date, ?string $category, Tag $tag): array
    {
        return [
            'id' => $entry->id, 'kind' => $entry->kind->value, 'date' => $date, 'title' => 'Learning',
            'body' => 'Use **EXPLAIN**.', 'body_html' => "<p>Use <strong>EXPLAIN</strong>.</p>\n", 'category' => $category,
            'links' => [['title' => 'Guide', 'url' => 'https://docs.example.test/guide']],
            'tags' => [['id' => $tag->id, 'slug' => $tag->slug, 'name' => $tag->name]],
        ];
    }

    private function hasQuery(string $url, array $expected): bool
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $actual);

        return parse_url($url, PHP_URL_PATH) === '/learning' && $actual == $expected;
    }
}
