from __future__ import annotations

import importlib.util
from datetime import datetime, timezone
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "boardprep_source_url_checker",
    ROOT / "bin" / "check-question-source-urls.py",
)
assert SPEC is not None and SPEC.loader is not None
CHECKER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CHECKER)

sources = [
    {"id": "primary", "url": "https://example.org/act"},
    {"id": "alias", "url": "https://example.org/act"},
    {"id": "other", "url": "https://example.org/other"},
    {"id": "no-url", "url": ""},
]
groups = CHECKER.group_source_ids_by_url(sources)
assert groups == {
    "https://example.org/act": ["primary", "alias"],
    "https://example.org/other": ["other"],
}

now = datetime(2026, 10, 23, 12, 0, tzinfo=timezone.utc)
fresh = [{
    "source_id": "primary",
    "url": "https://example.org/act",
    "status": "HTTP_REACHABLE",
    "http_status": 200,
    "checked_at_utc": "2026-10-23T11:00:00Z",
}]
expanded = CHECKER.expand_current_url_evidence(sources, fresh, now)
by_id = {row["source_id"]: row for row in expanded}
assert by_id["alias"]["status"] == "HTTP_REACHABLE"
assert by_id["alias"]["http_status"] == 200
assert by_id["alias"]["checked_at_utc"] == by_id["primary"]["checked_at_utc"]
assert "other" not in by_id
assert CHECKER.snapshot_write_status({"coverage": {"complete": True}}) == "WRITTEN_COMPLETE"
assert CHECKER.snapshot_write_status({"coverage": {"complete": False}}) == "WRITTEN_PARTIAL"
assert CHECKER.snapshot_write_status(None) == "NOT_WRITTEN"

stale = [{
    "source_id": "primary",
    "url": "https://example.org/act",
    "status": "HTTP_REACHABLE",
    "http_status": 200,
    "checked_at_utc": "2026-09-01T11:00:00Z",
}]
stale_expanded = CHECKER.expand_current_url_evidence(sources, stale, now)
assert {row["source_id"] for row in stale_expanded} == {"primary"}

timezone_missing = [{
    "source_id": "primary",
    "url": "https://example.org/act",
    "status": "HTTP_REACHABLE",
    "http_status": 200,
    "checked_at_utc": "2026-10-23T11:00:00",
}]
naive_expanded = CHECKER.expand_current_url_evidence(sources, timezone_missing, now)
assert {row["source_id"] for row in naive_expanded} == {"primary"}

print("SOURCE_URL_CHECKER_URL_DEDUP=PASS")
print("SOURCE_URL_CHECKER_ALIAS_EVIDENCE=PASS")
print("SOURCE_URL_CHECKER_STALE_EVIDENCE_NOT_REUSED=PASS")
print("SOURCE_URL_CHECKER_SNAPSHOT_COMPLETENESS_LABEL=PASS")
print("SOURCE_URL_CHECKER_TIMEZONE_VALIDATION=PASS")
