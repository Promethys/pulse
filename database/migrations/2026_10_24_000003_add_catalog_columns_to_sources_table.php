<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->text('site_url')->nullable();
            $table->text('credentials')->nullable();
        });

        DB::table('sources')->where('enabled', true)->where('slug', '<>', 'newsletters')->update(['is_default' => true]);

        Schema::create('interest_source', function (Blueprint $table) {
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->primary(['interest_id', 'source_id']);
            $table->timestamps();
        });

        Schema::create('source_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unique(['user_id', 'source_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_subscriptions');
        Schema::dropIfExists('interest_source');

        Schema::table('sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['is_default', 'site_url', 'credentials']);
        });
    }
};
