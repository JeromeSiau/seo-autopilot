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
            $table->string('crawl_status', 20)->default('pending')->after('last_crawled_at');
            $table->unsignedInteger('crawl_pages_count')->default(0)->after('crawl_status');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['crawl_status', 'crawl_pages_count']);
        });
    }
};
