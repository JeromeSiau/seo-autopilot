<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('source_url')->nullable();
            $table->text('content');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'is_active']);
            $table->index(['site_id', 'type']);
        });

        Schema::create('brand_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('label');
            $table->text('value');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['site_id', 'is_active']);
            $table->index(['site_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_rules');
        Schema::dropIfExists('brand_assets');
    }
};
