<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sources')
            ->whereIn('slug', ['r-laravel', 'r-claudeai', 'r-localllama'])
            ->update(['enabled' => false]);
    }

    public function down(): void
    {
        DB::table('sources')
            ->whereIn('slug', ['r-laravel', 'r-claudeai', 'r-localllama'])
            ->update(['enabled' => true]);
    }
};
