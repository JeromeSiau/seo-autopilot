<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'key']);
        });

        Schema::table('ai_prompts', function (Blueprint $table) {
            $table->foreignId('ai_prompt_set_id')
                ->nullable()
                ->after('site_id')
                ->constrained('ai_prompt_sets')
                ->nullOnDelete();
        });

        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->string('provider', 64)->default('estimated')->after('engine');
        });

        Schema::create('ai_visibility_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_prompt_id')->nullable()->constrained('ai_prompts')->nullOnDelete();
            $table->foreignId('ai_visibility_check_id')->nullable()->constrained('ai_visibility_checks')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('fingerprint');
            $table->string('type', 64);
            $table->string('severity', 16);
            $table->string('title');
            $table->text('reason');
            $table->string('engine', 32)->nullable();
            $table->decimal('visibility_delta', 8, 1)->nullable();
            $table->json('related_domains')->nullable();
            $table->string('status', 16)->default('open');
            $table->json('metadata')->nullable();
            $table->timestamp('first_detected_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'fingerprint']);
            $table->index(['site_id', 'status', 'last_detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_visibility_alerts');

        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->dropColumn('provider');
        });

        Schema::table('ai_prompts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_prompt_set_id');
        });

        Schema::dropIfExists('ai_prompt_sets');
    }
};
