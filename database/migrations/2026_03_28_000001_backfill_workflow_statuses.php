<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('articles')
            ->where('status', 'ready')
            ->update(['status' => 'review']);

        DB::table('keywords')
            ->where('status', 'processing')
            ->update(['status' => 'queued']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE articles
                MODIFY COLUMN status ENUM('draft', 'generating', 'review', 'approved', 'published', 'failed')
                NOT NULL DEFAULT 'draft'
            ");

            DB::statement("
                ALTER TABLE keywords
                MODIFY COLUMN status ENUM('pending', 'queued', 'generating', 'completed', 'scheduled', 'failed', 'skipped')
                NOT NULL DEFAULT 'pending'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE articles
                MODIFY COLUMN status ENUM('draft', 'generating', 'ready', 'published', 'failed')
                NOT NULL DEFAULT 'draft'
            ");

            DB::statement("
                ALTER TABLE keywords
                MODIFY COLUMN status ENUM('pending', 'scheduled', 'generating', 'completed', 'skipped')
                NOT NULL DEFAULT 'pending'
            ");
        }

        DB::table('articles')
            ->whereIn('status', ['review', 'approved'])
            ->update(['status' => 'ready']);

        DB::table('keywords')
            ->where('status', 'queued')
            ->update(['status' => 'pending']);
    }
};
