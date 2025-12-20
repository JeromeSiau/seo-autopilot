<?php

namespace App\Services\Publisher\DTOs;

use App\Models\Article;

readonly class PublishRequest
{
    public function __construct(
        public string $title,
        public string $content,
        public ?string $slug = null,
        public ?string $excerpt = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $featuredImageUrl = null,
        public ?string $featuredImagePath = null,
        public array $categories = [],
        public array $tags = [],
        public string $status = 'publish',
        public ?string $authorName = null,
    ) {}

    public static function fromArticle(Article $article): self
    {
        $images = $article->images ?? [];
        $featuredImage = $images['featured'] ?? null;

        return new self(
            title: $article->title,
            content: $article->content,
            slug: $article->slug,
            excerpt: $article->meta_description,
            metaTitle: $article->meta_title,
            metaDescription: $article->meta_description,
            featuredImageUrl: $featuredImage['url'] ?? null,
            featuredImagePath: $featuredImage['local_path'] ?? null,
            categories: [],
            tags: [],
            status: 'publish',
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'featured_image_url' => $this->featuredImageUrl,
            'categories' => $this->categories,
            'tags' => $this->tags,
            'status' => $this->status,
        ];
    }
}
