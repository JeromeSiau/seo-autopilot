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
        Schema::create('brand_voices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tone')->nullable();
            $table->json('vocabulary')->nullable();
            $table->text('writing_style')->nullable();
            $table->text('analyzed_from')->nullable();
            $table->json('examples')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_voices');
    }
};
