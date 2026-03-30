<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('prompt');
            $table->string('topic')->nullable();
            $table->string('intent')->nullable();
            $table->unsignedInteger('priority')->default(50);
            $table->string('locale', 12)->default('en');
            $table->string('country', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'prompt']);
            $table->index(['site_id', 'is_active']);
        });

        Schema::create('ai_visibility_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_prompt_id')->constrained('ai_prompts')->cascadeOnDelete();
            $table->string('engine', 32);
            $table->string('status', 32)->default('completed');
            $table->unsignedInteger('visibility_score')->default(0);
            $table->boolean('appears')->default(false);
            $table->string('rank_bucket', 32)->nullable();
            $table->json('raw_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['site_id', 'engine', 'checked_at']);
            $table->index(['ai_prompt_id', 'engine', 'checked_at']);
        });

        Schema::create('ai_visibility_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_visibility_check_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('url')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('mention_type', 32)->default('domain');
            $table->unsignedInteger('position')->nullable();
            $table->boolean('is_our_brand')->default(false);
            $table->timestamps();

            $table->index(['ai_visibility_check_id', 'is_our_brand']);
        });

        Schema::create('ai_visibility_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_visibility_check_id')->constrained()->cascadeOnDelete();
            $table->string('source_domain')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_title')->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_sources');
        Schema::dropIfExists('ai_visibility_mentions');
        Schema::dropIfExists('ai_visibility_checks');
        Schema::dropIfExists('ai_prompts');
    }
};
