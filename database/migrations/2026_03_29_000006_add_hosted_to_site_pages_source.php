<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE site_pages MODIFY COLUMN source ENUM('sitemap', 'gsc', 'crawl', 'hosted') NOT NULL DEFAULT 'sitemap'");
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();
        Schema::rename('site_pages', 'site_pages_old');
        DB::statement('DROP INDEX IF EXISTS site_pages_site_id_source_index');
        DB::statement('DROP INDEX IF EXISTS site_pages_site_id_url_unique');

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('title')->nullable();
            $table->enum('source', ['sitemap', 'gsc', 'crawl', 'hosted'])->default('sitemap');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url']);
            $table->index(['site_id', 'source']);
        });

        DB::table('site_pages_old')
            ->orderBy('id')
            ->get()
            ->each(fn (object $row) => DB::table('site_pages')->insert((array) $row));

        Schema::drop('site_pages_old');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE site_pages MODIFY COLUMN source ENUM('sitemap', 'gsc', 'crawl') NOT NULL DEFAULT 'sitemap'");
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();
        Schema::rename('site_pages', 'site_pages_old');
        DB::statement('DROP INDEX IF EXISTS site_pages_site_id_source_index');
        DB::statement('DROP INDEX IF EXISTS site_pages_site_id_url_unique');

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('title')->nullable();
            $table->enum('source', ['sitemap', 'gsc', 'crawl'])->default('sitemap');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url']);
            $table->index(['site_id', 'source']);
        });

        DB::table('site_pages_old')
            ->where('source', '!=', 'hosted')
            ->orderBy('id')
            ->get()
            ->each(fn (object $row) => DB::table('site_pages')->insert((array) $row));

        Schema::drop('site_pages_old');
        Schema::enableForeignKeyConstraints();
    }
};
