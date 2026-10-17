<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type');
            $table->text('url')->nullable();
            $table->string('tag');
            $table->unsignedInteger('max_items');
            $table->unsignedInteger('position')->index();
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('discord_tag_id')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('rendered')->nullable();
            $table->unsignedInteger('messages_sent')->default(0);
            $table->string('discord_thread_id')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('model_used')->nullable();
            $table->timestamps();

            $table->unique(['date', 'kind']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('source_slug')->index();
            $table->text('url');
            $table->string('url_normalized', 2048)->unique();
            $table->string('title', 500);
            $table->text('excerpt');
            $table->json('links');
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('candidate');
            $table->text('summary')->nullable();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('item_tag', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['item_id', 'tag_id']);
        });

        Schema::create('learning_topics', function (Blueprint $table) {
            $table->id();
            $table->string('title')->unique();
            $table->string('category');
            $table->date('last_used_on')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->foreignId('learning_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->json('links');
            $table->timestamps();
        });

        Schema::create('learning_post_tag', function (Blueprint $table) {
            $table->foreignId('learning_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['learning_post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_post_tag');
        Schema::dropIfExists('learning_posts');
        Schema::dropIfExists('learning_topics');
        Schema::dropIfExists('item_tag');
        Schema::dropIfExists('items');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('sources');
    }
};
