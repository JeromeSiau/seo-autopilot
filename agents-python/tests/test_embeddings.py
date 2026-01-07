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
