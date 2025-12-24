<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->string('agent_type', 50); // research, competitor, fact_checker, internal_linking, writing, outline, polish
            $table->string('event_type', 50); // started, progress, completed, error
            $table->text('message');
            $table->text('reasoning')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('progress_current')->nullable();
            $table->unsignedInteger('progress_total')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'created_at']);
            $table->index('agent_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_events');
    }
};
