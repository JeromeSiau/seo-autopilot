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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('domain');
            $table->string('language', 5)->default('en');
            $table->text('gsc_token')->nullable();
            $table->text('gsc_refresh_token')->nullable();
            $table->text('ga4_token')->nullable();
            $table->text('ga4_refresh_token')->nullable();
            $table->string('ga4_property_id')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
