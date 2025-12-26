<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Note: created_at and ['site_id', 'status'] already exist
            $table->index(['site_id', 'created_at']);
        });

        // Note: keywords already has score and ['site_id', 'status'] indexes

        Schema::table('notifications', function (Blueprint $table) {
            // Note: ['user_id', 'read_at'] already exists
            $table->index('read_at');
        });

        // Note: agent_events already has ['article_id', 'created_at'] index
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
        });
    }
};
