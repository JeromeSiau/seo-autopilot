# Agent System Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform SEO Autopilot into a transparent agent-based system with real-time activity feed, web research, fact checking, and internal linking.

**Architecture:** Node.js agents running as subprocesses communicate with Laravel via Redis pub/sub. Events are stored in DB and broadcast to frontend via Laravel Reverb WebSockets.

**Tech Stack:** Laravel 12, Node.js (Crawlee + Puppeteer), Redis, Laravel Reverb, React + TypeScript

---

## Phase 1: Infrastructure Setup

### Task 1.1: Create agent_events Migration

**Files:**
- Create: `database/migrations/2025_12_24_000001_create_agent_events_table.php`

**Step 1: Create migration file**

```bash
php artisan make:migration create_agent_events_table
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');
            $table->string('agent_type', 50); // research, competitor, fact_checker, internal_linking, writing, outline, polish
            $table->string('event_type', 50); // started, progress, completed, error
            $table->text('message');
            $table->text('reasoning')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('progress_current')->nullable();
            $table->unsignedInteger('progress_total')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'created_at']);
            $table->index('agent_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_events');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```
Expected: Migration runs successfully

**Step 4: Commit**

```bash
git add database/migrations/*_create_agent_events_table.php
git commit -m "feat: add agent_events table for activity tracking"
```

---

### Task 1.2: Create AgentEvent Model

**Files:**
- Create: `app/Models/AgentEvent.php`

**Step 1: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEvent extends Model
{
    protected $fillable = [
        'article_id',
        'agent_type',
        'event_type',
        'message',
        'reasoning',
        'metadata',
        'progress_current',
        'progress_total',
    ];

    protected $casts = [
        'metadata' => 'array',
        'progress_current' => 'integer',
        'progress_total' => 'integer',
    ];

    public const TYPE_RESEARCH = 'research';
    public const TYPE_COMPETITOR = 'competitor';
    public const TYPE_FACT_CHECKER = 'fact_checker';
    public const TYPE_INTERNAL_LINKING = 'internal_linking';
    public const TYPE_OUTLINE = 'outline';
    public const TYPE_WRITING = 'writing';
    public const TYPE_POLISH = 'polish';

    public const EVENT_STARTED = 'started';
    public const EVENT_PROGRESS = 'progress';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_ERROR = 'error';

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function getProgressPercentAttribute(): ?int
    {
        if ($this->progress_total === null || $this->progress_total === 0) {
            return null;
        }
        return (int) round(($this->progress_current / $this->progress_total) * 100);
    }

    public function scopeForArticle($query, int $articleId)
    {
        return $query->where('article_id', $articleId);
    }

    public function scopeByAgent($query, string $agentType)
    {
        return $query->where('agent_type', $agentType);
    }
}
```

**Step 2: Add relation to Article model**

In `app/Models/Article.php`, add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// Add this method
public function agentEvents(): HasMany
{
    return $this->hasMany(AgentEvent::class);
}
```

**Step 3: Commit**

```bash
git add app/Models/AgentEvent.php app/Models/Article.php
git commit -m "feat: add AgentEvent model with Article relation"
```

---

### Task 1.3: Install and Configure Laravel Reverb

**Files:**
- Modify: `composer.json`
- Modify: `config/broadcasting.php`
- Create: `config/reverb.php`

**Step 1: Install Reverb**

```bash
php artisan install:broadcasting
```

When prompted, select "Reverb" as the broadcaster.

**Step 2: Configure environment**

Add to `.env`:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=seo-autopilot
REVERB_APP_KEY=your-app-key-here
REVERB_APP_SECRET=your-app-secret-here
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

**Step 3: Install frontend dependencies**

```bash
npm install laravel-echo pusher-js
```

**Step 4: Configure Echo**

Create/modify `resources/js/echo.ts`:

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

**Step 5: Add Vite env variables**

Add to `.env`:

```env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Step 6: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json config/ resources/js/echo.ts .env.example
git commit -m "feat: install and configure Laravel Reverb for WebSocket broadcasting"
```

---

### Task 1.4: Create AgentActivityEvent Broadcast Event

**Files:**
- Create: `app/Events/AgentActivityEvent.php`

**Step 1: Create event**

```php
<?php

namespace App\Events;

use App\Models\AgentEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentActivityEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AgentEvent $agentEvent
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('article.' . $this->agentEvent->article_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'agent.activity';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->agentEvent->id,
            'agent_type' => $this->agentEvent->agent_type,
            'event_type' => $this->agentEvent->event_type,
            'message' => $this->agentEvent->message,
            'reasoning' => $this->agentEvent->reasoning,
            'metadata' => $this->agentEvent->metadata,
            'progress_current' => $this->agentEvent->progress_current,
            'progress_total' => $this->agentEvent->progress_total,
            'progress_percent' => $this->agentEvent->progress_percent,
            'created_at' => $this->agentEvent->created_at->toISOString(),
        ];
    }
}
```

**Step 2: Create broadcast channel authorization**

In `routes/channels.php`, add:

```php
use App\Models\Article;

Broadcast::channel('article.{articleId}', function ($user, $articleId) {
    $article = Article::find($articleId);
    if (!$article) {
        return false;
    }
    return $user->team_id === $article->site->team_id;
});
```

**Step 3: Commit**

```bash
git add app/Events/AgentActivityEvent.php routes/channels.php
git commit -m "feat: add AgentActivityEvent broadcast event with channel auth"
```

---

### Task 1.5: Create AgentEventService

**Files:**
- Create: `app/Services/Agent/AgentEventService.php`

**Step 1: Create service**

```php
<?php

namespace App\Services\Agent;

use App\Events\AgentActivityEvent;
use App\Models\AgentEvent;
use App\Models\Article;

class AgentEventService
{
    public function emit(
        Article $article,
        string $agentType,
        string $eventType,
        string $message,
        ?string $reasoning = null,
        ?array $metadata = null,
        ?int $progressCurrent = null,
        ?int $progressTotal = null
    ): AgentEvent {
        $event = AgentEvent::create([
            'article_id' => $article->id,
            'agent_type' => $agentType,
            'event_type' => $eventType,
            'message' => $message,
            'reasoning' => $reasoning,
            'metadata' => $metadata,
            'progress_current' => $progressCurrent,
            'progress_total' => $progressTotal,
        ]);

        broadcast(new AgentActivityEvent($event))->toOthers();

        return $event;
    }

    public function started(Article $article, string $agentType, string $message, ?string $reasoning = null): AgentEvent
    {
        return $this->emit($article, $agentType, AgentEvent::EVENT_STARTED, $message, $reasoning);
    }

    public function progress(
        Article $article,
        string $agentType,
        string $message,
        ?int $current = null,
        ?int $total = null,
        ?string $reasoning = null,
        ?array $metadata = null
    ): AgentEvent {
        return $this->emit($article, $agentType, AgentEvent::EVENT_PROGRESS, $message, $reasoning, $metadata, $current, $total);
    }

    public function completed(Article $article, string $agentType, string $message, ?string $reasoning = null, ?array $metadata = null): AgentEvent
    {
        return $this->emit($article, $agentType, AgentEvent::EVENT_COMPLETED, $message, $reasoning, $metadata);
    }

    public function error(Article $article, string $agentType, string $message, ?string $errorDetails = null): AgentEvent
    {
        return $this->emit($article, $agentType, AgentEvent::EVENT_ERROR, $message, null, ['error' => $errorDetails]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/Agent/AgentEventService.php
git commit -m "feat: add AgentEventService for emitting and broadcasting events"
```

---

## Phase 2: Node.js Agent Infrastructure

### Task 2.1: Initialize Node.js Agents Project

**Files:**
- Create: `agents/package.json`
- Create: `agents/.nvmrc`
- Create: `agents/.gitignore`

**Step 1: Create agents directory and initialize npm**

```bash
mkdir -p agents
cd agents
npm init -y
```

**Step 2: Write package.json**

```json
{
  "name": "seo-autopilot-agents",
  "version": "1.0.0",
  "type": "module",
  "scripts": {
    "research": "node research-agent/index.js",
    "competitor": "node competitor-agent/index.js",
    "fact-checker": "node fact-checker-agent/index.js",
    "internal-linking": "node internal-linking-agent/index.js"
  },
  "dependencies": {
    "crawlee": "^3.11.0",
    "puppeteer": "^23.0.0",
    "puppeteer-extra": "^3.3.6",
    "puppeteer-extra-plugin-stealth": "^2.11.2",
    "ioredis": "^5.4.0",
    "openai": "^4.70.0",
    "commander": "^12.1.0",
    "dotenv": "^16.4.5"
  },
  "engines": {
    "node": ">=20.0.0"
  }
}
```

**Step 3: Create .nvmrc**

```
20
```

**Step 4: Create .gitignore**

```
node_modules/
.env
*.log
```

**Step 5: Install dependencies**

```bash
cd agents
npm install
```

**Step 6: Commit**

```bash
git add agents/package.json agents/.nvmrc agents/.gitignore agents/package-lock.json
git commit -m "feat: initialize Node.js agents project with Crawlee and dependencies"
```

---

### Task 2.2: Create Shared Event Emitter

**Files:**
- Create: `agents/shared/event-emitter.js`
- Create: `agents/shared/config.js`

**Step 1: Create config.js**

```javascript
import 'dotenv/config';

export const config = {
    redis: {
        host: process.env.REDIS_HOST || 'localhost',
        port: parseInt(process.env.REDIS_PORT || '6379'),
        password: process.env.REDIS_PASSWORD || undefined,
    },
    openai: {
        apiKey: process.env.OPENAI_API_KEY,
    },
    database: {
        host: process.env.DB_HOST || 'localhost',
        port: parseInt(process.env.DB_PORT || '3306'),
        database: process.env.DB_DATABASE || 'seo_autopilot',
        user: process.env.DB_USERNAME || 'root',
        password: process.env.DB_PASSWORD || '',
    },
};
```

**Step 2: Create event-emitter.js**

```javascript
import Redis from 'ioredis';
import { config } from './config.js';

let redis = null;

function getRedis() {
    if (!redis) {
        redis = new Redis(config.redis);
    }
    return redis;
}

export async function emitEvent(articleId, agentType, eventType, message, options = {}) {
    const event = {
        article_id: articleId,
        agent_type: agentType,
        event_type: eventType,
        message: message,
        reasoning: options.reasoning || null,
        metadata: options.metadata || null,
        progress_current: options.progressCurrent || null,
        progress_total: options.progressTotal || null,
        timestamp: Date.now(),
    };

    const redis = getRedis();
    const channel = `agent-events:${articleId}`;

    // Publish for real-time listeners
    await redis.publish(channel, JSON.stringify(event));

    // Also store in a list for persistence (Laravel will pick this up)
    await redis.rpush(`agent-events-queue`, JSON.stringify(event));

    console.log(`[${agentType}] ${eventType}: ${message}`);

    return event;
}

export async function emitStarted(articleId, agentType, message, reasoning = null) {
    return emitEvent(articleId, agentType, 'started', message, { reasoning });
}

export async function emitProgress(articleId, agentType, message, options = {}) {
    return emitEvent(articleId, agentType, 'progress', message, options);
}

export async function emitCompleted(articleId, agentType, message, options = {}) {
    return emitEvent(articleId, agentType, 'completed', message, options);
}

export async function emitError(articleId, agentType, message, error = null) {
    return emitEvent(articleId, agentType, 'error', message, {
        metadata: { error: error?.message || error }
    });
}

export async function closeRedis() {
    if (redis) {
        await redis.quit();
        redis = null;
    }
}
```

**Step 3: Commit**

```bash
git add agents/shared/
git commit -m "feat: add shared event emitter for Node.js agents"
```

---

### Task 2.3: Create Shared Puppeteer Setup with Stealth

**Files:**
- Create: `agents/shared/browser.js`

**Step 1: Create browser.js**

```javascript
import { PuppeteerCrawler } from 'crawlee';
import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';

// Enable stealth mode
puppeteer.use(StealthPlugin());

export function createCrawler(options = {}) {
    return new PuppeteerCrawler({
        launchContext: {
            launcher: puppeteer,
            launchOptions: {
                headless: true,
                args: [
                    '--no-sandbox',
                    '--disable-setuid-sandbox',
                    '--disable-dev-shm-usage',
                    '--disable-accelerated-2d-canvas',
                    '--disable-gpu',
                ],
            },
        },
        maxConcurrency: options.maxConcurrency || 3,
        maxRequestRetries: options.maxRetries || 2,
        requestHandlerTimeoutSecs: options.timeout || 60,
        ...options,
    });
}

export async function launchBrowser() {
    return puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
        ],
    });
}

export function randomDelay(min = 1000, max = 3000) {
    return new Promise(resolve =>
        setTimeout(resolve, Math.floor(Math.random() * (max - min + 1) + min))
    );
}
```

**Step 2: Commit**

```bash
git add agents/shared/browser.js
git commit -m "feat: add shared Puppeteer setup with stealth plugin"
```

---

### Task 2.4: Create Shared LLM Client

**Files:**
- Create: `agents/shared/llm.js`

**Step 1: Create llm.js**

```javascript
import OpenAI from 'openai';
import { config } from './config.js';

let openai = null;

function getOpenAI() {
    if (!openai) {
        openai = new OpenAI({ apiKey: config.openai.apiKey });
    }
    return openai;
}

export async function generateJSON(prompt, systemPrompt = '', options = {}) {
    const client = getOpenAI();

    const response = await client.chat.completions.create({
        model: options.model || 'gpt-4o-mini',
        messages: [
            ...(systemPrompt ? [{ role: 'system', content: systemPrompt }] : []),
            { role: 'user', content: prompt },
        ],
        response_format: { type: 'json_object' },
        temperature: options.temperature || 0.7,
        max_tokens: options.maxTokens || 4096,
    });

    const content = response.choices[0].message.content;
    return JSON.parse(content);
}

export async function generateText(prompt, systemPrompt = '', options = {}) {
    const client = getOpenAI();

    const response = await client.chat.completions.create({
        model: options.model || 'gpt-4o-mini',
        messages: [
            ...(systemPrompt ? [{ role: 'system', content: systemPrompt }] : []),
            { role: 'user', content: prompt },
        ],
        temperature: options.temperature || 0.7,
        max_tokens: options.maxTokens || 4096,
    });

    return response.choices[0].message.content;
}
```

**Step 2: Commit**

```bash
git add agents/shared/llm.js
git commit -m "feat: add shared LLM client for Node.js agents"
```

---

### Task 2.5: Create Laravel Redis Event Listener

**Files:**
- Create: `app/Console/Commands/ProcessAgentEvents.php`

**Step 1: Create command**

```php
<?php

namespace App\Console\Commands;

use App\Events\AgentActivityEvent;
use App\Models\AgentEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ProcessAgentEvents extends Command
{
    protected $signature = 'agents:process-events';
    protected $description = 'Process agent events from Redis queue and broadcast them';

    public function handle(): int
    {
        $this->info('Starting agent events processor...');

        while (true) {
            $event = Redis::lpop('agent-events-queue');

            if ($event) {
                $this->processEvent(json_decode($event, true));
            } else {
                // No events, wait a bit
                usleep(100000); // 100ms
            }
        }

        return 0;
    }

    private function processEvent(array $data): void
    {
        try {
            $agentEvent = AgentEvent::create([
                'article_id' => $data['article_id'],
                'agent_type' => $data['agent_type'],
                'event_type' => $data['event_type'],
                'message' => $data['message'],
                'reasoning' => $data['reasoning'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'progress_current' => $data['progress_current'] ?? null,
                'progress_total' => $data['progress_total'] ?? null,
            ]);

            broadcast(new AgentActivityEvent($agentEvent))->toOthers();

            $this->line("[{$data['agent_type']}] {$data['message']}");
        } catch (\Exception $e) {
            $this->error("Failed to process event: {$e->getMessage()}");
        }
    }
}
```

**Step 2: Commit**

```bash
git add app/Console/Commands/ProcessAgentEvents.php
git commit -m "feat: add command to process agent events from Redis"
```

---

### Task 2.6: Create AgentRunner Service

**Files:**
- Create: `app/Services/Agent/AgentRunner.php`

**Step 1: Create service**

```php
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
```

**Step 2: Commit**

```bash
git add app/Services/Agent/AgentRunner.php
git commit -m "feat: add AgentRunner service to execute Node.js agents"
```

---

## Phase 3: Research Agent

### Task 3.1: Create Research Agent Structure

**Files:**
- Create: `agents/research-agent/index.js`
- Create: `agents/research-agent/google-search.js`
- Create: `agents/research-agent/content-scraper.js`

**Step 1: Create index.js**

```javascript
import { program } from 'commander';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { generateJSON } from '../shared/llm.js';
import { searchGoogle } from './google-search.js';
import { scrapeUrls } from './content-scraper.js';

const AGENT_TYPE = 'research';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--keyword <keyword>', 'Target keyword')
    .option('--siteId <id>', 'Site ID')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, keyword } = options;

    try {
        await emitStarted(articleId, AGENT_TYPE, `Démarrage de la recherche pour "${keyword}"`,
            `Le keyword "${keyword}" sera analysé pour comprendre l'intention de recherche et collecter des sources.`);

        // Step 1: Generate search queries
        await emitProgress(articleId, AGENT_TYPE, 'Génération des requêtes de recherche...');

        const queries = await generateSearchQueries(keyword);

        await emitProgress(articleId, AGENT_TYPE,
            `${queries.length} requêtes de recherche préparées`,
            { reasoning: `Requêtes variées pour couvrir différents angles du sujet.` }
        );

        // Step 2: Search Google for each query
        let allUrls = [];
        for (let i = 0; i < queries.length; i++) {
            await emitProgress(articleId, AGENT_TYPE,
                `Recherche Google: "${queries[i]}"`,
                { progressCurrent: i + 1, progressTotal: queries.length }
            );

            const urls = await searchGoogle(queries[i]);
            allUrls = [...allUrls, ...urls];
        }

        // Deduplicate URLs
        const uniqueUrls = [...new Set(allUrls)];
        await emitProgress(articleId, AGENT_TYPE,
            `${allUrls.length} URLs collectées (dédupliquées: ${uniqueUrls.length})`
        );

        // Step 3: Scrape content from URLs
        await emitProgress(articleId, AGENT_TYPE, 'Extraction du contenu des pages...');

        const scrapedContent = await scrapeUrls(uniqueUrls, (current, total) => {
            emitProgress(articleId, AGENT_TYPE,
                `Extraction du contenu (${current}/${total})...`,
                { progressCurrent: current, progressTotal: total }
            );
        });

        const validContent = scrapedContent.filter(c => c.content && c.content.length > 200);
        await emitProgress(articleId, AGENT_TYPE,
            `Extraction terminée, ${validContent.length} pages exploitables`
        );

        // Step 4: Analyze and synthesize
        await emitProgress(articleId, AGENT_TYPE, 'Analyse et synthèse des sources...');

        const analysis = await analyzeContent(keyword, validContent);

        await emitCompleted(articleId, AGENT_TYPE,
            `Recherche terminée: ${analysis.entities.length} entités identifiées, ${analysis.facts.length} faits collectés`,
            {
                reasoning: analysis.summary,
                metadata: {
                    sources_count: validContent.length,
                    entities_count: analysis.entities.length,
                    facts_count: analysis.facts.length,
                }
            }
        );

        // Output result as JSON (last line, for Laravel to parse)
        const result = {
            success: true,
            sources: validContent.map(c => ({ url: c.url, title: c.title, snippet: c.content.substring(0, 500) })),
            key_topics: analysis.topics,
            entities: analysis.entities,
            facts: analysis.facts,
            suggested_angles: analysis.angles,
            competitor_urls: uniqueUrls.slice(0, 10),
        };

        console.log(JSON.stringify(result));

    } catch (error) {
        await emitError(articleId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message }));
        process.exit(1);
    } finally {
        await closeRedis();
    }
}

async function generateSearchQueries(keyword) {
    const result = await generateJSON(`
        Génère 5-6 requêtes de recherche Google variées pour le keyword "${keyword}".
        Les requêtes doivent couvrir:
        - La requête principale
        - Des variations avec "best", "top", "guide"
        - Des questions (how to, what is)
        - Des comparaisons si pertinent

        Retourne un JSON: { "queries": ["query1", "query2", ...] }
    `);
    return result.queries;
}

async function analyzeContent(keyword, sources) {
    const sourcesText = sources.map(s =>
        `[${s.title}]\n${s.content.substring(0, 2000)}`
    ).join('\n\n---\n\n');

    const result = await generateJSON(`
        Analyse ces sources sur le sujet "${keyword}" et extrait:

        1. topics: Les sous-sujets principaux couverts (liste de strings)
        2. entities: Les entités importantes (outils, marques, personnes) mentionnées
        3. facts: Les faits/statistiques citables avec leur source
        4. angles: 2-3 angles d'article suggérés pour se différencier
        5. summary: Un résumé de 2-3 phrases de ce que les sources couvrent

        Sources:
        ${sourcesText}

        Retourne un JSON avec ces 5 clés.
    `, '', { model: 'gpt-4o', maxTokens: 4096 });

    return result;
}

main();
```

**Step 2: Commit**

```bash
git add agents/research-agent/index.js
git commit -m "feat: add research agent main entry point"
```

---

### Task 3.2: Create Google Search Scraper

**Files:**
- Create: `agents/research-agent/google-search.js`

**Step 1: Create google-search.js**

```javascript
import { launchBrowser, randomDelay } from '../shared/browser.js';

export async function searchGoogle(query, maxResults = 10) {
    const browser = await launchBrowser();
    const urls = [];

    try {
        const page = await browser.newPage();

        // Set realistic viewport and user agent
        await page.setViewport({ width: 1920, height: 1080 });

        // Navigate to Google
        const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(query)}&num=${maxResults}`;
        await page.goto(searchUrl, { waitUntil: 'networkidle2', timeout: 30000 });

        // Wait for results
        await page.waitForSelector('#search', { timeout: 10000 });

        // Extract organic result URLs
        const results = await page.evaluate(() => {
            const links = [];
            const resultElements = document.querySelectorAll('#search .g a[href^="http"]');

            resultElements.forEach(el => {
                const href = el.getAttribute('href');
                // Filter out Google's own URLs and ads
                if (href &&
                    !href.includes('google.com') &&
                    !href.includes('youtube.com') &&
                    !href.includes('webcache') &&
                    !href.includes('translate.google')) {
                    links.push(href);
                }
            });

            return [...new Set(links)]; // Dedupe
        });

        urls.push(...results.slice(0, maxResults));

        await randomDelay(1000, 2000);

    } catch (error) {
        console.error(`Google search failed for "${query}":`, error.message);
    } finally {
        await browser.close();
    }

    return urls;
}
```

**Step 2: Commit**

```bash
git add agents/research-agent/google-search.js
git commit -m "feat: add Google search scraper for research agent"
```

---

### Task 3.3: Create Content Scraper

**Files:**
- Create: `agents/research-agent/content-scraper.js`

**Step 1: Create content-scraper.js**

```javascript
import { createCrawler, randomDelay } from '../shared/browser.js';
import { RequestQueue } from 'crawlee';

export async function scrapeUrls(urls, onProgress = null) {
    const results = [];
    let processed = 0;
    const total = urls.length;

    const requestQueue = await RequestQueue.open();

    for (const url of urls) {
        await requestQueue.addRequest({ url });
    }

    const crawler = createCrawler({
        maxConcurrency: 2,
        requestHandler: async ({ page, request }) => {
            try {
                // Wait for main content
                await page.waitForSelector('body', { timeout: 15000 });
                await randomDelay(500, 1500);

                // Extract content
                const data = await page.evaluate(() => {
                    // Remove unwanted elements
                    const selectorsToRemove = [
                        'nav', 'header', 'footer', 'aside',
                        '.sidebar', '.menu', '.navigation',
                        '.comments', '.advertisement', '.ad',
                        'script', 'style', 'noscript'
                    ];
                    selectorsToRemove.forEach(sel => {
                        document.querySelectorAll(sel).forEach(el => el.remove());
                    });

                    // Get title
                    const title = document.querySelector('h1')?.textContent?.trim() ||
                                  document.querySelector('title')?.textContent?.trim() ||
                                  '';

                    // Get headings
                    const headings = [];
                    document.querySelectorAll('h2, h3').forEach(h => {
                        headings.push({
                            level: h.tagName.toLowerCase(),
                            text: h.textContent?.trim() || ''
                        });
                    });

                    // Get main content
                    const contentSelectors = [
                        'article', 'main', '.content', '.post-content',
                        '.entry-content', '.article-content', '#content'
                    ];

                    let content = '';
                    for (const sel of contentSelectors) {
                        const el = document.querySelector(sel);
                        if (el) {
                            content = el.textContent?.trim() || '';
                            break;
                        }
                    }

                    // Fallback to body
                    if (!content || content.length < 200) {
                        content = document.body.textContent?.trim() || '';
                    }

                    // Clean up whitespace
                    content = content.replace(/\s+/g, ' ').trim();

                    return { title, headings, content };
                });

                results.push({
                    url: request.url,
                    title: data.title,
                    headings: data.headings,
                    content: data.content.substring(0, 10000), // Limit content size
                });

            } catch (error) {
                console.error(`Failed to scrape ${request.url}:`, error.message);
            }

            processed++;
            if (onProgress) {
                onProgress(processed, total);
            }
        },
        failedRequestHandler: async ({ request }) => {
            console.error(`Request failed: ${request.url}`);
            processed++;
            if (onProgress) {
                onProgress(processed, total);
            }
        },
    });

    await crawler.run();

    return results;
}
```

**Step 2: Commit**

```bash
git add agents/research-agent/content-scraper.js
git commit -m "feat: add content scraper for research agent"
```

---

## Phase 4: Competitor Agent

### Task 4.1: Create Competitor Agent

**Files:**
- Create: `agents/competitor-agent/index.js`
- Create: `agents/competitor-agent/serp-analyzer.js`

**Step 1: Create index.js**

```javascript
import { program } from 'commander';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { generateJSON } from '../shared/llm.js';
import { analyzeCompetitors } from './serp-analyzer.js';

const AGENT_TYPE = 'competitor';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--keyword <keyword>', 'Target keyword')
    .requiredOption('--urls <urls>', 'JSON array of competitor URLs')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, keyword, urls: urlsJson } = options;
    const urls = JSON.parse(urlsJson);

    try {
        await emitStarted(articleId, AGENT_TYPE,
            `Analyse de ${urls.length} concurrents pour "${keyword}"`,
            `Je vais analyser la structure, le word count et les topics couverts par chaque concurrent.`
        );

        // Analyze each competitor
        const analyses = await analyzeCompetitors(urls, (current, total, url, data) => {
            const message = data
                ? `${new URL(url).hostname}: ${data.wordCount} mots, ${data.headings.h2} H2`
                : `Analyse de ${new URL(url).hostname} (${current}/${total})...`;

            emitProgress(articleId, AGENT_TYPE, message, {
                progressCurrent: current,
                progressTotal: total,
                metadata: data ? { url, ...data } : null
            });
        });

        const validAnalyses = analyses.filter(a => a.wordCount > 0);

        // Calculate statistics
        const wordCounts = validAnalyses.map(a => a.wordCount);
        const avgWordCount = Math.round(wordCounts.reduce((a, b) => a + b, 0) / wordCounts.length);
        const top3Avg = Math.round(wordCounts.slice(0, 3).reduce((a, b) => a + b, 0) / 3);

        await emitProgress(articleId, AGENT_TYPE,
            `Statistiques calculées: moyenne ${avgWordCount} mots`
        );

        // Extract common topics
        await emitProgress(articleId, AGENT_TYPE, 'Extraction des topics communs...');

        const topicAnalysis = await analyzeTopics(keyword, validAnalyses);

        // Generate recommendations
        const recommendedWordCount = Math.round(top3Avg * 1.15); // 15% more than top 3

        await emitCompleted(articleId, AGENT_TYPE,
            `Analyse terminée: moyenne ${avgWordCount} mots, recommandation ${recommendedWordCount} mots`,
            {
                reasoning: `Les 3 premiers résultats font en moyenne ${top3Avg} mots. Pour les dépasser, je recommande ${recommendedWordCount} mots (+15%). ${topicAnalysis.gaps.length} content gaps identifiés.`,
                metadata: {
                    competitors_analyzed: validAnalyses.length,
                    avg_word_count: avgWordCount,
                    recommended_word_count: recommendedWordCount,
                    content_gaps: topicAnalysis.gaps.length,
                }
            }
        );

        // Output result
        const result = {
            success: true,
            competitors: validAnalyses,
            avg_word_count: avgWordCount,
            top3_avg_word_count: top3Avg,
            recommended_word_count: recommendedWordCount,
            common_topics: topicAnalysis.common,
            content_gaps: topicAnalysis.gaps,
            recommended_headings: topicAnalysis.recommendedHeadings,
        };

        console.log(JSON.stringify(result));

    } catch (error) {
        await emitError(articleId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message }));
        process.exit(1);
    } finally {
        await closeRedis();
    }
}

async function analyzeTopics(keyword, competitors) {
    const headingsText = competitors.map(c =>
        `[${c.url}]\n${c.allHeadings.map(h => `${h.level}: ${h.text}`).join('\n')}`
    ).join('\n\n');

    const result = await generateJSON(`
        Analyse ces titres H2/H3 des articles concurrents sur "${keyword}":

        ${headingsText}

        Retourne un JSON avec:
        1. common: Topics couverts par 50%+ des concurrents (liste de strings)
        2. gaps: Topics couverts par moins de 30% (opportunités de différenciation)
        3. recommendedHeadings: Structure H2/H3 recommandée pour un article complet
           Format: [{ "level": "h2", "text": "..." }, ...]
    `, '', { model: 'gpt-4o' });

    return result;
}

main();
```

**Step 2: Create serp-analyzer.js**

```javascript
import { launchBrowser, randomDelay } from '../shared/browser.js';

export async function analyzeCompetitors(urls, onProgress = null) {
    const results = [];

    for (let i = 0; i < urls.length; i++) {
        const url = urls[i];

        if (onProgress) {
            onProgress(i + 1, urls.length, url, null);
        }

        const analysis = await analyzePage(url);
        results.push(analysis);

        if (onProgress && analysis.wordCount > 0) {
            onProgress(i + 1, urls.length, url, analysis);
        }

        await randomDelay(1000, 2000);
    }

    // Sort by word count (assuming higher ranking = first in list)
    return results;
}

async function analyzePage(url) {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });

        const data = await page.evaluate(() => {
            // Remove non-content elements
            ['nav', 'header', 'footer', 'aside', '.sidebar', '.menu',
             '.comments', '.ad', 'script', 'style'].forEach(sel => {
                document.querySelectorAll(sel).forEach(el => el.remove());
            });

            // Get all headings
            const headings = { h1: 0, h2: 0, h3: 0 };
            const allHeadings = [];

            document.querySelectorAll('h1, h2, h3').forEach(h => {
                const level = h.tagName.toLowerCase();
                headings[level]++;
                allHeadings.push({ level, text: h.textContent?.trim() || '' });
            });

            // Get word count from main content
            const contentSelectors = ['article', 'main', '.content', '.post-content', '.entry-content'];
            let content = '';

            for (const sel of contentSelectors) {
                const el = document.querySelector(sel);
                if (el) {
                    content = el.textContent || '';
                    break;
                }
            }

            if (!content) {
                content = document.body.textContent || '';
            }

            const wordCount = content.trim().split(/\s+/).filter(w => w.length > 0).length;

            // Count media
            const images = document.querySelectorAll('article img, main img, .content img').length;
            const videos = document.querySelectorAll('iframe[src*="youtube"], iframe[src*="vimeo"], video').length;
            const tables = document.querySelectorAll('table').length;
            const lists = document.querySelectorAll('ul, ol').length;

            return {
                headings,
                allHeadings,
                wordCount,
                media: { images, videos, tables, lists }
            };
        });

        return {
            url,
            ...data,
        };

    } catch (error) {
        console.error(`Failed to analyze ${url}:`, error.message);
        return { url, wordCount: 0, headings: {}, allHeadings: [], media: {} };
    } finally {
        await browser.close();
    }
}
```

**Step 3: Commit**

```bash
git add agents/competitor-agent/
git commit -m "feat: add competitor analyzer agent"
```

---

## Phase 5: Fact Checker Agent

### Task 5.1: Create Fact Checker Agent

**Files:**
- Create: `agents/fact-checker-agent/index.js`
- Create: `agents/fact-checker-agent/claim-extractor.js`
- Create: `agents/fact-checker-agent/verifier.js`

**Step 1: Create index.js**

```javascript
import { program } from 'commander';
import { readFileSync } from 'fs';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { extractClaims } from './claim-extractor.js';
import { verifyClaims } from './verifier.js';

const AGENT_TYPE = 'fact_checker';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--contentFile <path>', 'Path to article content file')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, contentFile } = options;
    const content = readFileSync(contentFile, 'utf-8');

    try {
        await emitStarted(articleId, AGENT_TYPE,
            'Démarrage de la vérification des faits',
            'Je vais identifier les affirmations factuelles et les vérifier avec des sources fiables.'
        );

        // Step 1: Extract claims
        await emitProgress(articleId, AGENT_TYPE, 'Extraction des affirmations vérifiables...');

        const claims = await extractClaims(content);

        await emitProgress(articleId, AGENT_TYPE,
            `${claims.length} affirmations à vérifier identifiées`,
            { metadata: { claims_count: claims.length } }
        );

        // Step 2: Verify each claim
        const verifiedClaims = [];

        for (let i = 0; i < claims.length; i++) {
            const claim = claims[i];

            await emitProgress(articleId, AGENT_TYPE,
                `Vérification (${i + 1}/${claims.length}): "${claim.text.substring(0, 50)}..."`,
                { progressCurrent: i + 1, progressTotal: claims.length }
            );

            const verification = await verifyClaims([claim]);
            const verified = verification[0];
            verifiedClaims.push(verified);

            // Emit result for this claim
            const statusEmoji = {
                verified: '✅',
                partially_true: '⚠️',
                incorrect: '❌',
                unverifiable: '❓'
            }[verified.status] || '❓';

            await emitProgress(articleId, AGENT_TYPE,
                `${statusEmoji} "${claim.text.substring(0, 40)}..." → ${verified.status}`,
                {
                    metadata: {
                        claim: claim.text.substring(0, 100),
                        status: verified.status,
                        source: verified.source_url
                    }
                }
            );
        }

        // Calculate stats
        const stats = {
            verified: verifiedClaims.filter(c => c.status === 'verified').length,
            partially_true: verifiedClaims.filter(c => c.status === 'partially_true').length,
            incorrect: verifiedClaims.filter(c => c.status === 'incorrect').length,
            unverifiable: verifiedClaims.filter(c => c.status === 'unverifiable').length,
        };

        await emitCompleted(articleId, AGENT_TYPE,
            `Vérification terminée: ${stats.verified} ✅, ${stats.partially_true} ⚠️, ${stats.incorrect} ❌, ${stats.unverifiable} ❓`,
            {
                reasoning: stats.incorrect > 0
                    ? `${stats.incorrect} erreur(s) factuelle(s) trouvée(s). Des corrections sont proposées.`
                    : 'Toutes les affirmations vérifiables sont correctes.',
                metadata: stats
            }
        );

        // Output result
        const result = {
            success: true,
            total_claims: claims.length,
            ...stats,
            claims: verifiedClaims,
            citations_to_add: verifiedClaims
                .filter(c => c.status === 'verified' && c.source_url)
                .map(c => ({
                    text: c.original_text,
                    source_url: c.source_url,
                    source_title: c.source_title
                })),
            corrections: verifiedClaims
                .filter(c => c.status === 'incorrect' && c.corrected_text)
                .map(c => ({
                    original: c.original_text,
                    corrected: c.corrected_text,
                    source_url: c.source_url
                }))
        };

        console.log(JSON.stringify(result));

    } catch (error) {
        await emitError(articleId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message }));
        process.exit(1);
    } finally {
        await closeRedis();
    }
}

main();
```

**Step 2: Create claim-extractor.js**

```javascript
import { generateJSON } from '../shared/llm.js';

export async function extractClaims(content) {
    const result = await generateJSON(`
        Analyse ce texte et extrait toutes les affirmations factuelles vérifiables.

        Types d'affirmations à extraire:
        - Statistiques et pourcentages ("73% des utilisateurs...")
        - Dates et événements ("lancé en 2020", "fondé par X")
        - Comparaisons chiffrées ("2x plus rapide", "50% moins cher")
        - Faits techniques vérifiables ("utilise l'algorithme X")
        - Citations de sources ("selon une étude de...")

        NE PAS extraire:
        - Opinions subjectives
        - Conseils généraux
        - Affirmations vagues non vérifiables

        Texte:
        ${content.substring(0, 15000)}

        Retourne un JSON: { "claims": [{ "text": "...", "type": "statistic|date|comparison|technical|citation", "context": "phrase complète contenant l'affirmation" }] }
    `, '', { model: 'gpt-4o' });

    return result.claims || [];
}
```

**Step 3: Create verifier.js**

```javascript
import { generateJSON } from '../shared/llm.js';
import { searchGoogle } from '../research-agent/google-search.js';
import { scrapeUrls } from '../research-agent/content-scraper.js';

export async function verifyClaims(claims) {
    const results = [];

    for (const claim of claims) {
        const verification = await verifySingleClaim(claim);
        results.push({
            original_text: claim.text,
            ...verification
        });
    }

    return results;
}

async function verifySingleClaim(claim) {
    try {
        // Search for verification
        const searchQuery = `${claim.text} source fact check`;
        const urls = await searchGoogle(searchQuery, 5);

        if (urls.length === 0) {
            return {
                status: 'unverifiable',
                reason: 'Aucune source trouvée pour vérifier cette affirmation.'
            };
        }

        // Scrape top results
        const sources = await scrapeUrls(urls.slice(0, 3));
        const validSources = sources.filter(s => s.content && s.content.length > 200);

        if (validSources.length === 0) {
            return {
                status: 'unverifiable',
                reason: 'Impossible d\'extraire le contenu des sources.'
            };
        }

        // Use LLM to verify
        const sourcesText = validSources.map(s =>
            `[${s.title}] (${s.url})\n${s.content.substring(0, 2000)}`
        ).join('\n\n---\n\n');

        const result = await generateJSON(`
            Vérifie cette affirmation avec les sources fournies:

            Affirmation: "${claim.text}"
            Type: ${claim.type}

            Sources:
            ${sourcesText}

            Analyse les sources et détermine si l'affirmation est:
            - "verified": Confirmée par au moins une source fiable
            - "partially_true": Partiellement vraie (nuance nécessaire)
            - "incorrect": Fausse ou erronée (fournis la correction)
            - "unverifiable": Impossible à vérifier avec ces sources

            Retourne un JSON:
            {
                "status": "verified|partially_true|incorrect|unverifiable",
                "reason": "Explication courte",
                "source_url": "URL de la meilleure source (si trouvée)",
                "source_title": "Titre de la source",
                "corrected_text": "Texte corrigé (si incorrect, sinon null)"
            }
        `, '', { model: 'gpt-4o' });

        return result;

    } catch (error) {
        return {
            status: 'unverifiable',
            reason: `Erreur lors de la vérification: ${error.message}`
        };
    }
}
```

**Step 4: Commit**

```bash
git add agents/fact-checker-agent/
git commit -m "feat: add fact checker agent with claim extraction and verification"
```

---

## Phase 6: Internal Linking Agent

### Task 6.1: Create Internal Linking Agent

**Files:**
- Create: `agents/internal-linking-agent/index.js`
- Create: `agents/internal-linking-agent/site-scanner.js`
- Create: `agents/internal-linking-agent/link-suggester.js`

**Step 1: Create index.js**

```javascript
import { program } from 'commander';
import { readFileSync } from 'fs';
import { emitStarted, emitProgress, emitCompleted, emitError, closeRedis } from '../shared/event-emitter.js';
import { loadSiteIndex } from './site-scanner.js';
import { findLinkOpportunities, insertLinks } from './link-suggester.js';

const AGENT_TYPE = 'internal_linking';

program
    .requiredOption('--articleId <id>', 'Article ID')
    .requiredOption('--siteId <id>', 'Site ID')
    .requiredOption('--contentFile <path>', 'Path to article content file')
    .parse();

const options = program.opts();

async function main() {
    const { articleId, siteId, contentFile } = options;
    const content = readFileSync(contentFile, 'utf-8');

    try {
        await emitStarted(articleId, AGENT_TYPE,
            'Démarrage du linking interne',
            'Je vais analyser vos pages existantes et insérer des liens pertinents.'
        );

        // Step 1: Load site index
        await emitProgress(articleId, AGENT_TYPE, 'Chargement de l\'index du site...');

        const sitePages = await loadSiteIndex(siteId);

        await emitProgress(articleId, AGENT_TYPE,
            `Index chargé: ${sitePages.length} pages existantes`
        );

        if (sitePages.length === 0) {
            await emitCompleted(articleId, AGENT_TYPE,
                'Aucune page existante pour le linking',
                { reasoning: 'Le site n\'a pas encore de pages indexées.' }
            );
            console.log(JSON.stringify({
                success: true,
                links_added: [],
                content: content
            }));
            return;
        }

        // Step 2: Find link opportunities
        await emitProgress(articleId, AGENT_TYPE, 'Recherche d\'opportunités de liens...');

        const opportunities = await findLinkOpportunities(content, sitePages);

        await emitProgress(articleId, AGENT_TYPE,
            `${opportunities.length} opportunités de liens trouvées`,
            {
                reasoning: `J'ai identifié ${opportunities.length} termes pouvant lier vers vos pages existantes.`,
                metadata: { opportunities_count: opportunities.length }
            }
        );

        // Step 3: Select and insert links (respecting rules)
        await emitProgress(articleId, AGENT_TYPE, 'Sélection et insertion des liens...');

        const { linkedContent, linksAdded, linksSkipped } = await insertLinks(
            content,
            opportunities,
            {
                maxLinksPerSection: 2,
                minWordsBetweenLinks: 300,
                skipIntroWords: 150,
                preferOrphanPages: true,
            }
        );

        await emitCompleted(articleId, AGENT_TYPE,
            `${linksAdded.length} liens insérés (${linksSkipped.length} ignorés)`,
            {
                reasoning: linksSkipped.length > 0
                    ? `${linksSkipped.length} liens ignorés pour éviter la sur-optimisation (densité, intro, doublon).`
                    : 'Tous les liens pertinents ont été insérés.',
                metadata: {
                    links_added: linksAdded.length,
                    links_skipped: linksSkipped.length,
                }
            }
        );

        // Output result
        const result = {
            success: true,
            content: linkedContent,
            links_added: linksAdded,
            links_skipped: linksSkipped,
            site_health: {
                total_pages: sitePages.length,
                orphan_pages: sitePages.filter(p => (p.inbound_links || 0) === 0).length,
            }
        };

        console.log(JSON.stringify(result));

    } catch (error) {
        await emitError(articleId, AGENT_TYPE, `Erreur: ${error.message}`, error);
        console.log(JSON.stringify({ success: false, error: error.message, content }));
        process.exit(1);
    } finally {
        await closeRedis();
    }
}

main();
```

**Step 2: Create site-scanner.js**

```javascript
import mysql from 'mysql2/promise';
import { config } from '../shared/config.js';

export async function loadSiteIndex(siteId) {
    const connection = await mysql.createConnection({
        host: config.database.host,
        port: config.database.port,
        user: config.database.user,
        password: config.database.password,
        database: config.database.database,
    });

    try {
        const [rows] = await connection.execute(`
            SELECT
                id,
                url,
                title,
                meta_description,
                h1,
                keywords,
                inbound_links_count
            FROM site_pages
            WHERE site_id = ?
            AND url IS NOT NULL
            ORDER BY inbound_links_count ASC
        `, [siteId]);

        return rows.map(row => ({
            id: row.id,
            url: row.url,
            title: row.title || '',
            description: row.meta_description || '',
            h1: row.h1 || '',
            keywords: row.keywords ? JSON.parse(row.keywords) : [],
            inbound_links: row.inbound_links_count || 0,
        }));

    } finally {
        await connection.end();
    }
}
```

**Step 3: Create link-suggester.js**

```javascript
import { generateJSON } from '../shared/llm.js';

export async function findLinkOpportunities(content, sitePages) {
    // Create a summary of available pages for the LLM
    const pagesContext = sitePages.map(p =>
        `- "${p.title}" (${p.url}) - ${p.description?.substring(0, 100) || 'No description'}`
    ).join('\n');

    const result = await generateJSON(`
        Analyse ce contenu et trouve les opportunités de liens internes vers les pages existantes.

        Contenu de l'article:
        ${content.substring(0, 8000)}

        Pages disponibles pour le linking:
        ${pagesContext}

        Trouve les termes/phrases dans l'article qui pourraient naturellement lier vers ces pages.

        Règles:
        - L'anchor text doit être naturel (pas de bourrage de keywords)
        - Le lien doit apporter de la valeur au lecteur
        - Privilégie les pages avec peu de liens entrants (orphelines)

        Retourne un JSON: {
            "opportunities": [
                {
                    "anchor_text": "texte exact à lier dans l'article",
                    "target_url": "URL de la page cible",
                    "target_title": "Titre de la page cible",
                    "relevance_score": 1-10,
                    "reason": "Pourquoi ce lien est pertinent"
                }
            ]
        }
    `, '', { model: 'gpt-4o' });

    return (result.opportunities || [])
        .filter(o => o.relevance_score >= 6)
        .sort((a, b) => b.relevance_score - a.relevance_score);
}

export async function insertLinks(content, opportunities, options = {}) {
    const {
        maxLinksPerSection = 2,
        minWordsBetweenLinks = 300,
        skipIntroWords = 150,
    } = options;

    let linkedContent = content;
    const linksAdded = [];
    const linksSkipped = [];

    // Track positions of inserted links
    const linkPositions = [];

    for (const opp of opportunities) {
        // Find the anchor text in content
        const anchorIndex = linkedContent.indexOf(opp.anchor_text);

        if (anchorIndex === -1) {
            linksSkipped.push({ ...opp, reason: 'Anchor text not found' });
            continue;
        }

        // Check: not in intro
        const wordsBeforeAnchor = linkedContent.substring(0, anchorIndex).split(/\s+/).length;
        if (wordsBeforeAnchor < skipIntroWords) {
            linksSkipped.push({ ...opp, reason: 'In introduction' });
            continue;
        }

        // Check: minimum distance from other links
        const tooCloseToOtherLink = linkPositions.some(pos => {
            const distance = Math.abs(anchorIndex - pos);
            const wordsBetween = linkedContent.substring(
                Math.min(anchorIndex, pos),
                Math.max(anchorIndex, pos)
            ).split(/\s+/).length;
            return wordsBetween < minWordsBetweenLinks;
        });

        if (tooCloseToOtherLink) {
            linksSkipped.push({ ...opp, reason: 'Too close to another link' });
            continue;
        }

        // Check: not already linked
        if (linkedContent.includes(`href="${opp.target_url}"`)) {
            linksSkipped.push({ ...opp, reason: 'Page already linked' });
            continue;
        }

        // Insert the link
        const linkHtml = `<a href="${opp.target_url}">${opp.anchor_text}</a>`;
        linkedContent = linkedContent.replace(opp.anchor_text, linkHtml);

        linkPositions.push(anchorIndex);
        linksAdded.push({
            anchor_text: opp.anchor_text,
            target_url: opp.target_url,
            target_title: opp.target_title,
            position: anchorIndex,
        });
    }

    return { linkedContent, linksAdded, linksSkipped };
}
```

**Step 4: Add mysql2 dependency**

```bash
cd agents
npm install mysql2
```

**Step 5: Commit**

```bash
git add agents/internal-linking-agent/ agents/package.json agents/package-lock.json
git commit -m "feat: add internal linking agent"
```

---

## Phase 7: Frontend UI Components

### Task 7.1: Create Activity Hook

**Files:**
- Create: `resources/js/hooks/useAgentActivity.ts`

**Step 1: Create hook**

```typescript
import { useEffect, useState, useCallback } from 'react';

interface AgentEvent {
    id: number;
    agent_type: string;
    event_type: 'started' | 'progress' | 'completed' | 'error';
    message: string;
    reasoning: string | null;
    metadata: Record<string, unknown> | null;
    progress_current: number | null;
    progress_total: number | null;
    progress_percent: number | null;
    created_at: string;
}

interface UseAgentActivityOptions {
    articleId: number;
    enabled?: boolean;
}

export function useAgentActivity({ articleId, enabled = true }: UseAgentActivityOptions) {
    const [events, setEvents] = useState<AgentEvent[]>([]);
    const [isConnected, setIsConnected] = useState(false);
    const [activeAgents, setActiveAgents] = useState<string[]>([]);

    useEffect(() => {
        if (!enabled || !articleId || !window.Echo) {
            return;
        }

        const channel = window.Echo.private(`article.${articleId}`);

        channel
            .listen('.agent.activity', (event: AgentEvent) => {
                setEvents(prev => [...prev, event]);

                // Track active agents
                if (event.event_type === 'started') {
                    setActiveAgents(prev => [...new Set([...prev, event.agent_type])]);
                } else if (event.event_type === 'completed' || event.event_type === 'error') {
                    setActiveAgents(prev => prev.filter(a => a !== event.agent_type));
                }
            })
            .subscribed(() => {
                setIsConnected(true);
            })
            .error(() => {
                setIsConnected(false);
            });

        return () => {
            channel.stopListening('.agent.activity');
            window.Echo.leave(`article.${articleId}`);
        };
    }, [articleId, enabled]);

    const clearEvents = useCallback(() => {
        setEvents([]);
    }, []);

    const getEventsByAgent = useCallback((agentType: string) => {
        return events.filter(e => e.agent_type === agentType);
    }, [events]);

    const getLatestEventByAgent = useCallback((agentType: string) => {
        const agentEvents = events.filter(e => e.agent_type === agentType);
        return agentEvents[agentEvents.length - 1] || null;
    }, [events]);

    return {
        events,
        isConnected,
        activeAgents,
        clearEvents,
        getEventsByAgent,
        getLatestEventByAgent,
        hasNewEvents: events.length > 0,
    };
}
```

**Step 2: Add Echo type declaration**

Create `resources/js/types/echo.d.ts`:

```typescript
import Echo from 'laravel-echo';

declare global {
    interface Window {
        Echo: Echo;
        Pusher: typeof import('pusher-js').default;
    }
}
```

**Step 3: Commit**

```bash
git add resources/js/hooks/useAgentActivity.ts resources/js/types/echo.d.ts
git commit -m "feat: add useAgentActivity hook for real-time events"
```

---

### Task 7.2: Create Activity Components

**Files:**
- Create: `resources/js/Components/AgentActivity/ActivityDrawer.tsx`
- Create: `resources/js/Components/AgentActivity/ActivityFeed.tsx`
- Create: `resources/js/Components/AgentActivity/ActivityItem.tsx`
- Create: `resources/js/Components/AgentActivity/AgentBadge.tsx`

**Step 1: Create AgentBadge.tsx**

```typescript
import { Search, BarChart3, CheckCircle, Link2, PenTool, Sparkles, AlertCircle } from 'lucide-react';
import { clsx } from 'clsx';

const AGENT_CONFIG: Record<string, { icon: typeof Search; color: string; label: string }> = {
    research: { icon: Search, color: 'text-blue-500 bg-blue-50', label: 'Research' },
    competitor: { icon: BarChart3, color: 'text-purple-500 bg-purple-50', label: 'Competitor' },
    fact_checker: { icon: CheckCircle, color: 'text-orange-500 bg-orange-50', label: 'Fact Check' },
    internal_linking: { icon: Link2, color: 'text-green-500 bg-green-50', label: 'Linking' },
    writing: { icon: PenTool, color: 'text-indigo-500 bg-indigo-50', label: 'Writing' },
    outline: { icon: Sparkles, color: 'text-cyan-500 bg-cyan-50', label: 'Outline' },
    polish: { icon: Sparkles, color: 'text-pink-500 bg-pink-50', label: 'Polish' },
};

interface AgentBadgeProps {
    agentType: string;
    size?: 'sm' | 'md';
    showLabel?: boolean;
}

export function AgentBadge({ agentType, size = 'md', showLabel = true }: AgentBadgeProps) {
    const config = AGENT_CONFIG[agentType] || {
        icon: AlertCircle,
        color: 'text-gray-500 bg-gray-50',
        label: agentType
    };

    const Icon = config.icon;
    const iconSize = size === 'sm' ? 14 : 18;

    return (
        <span className={clsx(
            'inline-flex items-center gap-1.5 rounded-full font-medium',
            config.color,
            size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'
        )}>
            <Icon size={iconSize} />
            {showLabel && config.label}
        </span>
    );
}
```

**Step 2: Create ActivityItem.tsx**

```typescript
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { clsx } from 'clsx';
import { AgentBadge } from './AgentBadge';

interface ActivityItemProps {
    event: {
        id: number;
        agent_type: string;
        event_type: string;
        message: string;
        reasoning: string | null;
        progress_current: number | null;
        progress_total: number | null;
        progress_percent: number | null;
        created_at: string;
    };
}

export function ActivityItem({ event }: ActivityItemProps) {
    const [expanded, setExpanded] = useState(false);

    const statusIcon = {
        started: '🚀',
        progress: '⏳',
        completed: '✅',
        error: '❌',
    }[event.event_type] || '•';

    const timeAgo = formatDistanceToNow(new Date(event.created_at), {
        addSuffix: true,
        locale: fr
    });

    return (
        <div className={clsx(
            'py-2 px-3 border-l-2',
            event.event_type === 'error' && 'border-red-500 bg-red-50',
            event.event_type === 'completed' && 'border-green-500',
            event.event_type === 'started' && 'border-blue-500',
            event.event_type === 'progress' && 'border-gray-300',
        )}>
            <div className="flex items-start gap-2">
                <span className="text-sm">{statusIcon}</span>
                <div className="flex-1 min-w-0">
                    <p className="text-sm text-gray-900">{event.message}</p>

                    {event.progress_percent !== null && (
                        <div className="mt-1">
                            <div className="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-blue-500 transition-all duration-300"
                                    style={{ width: `${event.progress_percent}%` }}
                                />
                            </div>
                            <span className="text-xs text-gray-500">
                                {event.progress_current}/{event.progress_total}
                            </span>
                        </div>
                    )}

                    {event.reasoning && (
                        <button
                            onClick={() => setExpanded(!expanded)}
                            className="flex items-center gap-1 mt-1 text-xs text-gray-500 hover:text-gray-700"
                        >
                            {expanded ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
                            Détails
                        </button>
                    )}

                    {expanded && event.reasoning && (
                        <p className="mt-1 text-xs text-gray-600 italic bg-gray-50 p-2 rounded">
                            {event.reasoning}
                        </p>
                    )}
                </div>
                <span className="text-xs text-gray-400 whitespace-nowrap">{timeAgo}</span>
            </div>
        </div>
    );
}
```

**Step 3: Create ActivityFeed.tsx**

```typescript
import { useRef, useEffect } from 'react';
import { ActivityItem } from './ActivityItem';
import { AgentBadge } from './AgentBadge';

interface AgentEvent {
    id: number;
    agent_type: string;
    event_type: string;
    message: string;
    reasoning: string | null;
    progress_current: number | null;
    progress_total: number | null;
    progress_percent: number | null;
    created_at: string;
}

interface ActivityFeedProps {
    events: AgentEvent[];
    groupByAgent?: boolean;
}

export function ActivityFeed({ events, groupByAgent = true }: ActivityFeedProps) {
    const feedRef = useRef<HTMLDivElement>(null);

    // Auto-scroll to bottom on new events
    useEffect(() => {
        if (feedRef.current) {
            feedRef.current.scrollTop = feedRef.current.scrollHeight;
        }
    }, [events]);

    if (events.length === 0) {
        return (
            <div className="text-center py-8 text-gray-500">
                <p>Aucune activité pour le moment</p>
            </div>
        );
    }

    if (!groupByAgent) {
        return (
            <div ref={feedRef} className="space-y-1 max-h-96 overflow-y-auto">
                {events.map(event => (
                    <ActivityItem key={event.id} event={event} />
                ))}
            </div>
        );
    }

    // Group events by agent
    const groupedEvents = events.reduce((acc, event) => {
        if (!acc[event.agent_type]) {
            acc[event.agent_type] = [];
        }
        acc[event.agent_type].push(event);
        return acc;
    }, {} as Record<string, AgentEvent[]>);

    const agentOrder = ['research', 'competitor', 'outline', 'writing', 'fact_checker', 'polish', 'internal_linking'];
    const sortedAgents = Object.keys(groupedEvents).sort(
        (a, b) => agentOrder.indexOf(a) - agentOrder.indexOf(b)
    );

    return (
        <div ref={feedRef} className="space-y-4 max-h-96 overflow-y-auto">
            {sortedAgents.map(agentType => {
                const agentEvents = groupedEvents[agentType];
                const lastEvent = agentEvents[agentEvents.length - 1];
                const isActive = lastEvent.event_type !== 'completed' && lastEvent.event_type !== 'error';

                return (
                    <div key={agentType} className="border rounded-lg overflow-hidden">
                        <div className="flex items-center justify-between px-3 py-2 bg-gray-50 border-b">
                            <AgentBadge agentType={agentType} size="sm" />
                            {isActive && (
                                <span className="flex items-center gap-1 text-xs text-blue-600">
                                    <span className="w-2 h-2 bg-blue-500 rounded-full animate-pulse" />
                                    En cours
                                </span>
                            )}
                        </div>
                        <div className="divide-y divide-gray-100">
                            {agentEvents.map(event => (
                                <ActivityItem key={event.id} event={event} />
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
```

**Step 4: Create ActivityDrawer.tsx**

```typescript
import { Fragment } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { X, Activity } from 'lucide-react';
import { ActivityFeed } from './ActivityFeed';

interface AgentEvent {
    id: number;
    agent_type: string;
    event_type: string;
    message: string;
    reasoning: string | null;
    progress_current: number | null;
    progress_total: number | null;
    progress_percent: number | null;
    created_at: string;
}

interface ActivityDrawerProps {
    isOpen: boolean;
    onClose: () => void;
    events: AgentEvent[];
    activeAgents: string[];
    articleTitle?: string;
}

export function ActivityDrawer({
    isOpen,
    onClose,
    events,
    activeAgents,
    articleTitle
}: ActivityDrawerProps) {
    return (
        <Transition.Root show={isOpen} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                <Transition.Child
                    as={Fragment}
                    enter="ease-in-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in-out duration-300"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-hidden">
                    <div className="absolute inset-0 overflow-hidden">
                        <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                            <Transition.Child
                                as={Fragment}
                                enter="transform transition ease-in-out duration-300"
                                enterFrom="translate-x-full"
                                enterTo="translate-x-0"
                                leave="transform transition ease-in-out duration-300"
                                leaveFrom="translate-x-0"
                                leaveTo="translate-x-full"
                            >
                                <Dialog.Panel className="pointer-events-auto w-screen max-w-md">
                                    <div className="flex h-full flex-col overflow-y-scroll bg-white shadow-xl">
                                        {/* Header */}
                                        <div className="bg-gray-900 px-4 py-6 sm:px-6">
                                            <div className="flex items-center justify-between">
                                                <Dialog.Title className="flex items-center gap-2 text-base font-semibold text-white">
                                                    <Activity size={20} />
                                                    Activité des Agents
                                                    {activeAgents.length > 0 && (
                                                        <span className="ml-2 px-2 py-0.5 text-xs bg-blue-500 rounded-full">
                                                            {activeAgents.length} actif{activeAgents.length > 1 ? 's' : ''}
                                                        </span>
                                                    )}
                                                </Dialog.Title>
                                                <button
                                                    type="button"
                                                    className="text-gray-400 hover:text-white"
                                                    onClick={onClose}
                                                >
                                                    <X size={24} />
                                                </button>
                                            </div>
                                            {articleTitle && (
                                                <p className="mt-1 text-sm text-gray-400">
                                                    {articleTitle}
                                                </p>
                                            )}
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 px-4 py-4 sm:px-6">
                                            <ActivityFeed events={events} groupByAgent={true} />
                                        </div>
                                    </div>
                                </Dialog.Panel>
                            </Transition.Child>
                        </div>
                    </div>
                </div>
            </Dialog>
        </Transition.Root>
    );
}
```

**Step 5: Commit**

```bash
git add resources/js/Components/AgentActivity/
git commit -m "feat: add agent activity UI components (Drawer, Feed, Item, Badge)"
```

---

### Task 7.3: Create Activity Button Component

**Files:**
- Create: `resources/js/Components/AgentActivity/ActivityButton.tsx`

**Step 1: Create ActivityButton.tsx**

```typescript
import { Activity } from 'lucide-react';
import { clsx } from 'clsx';

interface ActivityButtonProps {
    activeAgents: number;
    hasNewEvents: boolean;
    onClick: () => void;
}

export function ActivityButton({ activeAgents, hasNewEvents, onClick }: ActivityButtonProps) {
    return (
        <button
            onClick={onClick}
            className={clsx(
                'fixed bottom-6 right-6 flex items-center gap-2 px-4 py-3 rounded-full shadow-lg transition-all',
                'hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2',
                activeAgents > 0
                    ? 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500'
                    : 'bg-gray-900 text-white hover:bg-gray-800 focus:ring-gray-500'
            )}
        >
            <Activity size={20} className={activeAgents > 0 ? 'animate-pulse' : ''} />

            {activeAgents > 0 ? (
                <span className="text-sm font-medium">
                    {activeAgents} agent{activeAgents > 1 ? 's' : ''} actif{activeAgents > 1 ? 's' : ''}
                </span>
            ) : (
                <span className="text-sm font-medium">Activité</span>
            )}

            {hasNewEvents && (
                <span className="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full" />
            )}
        </button>
    );
}
```

**Step 2: Export all components**

Create `resources/js/Components/AgentActivity/index.ts`:

```typescript
export { ActivityDrawer } from './ActivityDrawer';
export { ActivityFeed } from './ActivityFeed';
export { ActivityItem } from './ActivityItem';
export { ActivityButton } from './ActivityButton';
export { AgentBadge } from './AgentBadge';
```

**Step 3: Commit**

```bash
git add resources/js/Components/AgentActivity/
git commit -m "feat: add ActivityButton component and export all activity components"
```

---

## Phase 8: Pipeline Integration (Summary)

### Task 8.1: Update GenerateArticleJob

This is a larger task - modify `app/Jobs/GenerateArticleJob.php` to use the new agents pipeline. Key changes:

1. Inject `AgentRunner` and `AgentEventService`
2. Run Research + Competitor agents in parallel (Phase 1)
3. Generate outline and content with LLM (Phase 2)
4. Run Fact Checker agent (Phase 3)
5. Run Internal Linking agent (Phase 4)
6. Emit events at each step

**Files:**
- Modify: `app/Jobs/GenerateArticleJob.php`
- Modify: `app/Services/Content/ArticleGenerator.php`

This task requires careful implementation based on the existing ArticleGenerator service structure. The full implementation should:

1. Create the article record at the start (status: 'generating')
2. Emit started event
3. Run agents sequentially with progress events
4. Update article with final content
5. Mark as ready/failed based on result

**Commit message:** `feat: integrate agent pipeline into article generation job`

---

## Summary

This implementation plan covers:

| Phase | Tasks | Description |
|-------|-------|-------------|
| 1 | 1.1-1.5 | Infrastructure (DB, Model, Reverb, Events, Service) |
| 2 | 2.1-2.6 | Node.js agent infrastructure (shared modules, runner) |
| 3 | 3.1-3.3 | Research Agent (Google search, content scraping) |
| 4 | 4.1 | Competitor Agent (SERP analysis) |
| 5 | 5.1 | Fact Checker Agent (claim extraction, verification) |
| 6 | 6.1 | Internal Linking Agent (site index, link insertion) |
| 7 | 7.1-7.3 | Frontend UI (hook, components, drawer) |
| 8 | 8.1 | Pipeline integration |

**Total estimated tasks:** ~25 commits

**Run order for commands:**
1. Start Reverb: `php artisan reverb:start`
2. Start event processor: `php artisan agents:process-events`
3. Start queue worker: `php artisan queue:work`
4. Frontend dev: `npm run dev`
