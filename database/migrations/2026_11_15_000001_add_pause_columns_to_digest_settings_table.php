<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digest_settings', function (Blueprint $table) {
            $table->timestamp('paused_at')->nullable();
            $table->date('paused_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('digest_settings', function (Blueprint $table) {
            $table->dropColumn(['paused_at', 'paused_until']);
        });
    }
};
