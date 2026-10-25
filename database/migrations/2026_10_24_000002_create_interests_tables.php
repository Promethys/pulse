<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interests', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->index();
            $table->string('icon');
            $table->unsignedInteger('position');
            $table->boolean('has_learning')->default(false);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('interest_user', function (Blueprint $table) {
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['interest_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_user');
        Schema::dropIfExists('interests');
    }
};
