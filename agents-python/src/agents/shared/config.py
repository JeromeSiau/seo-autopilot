"""Configuration management for agents."""
import os
from dataclasses import dataclass
from dotenv import load_dotenv

# Load .env from project root
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), "../../../../.env"))

@dataclass
class Config:
    """Agent configuration loaded from environment variables."""

    openrouter_api_key: str = ""
    openrouter_base_url: str = "https://openrouter.ai/api/v1"
    voyage_api_key: str = ""
    redis_host: str = "localhost"
    redis_port: int = 6379
    redis_db: int = 0

    def __init__(self, require_all: bool = False):
        self.openrouter_api_key = os.getenv("OPENROUTER_API_KEY", "")
        self.openrouter_base_url = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")
        self.voyage_api_key = os.getenv("VOYAGE_API_KEY", "")
        self.redis_host = os.getenv("REDIS_HOST", "localhost")
        self.redis_port = int(os.getenv("REDIS_PORT", "6379"))
        self.redis_db = int(os.getenv("REDIS_DB", "0"))

        if require_all:
            self._validate()

    def _validate(self):
        if not self.openrouter_api_key:
            raise ValueError("OPENROUTER_API_KEY environment variable is required")
        if not self.voyage_api_key:
            raise ValueError("VOYAGE_API_KEY environment variable is required")

# Global config instance
config = Config()
