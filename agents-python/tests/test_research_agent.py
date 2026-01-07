# tests/test_research_agent.py
import pytest
from click.testing import CliRunner


def test_cli_requires_keyword():
    from agents.research.main import main
    runner = CliRunner()
    result = runner.invoke(main, ["--articleId", "123"])
    assert result.exit_code != 0
    assert "keyword" in result.output.lower() or "required" in result.output.lower()
