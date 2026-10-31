<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique('posts_date_kind_unique');
        });

        DB::statement('CREATE UNIQUE INDEX posts_user_id_date_kind_unique ON posts (user_id, date, kind) NULLS NOT DISTINCT');

        Schema::create('post_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['post_id', 'item_id']);
        });

        Schema::create('post_item_tag', function (Blueprint $table) {
            $table->foreignId('post_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_item_id', 'tag_id']);
        });

        Schema::create('learning_topic_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_topic_id')->constrained()->cascadeOnDelete();
            $table->date('used_on');
            $table->timestamps();

            $table->unique(['user_id', 'used_on']);
        });

        $this->copySelections();
    }

    public function copySelections(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO post_items (post_id, item_id, title, summary, position, created_at, updated_at)
            SELECT post_id, id, title, summary,
                ROW_NUMBER() OVER (PARTITION BY post_id ORDER BY id), NOW(), NOW()
            FROM items
            WHERE status = 'selected' AND post_id IS NOT NULL
            ON CONFLICT (post_id, item_id) DO NOTHING
            SQL);

        DB::statement(<<<'SQL'
            INSERT INTO post_item_tag (post_item_id, tag_id)
            SELECT post_items.id, item_tag.tag_id
            FROM post_items
            JOIN items ON items.id = post_items.item_id AND items.post_id = post_items.post_id
            JOIN item_tag ON item_tag.item_id = items.id
            WHERE items.status = 'selected'
            ON CONFLICT (post_item_id, tag_id) DO NOTHING
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_topic_usages');
        Schema::dropIfExists('post_item_tag');
        Schema::dropIfExists('post_items');

        DB::statement('DROP INDEX posts_user_id_date_kind_unique');

        Schema::table('posts', function (Blueprint $table) {
            $table->unique(['date', 'kind']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
