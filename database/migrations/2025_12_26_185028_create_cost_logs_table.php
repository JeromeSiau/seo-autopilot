<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('costable');
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('operation');
            $table->decimal('cost', 10, 6);
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_logs');
    }
};
