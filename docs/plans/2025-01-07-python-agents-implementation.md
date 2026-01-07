# Python Agents Migration - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Migrate all 5 Node.js agents to Python using Crawl4AI for LLM-optimized content extraction.

**Architecture:** CLI agents called via `uv run`, communication with Laravel via JSON stdout, progress events via Redis pub/sub.

**Tech Stack:** Python 3.11+, uv, Crawl4AI, httpx, redis-py, Voyage AI, OpenRouter (via openai SDK)

---

## Task 1: Project Setup

**Files:**
- Create: `agents-python/pyproject.toml`
- Create: `agents-python/src/agents/__init__.py`
- Create: `agents-python/src/agents/shared/__init__.py`

**Step 1: Create project structure**

```bash
mkdir -p agents-python/src/agents/shared
mkdir -p agents-python/src/agents/research
mkdir -p agents-python/src/agents/competitor
mkdir -p agents-python/src/agents/fact_checker
mkdir -p agents-python/src/agents/internal_linking
mkdir -p agents-python/src/agents/site_indexer
mkdir -p agents-python/tests
```

**Step 2: Create pyproject.toml**

```toml
[project]
name = "seo-autopilot-agents"
version = "1.0.0"
requires-python = ">=3.11"
description = "Python agents for SEO Autopilot"

dependencies = [
    "crawl4ai>=0.4.0",
    "httpx>=0.27",
    "openai>=1.0",
    "voyageai>=0.3",
    "redis>=5.0",
    "click>=8.0",
    "python-dotenv>=1.0",
    "sqlite-vec>=0.1",
]

[project.scripts]
research = "agents.research.main:main"
competitor = "agents.competitor.main:main"
fact-checker = "agents.fact_checker.main:main"
internal-linking = "agents.internal_linking.main:main"
site-indexer = "agents.site_indexer.main:main"

[build-system]
requires = ["hatchling"]
build-backend = "hatchling.build"

[tool.hatch.build.targets.wheel]
packages = ["src/agents"]

[tool.pytest.ini_options]
testpaths = ["tests"]
asyncio_mode = "auto"

[project.optional-dependencies]
dev = [
    "pytest>=8.0",
    "pytest-asyncio>=0.23",
    "pytest-mock>=3.12",
]
```

**Step 3: Create __init__.py files**

```python
# src/agents/__init__.py
"""SEO Autopilot Python Agents."""
```

```python
# src/agents/shared/__init__.py
"""Shared utilities for agents."""
```

**Step 4: Initialize uv project**

Run: `cd agents-python && uv sync`
Expected: Creates `.venv/` and `uv.lock`

**Step 5: Commit**

```bash
git add agents-python/
git commit -m "chore: initialize Python agents project structure"
```

---

## Task 2: Shared Config Module

**Files:**
- Create: `agents-python/src/agents/shared/config.py`
- Create: `agents-python/tests/test_config.py`

**Step 1: Write the failing test**

```python
# tests/test_config.py
import os
import pytest
from agents.shared.config import Config

def test_config_loads_from_env(monkeypatch):
    monkeypatch.setenv("OPENROUTER_API_KEY", "test-key")
    monkeypatch.setenv("VOYAGE_API_KEY", "voyage-key")
    monkeypatch.setenv("REDIS_HOST", "localhost")

    config = Config()

    assert config.openrouter_api_key == "test-key"
    assert config.voyage_api_key == "voyage-key"
    assert config.redis_host == "localhost"

def test_config_raises_on_missing_required():
    with pytest.raises(ValueError, match="OPENROUTER_API_KEY"):
        Config(require_all=True)
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_config.py -v`
Expected: FAIL with "ModuleNotFoundError: No module named 'agents'"

**Step 3: Write implementation**

```python
# src/agents/shared/config.py
"""Configuration management for agents."""
import os
from dataclasses import dataclass
from dotenv import load_dotenv

# Load .env from project root
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), "../../../../.env"))

@dataclass
class Config:
    """Agent configuration loaded from environment variables."""

    openrouter_api_key: str = ""
    openrouter_base_url: str = "https://openrouter.ai/api/v1"
    voyage_api_key: str = ""
    redis_host: str = "localhost"
    redis_port: int = 6379
    redis_db: int = 0

    def __init__(self, require_all: bool = False):
        self.openrouter_api_key = os.getenv("OPENROUTER_API_KEY", "")
        self.openrouter_base_url = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")
        self.voyage_api_key = os.getenv("VOYAGE_API_KEY", "")
        self.redis_host = os.getenv("REDIS_HOST", "localhost")
        self.redis_port = int(os.getenv("REDIS_PORT", "6379"))
        self.redis_db = int(os.getenv("REDIS_DB", "0"))

        if require_all:
            self._validate()

    def _validate(self):
        if not self.openrouter_api_key:
            raise ValueError("OPENROUTER_API_KEY environment variable is required")
        if not self.voyage_api_key:
            raise ValueError("VOYAGE_API_KEY environment variable is required")

# Global config instance
config = Config()
```

**Step 4: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_config.py -v`
Expected: PASS

**Step 5: Commit**

```bash
git add agents-python/src/agents/shared/config.py agents-python/tests/test_config.py
git commit -m "feat(agents): add config module with env loading"
```

---

## Task 3: Shared Output Module

**Files:**
- Create: `agents-python/src/agents/shared/output.py`
- Create: `agents-python/tests/test_output.py`

**Step 1: Write the failing test**

```python
# tests/test_output.py
import json
import sys
from io import StringIO
from agents.shared.output import emit_json

def test_emit_json_prints_valid_json(capsys):
    data = {"success": True, "count": 42}
    emit_json(data)

    captured = capsys.readouterr()
    assert json.loads(captured.out.strip()) == data

def test_emit_json_handles_nested_data(capsys):
    data = {"results": [{"url": "https://example.com", "title": "Test"}]}
    emit_json(data)

    captured = capsys.readouterr()
    parsed = json.loads(captured.out.strip())
    assert parsed["results"][0]["url"] == "https://example.com"
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_output.py -v`
Expected: FAIL with "ModuleNotFoundError"

**Step 3: Write implementation**

```python
# src/agents/shared/output.py
"""Output utilities for agents."""
import json
import sys
from typing import Any

def emit_json(data: dict[str, Any]) -> None:
    """Print JSON to stdout for Laravel to parse.

    Laravel expects the last line of stdout to be valid JSON.
    """
    print(json.dumps(data, ensure_ascii=False, default=str))
    sys.stdout.flush()
```

**Step 4: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_output.py -v`
Expected: PASS

**Step 5: Commit**

```bash
git add agents-python/src/agents/shared/output.py agents-python/tests/test_output.py
git commit -m "feat(agents): add JSON output module for Laravel communication"
```

---

## Task 4: Shared Events Module (Redis)

**Files:**
- Create: `agents-python/src/agents/shared/events.py`
- Create: `agents-python/tests/test_events.py`

**Step 1: Write the failing test**

```python
# tests/test_events.py
import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from agents.shared.events import EventEmitter

@pytest.fixture
def mock_redis():
    with patch("agents.shared.events.redis.from_url") as mock:
        client = MagicMock()
        client.publish = AsyncMock()
        client.rpush = AsyncMock()
        client.aclose = AsyncMock()
        mock.return_value = client
        yield client

@pytest.mark.asyncio
async def test_emit_progress(mock_redis):
    emitter = EventEmitter(article_id=123, agent_type="research")
    await emitter.progress("Processing page 1/10", progress_current=1, progress_total=10)

    mock_redis.publish.assert_called_once()
    call_args = mock_redis.publish.call_args
    assert "agent-events:123" in str(call_args)

@pytest.mark.asyncio
async def test_emit_completed(mock_redis):
    emitter = EventEmitter(article_id=123, agent_type="research")
    await emitter.completed("Done", metadata={"count": 5})

    mock_redis.publish.assert_called_once()
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_events.py -v`
Expected: FAIL

**Step 3: Write implementation**

```python
# src/agents/shared/events.py
"""Event emitter for real-time progress tracking via Redis."""
import json
import time
from typing import Any
import redis.asyncio as redis
from .config import config

class EventEmitter:
    """Emits agent events to Redis for Laravel to consume."""

    def __init__(self, article_id: int | str, agent_type: str):
        self.article_id = article_id
        self.agent_type = agent_type
        self._redis: redis.Redis | None = None

    async def _get_redis(self) -> redis.Redis:
        if self._redis is None:
            self._redis = redis.from_url(
                f"redis://{config.redis_host}:{config.redis_port}/{config.redis_db}"
            )
        return self._redis

    async def _emit(
        self,
        event_type: str,
        message: str,
        reasoning: str | None = None,
        metadata: dict | None = None,
        progress_current: int | None = None,
        progress_total: int | None = None,
    ) -> dict[str, Any]:
        event = {
            "article_id": self.article_id,
            "agent_type": self.agent_type,
            "event_type": event_type,
            "message": message,
            "reasoning": reasoning,
            "metadata": metadata,
            "progress_current": progress_current,
            "progress_total": progress_total,
            "timestamp": int(time.time() * 1000),
        }

        try:
            client = await self._get_redis()
            channel = f"agent-events:{self.article_id}"
            await client.publish(channel, json.dumps(event))
            await client.rpush("agent-events-queue", json.dumps(event))
            print(f"[{self.agent_type}] {event_type}: {message}")
        except Exception as e:
            print(f"Failed to emit event: {e}")

        return event

    async def started(self, message: str, reasoning: str | None = None) -> dict:
        return await self._emit("started", message, reasoning=reasoning)

    async def progress(
        self,
        message: str,
        reasoning: str | None = None,
        metadata: dict | None = None,
        progress_current: int | None = None,
        progress_total: int | None = None,
    ) -> dict:
        return await self._emit(
            "progress", message,
            reasoning=reasoning,
            metadata=metadata,
            progress_current=progress_current,
            progress_total=progress_total,
        )

    async def completed(self, message: str, reasoning: str | None = None, metadata: dict | None = None) -> dict:
        return await self._emit("completed", message, reasoning=reasoning, metadata=metadata)

    async def error(self, message: str, error: Exception | None = None) -> dict:
        return await self._emit(
            "error", message,
            metadata={"error": str(error) if error else None}
        )

    async def close(self) -> None:
        if self._redis:
            await self._redis.aclose()
            self._redis = None
```

**Step 4: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_events.py -v`
Expected: PASS

**Step 5: Commit**

```bash
git add agents-python/src/agents/shared/events.py agents-python/tests/test_events.py
git commit -m "feat(agents): add Redis event emitter for progress tracking"
```

---

## Task 5: Shared LLM Module

**Files:**
- Create: `agents-python/src/agents/shared/llm.py`
- Create: `agents-python/tests/test_llm.py`

**Step 1: Write the failing test**

```python
# tests/test_llm.py
import pytest
from unittest.mock import AsyncMock, patch, MagicMock

@pytest.mark.asyncio
async def test_generate_json_returns_parsed():
    mock_response = MagicMock()
    mock_response.choices = [MagicMock(message=MagicMock(content='{"key": "value"}'))]

    with patch("agents.shared.llm.AsyncOpenAI") as mock_client_class:
        mock_client = MagicMock()
        mock_client.chat.completions.create = AsyncMock(return_value=mock_response)
        mock_client_class.return_value = mock_client

        from agents.shared.llm import LLMClient
        client = LLMClient()
        result = await client.generate_json("Test prompt")

        assert result == {"key": "value"}

@pytest.mark.asyncio
async def test_generate_text_returns_string():
    mock_response = MagicMock()
    mock_response.choices = [MagicMock(message=MagicMock(content="Hello world"))]

    with patch("agents.shared.llm.AsyncOpenAI") as mock_client_class:
        mock_client = MagicMock()
        mock_client.chat.completions.create = AsyncMock(return_value=mock_response)
        mock_client_class.return_value = mock_client

        from agents.shared.llm import LLMClient
        client = LLMClient()
        result = await client.generate_text("Test prompt")

        assert result == "Hello world"
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_llm.py -v`
Expected: FAIL

**Step 3: Write implementation**

```python
# src/agents/shared/llm.py
"""LLM client using OpenRouter API."""
import json
from typing import Any
from openai import AsyncOpenAI
from .config import config

DEFAULT_MODEL = "deepseek/deepseek-chat"

class LLMClient:
    """Async LLM client for OpenRouter."""

    def __init__(self):
        if not config.openrouter_api_key:
            raise ValueError("OPENROUTER_API_KEY is required")

        self._client = AsyncOpenAI(
            base_url=config.openrouter_base_url,
            api_key=config.openrouter_api_key,
            default_headers={
                "HTTP-Referer": "https://seo-autopilot.com",
                "X-Title": "SEO Autopilot",
            },
        )

    async def generate_json(
        self,
        prompt: str,
        system_prompt: str = "",
        model: str = DEFAULT_MODEL,
        temperature: float = 0.7,
        max_tokens: int = 4096,
    ) -> dict[str, Any]:
        """Generate a JSON response from the LLM."""
        messages = []
        if system_prompt:
            messages.append({"role": "system", "content": system_prompt})
        messages.append({"role": "user", "content": prompt})

        response = await self._client.chat.completions.create(
            model=model,
            messages=messages,
            response_format={"type": "json_object"},
            temperature=temperature,
            max_tokens=max_tokens,
        )

        content = response.choices[0].message.content
        if not content:
            raise ValueError("Empty response from LLM")

        return json.loads(content)

    async def generate_text(
        self,
        prompt: str,
        system_prompt: str = "",
        model: str = DEFAULT_MODEL,
        temperature: float = 0.7,
        max_tokens: int = 4096,
    ) -> str:
        """Generate a text response from the LLM."""
        messages = []
        if system_prompt:
            messages.append({"role": "system", "content": system_prompt})
        messages.append({"role": "user", "content": prompt})

        response = await self._client.chat.completions.create(
            model=model,
            messages=messages,
            temperature=temperature,
            max_tokens=max_tokens,
        )

        content = response.choices[0].message.content
        if not content:
            raise ValueError("Empty response from LLM")

        return content
```

**Step 4: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_llm.py -v`
Expected: PASS

**Step 5: Commit**

```bash
git add agents-python/src/agents/shared/llm.py agents-python/tests/test_llm.py
git commit -m "feat(agents): add OpenRouter LLM client"
```

---

## Task 6: Shared Crawler Module (Crawl4AI)

**Files:**
- Create: `agents-python/src/agents/shared/crawler.py`
- Create: `agents-python/tests/test_crawler.py`

**Step 1: Write the failing test**

```python
# tests/test_crawler.py
import pytest
from unittest.mock import AsyncMock, MagicMock, patch

@pytest.mark.asyncio
async def test_extract_returns_markdown():
    mock_result = MagicMock()
    mock_result.success = True
    mock_result.markdown = MagicMock(fit_markdown="# Title\n\nContent here")
    mock_result.metadata = {"title": "Test Page"}
    mock_result.links = {"internal": [], "external": []}

    with patch("agents.shared.crawler.AsyncWebCrawler") as mock_crawler_class:
        mock_crawler = MagicMock()
        mock_crawler.arun = AsyncMock(return_value=mock_result)
        mock_crawler.__aenter__ = AsyncMock(return_value=mock_crawler)
        mock_crawler.__aexit__ = AsyncMock(return_value=None)
        mock_crawler_class.return_value = mock_crawler

        from agents.shared.crawler import ContentCrawler
        crawler = ContentCrawler()
        result = await crawler.extract("https://example.com")

        assert result["success"] is True
        assert "# Title" in result["markdown"]
        assert result["title"] == "Test Page"
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_crawler.py -v`
Expected: FAIL

**Step 3: Write implementation**

```python
# src/agents/shared/crawler.py
"""Content crawler using Crawl4AI."""
from crawl4ai import AsyncWebCrawler, BrowserConfig, CrawlerRunConfig
from crawl4ai.content_filter_strategy import PruningContentFilter
from crawl4ai.markdown_generation_strategy import DefaultMarkdownGenerator

class ContentCrawler:
    """Wrapper around Crawl4AI for LLM-optimized content extraction."""

    def __init__(self, headless: bool = True):
        self.browser_config = BrowserConfig(
            headless=headless,
            viewport_width=1280,
            viewport_height=800,
        )
        self.run_config = CrawlerRunConfig(
            markdown_generator=DefaultMarkdownGenerator(
                content_filter=PruningContentFilter(
                    threshold=0.4,
                    threshold_type="dynamic",
                )
            ),
            wait_until="domcontentloaded",
            page_timeout=30000,
        )

    async def extract(self, url: str) -> dict:
        """Extract content from a single URL.

        Returns:
            dict with keys: url, title, markdown, links, success
        """
        async with AsyncWebCrawler(config=self.browser_config) as crawler:
            result = await crawler.arun(url, config=self.run_config)
            return {
                "url": url,
                "title": result.metadata.get("title", "") if result.metadata else "",
                "markdown": result.markdown.fit_markdown if result.markdown else "",
                "links": result.links or {"internal": [], "external": []},
                "success": result.success,
            }

    async def extract_many(
        self,
        urls: list[str],
        on_progress: callable | None = None,
    ) -> list[dict]:
        """Extract content from multiple URLs concurrently.

        Args:
            urls: List of URLs to extract
            on_progress: Optional callback(current, total) for progress updates

        Returns:
            List of extraction results
        """
        results = []
        async with AsyncWebCrawler(config=self.browser_config) as crawler:
            for i, url in enumerate(urls):
                try:
                    result = await crawler.arun(url, config=self.run_config)
                    results.append({
                        "url": url,
                        "title": result.metadata.get("title", "") if result.metadata else "",
                        "markdown": result.markdown.fit_markdown if result.markdown else "",
                        "links": result.links or {"internal": [], "external": []},
                        "success": result.success,
                    })
                except Exception as e:
                    results.append({
                        "url": url,
                        "title": "",
                        "markdown": "",
                        "links": {"internal": [], "external": []},
                        "success": False,
                        "error": str(e),
                    })

                if on_progress:
                    on_progress(i + 1, len(urls))

        return results
```

**Step 4: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_crawler.py -v`
Expected: PASS

**Step 5: Commit**

```bash
git add agents-python/src/agents/shared/crawler.py agents-python/tests/test_crawler.py
git commit -m "feat(agents): add Crawl4AI content crawler"
```

---

## Task 7: Shared Embeddings Module

**Files:**
- Create: `agents-python/src/agents/shared/embeddings.py`
- Create: `agents-python/tests/test_embeddings.py`

**Step 1: Write the failing test**

```python
# tests/test_embeddings.py
import pytest
from unittest.mock import AsyncMock, patch, MagicMock

@pytest.mark.asyncio
async def test_embed_returns_vector():
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.json.return_value = {
        "data": [{"embedding": [0.1, 0.2, 0.3]}]
    }

    with patch("agents.shared.embeddings.httpx.AsyncClient") as mock_client_class:
        mock_client = MagicMock()
        mock_client.post = AsyncMock(return_value=mock_response)
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=None)
        mock_client_class.return_value = mock_client

        from agents.shared.embeddings import VoyageEmbedder
        embedder = VoyageEmbedder()
        result = await embedder.embed("test text")

        assert result == [0.1, 0.2, 0.3]

@pytest.mark.asyncio
async def test_embed_batch_returns_vectors():
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.json.return_value = {
        "data": [
            {"embedding": [0.1, 0.2]},
            {"embedding": [0.3, 0.4]},
        ]
    }

    with patch("agents.shared.embeddings.httpx.AsyncClient") as mock_client_class:
        mock_client = MagicMock()
        mock_client.post = AsyncMock(return_value=mock_response)
        mock_client.__aenter__ = AsyncMock(return_value=mock_client)
        mock_client.__aexit__ = AsyncMock(return_value=None)
        mock_client_class.return_value = mock_client

        from agents.shared.embeddings import VoyageEmbedder
        embedder = VoyageEmbedder()
        results = await embedder.embed_batch(["text1", "text2"])

        assert len(results) == 2
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_embeddings.py -v`
Expected: FAIL

**Step 3: Write implementation**

```python
# src/agents/shared/embeddings.py
"""Embedding generation using Voyage AI."""
import httpx
from .config import config

VOYAGE_API_URL = "https://api.voyageai.com/v1/embeddings"
VOYAGE_MODEL = "voyage-3"
MAX_BATCH_SIZE = 128

class VoyageEmbedder:
    """Async embedding client for Voyage AI."""

    def __init__(self):
        if not config.voyage_api_key:
            raise ValueError("VOYAGE_API_KEY is required")
        self.api_key = config.voyage_api_key

    async def embed(self, text: str, input_type: str = "document") -> list[float]:
        """Generate embedding for a single text.

        Args:
            text: Text to embed
            input_type: "document" or "query"

        Returns:
            Embedding vector
        """
        async with httpx.AsyncClient() as client:
            response = await client.post(
                VOYAGE_API_URL,
                headers={
                    "Content-Type": "application/json",
                    "Authorization": f"Bearer {self.api_key}",
                },
                json={
                    "input": [text],
                    "model": VOYAGE_MODEL,
                    "input_type": input_type,
                },
                timeout=30.0,
            )

            if response.status_code != 200:
                raise ValueError(f"Voyage API error ({response.status_code}): {response.text}")

            data = response.json()
            return data["data"][0]["embedding"]

    async def embed_batch(
        self,
        texts: list[str],
        input_type: str = "document",
    ) -> list[list[float]]:
        """Generate embeddings for multiple texts.

        Automatically batches requests if > 128 items.

        Args:
            texts: List of texts to embed
            input_type: "document" or "query"

        Returns:
            List of embedding vectors
        """
        embeddings = []

        async with httpx.AsyncClient() as client:
            for i in range(0, len(texts), MAX_BATCH_SIZE):
                batch = texts[i:i + MAX_BATCH_SIZE]

                response = await client.post(
                    VOYAGE_API_URL,
                    headers={
                        "Content-Type": "application/json",
                        "Authorization": f"Bearer {self.api_key}",
                    },
                    json={
                        "input": batch,
                        "model": VOYAGE_MODEL,
                        "input_type": input_type,
                    },
                    timeout=60.0,
                )

                if response.status_code != 200:
                    raise ValueError(f"Voyage API error ({response.status_code}): {response.text}")

                data = response.json()
                for item in data["data"]:
                    embeddings.append(item["embedding"])

        return embeddings
```

**Step 4: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_embeddings.py -v`
Expected: PASS

**Step 5: Commit**

```bash
git add agents-python/src/agents/shared/embeddings.py agents-python/tests/test_embeddings.py
git commit -m "feat(agents): add Voyage AI embeddings client"
```

---

## Task 8: Research Agent

**Files:**
- Create: `agents-python/src/agents/research/__init__.py`
- Create: `agents-python/src/agents/research/main.py`
- Create: `agents-python/tests/test_research_agent.py`

**Step 1: Write the failing test**

```python
# tests/test_research_agent.py
import pytest
from unittest.mock import AsyncMock, MagicMock, patch
from click.testing import CliRunner

def test_cli_requires_keyword():
    from agents.research.main import main
    runner = CliRunner()
    result = runner.invoke(main, ["--articleId", "123"])
    assert result.exit_code != 0
    assert "keyword" in result.output.lower() or "required" in result.output.lower()
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_research_agent.py -v`
Expected: FAIL

**Step 3: Write implementation**

```python
# src/agents/research/__init__.py
"""Research agent for content discovery."""
```

```python
# src/agents/research/main.py
"""Research agent - discovers and analyzes sources for a keyword."""
import asyncio
import click
from ..shared.crawler import ContentCrawler
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "research"

async def generate_search_queries(llm: LLMClient, keyword: str) -> list[str]:
    """Generate varied search queries for the keyword."""
    result = await llm.generate_json(f'''
        Génère 5-6 requêtes de recherche Google variées pour le keyword "{keyword}".
        Les requêtes doivent couvrir:
        - La requête principale
        - Des variations avec "best", "top", "guide"
        - Des questions (how to, what is)
        - Des comparaisons si pertinent

        Retourne un JSON: {{ "queries": ["query1", "query2", ...] }}
    ''')
    return result.get("queries", [keyword])

async def analyze_content(llm: LLMClient, keyword: str, sources: list[dict]) -> dict:
    """Analyze and synthesize the scraped content."""
    sources_text = "\n\n---\n\n".join([
        f"[{s['title']}]\n{s['markdown'][:2000]}"
        for s in sources if s.get("markdown")
    ])

    result = await llm.generate_json(f'''
        Analyse ces sources sur le sujet "{keyword}" et extrait:

        1. topics: Les sous-sujets principaux couverts (liste de strings)
        2. entities: Les entités importantes (outils, marques, personnes) mentionnées
        3. facts: Les faits/statistiques citables avec leur source
        4. angles: 2-3 angles d'article suggérés pour se différencier
        5. summary: Un résumé de 2-3 phrases de ce que les sources couvrent

        Sources:
        {sources_text}

        Retourne un JSON avec ces 5 clés.
    ''', model="google/gemini-2.0-flash-exp")

    return {
        "topics": result.get("topics", []),
        "entities": result.get("entities", []),
        "facts": result.get("facts", []),
        "angles": result.get("angles", []),
        "summary": result.get("summary", ""),
    }

async def search_google(query: str) -> list[str]:
    """Search Google and return URLs.

    TODO: Implement with SearXNG or Google Custom Search API.
    For now, this is a placeholder.
    """
    # Placeholder - will be implemented with SearXNG
    return []

async def run(article_id: int, keyword: str, site_id: int | None = None):
    """Main research agent logic."""
    events = EventEmitter(article_id, AGENT_TYPE)
    llm = LLMClient()
    crawler = ContentCrawler()

    try:
        await events.started(
            f'Démarrage de la recherche pour "{keyword}"',
            f'Le keyword "{keyword}" sera analysé pour comprendre l\'intention de recherche.'
        )

        # Step 1: Generate search queries
        await events.progress("Génération des requêtes de recherche...")
        queries = await generate_search_queries(llm, keyword)
        await events.progress(f"{len(queries)} requêtes préparées")

        # Step 2: Search and collect URLs
        all_urls = []
        for i, query in enumerate(queries):
            await events.progress(
                f'Recherche: "{query}"',
                progress_current=i + 1,
                progress_total=len(queries),
            )
            urls = await search_google(query)
            all_urls.extend(urls)

        unique_urls = list(set(all_urls))
        await events.progress(f"{len(unique_urls)} URLs collectées")

        if not unique_urls:
            await events.completed("Aucune URL trouvée")
            emit_json({
                "success": True,
                "sources": [],
                "key_topics": [],
                "entities": [],
                "facts": [],
                "suggested_angles": [],
                "competitor_urls": [],
            })
            return

        # Step 3: Extract content
        await events.progress("Extraction du contenu...")

        def on_progress(current, total):
            asyncio.create_task(events.progress(
                f"Extraction ({current}/{total})...",
                progress_current=current,
                progress_total=total,
            ))

        scraped = await crawler.extract_many(unique_urls, on_progress=on_progress)
        valid = [s for s in scraped if s["success"] and len(s.get("markdown", "")) > 200]

        if not valid:
            await events.completed("Aucune source exploitable")
            emit_json({
                "success": True,
                "sources": [],
                "key_topics": [],
                "entities": [],
                "facts": [],
                "suggested_angles": [],
                "competitor_urls": unique_urls[:10],
            })
            return

        await events.progress(f"{len(valid)} pages exploitables")

        # Step 4: Analyze
        await events.progress("Analyse et synthèse...")
        analysis = await analyze_content(llm, keyword, valid)

        await events.completed(
            f"Recherche terminée: {len(analysis['entities'])} entités, {len(analysis['facts'])} faits",
            reasoning=analysis["summary"],
            metadata={
                "sources_count": len(valid),
                "entities_count": len(analysis["entities"]),
                "facts_count": len(analysis["facts"]),
            },
        )

        emit_json({
            "success": True,
            "sources": [
                {"url": s["url"], "title": s["title"], "snippet": s["markdown"][:500]}
                for s in valid
            ],
            "key_topics": analysis["topics"],
            "entities": analysis["entities"],
            "facts": analysis["facts"],
            "suggested_angles": analysis["angles"],
            "competitor_urls": unique_urls[:10],
        })

    except Exception as e:
        await events.error(f"Erreur: {e}", e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()

@click.command()
@click.option("--articleId", required=True, type=int, help="Article ID")
@click.option("--keyword", required=True, help="Target keyword")
@click.option("--siteId", type=int, help="Site ID")
def main(articleid: int, keyword: str, siteid: int | None):
    """Research agent - discovers sources for a keyword."""
    asyncio.run(run(articleid, keyword, siteid))

if __name__ == "__main__":
    main()
```

**Step 4: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_research_agent.py -v`
Expected: PASS

**Step 5: Commit**

```bash
git add agents-python/src/agents/research/
git add agents-python/tests/test_research_agent.py
git commit -m "feat(agents): add research agent"
```

---

## Task 9: Site Indexer Agent

**Files:**
- Create: `agents-python/src/agents/site_indexer/__init__.py`
- Create: `agents-python/src/agents/site_indexer/main.py`
- Create: `agents-python/src/agents/site_indexer/database.py`
- Create: `agents-python/tests/test_site_indexer.py`

**Step 1: Write the failing test**

```python
# tests/test_site_indexer.py
import pytest
from click.testing import CliRunner

def test_cli_requires_site_url():
    from agents.site_indexer.main import main
    runner = CliRunner()
    result = runner.invoke(main, ["--siteId", "123"])
    assert result.exit_code != 0
```

**Step 2: Run test to verify it fails**

Run: `cd agents-python && uv run pytest tests/test_site_indexer.py -v`
Expected: FAIL

**Step 3: Write database module**

```python
# src/agents/site_indexer/database.py
"""SQLite database for site index."""
import sqlite3
import json
from pathlib import Path

class SiteIndexDB:
    """SQLite database for storing indexed pages."""

    def __init__(self, site_id: int, storage_path: str | None = None):
        if storage_path is None:
            # Default to Laravel storage path
            storage_path = Path(__file__).parent.parent.parent.parent.parent / "storage" / "indexes"

        storage_path = Path(storage_path)
        storage_path.mkdir(parents=True, exist_ok=True)

        self.db_path = storage_path / f"site_{site_id}.sqlite"
        self._conn: sqlite3.Connection | None = None
        self._init_db()

    def _get_conn(self) -> sqlite3.Connection:
        if self._conn is None:
            self._conn = sqlite3.connect(str(self.db_path))
            self._conn.row_factory = sqlite3.Row
        return self._conn

    def _init_db(self):
        conn = self._get_conn()
        conn.executescript('''
            CREATE TABLE IF NOT EXISTS pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT UNIQUE NOT NULL,
                title TEXT,
                h1 TEXT,
                meta_description TEXT,
                content TEXT,
                category TEXT,
                tags TEXT,
                internal_links TEXT,
                content_hash TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS embeddings (
                page_id INTEGER PRIMARY KEY,
                embedding BLOB,
                FOREIGN KEY (page_id) REFERENCES pages(id)
            );

            CREATE INDEX IF NOT EXISTS idx_pages_url ON pages(url);
        ''')
        conn.commit()

    def upsert_page(
        self,
        url: str,
        title: str = "",
        h1: str = "",
        meta_description: str = "",
        content: str = "",
        category: str = "",
        tags: list[str] | None = None,
        internal_links: list[str] | None = None,
        content_hash: str = "",
    ) -> int:
        """Insert or update a page, return its ID."""
        conn = self._get_conn()
        cursor = conn.execute('''
            INSERT INTO pages (url, title, h1, meta_description, content, category, tags, internal_links, content_hash, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(url) DO UPDATE SET
                title = excluded.title,
                h1 = excluded.h1,
                meta_description = excluded.meta_description,
                content = excluded.content,
                category = excluded.category,
                tags = excluded.tags,
                internal_links = excluded.internal_links,
                content_hash = excluded.content_hash,
                updated_at = CURRENT_TIMESTAMP
            RETURNING id
        ''', (
            url, title, h1, meta_description, content, category,
            json.dumps(tags or []),
            json.dumps(internal_links or []),
            content_hash,
        ))
        row = cursor.fetchone()
        conn.commit()
        return row[0]

    def upsert_embedding(self, page_id: int, embedding: list[float]):
        """Store embedding for a page."""
        conn = self._get_conn()
        # Store as binary blob for efficiency
        import struct
        blob = struct.pack(f'{len(embedding)}f', *embedding)
        conn.execute('''
            INSERT INTO embeddings (page_id, embedding)
            VALUES (?, ?)
            ON CONFLICT(page_id) DO UPDATE SET embedding = excluded.embedding
        ''', (page_id, blob))
        conn.commit()

    def get_known_urls(self) -> list[str]:
        """Get all indexed URLs."""
        conn = self._get_conn()
        cursor = conn.execute('SELECT url FROM pages')
        return [row[0] for row in cursor.fetchall()]

    def is_unchanged(self, url: str, content_hash: str) -> bool:
        """Check if page content is unchanged."""
        conn = self._get_conn()
        cursor = conn.execute(
            'SELECT content_hash FROM pages WHERE url = ?', (url,)
        )
        row = cursor.fetchone()
        return row is not None and row[0] == content_hash

    def count_pages(self) -> int:
        """Count total indexed pages."""
        conn = self._get_conn()
        cursor = conn.execute('SELECT COUNT(*) FROM pages')
        return cursor.fetchone()[0]

    def close(self):
        """Close database connection."""
        if self._conn:
            self._conn.close()
            self._conn = None
```

**Step 4: Write main module**

```python
# src/agents/site_indexer/__init__.py
"""Site indexer agent."""
```

```python
# src/agents/site_indexer/main.py
"""Site indexer agent - crawls and indexes a website."""
import asyncio
import hashlib
import click
from urllib.parse import urlparse
from crawl4ai import AsyncWebCrawler, BrowserConfig, CrawlerRunConfig
from crawl4ai.deep_crawl import BFSDeepCrawlStrategy
from ..shared.events import EventEmitter
from ..shared.embeddings import VoyageEmbedder
from ..shared.output import emit_json
from .database import SiteIndexDB

AGENT_TYPE = "site-indexer"
MAX_CONTENT_LENGTH = 8000

def validate_url(url: str) -> str:
    """Validate and normalize URL."""
    parsed = urlparse(url)
    if parsed.scheme not in ("http", "https"):
        raise ValueError("URL must use http or https protocol")
    return url.rstrip("/")

async def run(site_id: int, site_url: str, max_pages: int = 100, delta: bool = False):
    """Main site indexer logic."""
    events = EventEmitter(site_id, AGENT_TYPE)
    db = SiteIndexDB(site_id)

    try:
        validated_url = validate_url(site_url)
        domain = urlparse(validated_url).netloc

        await events.started("Starting site indexing", f"Indexing {validated_url}")

        # Get known URLs for delta mode
        known_urls = set()
        if delta:
            known_urls = set(db.get_known_urls())
            print(f"Delta mode: {len(known_urls)} known URLs")

        # Configure deep crawl
        crawl_strategy = BFSDeepCrawlStrategy(
            max_depth=3,
            max_pages=max_pages,
            include_patterns=[f"*{domain}*"],
            exclude_patterns=[
                "*/wp-admin/*", "*/cart/*", "*/checkout/*",
                "*/login/*", "*/register/*", "*?*",
            ],
        )

        browser_config = BrowserConfig(headless=True)
        run_config = CrawlerRunConfig(
            deep_crawl_strategy=crawl_strategy,
            wait_until="domcontentloaded",
            page_timeout=30000,
        )

        await events.progress("Discovering and crawling pages...")

        indexed_count = 0
        error_count = 0

        async with AsyncWebCrawler(config=browser_config) as crawler:
            results = await crawler.arun(validated_url, config=run_config)

            # Handle both single result and list of results
            pages = results if isinstance(results, list) else [results]
            total = len(pages)

            embedder = VoyageEmbedder()

            for i, page in enumerate(pages):
                if not page.success:
                    error_count += 1
                    continue

                page_url = page.url

                # Skip known pages in delta mode
                content_hash = hashlib.md5(
                    (page.markdown.fit_markdown or "").encode()
                ).hexdigest()

                if delta and page_url in known_urls:
                    if db.is_unchanged(page_url, content_hash):
                        continue

                await events.progress(
                    f"Processing page {i + 1}/{total}",
                    progress_current=i + 1,
                    progress_total=total,
                    metadata={"url": page_url},
                )

                # Extract metadata
                title = page.metadata.get("title", "") if page.metadata else ""
                markdown = page.markdown.fit_markdown if page.markdown else ""

                # Store page
                page_id = db.upsert_page(
                    url=page_url,
                    title=title,
                    content=markdown[:MAX_CONTENT_LENGTH],
                    content_hash=content_hash,
                )
                indexed_count += 1

                # Generate and store embedding
                try:
                    content_for_embedding = f"{title}\n\n{markdown}"[:MAX_CONTENT_LENGTH]
                    embedding = await embedder.embed(content_for_embedding, "document")
                    db.upsert_embedding(page_id, embedding)
                except Exception as e:
                    print(f"Embedding failed for {page_url}: {e}")

        total_pages = db.count_pages()

        await events.completed(
            "Site indexing completed",
            metadata={
                "indexed": indexed_count,
                "errors": error_count,
                "total": total_pages,
            },
        )

        emit_json({
            "pages_indexed": indexed_count,
            "discovered": len(pages) if 'pages' in dir() else 0,
            "errors": error_count,
        })

    except Exception as e:
        await events.error(f"Indexing failed: {e}", e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        db.close()
        await events.close()

@click.command()
@click.option("--siteId", required=True, type=int, help="Site ID")
@click.option("--siteUrl", required=True, help="Site URL to index")
@click.option("--maxPages", default=100, type=int, help="Maximum pages to index")
@click.option("--delta", is_flag=True, help="Only index new/changed pages")
def main(siteid: int, siteurl: str, maxpages: int, delta: bool):
    """Site indexer - crawls and indexes a website."""
    asyncio.run(run(siteid, siteurl, maxpages, delta))

if __name__ == "__main__":
    main()
```

**Step 5: Run test to verify it passes**

Run: `cd agents-python && uv run pytest tests/test_site_indexer.py -v`
Expected: PASS

**Step 6: Commit**

```bash
git add agents-python/src/agents/site_indexer/
git add agents-python/tests/test_site_indexer.py
git commit -m "feat(agents): add site indexer agent with deep crawl"
```

---

## Task 10: Competitor Agent (Skeleton)

**Files:**
- Create: `agents-python/src/agents/competitor/__init__.py`
- Create: `agents-python/src/agents/competitor/main.py`

**Step 1: Write skeleton implementation**

```python
# src/agents/competitor/__init__.py
"""Competitor analysis agent."""
```

```python
# src/agents/competitor/main.py
"""Competitor agent - analyzes SERP competitors."""
import asyncio
import json
import click
from ..shared.crawler import ContentCrawler
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "competitor"

async def run(article_id: int, keyword: str, urls: list[str]):
    """Analyze competitor content."""
    events = EventEmitter(article_id, AGENT_TYPE)
    crawler = ContentCrawler()
    llm = LLMClient()

    try:
        await events.started(f'Analyzing {len(urls)} competitors for "{keyword}"')

        # Extract content from competitor URLs
        await events.progress("Extracting competitor content...")
        results = await crawler.extract_many(urls)
        valid = [r for r in results if r["success"]]

        if not valid:
            await events.completed("No valid competitor content found")
            emit_json({"success": True, "analysis": {}})
            return

        # Analyze structure and content
        await events.progress("Analyzing content structure...")

        competitors_text = "\n\n---\n\n".join([
            f"URL: {c['url']}\nTitle: {c['title']}\n\n{c['markdown'][:3000]}"
            for c in valid
        ])

        analysis = await llm.generate_json(f'''
            Analyse ces articles concurrents pour le keyword "{keyword}":

            {competitors_text}

            Retourne un JSON avec:
            1. avg_word_count: Nombre moyen de mots
            2. common_headings: Les H2/H3 les plus fréquents
            3. topics_covered: Sujets couverts par tous
            4. gaps: Sujets manquants ou mal couverts
            5. recommendations: 3 recommandations pour se différencier
        ''', model="google/gemini-2.0-flash-exp")

        await events.completed(
            f"Analysis complete: {len(valid)} competitors analyzed",
            metadata={"competitors_count": len(valid)},
        )

        emit_json({
            "success": True,
            "competitors_analyzed": len(valid),
            "analysis": analysis,
        })

    except Exception as e:
        await events.error(str(e), e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()

@click.command()
@click.option("--articleId", required=True, type=int)
@click.option("--keyword", required=True)
@click.option("--urls", required=True, help="JSON array of URLs")
def main(articleid: int, keyword: str, urls: str):
    """Competitor agent - analyzes SERP competitors."""
    url_list = json.loads(urls)
    asyncio.run(run(articleid, keyword, url_list))

if __name__ == "__main__":
    main()
```

**Step 2: Commit**

```bash
git add agents-python/src/agents/competitor/
git commit -m "feat(agents): add competitor analysis agent"
```

---

## Task 11: Fact Checker Agent (Skeleton)

**Files:**
- Create: `agents-python/src/agents/fact_checker/__init__.py`
- Create: `agents-python/src/agents/fact_checker/main.py`

**Step 1: Write skeleton implementation**

```python
# src/agents/fact_checker/__init__.py
"""Fact checker agent."""
```

```python
# src/agents/fact_checker/main.py
"""Fact checker agent - verifies claims in content."""
import asyncio
import click
from pathlib import Path
from ..shared.crawler import ContentCrawler
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "fact-checker"

async def run(article_id: int, content: str):
    """Verify claims in content."""
    events = EventEmitter(article_id, AGENT_TYPE)
    llm = LLMClient()
    crawler = ContentCrawler()

    try:
        await events.started("Extracting claims from content")

        # Extract verifiable claims
        claims_result = await llm.generate_json(f'''
            Extrait les affirmations vérifiables de ce texte.
            Ignore les opinions et les généralités.

            Texte:
            {content[:5000]}

            Retourne: {{ "claims": [{{ "text": "...", "importance": "high|medium|low" }}] }}
        ''')

        claims = claims_result.get("claims", [])
        await events.progress(f"{len(claims)} claims extracted")

        # Verify each claim (simplified - real implementation would search web)
        verified_claims = []
        for i, claim in enumerate(claims[:10]):  # Limit to 10 claims
            await events.progress(
                f"Verifying claim {i + 1}/{min(len(claims), 10)}",
                progress_current=i + 1,
                progress_total=min(len(claims), 10),
            )

            # TODO: Search web for claim verification
            verified_claims.append({
                **claim,
                "verified": None,  # Unknown without web search
                "sources": [],
            })

        await events.completed(f"Checked {len(verified_claims)} claims")

        emit_json({
            "success": True,
            "claims_count": len(claims),
            "verified_claims": verified_claims,
        })

    except Exception as e:
        await events.error(str(e), e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()

@click.command()
@click.option("--articleId", required=True, type=int)
@click.option("--contentFile", required=True, help="Path to content file")
def main(articleid: int, contentfile: str):
    """Fact checker - verifies claims in content."""
    content = Path(contentfile).read_text()
    asyncio.run(run(articleid, content))

if __name__ == "__main__":
    main()
```

**Step 2: Commit**

```bash
git add agents-python/src/agents/fact_checker/
git commit -m "feat(agents): add fact checker agent skeleton"
```

---

## Task 12: Internal Linking Agent (Skeleton)

**Files:**
- Create: `agents-python/src/agents/internal_linking/__init__.py`
- Create: `agents-python/src/agents/internal_linking/main.py`

**Step 1: Write skeleton implementation**

```python
# src/agents/internal_linking/__init__.py
"""Internal linking agent."""
```

```python
# src/agents/internal_linking/main.py
"""Internal linking agent - suggests internal links."""
import asyncio
import struct
import sqlite3
import click
from pathlib import Path
from ..shared.embeddings import VoyageEmbedder
from ..shared.llm import LLMClient
from ..shared.events import EventEmitter
from ..shared.output import emit_json

AGENT_TYPE = "internal-linking"

def cosine_similarity(a: list[float], b: list[float]) -> float:
    """Calculate cosine similarity between two vectors."""
    dot = sum(x * y for x, y in zip(a, b))
    norm_a = sum(x * x for x in a) ** 0.5
    norm_b = sum(x * x for x in b) ** 0.5
    return dot / (norm_a * norm_b) if norm_a and norm_b else 0

async def run(article_id: int, site_id: int, content: str):
    """Suggest internal links for content."""
    events = EventEmitter(article_id, AGENT_TYPE)
    embedder = VoyageEmbedder()
    llm = LLMClient()

    try:
        await events.started("Finding relevant internal pages")

        # Load site index
        index_path = Path(__file__).parent.parent.parent.parent.parent / "storage" / "indexes" / f"site_{site_id}.sqlite"

        if not index_path.exists():
            await events.completed("No site index found")
            emit_json({"success": True, "links": []})
            return

        conn = sqlite3.connect(str(index_path))
        conn.row_factory = sqlite3.Row

        # Get content embedding
        await events.progress("Generating content embedding...")
        content_embedding = await embedder.embed(content[:8000], "query")

        # Find similar pages
        await events.progress("Finding similar pages...")
        cursor = conn.execute('''
            SELECT p.id, p.url, p.title, e.embedding
            FROM pages p
            JOIN embeddings e ON e.page_id = p.id
        ''')

        similarities = []
        for row in cursor:
            # Unpack embedding from blob
            embedding_blob = row["embedding"]
            num_floats = len(embedding_blob) // 4
            page_embedding = list(struct.unpack(f'{num_floats}f', embedding_blob))

            similarity = cosine_similarity(content_embedding, page_embedding)
            similarities.append({
                "url": row["url"],
                "title": row["title"],
                "similarity": similarity,
            })

        conn.close()

        # Get top similar pages
        top_pages = sorted(similarities, key=lambda x: x["similarity"], reverse=True)[:20]

        # Use LLM to suggest placements
        await events.progress("Generating link suggestions...")

        pages_text = "\n".join([
            f"- {p['title']} ({p['url']}) - similarity: {p['similarity']:.2f}"
            for p in top_pages
        ])

        suggestions = await llm.generate_json(f'''
            Voici un article et des pages internes similaires.
            Suggère où placer des liens internes de manière naturelle.

            Article (extrait):
            {content[:2000]}

            Pages disponibles:
            {pages_text}

            Retourne: {{
                "suggestions": [{{
                    "anchor_text": "texte à transformer en lien",
                    "target_url": "url de la page cible",
                    "context": "phrase où placer le lien"
                }}]
            }}
        ''', model="anthropic/claude-3.5-haiku")

        await events.completed(
            f"Found {len(suggestions.get('suggestions', []))} link opportunities",
        )

        emit_json({
            "success": True,
            "similar_pages": top_pages[:10],
            "suggestions": suggestions.get("suggestions", []),
        })

    except Exception as e:
        await events.error(str(e), e)
        emit_json({"success": False, "error": str(e)})
        raise
    finally:
        await events.close()

@click.command()
@click.option("--articleId", required=True, type=int)
@click.option("--siteId", required=True, type=int)
@click.option("--contentFile", required=True)
def main(articleid: int, siteid: int, contentfile: str):
    """Internal linking - suggests internal links."""
    content = Path(contentfile).read_text()
    asyncio.run(run(articleid, siteid, content))

if __name__ == "__main__":
    main()
```

**Step 2: Commit**

```bash
git add agents-python/src/agents/internal_linking/
git commit -m "feat(agents): add internal linking agent"
```

---

## Task 13: Update Laravel AgentRunner

**Files:**
- Modify: `app/Services/Agent/AgentRunner.php`

**Step 1: Update runAgent method**

```php
// Replace the runAgent method in AgentRunner.php

private function runAgent(string $agentName, array $args): array
{
    // Map agent names to Python script names
    $pythonAgentMap = [
        'research-agent' => 'research',
        'competitor-agent' => 'competitor',
        'fact-checker-agent' => 'fact-checker',
        'internal-linking-agent' => 'internal-linking',
    ];

    $pythonAgent = $pythonAgentMap[$agentName] ?? null;

    if ($pythonAgent) {
        // Use Python agent via uv
        $command = [
            'uv', 'run',
            '--project', base_path('agents-python'),
            $pythonAgent,
        ];
    } else {
        // Fallback to Node.js for unmigrated agents
        $command = ['node', "{$agentName}/index.js"];
    }

    foreach ($args as $key => $value) {
        $command[] = "{$key}={$value}";
    }

    Log::info("AgentRunner: Starting {$agentName}", ['args' => $args, 'python' => (bool)$pythonAgent]);

    $result = Process::path($pythonAgent ? base_path('agents-python') : $this->agentsPath)
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
```

**Step 2: Commit**

```bash
git add app/Services/Agent/AgentRunner.php
git commit -m "feat: update AgentRunner to use Python agents via uv"
```

---

## Task 14: Update SiteIndexService

**Files:**
- Modify: `app/Services/Crawler/SiteIndexService.php`

**Step 1: Update indexSite method**

```php
// Update the command array in indexSite method

$command = [
    'uv', 'run',
    '--project', base_path('agents-python'),
    'site-indexer',
    '--siteId', (string) $site->id,
    '--siteUrl', $site->url,
    '--maxPages', '500',
];

if ($delta) {
    $command[] = '--delta';
}

$result = Process::path(base_path('agents-python'))
    ->timeout(600)
    ->run($command);
```

**Step 2: Commit**

```bash
git add app/Services/Crawler/SiteIndexService.php
git commit -m "feat: update SiteIndexService to use Python site-indexer"
```

---

## Task 15: Integration Testing

**Step 1: Test research agent manually**

```bash
cd agents-python
uv run research --articleId=1 --keyword="test keyword"
```
Expected: JSON output with success status

**Step 2: Test site-indexer manually**

```bash
cd agents-python
uv run site-indexer --siteId=1 --siteUrl="https://example.com" --maxPages=5
```
Expected: JSON output with pages_indexed count

**Step 3: Test from Laravel**

```php
// In tinker or a test
$runner = app(\App\Services\Agent\AgentRunner::class);
$result = $runner->runResearchAgent($article, 'test keyword');
dd($result);
```

**Step 4: Commit any fixes**

```bash
git add -A
git commit -m "fix: integration testing fixes"
```

---

## Task 16: Cleanup (After Validation)

**Step 1: Remove Node.js agents**

```bash
rm -rf agents/
git add -A
git commit -m "chore: remove deprecated Node.js agents"
```

**Step 2: Update documentation**

Update any references to Node.js agents in documentation to point to Python agents.

---

## Summary

**Total Tasks:** 16
**Estimated Time:** 4-6 hours for core implementation

**Dependencies between tasks:**
- Tasks 1-7 (shared modules) must complete before Tasks 8-12 (agents)
- Task 13-14 (Laravel updates) can be done after any agent is ready
- Task 15 (testing) requires all previous tasks
- Task 16 (cleanup) only after full validation

**Critical paths:**
1. Setup → Config → Output → Events → LLM → Crawler → Research Agent
2. Setup → Config → Embeddings → Site Indexer
