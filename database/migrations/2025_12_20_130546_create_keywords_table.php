<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->integer('volume')->nullable();
            $table->integer('difficulty')->nullable();
            $table->decimal('cpc', 8, 2)->nullable();
            $table->enum('status', ['pending', 'scheduled', 'generating', 'completed', 'skipped'])->default('pending');
            $table->string('cluster_id')->nullable();
            $table->enum('source', ['search_console', 'ai_generated', 'manual', 'dataforseo'])->default('manual');
            $table->integer('current_position')->nullable();
            $table->integer('impressions')->nullable();
            $table->date('scheduled_for')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'cluster_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
