# tests/test_output.py
import json
import sys
from io import StringIO
from agents.shared.output import emit_json

def test_emit_json_prints_valid_json(capsys):
    data = {"success": True, "count": 42}
    emit_json(data)

    captured = capsys.readouterr()
    assert json.loads(captured.out.strip()) == data

def test_emit_json_handles_nested_data(capsys):
    data = {"results": [{"url": "https://example.com", "title": "Test"}]}
    emit_json(data)

    captured = capsys.readouterr()
    parsed = json.loads(captured.out.strip())
    assert parsed["results"][0]["url"] == "https://example.com"
