<?php

declare(strict_types=1);

namespace Tests\Feature\Digest;

use App\Enums\SourceType;
use App\Models\Item;
use App\Models\Source;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItemSourceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_backfills_matching_slugs_with_original_creation_times_and_keeps_legacy_items(): void
    {
        $drop = require database_path('migrations/2026_10_26_000002_drop_single_owner_columns.php');
        $drop->down();

        $migration = require database_path('migrations/2026_10_26_000001_create_item_source_table.php');
        $migration->down();
        foreach (['first', 'second'] as $slug) {
            DB::table('sources')->insert([
                'slug' => $slug,
                'name' => $slug,
                'type' => SourceType::Feed->value,
                'tag' => 'devtools',
                'max_items' => 10,
                'position' => 1,
                'enabled' => false,
            ]);
        }
        $this->insertMvpItem(101, 'first', '2026-10-10 08:00:00');
        $this->insertMvpItem(102, 'first', '2026-10-11 09:00:00');
        $this->insertMvpItem(103, 'second', '2026-10-12 10:00:00');
        $this->insertMvpItem(104, 'unknown', '2026-10-13 11:00:00');
        $items = DB::table('items')->orderBy('id')->get()->toArray();

        $migration->up();

        $this->assertDatabaseCount('item_source', 3);
        foreach ([[101, 'first', '2026-10-10 08:00:00'], [102, 'first', '2026-10-11 09:00:00'], [103, 'second', '2026-10-12 10:00:00']] as [$itemId, $slug, $createdAt]) {
            $this->assertDatabaseHas('item_source', [
                'item_id' => $itemId,
                'source_id' => DB::table('sources')->where('slug', $slug)->value('id'),
                'first_seen_at' => $createdAt,
            ]);
        }
        $this->assertDatabaseMissing('item_source', ['item_id' => 104]);
        $this->assertTrue(Schema::hasColumn('items', 'source_slug'));
        $this->assertEquals($items, DB::table('items')->orderBy('id')->get()->toArray());
    }

    public function test_backfill_can_run_twice_without_duplicates_or_overwriting_first_seen_at(): void
    {
        $drop = require database_path('migrations/2026_10_26_000002_drop_single_owner_columns.php');
        $drop->down();

        $migration = require database_path('migrations/2026_10_26_000001_create_item_source_table.php');
        $migration->down();
        $source = Source::factory()->create(['slug' => 'first']);
        $this->insertMvpItem(101, 'first', '2026-10-10 08:00:00');
        $migration->up();
        DB::table('item_source')->where('item_id', 101)->update(['first_seen_at' => '2026-10-09 07:00:00']);
        $this->insertMvpItem(102, 'first', '2026-10-11 09:00:00');
        $this->insertMvpItem(103, 'unknown', '2026-10-12 10:00:00');

        $migration->backfill();
        $migration->backfill();

        $this->assertDatabaseCount('item_source', 2);
        $this->assertDatabaseHas('item_source', ['item_id' => 101, 'source_id' => $source->id, 'first_seen_at' => '2026-10-09 07:00:00']);
        $this->assertDatabaseHas('item_source', ['item_id' => 102, 'source_id' => $source->id, 'first_seen_at' => '2026-10-11 09:00:00']);
        $this->assertDatabaseMissing('item_source', ['item_id' => 103]);
    }

    public function test_schema_has_the_composite_primary_key_and_source_first_seen_index(): void
    {
        $indexes = Schema::getIndexes('item_source');

        $this->assertTrue(collect($indexes)->contains(fn (array $index) => $index['primary'] && $index['columns'] === ['item_id', 'source_id']));
        $this->assertTrue(collect($indexes)->contains(fn (array $index) => ! $index['unique'] && $index['columns'] === ['source_id', 'first_seen_at']));
        $column = collect(Schema::getColumns('item_source'))->firstWhere('name', 'first_seen_at');
        $this->assertNotNull($column);
        $this->assertSame('timestamp', $column['type_name']);
    }

    public function test_the_same_item_source_pair_cannot_be_inserted_twice(): void
    {
        $row = ['item_id' => Item::factory()->create()->id, 'source_id' => Source::factory()->create()->id, 'first_seen_at' => now()];
        DB::table('item_source')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('item_source')->insert($row);
    }

    public function test_deleting_an_item_cascades_only_its_provenance_rows(): void
    {
        $item = Item::factory()->create();
        $other = Item::factory()->create();
        $sources = Source::factory()->count(2)->create();
        foreach ($sources as $source) {
            DB::table('item_source')->insert(['item_id' => $item->id, 'source_id' => $source->id, 'first_seen_at' => now()]);
        }
        DB::table('item_source')->insert(['item_id' => $other->id, 'source_id' => $sources[0]->id, 'first_seen_at' => now()]);

        $item->delete();

        $this->assertDatabaseMissing('item_source', ['item_id' => $item->id]);
        $this->assertDatabaseCount('item_source', 1);
        $this->assertModelExists($other);
        foreach ($sources as $source) {
            $this->assertModelExists($source);
        }
    }

    public function test_deleting_a_source_cascades_only_its_provenance_rows(): void
    {
        $source = Source::factory()->create();
        $other = Source::factory()->create();
        $items = Item::factory()->count(2)->create();
        foreach ($items as $item) {
            DB::table('item_source')->insert(['item_id' => $item->id, 'source_id' => $source->id, 'first_seen_at' => now()]);
        }
        DB::table('item_source')->insert(['item_id' => $items[0]->id, 'source_id' => $other->id, 'first_seen_at' => now()]);

        $source->delete();

        $this->assertDatabaseMissing('item_source', ['source_id' => $source->id]);
        $this->assertDatabaseCount('item_source', 1);
        $this->assertModelExists($other);
        foreach ($items as $item) {
            $this->assertModelExists($item);
        }
    }

    public function test_item_sources_relationship_exposes_all_sources_with_datetime_first_seen_at(): void
    {
        $item = Item::factory()->create();
        $sources = Source::factory()->count(2)->create();
        $seenAt = now()->startOfSecond();

        $this->assertInstanceOf(BelongsToMany::class, $item->sources());
        foreach ($sources as $source) {
            $item->sources()->attach($source, ['first_seen_at' => $seenAt]);
        }

        $this->assertEqualsCanonicalizing($sources->modelKeys(), $item->fresh()->sources->modelKeys());
        foreach ($item->fresh()->sources as $source) {
            $this->assertInstanceOf(CarbonInterface::class, $source->pivot->first_seen_at);
            $this->assertTrue($source->pivot->first_seen_at->equalTo($seenAt));
        }
    }

    public function test_source_items_relationship_exposes_all_items_with_datetime_first_seen_at(): void
    {
        $source = Source::factory()->create();
        $items = Item::factory()->count(2)->create();
        $seenAt = now()->startOfSecond();

        $this->assertInstanceOf(BelongsToMany::class, $source->items());
        foreach ($items as $item) {
            $source->items()->attach($item, ['first_seen_at' => $seenAt]);
        }

        $this->assertEqualsCanonicalizing($items->modelKeys(), $source->fresh()->items->modelKeys());
        foreach ($source->fresh()->items as $item) {
            $this->assertInstanceOf(CarbonInterface::class, $item->pivot->first_seen_at);
            $this->assertTrue($item->pivot->first_seen_at->equalTo($seenAt));
        }
    }

    private function insertMvpItem(int $id, string $slug, string $createdAt): void
    {
        DB::table('items')->insert([
            'id' => $id,
            'source_slug' => $slug,
            'url' => 'https://example.com/items/'.$id,
            'url_normalized' => 'https://example.com/items/'.$id,
            'title' => 'Item '.$id,
            'excerpt' => 'Original excerpt',
            'links' => json_encode(['https://example.com/items/'.$id], JSON_THROW_ON_ERROR),
            'status' => 'candidate',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
