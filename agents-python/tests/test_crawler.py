# tests/test_crawler.py
"""Tests for ContentCrawler module."""
import pytest
from unittest.mock import AsyncMock, MagicMock, patch


@pytest.mark.asyncio
async def test_extract_returns_markdown():
    """Test single URL extraction returns expected structure with markdown."""
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
        assert result["url"] == "https://example.com"
        assert "internal" in result["links"]


@pytest.mark.asyncio
async def test_extract_handles_missing_metadata():
    """Test extraction handles None metadata gracefully."""
    mock_result = MagicMock()
    mock_result.success = True
    mock_result.markdown = MagicMock(fit_markdown="# Content")
    mock_result.metadata = None
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

        assert result["title"] == ""
        assert result["success"] is True


@pytest.mark.asyncio
async def test_extract_handles_missing_markdown():
    """Test extraction handles None markdown gracefully."""
    mock_result = MagicMock()
    mock_result.success = True
    mock_result.markdown = None
    mock_result.metadata = {"title": "Test"}
    mock_result.links = None

    with patch("agents.shared.crawler.AsyncWebCrawler") as mock_crawler_class:
        mock_crawler = MagicMock()
        mock_crawler.arun = AsyncMock(return_value=mock_result)
        mock_crawler.__aenter__ = AsyncMock(return_value=mock_crawler)
        mock_crawler.__aexit__ = AsyncMock(return_value=None)
        mock_crawler_class.return_value = mock_crawler

        from agents.shared.crawler import ContentCrawler
        crawler = ContentCrawler()
        result = await crawler.extract("https://example.com")

        assert result["markdown"] == ""
        assert result["links"] == {"internal": [], "external": []}


@pytest.mark.asyncio
async def test_extract_many_returns_list():
    """Test multi-URL extraction returns list of results."""
    mock_result1 = MagicMock()
    mock_result1.success = True
    mock_result1.markdown = MagicMock(fit_markdown="# Page 1")
    mock_result1.metadata = {"title": "Page 1"}
    mock_result1.links = {"internal": [], "external": []}

    mock_result2 = MagicMock()
    mock_result2.success = True
    mock_result2.markdown = MagicMock(fit_markdown="# Page 2")
    mock_result2.metadata = {"title": "Page 2"}
    mock_result2.links = {"internal": [], "external": []}

    with patch("agents.shared.crawler.AsyncWebCrawler") as mock_crawler_class:
        mock_crawler = MagicMock()
        mock_crawler.arun = AsyncMock(side_effect=[mock_result1, mock_result2])
        mock_crawler.__aenter__ = AsyncMock(return_value=mock_crawler)
        mock_crawler.__aexit__ = AsyncMock(return_value=None)
        mock_crawler_class.return_value = mock_crawler

        from agents.shared.crawler import ContentCrawler
        crawler = ContentCrawler()
        results = await crawler.extract_many([
            "https://example.com/1",
            "https://example.com/2"
        ])

        assert len(results) == 2
        assert results[0]["title"] == "Page 1"
        assert results[1]["title"] == "Page 2"


@pytest.mark.asyncio
async def test_extract_many_handles_errors():
    """Test multi-URL extraction handles individual URL failures gracefully."""
    mock_result1 = MagicMock()
    mock_result1.success = True
    mock_result1.markdown = MagicMock(fit_markdown="# Page 1")
    mock_result1.metadata = {"title": "Page 1"}
    mock_result1.links = {"internal": [], "external": []}

    with patch("agents.shared.crawler.AsyncWebCrawler") as mock_crawler_class:
        mock_crawler = MagicMock()
        mock_crawler.arun = AsyncMock(
            side_effect=[mock_result1, Exception("Network error")]
        )
        mock_crawler.__aenter__ = AsyncMock(return_value=mock_crawler)
        mock_crawler.__aexit__ = AsyncMock(return_value=None)
        mock_crawler_class.return_value = mock_crawler

        from agents.shared.crawler import ContentCrawler
        crawler = ContentCrawler()
        results = await crawler.extract_many([
            "https://example.com/1",
            "https://example.com/2"
        ])

        assert len(results) == 2
        assert results[0]["success"] is True
        assert results[1]["success"] is False
        assert "error" in results[1]
        assert "Network error" in results[1]["error"]


@pytest.mark.asyncio
async def test_extract_many_calls_progress_callback():
    """Test progress callback is called during multi-URL extraction."""
    mock_result = MagicMock()
    mock_result.success = True
    mock_result.markdown = MagicMock(fit_markdown="# Content")
    mock_result.metadata = {"title": "Test"}
    mock_result.links = {"internal": [], "external": []}

    progress_calls = []

    def on_progress(current, total):
        progress_calls.append((current, total))

    with patch("agents.shared.crawler.AsyncWebCrawler") as mock_crawler_class:
        mock_crawler = MagicMock()
        mock_crawler.arun = AsyncMock(return_value=mock_result)
        mock_crawler.__aenter__ = AsyncMock(return_value=mock_crawler)
        mock_crawler.__aexit__ = AsyncMock(return_value=None)
        mock_crawler_class.return_value = mock_crawler

        from agents.shared.crawler import ContentCrawler
        crawler = ContentCrawler()
        await crawler.extract_many(
            ["https://example.com/1", "https://example.com/2", "https://example.com/3"],
            on_progress=on_progress
        )

        assert progress_calls == [(1, 3), (2, 3), (3, 3)]


@pytest.mark.asyncio
async def test_extract_failed_crawl():
    """Test extraction returns success=False when crawl fails."""
    mock_result = MagicMock()
    mock_result.success = False
    mock_result.markdown = None
    mock_result.metadata = None
    mock_result.links = None

    with patch("agents.shared.crawler.AsyncWebCrawler") as mock_crawler_class:
        mock_crawler = MagicMock()
        mock_crawler.arun = AsyncMock(return_value=mock_result)
        mock_crawler.__aenter__ = AsyncMock(return_value=mock_crawler)
        mock_crawler.__aexit__ = AsyncMock(return_value=None)
        mock_crawler_class.return_value = mock_crawler

        from agents.shared.crawler import ContentCrawler
        crawler = ContentCrawler()
        result = await crawler.extract("https://example.com")

        assert result["success"] is False
        assert result["markdown"] == ""
        assert result["title"] == ""
