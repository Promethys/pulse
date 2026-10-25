<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digest_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('timezone')->default('UTC');
            $table->text('profile')->nullable();
            $table->boolean('news_enabled')->default(true);
            $table->string('news_time', 5)->default('09:00');
            $table->boolean('learning_enabled')->default(true);
            $table->string('learning_time', 5)->default('09:30');
            $table->unsignedTinyInteger('max_items')->default(20);
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->boolean('uses_instance_ai')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digest_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'uses_instance_ai']);
        });
    }
};
