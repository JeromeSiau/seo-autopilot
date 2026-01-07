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
    with pytest.raises(ValueError, match="OPENROUTER_API_KEY"):
        Config(require_all=True)
