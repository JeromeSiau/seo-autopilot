<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_type', 32);
            $table->string('severity', 16)->default('medium');
            $table->text('reason');
            $table->json('recommended_actions')->nullable();
            $table->json('metrics_snapshot')->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status', 'detected_at']);
            $table->index(['article_id', 'status']);
        });

        Schema::create('article_refresh_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refresh_recommendation_id')->nullable()->constrained()->nullOnDelete();
            $table->json('old_score_snapshot')->nullable();
            $table->json('new_score_snapshot')->nullable();
            $table->string('status', 16)->default('drafted');
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_refresh_runs');
        Schema::dropIfExists('refresh_recommendations');
    }
};
