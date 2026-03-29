# src/agents/shared/llm.py
"""LLM client using OpenRouter API."""
import json
from typing import Any
from openai import AsyncOpenAI
from .config import config

DEFAULT_MODEL = "deepseek/deepseek-v3.2"

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
