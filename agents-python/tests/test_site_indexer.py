# tests/test_site_indexer.py
import pytest
from click.testing import CliRunner


def test_cli_requires_site_url():
    from agents.site_indexer.main import main
    runner = CliRunner()
    result = runner.invoke(main, ["--siteId", "123"])
    assert result.exit_code != 0
