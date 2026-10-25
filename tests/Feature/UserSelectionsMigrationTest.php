<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSelectionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_items_and_tags_are_copied_in_item_id_order_per_post_without_changing_mvp_rows(): void
    {
        $migration = require database_path('migrations/2026_10_25_000001_add_user_selections_tables.php');
        $migration->down();
        DB::table('posts')->insert([
            ['id' => 101, 'date' => '2026-10-25', 'kind' => 'news'],
            ['id' => 102, 'date' => '2026-10-26', 'kind' => 'news'],
        ]);
        $this->insertMvpItem(30, 101, 'selected', 'Third item summary');
        $this->insertMvpItem(10, 101, 'selected', null);
        $this->insertMvpItem(20, 101, 'skipped', 'Skipped summary');
        $this->insertMvpItem(40, 101, 'candidate', null);
        $this->insertMvpItem(50, null, 'selected', 'Unattached summary');
        $this->insertMvpItem(60, 102, 'selected', 'Other post summary');
        DB::table('tags')->insert([
            ['id' => 101, 'slug' => 'php', 'name' => 'PHP'],
            ['id' => 102, 'slug' => 'testing', 'name' => 'Testing'],
        ]);
        DB::table('item_tag')->insert([
            ['item_id' => 10, 'tag_id' => 101],
            ['item_id' => 10, 'tag_id' => 102],
            ['item_id' => 30, 'tag_id' => 102],
            ['item_id' => 20, 'tag_id' => 101],
            ['item_id' => 40, 'tag_id' => 101],
            ['item_id' => 50, 'tag_id' => 101],
        ]);
        $items = DB::table('items')->orderBy('id')->get()->toArray();
        $tags = DB::table('item_tag')->orderBy('item_id')->orderBy('tag_id')->get()->toArray();
        $posts = DB::table('posts')->orderBy('id')->get()->toArray();

        $migration->up();

        $this->assertDatabaseCount('post_items', 3);
        foreach ([[10, 101, 1, null], [30, 101, 2, 'Third item summary'], [60, 102, 1, 'Other post summary']] as [$itemId, $postId, $position, $summary]) {
            $this->assertDatabaseHas('post_items', [
                'post_id' => $postId,
                'item_id' => $itemId,
                'title' => 'Item '.$itemId,
                'summary' => $summary,
                'position' => $position,
            ]);
        }
        foreach ([20, 40, 50] as $itemId) {
            $this->assertDatabaseMissing('post_items', ['item_id' => $itemId]);
        }
        $this->assertDatabaseCount('post_item_tag', 3);
        foreach ([[10, 101], [10, 102], [30, 102]] as [$itemId, $tagId]) {
            $this->assertDatabaseHas('post_item_tag', [
                'post_item_id' => DB::table('post_items')->where('item_id', $itemId)->value('id'),
                'tag_id' => $tagId,
            ]);
        }
        $this->assertEquals($items, DB::table('items')->orderBy('id')->get()->toArray());
        $this->assertEquals($tags, DB::table('item_tag')->orderBy('item_id')->orderBy('tag_id')->get()->toArray());
        foreach ($posts as $post) {
            $this->assertDatabaseHas('posts', [...(array) $post, 'user_id' => null]);
        }
    }

    public function test_copy_selections_skips_existing_rows_and_can_be_repeated_without_duplicate_tags(): void
    {
        $migration = require database_path('migrations/2026_10_25_000001_add_user_selections_tables.php');
        $migration->down();
        DB::table('posts')->insert(['id' => 101, 'date' => '2026-10-25', 'kind' => 'news']);
        $this->insertMvpItem(10, 101, 'selected', 'Original summary');
        DB::table('tags')->insert(['id' => 101, 'slug' => 'php', 'name' => 'PHP']);
        DB::table('item_tag')->insert(['item_id' => 10, 'tag_id' => 101]);
        $migration->up();
        DB::table('post_items')->where('item_id', 10)->update(['title' => 'Personal title', 'summary' => 'Personal summary', 'position' => 7]);
        $existing = DB::table('post_items')->where('item_id', 10)->first();
        $existingTags = DB::table('post_item_tag')->get()->toArray();
        $this->insertMvpItem(20, 101, 'selected', null);
        DB::table('item_tag')->insert(['item_id' => 20, 'tag_id' => 101]);

        $migration->copySelections();
        $migration->copySelections();

        $this->assertDatabaseCount('post_items', 2);
        $this->assertEquals($existing, DB::table('post_items')->where('item_id', 10)->first());
        $this->assertEquals($existingTags, DB::table('post_item_tag')->where('post_item_id', $existing->id)->get()->toArray());
        $this->assertDatabaseHas('post_items', ['post_id' => 101, 'item_id' => 20, 'title' => 'Item 20', 'summary' => null, 'position' => 2]);
        $this->assertDatabaseCount('post_item_tag', 2);
        $this->assertDatabaseHas('post_item_tag', [
            'post_item_id' => DB::table('post_items')->where('item_id', 20)->value('id'),
            'tag_id' => 101,
        ]);
    }

    public function test_rollback_removes_new_tables_and_user_id_and_restores_legacy_post_uniqueness(): void
    {
        $migration = require database_path('migrations/2026_10_25_000001_add_user_selections_tables.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('post_items'));
        $this->assertFalse(Schema::hasTable('post_item_tag'));
        $this->assertFalse(Schema::hasTable('learning_topic_usages'));
        $this->assertFalse(Schema::hasColumn('posts', 'user_id'));
        DB::table('posts')->insert(['date' => '2026-10-25', 'kind' => 'news']);
        DB::table('posts')->insert(['date' => '2026-10-25', 'kind' => 'learning']);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('posts')->insert(['date' => '2026-10-25', 'kind' => 'news']);
    }

    private function insertMvpItem(int $id, ?int $postId, string $status, ?string $summary): void
    {
        DB::table('items')->insert([
            'id' => $id,
            'source_slug' => 'laravel-news',
            'url' => 'https://example.com/items/'.$id,
            'url_normalized' => 'https://example.com/items/'.$id,
            'title' => 'Item '.$id,
            'excerpt' => 'Original excerpt',
            'links' => json_encode(['https://example.com/items/'.$id], JSON_THROW_ON_ERROR),
            'status' => $status,
            'summary' => $summary,
            'post_id' => $postId,
        ]);
    }
}
