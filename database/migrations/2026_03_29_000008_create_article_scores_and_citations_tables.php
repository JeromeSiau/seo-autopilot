<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->unsignedTinyInteger('brand_fit_score')->default(0);
            $table->unsignedTinyInteger('seo_score')->default(0);
            $table->unsignedTinyInteger('citation_score')->default(0);
            $table->unsignedTinyInteger('internal_link_score')->default(0);
            $table->unsignedTinyInteger('fact_confidence_score')->default(0);
            $table->json('warnings')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamps();
        });

        Schema::create('article_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('domain')->nullable();
            $table->text('excerpt')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_citations');
        Schema::dropIfExists('article_scores');
    }
};
