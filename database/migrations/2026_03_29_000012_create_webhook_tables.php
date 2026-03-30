<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->json('events');
            $table->text('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event_name');
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_endpoint_id', 'event_name']);
            $table->index(['status', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
