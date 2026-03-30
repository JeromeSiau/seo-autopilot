<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosted_authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('hosted_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('hosted_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('hosted_author_id')
                ->nullable()
                ->after('site_id')
                ->constrained('hosted_authors')
                ->nullOnDelete();
            $table->foreignId('hosted_category_id')
                ->nullable()
                ->after('hosted_author_id')
                ->constrained('hosted_categories')
                ->nullOnDelete();
        });

        Schema::create('article_hosted_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hosted_tag_id')->constrained('hosted_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['article_id', 'hosted_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_hosted_tag');

        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('hosted_author_id');
            $table->dropConstrainedForeignId('hosted_category_id');
        });

        Schema::dropIfExists('hosted_tags');
        Schema::dropIfExists('hosted_categories');
        Schema::dropIfExists('hosted_authors');
    }
};
