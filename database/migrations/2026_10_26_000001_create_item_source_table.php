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
        Schema::create('item_source', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->timestamp('first_seen_at');
            $table->primary(['item_id', 'source_id']);
            $table->index(['source_id', 'first_seen_at']);
        });

        $this->backfill();
    }

    public function backfill(): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO item_source (item_id, source_id, first_seen_at)
            SELECT items.id, sources.id, items.created_at
            FROM items
            JOIN sources ON sources.slug = items.source_slug
            ON CONFLICT DO NOTHING
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('item_source');
    }
};
