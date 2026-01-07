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
