<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('ok');
            $table->text('error')->nullable();
            $table->text('suggested_url')->nullable();
            $table->timestamps();
            $table->index(['source_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_checks');
    }
};
