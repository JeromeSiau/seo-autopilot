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
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('gsc_token_expires_at')->nullable()->after('gsc_refresh_token');
            $table->timestamp('ga4_token_expires_at')->nullable()->after('ga4_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['gsc_token_expires_at', 'ga4_token_expires_at']);
        });
    }
};
