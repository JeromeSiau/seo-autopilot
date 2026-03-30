<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table) {
            $table->boolean('show_in_sitemap')->default(true)->after('schema_enabled');
            $table->boolean('show_in_feed')->default(false)->after('show_in_sitemap');
            $table->boolean('breadcrumbs_enabled')->default(true)->after('show_in_feed');
        });

        DB::table('hosted_pages')
            ->whereIn('kind', ['home', 'about'])
            ->update([
                'show_in_sitemap' => true,
                'breadcrumbs_enabled' => true,
            ]);

        DB::table('hosted_pages')
            ->where('kind', 'legal')
            ->update([
                'show_in_sitemap' => false,
                'breadcrumbs_enabled' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table) {
            $table->dropColumn([
                'show_in_sitemap',
                'show_in_feed',
                'breadcrumbs_enabled',
            ]);
        });
    }
};
