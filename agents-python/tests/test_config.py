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

def test_config_raises_on_missing_required(monkeypatch):
    monkeypatch.delenv("OPENROUTER_API_KEY", raising=False)
    monkeypatch.delenv("VOYAGE_API_KEY", raising=False)
    with pytest.raises(ValueError, match="Missing required environment variables"):
        Config(require_all=True)


def test_config_reports_all_missing_keys_together(monkeypatch):
    monkeypatch.delenv("OPENROUTER_API_KEY", raising=False)
    monkeypatch.delenv("VOYAGE_API_KEY", raising=False)
    with pytest.raises(ValueError) as exc_info:
        Config(require_all=True)
    error_message = str(exc_info.value)
    assert "OPENROUTER_API_KEY" in error_message
    assert "VOYAGE_API_KEY" in error_message


def test_config_invalid_redis_port_raises_value_error(monkeypatch):
    monkeypatch.setenv("REDIS_PORT", "not-a-number")
    with pytest.raises(ValueError, match="REDIS_PORT must be a valid integer"):
        Config()


def test_config_invalid_redis_db_raises_value_error(monkeypatch):
    monkeypatch.setenv("REDIS_DB", "invalid")
    with pytest.raises(ValueError, match="REDIS_DB must be a valid integer"):
        Config()
