<?php

use App\Models\HostedPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table) {
            $table->dropUnique('hosted_pages_site_id_kind_unique');
            $table->string('slug', 150)->nullable()->after('kind');
            $table->string('navigation_label')->nullable()->after('title');
            $table->boolean('show_in_navigation')->default(true)->after('meta_description');
            $table->unsignedInteger('sort_order')->default(100)->after('show_in_navigation');
        });

        DB::table('hosted_pages')->orderBy('id')->get()->each(function (object $page): void {
            $slug = match ($page->kind) {
                HostedPage::KIND_HOME => 'home',
                HostedPage::KIND_ABOUT => 'about',
                HostedPage::KIND_LEGAL => 'legal',
                default => $page->slug ?: null,
            };

            $navigationLabel = $page->title;
            $showInNavigation = match ($page->kind) {
                HostedPage::KIND_LEGAL => false,
                default => true,
            };
            $sortOrder = match ($page->kind) {
                HostedPage::KIND_HOME => 0,
                HostedPage::KIND_ABOUT => 200,
                HostedPage::KIND_LEGAL => 900,
                default => 400,
            };

            DB::table('hosted_pages')
                ->where('id', $page->id)
                ->update([
                    'slug' => $slug,
                    'navigation_label' => $navigationLabel,
                    'show_in_navigation' => $showInNavigation,
                    'sort_order' => $sortOrder,
                ]);
        });

        Schema::table('hosted_pages', function (Blueprint $table) {
            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::table('hosted_pages', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'slug']);
            $table->dropIndex(['site_id', 'kind']);
            $table->dropColumn([
                'slug',
                'navigation_label',
                'show_in_navigation',
                'sort_order',
            ]);
            $table->unique(['site_id', 'kind']);
        });
    }
};
