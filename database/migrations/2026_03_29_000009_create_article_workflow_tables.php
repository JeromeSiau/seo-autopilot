<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'resolved_at']);
        });

        Schema::create('article_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'role']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_to')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'status']);
            $table->index(['requested_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('article_assignments');
        Schema::dropIfExists('editorial_comments');
    }
};
