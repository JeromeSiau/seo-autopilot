<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->boolean('autopilot_enabled')->default(false);
            $table->unsignedTinyInteger('articles_per_week')->default(5);
            $table->json('publish_days')->default('["mon","tue","wed","thu","fri","sat","sun"]');
            $table->boolean('auto_publish')->default(true);
            $table->timestamps();

            $table->unique('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
