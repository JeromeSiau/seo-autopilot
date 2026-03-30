<?php

namespace App\Services\AiVisibility\Connectors;

use App\Models\AiPrompt;
use App\Models\Site;

interface AiVisibilityConnector
{
    public function key(): string;

    public function evaluate(Site $site, AiPrompt $prompt, string $engine, array $analysis): array;
}
