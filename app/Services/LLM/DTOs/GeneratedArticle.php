<?php

namespace App\Services\LLM\DTOs;

class GeneratedArticle
{
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly string $metaTitle,
        public readonly string $metaDescription,
        public readonly array $images,
        public readonly array $internalLinkSuggestions,
        public readonly int $wordCount,
        public readonly float $totalCost,
        public readonly int $generationTimeSeconds,
        public readonly array $llmsUsed,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            content: $data['content'],
            metaTitle: $data['meta_title'],
            metaDescription: $data['meta_description'],
            images: $data['images'] ?? [],
            internalLinkSuggestions: $data['internal_link_suggestions'] ?? [],
            wordCount: $data['word_count'] ?? str_word_count(strip_tags($data['content'])),
            totalCost: $data['total_cost'] ?? 0,
            generationTimeSeconds: $data['generation_time_seconds'] ?? 0,
            llmsUsed: $data['llms_used'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'images' => $this->images,
            'internal_link_suggestions' => $this->internalLinkSuggestions,
            'word_count' => $this->wordCount,
            'total_cost' => $this->totalCost,
            'generation_time_seconds' => $this->generationTimeSeconds,
            'llms_used' => $this->llmsUsed,
        ];
    }
}
