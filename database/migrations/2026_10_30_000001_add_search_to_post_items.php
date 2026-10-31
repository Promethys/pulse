<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE post_items ADD COLUMN search tsvector GENERATED ALWAYS AS (to_tsvector('english', coalesce(title, '') || ' ' || coalesce(summary, ''))) STORED");
        DB::statement('CREATE INDEX post_items_search_index ON post_items USING GIN (search)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX post_items_search_index');
        DB::statement('ALTER TABLE post_items DROP COLUMN search');
    }
};
