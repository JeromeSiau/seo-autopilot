<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
