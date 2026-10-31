<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->timestamp('scouted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->dropColumn('scouted_at');
        });
    }
};
