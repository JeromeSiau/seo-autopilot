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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_voice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('images')->nullable();
            $table->enum('status', ['draft', 'generating', 'ready', 'published', 'failed'])->default('draft');
            $table->text('error_message')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('published_url')->nullable();
            $table->string('llm_used')->nullable();
            $table->decimal('generation_cost', 8, 4)->nullable();
            $table->integer('word_count')->nullable();
            $table->integer('generation_time_seconds')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index('keyword_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
