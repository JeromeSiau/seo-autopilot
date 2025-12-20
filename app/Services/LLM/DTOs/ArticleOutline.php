<?php

namespace App\Services\LLM\DTOs;

class ArticleOutline
{
    public function __construct(
        public readonly string $title,
        public readonly string $metaTitle,
        public readonly string $metaDescription,
        public readonly array $sections,
        public readonly int $estimatedWordCount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            metaTitle: $data['meta_title'],
            metaDescription: $data['meta_description'],
            sections: array_map(
                fn($s) => OutlineSection::fromArray($s),
                $data['sections'] ?? []
            ),
            estimatedWordCount: $data['estimated_word_count'] ?? 1500,
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'sections' => array_map(fn($s) => $s->toArray(), $this->sections),
            'estimated_word_count' => $this->estimatedWordCount,
        ];
    }

    public function toPromptContext(): string
    {
        $context = "## Article Structure\n\n";
        $context .= "# {$this->title}\n\n";

        foreach ($this->sections as $index => $section) {
            $prefix = str_repeat('#', $section->level + 1);
            $context .= "{$prefix} {$section->heading}\n";
            $context .= "Target: ~{$section->targetWordCount} words\n";
            if (!empty($section->keyPoints)) {
                $context .= "Key points: " . implode(', ', $section->keyPoints) . "\n";
            }
            $context .= "\n";
        }

        return $context;
    }
}

class OutlineSection
{
    public function __construct(
        public readonly string $heading,
        public readonly int $level,
        public readonly int $targetWordCount,
        public readonly array $keyPoints = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            heading: $data['heading'],
            level: $data['level'] ?? 2,
            targetWordCount: $data['target_word_count'] ?? 200,
            keyPoints: $data['key_points'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'heading' => $this->heading,
            'level' => $this->level,
            'target_word_count' => $this->targetWordCount,
            'key_points' => $this->keyPoints,
        ];
    }
}
