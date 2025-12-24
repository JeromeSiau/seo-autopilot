<?php

namespace App\Services\Agent;

use App\Models\Article;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class AgentRunner
{
    private string $agentsPath;

    public function __construct()
    {
        $this->agentsPath = base_path('agents');
    }

    public function runResearchAgent(Article $article, string $keyword): array
    {
        return $this->runAgent('research-agent', [
            '--articleId' => $article->id,
            '--keyword' => $keyword,
            '--siteId' => $article->site_id,
        ]);
    }

    public function runCompetitorAgent(Article $article, string $keyword, array $urls): array
    {
        return $this->runAgent('competitor-agent', [
            '--articleId' => $article->id,
            '--keyword' => $keyword,
            '--urls' => json_encode($urls),
        ]);
    }

    public function runFactCheckerAgent(Article $article, string $content): array
    {
        // Write content to temp file (too long for CLI arg)
        $tempFile = tempnam(sys_get_temp_dir(), 'article_');
        file_put_contents($tempFile, $content);

        $result = $this->runAgent('fact-checker-agent', [
            '--articleId' => $article->id,
            '--contentFile' => $tempFile,
        ]);

        unlink($tempFile);
        return $result;
    }

    public function runInternalLinkingAgent(Article $article, string $content): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'article_');
        file_put_contents($tempFile, $content);

        $result = $this->runAgent('internal-linking-agent', [
            '--articleId' => $article->id,
            '--siteId' => $article->site_id,
            '--contentFile' => $tempFile,
        ]);

        unlink($tempFile);
        return $result;
    }

    private function runAgent(string $agentName, array $args): array
    {
        $command = $this->buildCommand($agentName, $args);

        Log::info("AgentRunner: Starting {$agentName}", ['args' => $args]);

        $result = Process::path($this->agentsPath)
            ->timeout(600) // 10 minutes max
            ->run($command);

        if (!$result->successful()) {
            Log::error("AgentRunner: {$agentName} failed", [
                'output' => $result->output(),
                'error' => $result->errorOutput(),
            ]);
            throw new \RuntimeException("Agent {$agentName} failed: " . $result->errorOutput());
        }

        // Parse JSON output from agent
        $output = trim($result->output());
        $lines = explode("\n", $output);
        $lastLine = end($lines);

        try {
            return json_decode($lastLine, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning("AgentRunner: Could not parse JSON output", ['output' => $output]);
            return ['raw_output' => $output];
        }
    }

    private function buildCommand(string $agentName, array $args): string
    {
        $parts = ["node {$agentName}/index.js"];

        foreach ($args as $key => $value) {
            if (is_string($value) && str_contains($value, ' ')) {
                $parts[] = "{$key}=" . escapeshellarg($value);
            } else {
                $parts[] = "{$key}={$value}";
            }
        }

        return implode(' ', $parts);
    }
}
