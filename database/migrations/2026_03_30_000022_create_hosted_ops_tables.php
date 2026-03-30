<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosted_export_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_hosting_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('target_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hosted_deploy_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_hosting_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('info');
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosted_deploy_events');
        Schema::dropIfExists('hosted_export_runs');
    }
};
