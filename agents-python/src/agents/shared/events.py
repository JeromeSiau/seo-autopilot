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
