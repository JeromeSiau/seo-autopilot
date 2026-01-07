"""Output utilities for agents."""
import json
import sys
from typing import Any


def emit_json(data: dict[str, Any]) -> None:
    """Print JSON to stdout for Laravel to parse.

    Laravel expects the last line of stdout to be valid JSON.
    """
    print(json.dumps(data, ensure_ascii=False, default=str))
    sys.stdout.flush()
