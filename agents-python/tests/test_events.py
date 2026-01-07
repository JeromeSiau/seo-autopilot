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
