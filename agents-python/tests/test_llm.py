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
