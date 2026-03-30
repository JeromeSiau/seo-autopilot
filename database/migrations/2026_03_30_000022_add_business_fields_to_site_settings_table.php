<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->decimal('modeled_conversion_rate', 5, 2)->nullable()->after('articles_allocated');
            $table->decimal('average_conversion_value', 10, 2)->nullable()->after('modeled_conversion_rate');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['modeled_conversion_rate', 'average_conversion_value']);
        });
    }
};
