<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('status');
            $table->unsignedSmallInteger('max_attempts')->nullable()->after('attempt_number');
            $table->timestamp('next_retry_at')->nullable()->after('attempted_at');

            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_retry_at']);
            $table->dropColumn([
                'attempt_number',
                'max_attempts',
                'next_retry_at',
            ]);
        });
    }
};
