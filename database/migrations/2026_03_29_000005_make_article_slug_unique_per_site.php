<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $articles = DB::table('articles')
            ->select('id', 'site_id', 'title', 'slug')
            ->orderBy('site_id')
            ->orderBy('id')
            ->get();

        $usedSlugs = [];

        foreach ($articles as $article) {
            $siteId = (int) $article->site_id;
            $baseSlug = Str::slug($article->slug ?: $article->title ?: 'article');
            $baseSlug = $baseSlug !== '' ? $baseSlug : 'article';

            $usedSlugs[$siteId] ??= [];

            $slug = $baseSlug;
            $suffix = 2;

            while (in_array($slug, $usedSlugs[$siteId], true)) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $usedSlugs[$siteId][] = $slug;

            if ($slug !== $article->slug) {
                DB::table('articles')
                    ->where('id', $article->id)
                    ->update(['slug' => $slug]);
            }
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['site_id', 'slug']);
        });
    }
};
