<?php

declare(strict_types=1);

namespace Tests\Feature\Reading;

use App\Models\Post;
use App\Models\PostItem;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Reading\SearchFilters;
use App\Reading\SearchQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchQueryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('textQueries')]
    public function test_searches_titles_and_summaries_with_english_websearch(string $title, ?string $summary, string $q, bool $matches): void
    {
        $user = User::factory()->create();
        $item = $this->entry($user, '2026-10-28', ['title' => $title, 'summary' => $summary]);
        $this->entry(User::factory()->create(), '2026-10-29', ['title' => $title, 'summary' => $summary]);
        $result = app(SearchQuery::class)->for($user, $this->filters(q: $q));

        $this->assertSame($matches ? [$item->id] : [], $result->getCollection()->modelKeys());
    }

    public static function textQueries(): array
    {
        return [
            'title' => ['Compiler release', 'Faster builds', 'compiler', true],
            'summary' => ['Release notes', 'The compiler is faster', 'compiler', true],
            'stemming' => ['Deployment guide', null, 'deploying', true],
            'null summary' => ['Compiler release', null, 'compiler', true],
            'unbalanced quote' => ['Compiler release', null, '"compiler', true],
            'negative word' => ['Compiler release', null, 'compiler -slow', true],
            'excluded word' => ['Slow compiler', null, 'compiler -slow', false],
            'stop words' => ['Compiler release', null, 'the and of', false],
            'not a substring' => ['Compiler release', null, 'pile', false],
        ];
    }

    public function test_filters_combine_with_any_of_tags_and_inclusive_digest_dates(): void
    {
        $user = User::factory()->create();
        $alpha = Tag::factory()->create();
        $beta = Tag::factory()->create();
        $source = Source::factory()->create();
        $expected = [];
        foreach (['2026-10-20', '2026-10-28', '2026-10-29'] as $date) {
            $item = $this->entry($user, $date, ['title' => 'Compiler release']);
            $item->tags()->attach($date === '2026-10-20' ? $alpha : $beta);
            if ($date === '2026-10-28') {
                $item->tags()->attach($alpha);
            }
            $item->item->sources()->attach($source, ['first_seen_at' => now()]);
            $expected[] = $item->id;
        }
        $this->entry($user, '2026-10-19', ['title' => 'Compiler release']);
        $after = $this->entry($user, '2026-10-30', ['title' => 'Compiler release']);
        $unrelated = $this->entry($user, '2026-10-25', ['title' => 'Compiler release']);
        $unrelated->item->sources()->attach($source, ['first_seen_at' => now()]);
        $filters = $this->filters(tags: [$alpha->slug, $beta->slug]);
        $this->assertSame(array_reverse($expected), app(SearchQuery::class)->for($user, $filters)->getCollection()->modelKeys());
        $this->assertSame(array_reverse($expected), app(SearchQuery::class)->for($user, $this->filters(
            q: 'compiler', tags: [$alpha->slug, $beta->slug], source: $source->slug, from: '2026-10-20', to: '2026-10-29',
        ))->getCollection()->modelKeys());
        $this->assertSame([$after->id, $expected[2]], app(SearchQuery::class)->for($user, $this->filters(from: '2026-10-29'))->getCollection()->modelKeys());
        $this->assertCount(2, app(SearchQuery::class)->for($user, $this->filters(to: '2026-10-20'))->items());
        $this->assertSame(4, app(SearchQuery::class)->for($user, $this->filters(source: $source->slug))->total());
        $this->assertSame(0, app(SearchQuery::class)->for($user, $this->filters(tags: ['missing']))->total());
        $this->assertSame(0, app(SearchQuery::class)->for($user, $this->filters(source: 'missing'))->total());
    }

    #[DataProvider('individualFilters')]
    public function test_each_filter_excludes_nonmatching_items(array $values): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['slug' => 'compiler']);
        $source = Source::factory()->create(['slug' => 'compiler-news']);
        $match = $this->entry($user, '2026-10-28', ['title' => 'Compiler release']);
        $match->tags()->attach($tag);
        $match->item->sources()->attach($source, ['first_seen_at' => now()]);
        $this->entry($user, '2026-10-19', ['title' => 'Database guide']);
        $this->entry($user, '2026-10-30', ['title' => 'Database guide']);

        $this->assertSame([$match->id], app(SearchQuery::class)->for($user, new SearchFilters(...$values))->getCollection()->modelKeys());
    }

    public static function individualFilters(): array
    {
        return [
            'text' => [['compiler', [], null, null, null]],
            'tags' => [[null, ['compiler'], null, null, null]],
            'source' => [[null, [], 'compiler-news', null, null]],
            'dates' => [[null, [], null, '2026-10-28', '2026-10-28']],
        ];
    }

    public function test_combining_filters_requires_every_filter_to_match(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $source = Source::factory()->create();
        $expected = null;
        foreach (['match', 'text', 'tag', 'source', 'from', 'to'] as $case) {
            $date = match ($case) {
                'from' => '2026-10-19',
                'to' => '2026-10-30',
                default => '2026-10-28',
            };
            $post = Post::query()->where('user_id', $user->id)->whereDate('date', $date)->first()
                ?? Post::factory()->create(['user_id' => $user->id, 'date' => $date]);
            $item = PostItem::factory()->create(['post_id' => $post->id, 'title' => $case === 'text' ? 'Database guide' : 'Compiler release']);
            if ($case !== 'tag') {
                $item->tags()->attach($tag);
            }
            if ($case !== 'source') {
                $item->item->sources()->attach($source, ['first_seen_at' => now()]);
            }
            if ($case === 'match') {
                $expected = $item->id;
            }
        }
        $result = app(SearchQuery::class)->for($user, $this->filters(q: 'compiler', tags: [$tag->slug], source: $source->slug, from: '2026-10-20', to: '2026-10-29'));

        $this->assertSame([$expected], $result->getCollection()->modelKeys());
    }

    public function test_unfiltered_results_are_owned_paginated_ordered_and_eager_loaded(): void
    {
        $user = User::factory()->create();
        $old = $this->entry($user, '2026-10-20');
        $later = $this->entry($user, '2026-10-29', ['position' => 9]);
        $first = PostItem::factory()->create(['post_id' => $later->post_id, 'position' => 1]);
        $this->entry(User::factory()->create(), '2026-10-30');
        $result = app(SearchQuery::class)->for($user, $this->filters(), 2);

        $this->assertSame(3, $result->total());
        $this->assertSame(2, $result->perPage());
        $this->assertSame([$first->id, $later->id], $result->getCollection()->modelKeys());
        foreach ($result->items() as $item) {
            foreach (['post', 'item', 'tags'] as $relation) {
                $this->assertTrue($item->relationLoaded($relation));
            }
            $this->assertTrue($item->item->relationLoaded('sources'));
        }
        $this->assertSame(30, app(SearchQuery::class)->for($user, $this->filters())->perPage());
        Paginator::currentPageResolver(fn () => 2);
        try {
            $this->assertSame([$old->id], app(SearchQuery::class)->for($user, $this->filters(), 2)->getCollection()->modelKeys());
        } finally {
            Paginator::currentPageResolver(fn () => 1);
        }
    }

    public function test_options_and_source_filters_never_expose_other_users_custom_sources(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $item = $this->entry($user, '2026-10-28');
        $privateItem = $this->entry($other, '2026-10-29');
        $catalog = Source::factory()->create(['name' => 'Zulu']);
        $own = Source::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);
        $private = Source::factory()->create(['user_id' => $other->id, 'name' => 'Private']);
        $unused = Source::factory()->create();
        $item->item->sources()->attach([$catalog->id, $own->id, $private->id], ['first_seen_at' => now()]);
        $privateItem->item->sources()->attach($unused, ['first_seen_at' => now()]);
        $alpha = Tag::factory()->create(['name' => 'Alpha']);
        $zulu = Tag::factory()->create(['name' => 'Zulu']);
        $item->tags()->attach([$zulu->id, $alpha->id]);
        $privateItem->tags()->attach(Tag::factory()->create());
        $options = app(SearchQuery::class)->options($user);

        $this->assertSame([$alpha->id, $zulu->id], array_map(fn (Tag $tag) => $tag->id, $options['tags']));
        $this->assertSame([$own->id, $catalog->id], array_map(fn (Source $source) => $source->id, $options['sources']));
        foreach ([$catalog, $own] as $visible) {
            $this->assertSame([$item->id], app(SearchQuery::class)->for($user, $this->filters(source: $visible->slug))->getCollection()->modelKeys());
        }
        $this->assertSame(0, app(SearchQuery::class)->for($user, $this->filters(source: $private->slug))->total());
        $loaded = app(SearchQuery::class)->for($user, $this->filters())->items()[0];
        $this->assertEqualsCanonicalizing([$catalog->id, $own->id], $loaded->item->sources->modelKeys());
        $this->assertSame(['tags' => [], 'sources' => []], app(SearchQuery::class)->options(User::factory()->create()));
    }

    #[DataProvider('activeFilters')]
    public function test_active_count_excludes_text_and_counts_a_date_range_once(array $values, int $expected): void
    {
        $this->assertSame($expected, (new SearchFilters(...$values))->active());
    }

    public static function activeFilters(): array
    {
        return [
            'none' => [[null, [], null, null, null], 0],
            'text only' => [['compiler', [], null, null, null], 0],
            'all' => [['compiler', ['php', 'vue'], 'feed', '2026-10-20', '2026-10-29'], 4],
            'from only' => [[null, [], null, '2026-10-20', null], 1],
            'to only' => [[null, [], null, null, '2026-10-29'], 1],
        ];
    }

    public function test_search_vector_is_generated_updates_with_content_and_is_hidden(): void
    {
        $item = PostItem::factory()->create(['title' => 'Compiler release', 'summary' => null]);
        $vector = DB::table('post_items')->where('id', $item->id)->value('search');
        $this->assertIsString($vector);
        $this->assertStringContainsString('compil', $vector);
        $this->assertArrayHasKey('search', $item->fresh()->getAttributes());
        $this->assertArrayNotHasKey('search', $item->fresh()->toArray());
        $item->update(['title' => 'Database release', 'summary' => 'Transaction guide']);
        $vector = DB::table('post_items')->where('id', $item->id)->value('search');
        $this->assertStringContainsString('transact', $vector);
        $this->assertStringNotContainsString('compil', $vector);
    }

    public function test_search_migration_rolls_back_and_restores_generated_column_and_gin_index(): void
    {
        $this->assertTrue(Schema::hasColumn('post_items', 'search'));
        $column = DB::selectOne("SELECT udt_name, is_generated FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'post_items' AND column_name = 'search'");
        $this->assertSame('tsvector', $column->udt_name);
        $this->assertSame('ALWAYS', $column->is_generated);
        $indexes = fn () => DB::select("SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'post_items' AND indexdef ILIKE '%USING gin (search)%'");
        $this->assertCount(1, $indexes());
        $migrations = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path) => str_contains(file_get_contents($path), 'tsvector')));
        $this->assertCount(1, $migrations);
        $migration = require $migrations[0];
        $migration->down();
        try {
            $this->assertFalse(Schema::hasColumn('post_items', 'search'));
            $this->assertCount(0, $indexes());
        } finally {
            $migration->up();
        }
        $this->assertTrue(Schema::hasColumn('post_items', 'search'));
        $this->assertCount(1, $indexes());
    }

    private function entry(User $user, string $date, array $attributes = []): PostItem
    {
        $post = Post::factory()->create(['user_id' => $user->id, 'date' => $date]);

        return PostItem::factory()->create(['post_id' => $post->id, ...$attributes]);
    }

    private function filters(?string $q = null, array $tags = [], ?string $source = null, ?string $from = null, ?string $to = null): SearchFilters
    {
        return new SearchFilters($q, $tags, $source, $from, $to);
    }
}
