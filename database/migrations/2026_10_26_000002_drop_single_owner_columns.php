<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('posts')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Run php artisan pulse:adopt <email> before removing the single-owner columns.');
        }

        (require database_path('migrations/2026_10_25_000001_add_user_selections_tables.php'))->copySelections();
        (require database_path('migrations/2026_10_26_000001_create_item_source_table.php'))->backfill();

        DB::table('posts')->where('status', 'posted')->update(['status' => 'generated']);

        Schema::dropIfExists('item_tag');

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropIndex('items_source_slug_index');
            $table->dropIndex('items_status_created_at_index');
            $table->dropColumn(['status', 'summary', 'post_id', 'source_slug']);
        });

        Schema::table('learning_topics', function (Blueprint $table) {
            $table->dropColumn('last_used_on');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['messages_sent', 'discord_thread_id', 'posted_at']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('source_slug')->nullable()->index();
            $table->string('status')->nullable();
            $table->text('summary')->nullable();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['status', 'created_at']);
        });

        Schema::create('item_tag', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['item_id', 'tag_id']);
        });

        Schema::table('learning_topics', function (Blueprint $table) {
            $table->date('last_used_on')->nullable();
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('messages_sent')->nullable();
            $table->string('discord_thread_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('user_id')->nullable()->change();
        });
    }
};
