<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_date');
            $table->enum('status', ['planned', 'generating', 'ready', 'published', 'skipped'])->default('planned');
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['site_id', 'scheduled_date']);
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_articles');
    }
};
