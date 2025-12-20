<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('business_description')->nullable()->after('language');
            $table->string('target_audience')->nullable()->after('business_description');
            $table->json('topics')->nullable()->after('target_audience');
            $table->timestamp('last_crawled_at')->nullable()->after('topics');
            $table->timestamp('onboarding_completed_at')->nullable()->after('last_crawled_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'business_description',
                'target_audience',
                'topics',
                'last_crawled_at',
                'onboarding_completed_at',
            ]);
        });
    }
};
