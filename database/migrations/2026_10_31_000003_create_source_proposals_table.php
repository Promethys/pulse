<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('origin');
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('interest_id')->nullable()->constrained()->nullOnDelete();
            $table->text('url');
            $table->text('site_url')->nullable();
            $table->string('name')->nullable();
            $table->text('reason')->nullable();
            $table->json('checks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_proposals');
    }
};
