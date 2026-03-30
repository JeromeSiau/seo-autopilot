<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosted_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('placement', 16)->default('header');
            $table->string('type', 16)->default('path');
            $table->string('label');
            $table->string('path')->nullable();
            $table->string('url')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['site_id', 'placement', 'is_active']);
            $table->index(['site_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosted_navigation_items');
    }
};
