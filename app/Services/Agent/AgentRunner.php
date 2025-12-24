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
        $tempFile = $this->createTempFile($content);

        try {
            return $this->runAgent('fact-checker-agent', [
                '--articleId' => $article->id,
                '--contentFile' => $tempFile,
            ]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function runInternalLinkingAgent(Article $article, string $content): array
    {
        $tempFile = $this->createTempFile($content);

        try {
            return $this->runAgent('internal-linking-agent', [
                '--articleId' => $article->id,
                '--siteId' => $article->site_id,
                '--contentFile' => $tempFile,
            ]);
        } finally {
            @unlink($tempFile);
        }
    }

    private function createTempFile(string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'article_');
        if ($tempFile === false) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        if (file_put_contents($tempFile, $content) === false) {
            @unlink($tempFile);
            throw new \RuntimeException('Failed to write content to temporary file');
        }

        return $tempFile;
    }

    private function runAgent(string $agentName, array $args): array
    {
        // Build command as array to prevent shell injection
        $command = ['node', "{$agentName}/index.js"];
        foreach ($args as $key => $value) {
            $command[] = "{$key}={$value}";
        }

        Log::info("AgentRunner: Starting {$agentName}", ['args' => $args]);

        $result = Process::path($this->agentsPath)
            ->timeout(600)
            ->run($command);

        if (!$result->successful()) {
            Log::error("AgentRunner: {$agentName} failed", [
                'output' => $result->output(),
                'error' => $result->errorOutput(),
                'exit_code' => $result->exitCode(),
            ]);

            $errorMessage = trim($result->errorOutput()) ?: trim($result->output()) ?: 'Unknown error';
            throw new \RuntimeException("Agent {$agentName} failed: {$errorMessage}");
        }

        // Parse JSON output from agent (last non-empty line)
        $output = trim($result->output());
        $lines = array_filter(explode("\n", $output), fn($line) => trim($line) !== '');
        $lastLine = end($lines);

        if (!$lastLine) {
            Log::warning("AgentRunner: {$agentName} produced no output");
            return ['raw_output' => ''];
        }

        try {
            return json_decode($lastLine, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning("AgentRunner: Could not parse JSON output", [
                'agent' => $agentName,
                'last_line' => $lastLine,
                'error' => $e->getMessage(),
            ]);
            return ['raw_output' => $output];
        }
    }
}
