<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DropSingleOwnerColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_up_copies_late_mvp_selections_tags_and_provenance_before_dropping_columns(): void
    {
        $this->freezeSecond();
        $migration = require database_path('migrations/2026_10_26_000002_drop_single_owner_columns.php');
        $migration->down();
        $user = User::factory()->create();
        DB::table('posts')->insert([
            ['id' => 101, 'user_id' => $user->id, 'date' => '2026-10-25', 'kind' => 'news', 'status' => 'posted'],
            ['id' => 102, 'user_id' => $user->id, 'date' => '2026-10-26', 'kind' => 'learning', 'status' => 'failed'],
        ]);
        DB::table('sources')->insert(['id' => 101, 'slug' => 'feed', 'name' => 'Feed', 'type' => 'feed', 'tag' => 'php', 'max_items' => 10, 'position' => 1]);
        foreach ([[10, 'selected', 101], [20, 'selected', 101], [30, 'skipped', 101], [40, 'selected', null]] as [$id, $status, $postId]) {
            $this->insertMvpItem($id, $postId, $status);
        }
        DB::table('tags')->insert(['id' => 101, 'slug' => 'php', 'name' => 'PHP']);
        foreach ([10, 20, 30, 40] as $id) {
            DB::table('item_tag')->insert(['item_id' => $id, 'tag_id' => 101]);
        }
        DB::table('post_items')->insert(['id' => 101, 'post_id' => 101, 'item_id' => 10, 'title' => 'Personal title', 'summary' => 'Personal summary', 'position' => 7, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('post_item_tag')->insert(['post_item_id' => 101, 'tag_id' => 101]);
        DB::table('item_source')->insert(['item_id' => 10, 'source_id' => 101, 'first_seen_at' => '2026-10-01 08:00:00']);
        $existing = DB::table('post_items')->where('id', 101)->first();

        $migration->up();

        $this->assertDatabaseCount('post_items', 2);
        $this->assertEquals($existing, DB::table('post_items')->where('id', 101)->first());
        $this->assertDatabaseHas('post_items', ['post_id' => 101, 'item_id' => 20, 'title' => 'Item 20', 'summary' => 'Summary 20', 'position' => 2]);
        $this->assertDatabaseCount('post_item_tag', 2);
        foreach ([10, 20] as $id) {
            $this->assertDatabaseHas('post_item_tag', ['post_item_id' => DB::table('post_items')->where('item_id', $id)->value('id'), 'tag_id' => 101]);
        }
        $this->assertDatabaseCount('item_source', 4);
        $this->assertDatabaseHas('item_source', ['item_id' => 10, 'source_id' => 101, 'first_seen_at' => '2026-10-01 08:00:00']);
        foreach ([20, 30, 40] as $id) {
            $this->assertDatabaseHas('item_source', ['item_id' => $id, 'source_id' => 101, 'first_seen_at' => '2026-10-20 08:00:00']);
        }
        $this->assertDatabaseHas('posts', ['id' => 101, 'user_id' => $user->id, 'status' => 'generated']);
        $this->assertDatabaseHas('posts', ['id' => 102, 'user_id' => $user->id, 'status' => 'failed']);
        $this->assertDatabaseCount('items', 4);
        $this->assertFalse(Schema::hasTable('item_tag'));
        foreach ($this->legacyColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertFalse(Schema::hasColumn($table, $column), $table.'.'.$column);
            }
        }
    }

    public function test_up_refuses_unowned_posts_before_changing_any_schema_or_data(): void
    {
        $migration = require database_path('migrations/2026_10_26_000002_drop_single_owner_columns.php');
        $migration->down();
        DB::table('posts')->insert(['id' => 101, 'user_id' => null, 'date' => '2026-10-25', 'kind' => 'news', 'status' => 'posted']);
        DB::table('sources')->insert(['id' => 101, 'slug' => 'feed', 'name' => 'Feed', 'type' => 'feed', 'tag' => 'php', 'max_items' => 10, 'position' => 1]);
        $this->insertMvpItem(10, 101, 'selected');
        DB::table('tags')->insert(['id' => 101, 'slug' => 'php', 'name' => 'PHP']);
        DB::table('item_tag')->insert(['item_id' => 10, 'tag_id' => 101]);
        $tables = ['posts', 'items', 'item_tag', 'post_items', 'post_item_tag', 'item_source', 'learning_topics'];
        $before = [];
        $columns = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->get()->toArray();
            $columns[$table] = Schema::getColumns($table);
        }

        try {
            $migration->up();
            $this->fail('Expected unowned posts to require pulse:adopt');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('pulse:adopt', $e->getMessage());
        }

        foreach ($tables as $table) {
            $this->assertEquals($before[$table], DB::table($table)->get()->toArray(), $table);
            $this->assertEquals($columns[$table], Schema::getColumns($table), $table);
        }
    }

    public function test_up_requires_a_user_for_new_posts(): void
    {
        $migration = require database_path('migrations/2026_10_26_000002_drop_single_owner_columns.php');
        $migration->down();
        $migration->up();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('user_id');

        DB::table('posts')->insert(['date' => '2026-10-25', 'kind' => 'news']);
    }

    public function test_down_restores_nullable_empty_columns_and_item_tag_without_recreating_legacy_data(): void
    {
        $migration = require database_path('migrations/2026_10_26_000002_drop_single_owner_columns.php');
        $migration->down();
        $user = User::factory()->create();
        DB::table('posts')->insert(['id' => 101, 'user_id' => $user->id, 'date' => '2026-10-25', 'kind' => 'news', 'status' => 'posted', 'messages_sent' => 2, 'discord_thread_id' => 'thread', 'posted_at' => now()]);
        $this->insertMvpItem(10, 101, 'selected');
        DB::table('learning_topics')->insert(['id' => 101, 'title' => 'Transactions', 'category' => 'databases', 'last_used_on' => '2026-10-25']);
        DB::table('tags')->insert(['id' => 101, 'slug' => 'php', 'name' => 'PHP']);
        DB::table('item_tag')->insert(['item_id' => 10, 'tag_id' => 101]);
        $migration->up();

        $migration->down();

        foreach ($this->legacyColumns() as $table => $names) {
            $columns = collect(Schema::getColumns($table));
            foreach ($names as $name) {
                $column = $columns->firstWhere('name', $name);
                $this->assertNotNull($column, $table.'.'.$name);
                $this->assertTrue($column['nullable'], $table.'.'.$name);
                $this->assertNull(DB::table($table)->value($name), $table.'.'.$name);
            }
        }
        $this->assertTrue(Schema::hasTable('item_tag'));
        $this->assertDatabaseCount('item_tag', 0);
        DB::table('item_tag')->insert(['item_id' => 10, 'tag_id' => 101]);
        $this->assertDatabaseHas('item_tag', ['item_id' => 10, 'tag_id' => 101]);
        DB::table('posts')->insert(['user_id' => null, 'date' => '2026-10-26', 'kind' => 'news']);
        $this->assertDatabaseHas('posts', ['date' => '2026-10-26', 'user_id' => null]);
        $this->assertDatabaseHas('posts', ['id' => 101, 'status' => 'generated']);
        $this->assertDatabaseHas('post_items', ['post_id' => 101, 'item_id' => 10, 'summary' => 'Summary 10']);
        $this->assertDatabaseCount('post_item_tag', 1);
    }

    /** @return array<string, list<string>> */
    private function legacyColumns(): array
    {
        return [
            'items' => ['status', 'summary', 'post_id', 'source_slug'],
            'learning_topics' => ['last_used_on'],
            'posts' => ['messages_sent', 'discord_thread_id', 'posted_at'],
        ];
    }

    private function insertMvpItem(int $id, ?int $postId, string $status): void
    {
        DB::table('items')->insert([
            'id' => $id,
            'source_slug' => 'feed',
            'url' => 'https://example.com/items/'.$id,
            'url_normalized' => 'https://example.com/items/'.$id,
            'title' => 'Item '.$id,
            'excerpt' => 'Original excerpt',
            'links' => json_encode(['https://example.com/items/'.$id], JSON_THROW_ON_ERROR),
            'status' => $status,
            'summary' => 'Summary '.$id,
            'post_id' => $postId,
            'created_at' => '2026-10-20 08:00:00',
            'updated_at' => '2026-10-20 08:00:00',
        ]);
    }
}
