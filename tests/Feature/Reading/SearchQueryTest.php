<?php

declare(strict_types=1);

namespace Tests\Feature\Reading;

use App\Enums\LearningKind;
use App\Enums\PostKind;
use App\Models\LearningPost;
use App\Models\LearningTopic;
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
        $this->assertSame(['tags' => [], 'sources' => [], 'categories' => []], app(SearchQuery::class)->options(User::factory()->create()));
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
        $migrations = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path) => str_contains(file_get_contents($path), 'ALTER TABLE post_items ADD COLUMN search')));
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

    #[DataProvider('learningTextQueries')]
    public function test_learning_searches_title_and_body_with_english_websearch(string $title, string $body, string $q, bool $matches): void
    {
        $user = User::factory()->create();
        $entry = $this->learningEntry($user, '2026-10-28', attributes: ['title' => $title, 'body' => $body]);
        $this->learningEntry(User::factory()->create(), '2026-10-29', attributes: ['title' => $title, 'body' => $body]);

        $result = app(SearchQuery::class)->learning($user, $this->filters(q: $q));

        $this->assertSame($matches ? [$entry->id] : [], $result->getCollection()->modelKeys());
    }

    public static function learningTextQueries(): array
    {
        return [
            'title' => ['Compiler guide', 'Measure builds', 'compiler', true],
            'body' => ['Measure builds', 'Inspect the compiler', 'compiler', true],
            'stemming' => ['Deployment guide', '', 'deploying', true],
            'empty title' => ['', 'Compiler guide', 'compiler', true],
            'unbalanced quote' => ['Compiler guide', '', '"compiler', true],
            'negative word' => ['Compiler guide', '', 'compiler -slow', true],
            'excluded word' => ['Slow compiler', '', 'compiler -slow', false],
            'stop words' => ['Compiler guide', '', 'the and of', false],
            'not a substring' => ['Compiler guide', '', 'pile', false],
        ];
    }

    #[DataProvider('learningFilterStates')]
    public function test_learning_filters_apply_individually_and_together(array $values, array $expected): void
    {
        $user = User::factory()->create();
        $php = Tag::factory()->create(['slug' => 'php']);
        $vue = Tag::factory()->create(['slug' => 'vue']);
        $entries = [
            $this->learningEntry($user, '2026-10-29', LearningKind::Topic, 'testing', ['title' => 'Compiler guide']),
            $this->learningEntry($user, '2026-10-28', LearningKind::Tip, 'testing', ['title' => 'Compiler guide']),
            $this->learningEntry($user, '2026-10-27', LearningKind::Topic, 'architecture', ['title' => 'Database guide']),
            $this->learningEntry($user, '2026-10-26', LearningKind::Topic, 'testing', ['title' => 'Compiler guide']),
        ];
        $entries[0]->tags()->attach([$php->id, $vue->id]);
        $entries[1]->tags()->attach($vue);
        $entries[2]->tags()->attach($php);
        $foreign = $this->learningEntry(User::factory()->create(), '2026-10-29', LearningKind::Topic, 'testing', ['title' => 'Compiler guide']);
        $foreign->tags()->attach([$php->id, $vue->id]);

        $result = app(SearchQuery::class)->learning($user, $this->filters(...$values));

        $this->assertSame(array_map(fn (int $index) => $entries[$index]->id, $expected), $result->getCollection()->modelKeys());
        $this->assertSame(count($expected), $result->total());
    }

    public static function learningFilterStates(): array
    {
        return [
            'text' => [['q' => 'compiler'], [0, 1, 3]],
            'any tags without duplicates' => [['tags' => ['php', 'vue']], [0, 1, 2]],
            'kind' => [['kind' => 'tip'], [1]],
            'category' => [['category' => 'testing'], [0, 1, 3]],
            'inclusive dates' => [['from' => '2026-10-27', 'to' => '2026-10-28'], [1, 2]],
            'from only' => [['from' => '2026-10-28'], [0, 1]],
            'to only' => [['to' => '2026-10-27'], [2, 3]],
            'combined' => [['q' => 'compiler', 'tags' => ['php', 'vue'], 'from' => '2026-10-28', 'to' => '2026-10-29', 'kind' => 'topic', 'category' => 'testing'], [0]],
            'unknown tag' => [['tags' => ['missing']], []],
            'unknown category' => [['category' => 'missing'], []],
            'source ignored' => [['source' => 'missing'], [0, 1, 2, 3]],
        ];
    }

    public function test_learning_is_ordered_paginated_and_eager_loaded(): void
    {
        $user = User::factory()->create();
        $old = $this->learningEntry($user, '2026-10-20');
        $topic = $this->learningEntry($user, '2026-10-29', LearningKind::Topic, 'testing');
        $tip = $this->learningEntry($user, '2026-10-29');
        $this->learningEntry(User::factory()->create(), '2026-10-30');
        $query = app(SearchQuery::class);
        $result = $query->learning($user, $this->filters(), 2);

        $this->assertSame(3, $result->total());
        $this->assertSame(2, $result->perPage());
        $this->assertSame([$tip->id, $topic->id], $result->getCollection()->modelKeys());
        foreach ($result->items() as $entry) {
            foreach (['post', 'topic', 'tags'] as $relation) {
                $this->assertTrue($entry->relationLoaded($relation));
            }
        }
        $this->assertSame(30, $query->learning($user, $this->filters())->perPage());
        Paginator::currentPageResolver(fn () => 2);
        try {
            $this->assertSame([$old->id], $query->learning($user, $this->filters(), 2)->getCollection()->modelKeys());
        } finally {
            Paginator::currentPageResolver(fn () => 1);
        }
    }

    public function test_counts_use_shared_and_type_specific_filters_without_pagination_or_foreign_results(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $source = Source::factory()->create();
        $news = $this->entry($user, '2026-10-28', ['title' => 'Compiler guide']);
        $news->tags()->attach($tag);
        $news->item->sources()->attach($source, ['first_seen_at' => now()]);
        $this->entry($user, '2026-10-27', ['title' => 'Compiler guide'])->tags()->attach($tag);
        foreach (range(1, 31) as $index) {
            $entry = $this->learningEntry($user, '2026-10-28', LearningKind::Topic, 'testing', ['title' => 'Compiler guide']);
            $entry->tags()->attach($tag);
        }
        $this->learningEntry($user, '2026-10-28', LearningKind::Tip, 'testing', ['title' => 'Compiler guide'])->tags()->attach($tag);
        $this->learningEntry($user, '2026-10-28', LearningKind::Topic, 'architecture', ['title' => 'Compiler guide'])->tags()->attach($tag);
        $this->learningEntry($user, '2026-10-28', LearningKind::Topic, 'testing', ['title' => 'Database guide'])->tags()->attach($tag);
        $this->learningEntry($user, '2026-10-28', LearningKind::Topic, 'testing', ['title' => 'Compiler guide']);
        $this->learningEntry($user, '2026-10-27', LearningKind::Topic, 'testing', ['title' => 'Compiler guide'])->tags()->attach($tag);
        $this->learningEntry(User::factory()->create(), '2026-10-28', LearningKind::Topic, 'testing', ['title' => 'Compiler guide'])->tags()->attach($tag);
        $this->entry(User::factory()->create(), '2026-10-28', ['title' => 'Compiler guide'])->tags()->attach($tag);
        $query = app(SearchQuery::class);
        $values = ['q' => 'compiler', 'tags' => [$tag->slug], 'source' => $source->slug, 'from' => '2026-10-28', 'to' => '2026-10-28', 'kind' => 'topic', 'category' => 'testing'];

        foreach (['news', 'learning'] as $type) {
            $this->assertSame(['news' => 1, 'learning' => 31], $query->counts($user, $this->filters(...[...$values, 'type' => $type])));
        }
        $this->assertSame([$news->id], $query->for($user, $this->filters(...$values))->getCollection()->modelKeys());
        $this->assertSame(['news' => 0, 'learning' => 0], $query->counts($user, $this->filters(q: 'missing')));
        $this->assertSame(['news' => 0, 'learning' => 0], $query->counts(User::factory()->create(), $this->filters()));
    }

    public function test_options_union_news_and_learning_tags_and_scope_distinct_sorted_categories(): void
    {
        $user = User::factory()->create();
        $alpha = Tag::factory()->create(['name' => 'Alpha']);
        $zulu = Tag::factory()->create(['name' => 'Zulu']);
        $this->entry($user, '2026-10-28')->tags()->attach($zulu);
        $this->learningEntry($user, '2026-10-28', LearningKind::Topic, 'testing')->tags()->attach([$alpha->id, $zulu->id]);
        $this->learningEntry($user, '2026-10-27', LearningKind::Topic, 'architecture')->tags()->attach($alpha);
        $this->learningEntry($user, '2026-10-26', LearningKind::Topic, 'testing');
        $this->learningEntry($user, '2026-10-25');
        $this->learningEntry(User::factory()->create(), '2026-10-28', LearningKind::Topic, 'private')->tags()->attach(Tag::factory()->create());
        Tag::factory()->create();
        LearningTopic::factory()->create(['category' => 'unused']);
        $options = app(SearchQuery::class)->options($user);

        $this->assertSame([$alpha->id, $zulu->id], array_map(fn (Tag $tag) => $tag->id, $options['tags']));
        $this->assertSame(['architecture', 'testing'], $options['categories']);
        $this->assertSame([], $options['sources']);
    }

    #[DataProvider('learningActiveFilters')]
    public function test_active_count_includes_kind_and_category_but_excludes_type(array $values, int $expected): void
    {
        $this->assertSame($expected, $this->filters(...$values)->active());
    }

    public static function learningActiveFilters(): array
    {
        return [
            'type only' => [['type' => 'learning'], 0],
            'kind' => [['kind' => 'tip'], 1],
            'category' => [['category' => 'testing'], 1],
            'all learning filters' => [['q' => 'compiler', 'tags' => ['php', 'vue'], 'from' => '2026-10-20', 'to' => '2026-10-29', 'type' => 'learning', 'kind' => 'topic', 'category' => 'testing'], 5],
        ];
    }

    public function test_learning_search_vector_is_generated_updates_and_is_hidden(): void
    {
        $entry = $this->learningEntry(User::factory()->create(), '2026-10-28', attributes: ['title' => 'Compiler guide', 'body' => '']);
        $vector = DB::table('learning_posts')->where('id', $entry->id)->value('search');
        $this->assertIsString($vector);
        $this->assertStringContainsString('compil', $vector);
        $this->assertArrayHasKey('search', $entry->fresh()->getAttributes());
        $this->assertArrayNotHasKey('search', $entry->fresh()->toArray());
        $entry->update(['title' => '', 'body' => 'Deployment guide']);
        $vector = DB::table('learning_posts')->where('id', $entry->id)->value('search');
        $this->assertStringContainsString('deploy', $vector);
        $this->assertStringNotContainsString('compil', $vector);
    }

    public function test_learning_search_migration_rolls_back_and_restores_generated_column_and_gin_index(): void
    {
        $this->assertTrue(Schema::hasColumn('learning_posts', 'search'));
        $column = DB::selectOne("SELECT udt_name, is_generated FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'learning_posts' AND column_name = 'search'");
        $this->assertSame('tsvector', $column->udt_name);
        $this->assertSame('ALWAYS', $column->is_generated);
        $indexes = fn () => DB::select("SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'learning_posts' AND indexdef ILIKE '%USING gin (search)%'");
        $this->assertCount(1, $indexes());
        $migrations = array_values(array_filter(glob(database_path('migrations/*.php')), fn (string $path) => str_contains(file_get_contents($path), 'ALTER TABLE learning_posts ADD COLUMN search')));
        $this->assertCount(1, $migrations);
        $migration = require $migrations[0];
        $migration->down();
        try {
            $this->assertFalse(Schema::hasColumn('learning_posts', 'search'));
            $this->assertCount(0, $indexes());
        } finally {
            $migration->up();
        }
        $this->assertTrue(Schema::hasColumn('learning_posts', 'search'));
        $this->assertCount(1, $indexes());
    }

    private function learningEntry(User $user, string $date, LearningKind $kind = LearningKind::Tip, ?string $category = null, array $attributes = []): LearningPost
    {
        $post = Post::query()->firstOrCreate(['user_id' => $user->id, 'date' => $date, 'kind' => PostKind::Learning]);

        return LearningPost::query()->create([
            'post_id' => $post->id, 'kind' => $kind,
            'learning_topic_id' => $category === null ? null : LearningTopic::factory()->create(['category' => $category])->id,
            'title' => 'Learning', 'body' => 'Body', 'links' => [], ...$attributes,
        ]);
    }

    private function entry(User $user, string $date, array $attributes = []): PostItem
    {
        $post = Post::factory()->create(['user_id' => $user->id, 'date' => $date]);

        return PostItem::factory()->create(['post_id' => $post->id, ...$attributes]);
    }

    private function filters(?string $q = null, array $tags = [], ?string $source = null, ?string $from = null, ?string $to = null, string $type = 'news', ?string $kind = null, ?string $category = null): SearchFilters
    {
        return new SearchFilters(q: $q, tags: $tags, source: $source, from: $from, to: $to, type: $type, kind: $kind, category: $category);
    }
}
