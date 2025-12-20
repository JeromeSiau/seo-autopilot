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
        Schema::create('article_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->decimal('position', 5, 2)->nullable();
            $table->decimal('ctr', 5, 2)->nullable();
            $table->integer('sessions')->nullable();
            $table->integer('page_views')->nullable();
            $table->decimal('avg_time_on_page', 8, 2)->nullable();
            $table->decimal('bounce_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'date']);
            $table->index(['article_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_analytics');
    }
};
