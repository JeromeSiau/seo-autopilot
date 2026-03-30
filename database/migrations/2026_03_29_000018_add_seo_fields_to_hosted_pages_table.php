<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table): void {
            $table->string('canonical_url')->nullable()->after('meta_description');
            $table->string('social_title')->nullable()->after('canonical_url');
            $table->string('social_description', 500)->nullable()->after('social_title');
            $table->foreignId('social_image_asset_id')
                ->nullable()
                ->after('social_description')
                ->constrained('hosted_assets')
                ->nullOnDelete();
            $table->string('social_image_url')->nullable()->after('social_image_asset_id');
            $table->boolean('robots_noindex')->default(false)->after('social_image_url');
            $table->boolean('schema_enabled')->default(true)->after('robots_noindex');
        });
    }

    public function down(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('social_image_asset_id');
            $table->dropColumn([
                'canonical_url',
                'social_title',
                'social_description',
                'social_image_url',
                'robots_noindex',
                'schema_enabled',
            ]);
        });
    }
};
