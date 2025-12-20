<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->timestamp('queued_at')->nullable()->after('score');
            $table->timestamp('processed_at')->nullable()->after('queued_at');
            $table->unsignedInteger('priority')->default(0)->after('processed_at');

            $table->index(['site_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'status', 'priority']);
            $table->dropColumn(['queued_at', 'processed_at', 'priority']);
        });
    }
};
