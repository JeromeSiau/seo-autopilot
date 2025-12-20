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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('published_remote_id')->nullable()->after('published_url');
            $table->string('published_via')->nullable()->after('published_remote_id');
            $table->text('error_message')->nullable()->after('published_via');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['published_remote_id', 'published_via', 'error_message']);
        });
    }
};
