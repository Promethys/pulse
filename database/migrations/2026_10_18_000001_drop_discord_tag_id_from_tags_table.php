<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each forum has its own tag ids, so one id per tag was wrong; tags are matched by name per forum.
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('discord_tag_id');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('discord_tag_id')->nullable();
        });
    }
};
