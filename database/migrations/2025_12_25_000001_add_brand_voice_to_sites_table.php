<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('tone')->nullable()->after('topics');
            $table->text('writing_style')->nullable()->after('tone');
            $table->json('vocabulary')->nullable()->after('writing_style');
            $table->json('brand_examples')->nullable()->after('vocabulary');
        });

        // Remove brand_voice_id from articles if it exists
        if (Schema::hasColumn('articles', 'brand_voice_id')) {
            Schema::table('articles', function (Blueprint $table) {
                try {
                    $table->dropForeign(['brand_voice_id']);
                } catch (\Exception $e) {
                    // Foreign key may not exist, continue
                }
                $table->dropColumn('brand_voice_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['tone', 'writing_style', 'vocabulary', 'brand_examples']);
        });
    }
};
