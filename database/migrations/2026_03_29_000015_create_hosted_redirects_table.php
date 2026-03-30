<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosted_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('source_path', 255);
            $table->string('destination_url', 2048);
            $table->unsignedSmallInteger('http_status')->default(301);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'source_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosted_redirects');
    }
};
