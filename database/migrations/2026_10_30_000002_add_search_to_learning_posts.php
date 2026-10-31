<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE learning_posts ADD COLUMN search tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(title, '') || ' ' || coalesce(body, ''))) STORED");
        DB::statement('CREATE INDEX learning_posts_search_index ON learning_posts USING GIN (search)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX learning_posts_search_index');
        DB::statement('ALTER TABLE learning_posts DROP COLUMN search');
    }
};
