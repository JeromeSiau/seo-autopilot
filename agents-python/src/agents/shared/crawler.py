# src/agents/shared/crawler.py
"""Content crawler using Crawl4AI."""
from typing import Callable

from crawl4ai import AsyncWebCrawler, BrowserConfig, CrawlerRunConfig
from crawl4ai.content_filter_strategy import PruningContentFilter
from crawl4ai.markdown_generation_strategy import DefaultMarkdownGenerator


class ContentCrawler:
    """Wrapper around Crawl4AI for LLM-optimized content extraction.

    This crawler extracts clean markdown from web pages, optimized for LLM
    consumption. It uses PruningContentFilter to remove boilerplate content
    (navigation, footer, ads) and returns the cleaned/filtered markdown.
    """

    def __init__(self, headless: bool = True):
        """Initialize the content crawler.

        Args:
            headless: Whether to run the browser in headless mode.
        """
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

        Args:
            url: The URL to extract content from.

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
        on_progress: Callable[[int, int], None] | None = None,
    ) -> list[dict]:
        """Extract content from multiple URLs.

        Uses a single browser session for efficiency when crawling multiple
        URLs. Handles individual URL failures gracefully without stopping
        the entire batch.

        Args:
            urls: List of URLs to extract content from.
            on_progress: Optional callback(current, total) for progress updates.

        Returns:
            List of extraction results, one per URL.
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
