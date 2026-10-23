#!/usr/bin/env python3
"""Refresh a bounded set of public source URL checks for the developer dashboard."""

from __future__ import annotations

import argparse
import hashlib
import ipaddress
import json
import socket
import sys
import threading
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener

ROOT = Path(__file__).resolve().parents[1]
SOURCES_PATH = ROOT / "storage" / "sources.json"
QUESTIONS_PATH = ROOT / "storage" / "questions.json"
LINKS_PATH = ROOT / "storage" / "study-question-links.json"
MATERIALS_PATH = ROOT / "storage" / "study-materials.json"
SNAPSHOT_PATH = ROOT / "storage" / "source-url-health.json"
SCHEMA = "boardprep.source-url-health.v1"
MAX_AGE_DAYS = 30
socket.setdefaulttimeout(6)


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def read_json(path: Path):
    with path.open(encoding="utf-8") as stream:
        return json.load(stream)


def canonical_json(value) -> bytes:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":")).encode("utf-8")


def registry_fingerprint(sources) -> str:
    rows = [
        {"id": str(source.get("id", "")), "url": str(source["url"])}
        for source in sources
        if isinstance(source, dict) and isinstance(source.get("url"), str) and source["url"].strip()
    ]
    rows.sort(key=lambda row: (row["id"], row["url"]))
    return hashlib.sha256(canonical_json(rows)).hexdigest()


def payload_digest(snapshot) -> str:
    payload = {key: value for key, value in snapshot.items() if key != "payload_sha256"}
    return hashlib.sha256(canonical_json(payload)).hexdigest()


def validate_public_url(url: str) -> str:
    parsed = urlsplit(url)
    if parsed.scheme not in {"http", "https"} or not parsed.hostname or parsed.username or parsed.password:
        raise ValueError("URL must be public HTTP(S) without embedded credentials")
    host = parsed.hostname.rstrip(".").lower()
    try:
        literal = ipaddress.ip_address(host)
        addresses = [literal]
    except ValueError:
        addresses = []
        for result in socket.getaddrinfo(host, parsed.port or (443 if parsed.scheme == "https" else 80), type=socket.SOCK_STREAM):
            addresses.append(ipaddress.ip_address(result[4][0].split("%", 1)[0]))
    if not addresses or any(not address.is_global for address in addresses):
        raise ValueError("URL host did not resolve exclusively to public IP addresses")
    return host


def response_for(url: str, method: str):
    opener = build_opener(NoRedirect())
    request = Request(url, method=method, headers={"User-Agent": "BoardPrepSourceAudit/1.0", "Range": "bytes=0-1023"} if method == "GET" else {"User-Agent": "BoardPrepSourceAudit/1.0"})
    try:
        response = opener.open(request, timeout=6)
        try:
            if method == "GET":
                response.read(1024)
            return response.status, response.headers
        finally:
            response.close()
    except HTTPError as error:
        try:
            if method == "GET":
                error.read(1024)
            return error.code, error.headers
        finally:
            error.close()


def request_bounded(url: str, method: str):
    current = url
    for _ in range(6):
        host = validate_public_url(current)
        with host_lock(host):
            status, headers = response_for(current, method)
        if status in {301, 302, 303, 307, 308}:
            location = headers.get("Location") if headers else None
            if not location:
                return status, current, "REDIRECT_LIMIT"
            current = urljoin(current, location)
            validate_public_url(current)
            continue
        return status, current, None
    return 0, current, "REDIRECT_LIMIT"


_host_locks = {}
_host_locks_guard = threading.Lock()


def host_lock(host: str):
    with _host_locks_guard:
        return _host_locks.setdefault(host, threading.Lock())


def classify(source_id: str, url: str, affected_ids: list[str], evidence_ref: str):
    now = datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")
    result = {"source_id": source_id, "url": url, "status": "REQUEST_ERROR", "checked_at_utc": now, "http_status": None, "final_url": url, "affected_question_ids": sorted(set(affected_ids)), "evidence_ref": evidence_ref}
    try:
        status, final_url, error = request_bounded(url, "HEAD")
        if error:
            result["status"] = error
        elif status in {403, 401, 404, 410, 405, 501}:
            get_status, get_final, get_error = request_bounded(final_url, "GET")
            result["http_status"] = get_status
            result["final_url"] = get_final
            if get_error:
                result["status"] = get_error
            elif status in {404, 410} and get_status in {404, 410}:
                result["status"] = "CONFIRMED_404"
            elif status in {401, 403} or get_status in {401, 403, 429}:
                result["status"] = "ACCESS_RESTRICTED"
            elif status in {404, 410}:
                result["status"] = "HTTP_OTHER"
            elif 200 <= get_status < 300:
                result["status"] = "HTTP_REACHABLE"
            else:
                result["status"] = "HTTP_OTHER"
        elif 200 <= status < 300:
            result["status"] = "HTTP_REACHABLE"
            result["http_status"] = status
            result["final_url"] = final_url
        else:
            result["status"] = "ACCESS_RESTRICTED" if status in {401, 403, 429} else "HTTP_OTHER"
            result["http_status"] = status
            result["final_url"] = final_url
    except socket.gaierror as error:
        result["status"] = "DNS_ERROR"
        result["error"] = str(error)[:240]
    except URLError as error:
        result["status"] = "DNS_ERROR" if isinstance(error.reason, socket.gaierror) else "REQUEST_ERROR"
        result["error"] = str(error.reason)[:240]
    except (ValueError, TimeoutError, OSError) as error:
        result["status"] = "UNSAFE_URL" if isinstance(error, ValueError) else "REQUEST_ERROR"
        result["error"] = str(error)[:240]
    return result


def affected_questions(questions, links, materials):
    ids_by_source = {}
    for question in questions:
        if not isinstance(question, dict):
            continue
        question_id = str(question.get("id", ""))
        for source_id in question.get("source_ids", []) or []:
            ids_by_source.setdefault(str(source_id), set()).add(question_id)
    material_sources = {
        str(material.get("id", "")): set(map(str, material.get("source_ids", []) or []))
        for material in materials if isinstance(material, dict)
    }
    for link in links:
        if not isinstance(link, dict):
            continue
        question_id = str(link.get("question_id", ""))
        for source_id in link.get("source_ids", []) or []:
            ids_by_source.setdefault(str(source_id), set()).add(question_id)
        for source_id in material_sources.get(str(link.get("material_id", "")), set()):
            ids_by_source.setdefault(source_id, set()).add(question_id)
    return ids_by_source


def write_snapshot(sources, refreshed):
    source_by_id = {str(source.get("id")): source for source in sources if isinstance(source, dict)}
    current = []
    if SNAPSHOT_PATH.exists():
        previous_rows = read_json(SNAPSHOT_PATH)
        if not isinstance(previous_rows, list):
            raise ValueError("Existing source URL snapshot is not a JSON list; refusing to overwrite it")
        if len(previous_rows) > 1:
            raise ValueError("Existing source URL snapshot has multiple rows; refusing to overwrite ambiguous evidence")
        previous = previous_rows[0] if previous_rows else None
        if previous is not None:
            if not isinstance(previous, dict) or previous.get("id") != "latest" or previous.get("schema_version") != SCHEMA:
                raise ValueError("Existing source URL snapshot has an unsupported shape; refusing to overwrite it")
            if previous.get("payload_sha256") != payload_digest(previous):
                raise ValueError("Existing source URL snapshot checksum is invalid; refusing to overwrite evidence")
            if not isinstance(previous.get("records"), list):
                raise ValueError("Existing source URL snapshot has no records list; refusing to overwrite it")
            current = [row for row in previous["records"] if isinstance(row, dict)]
    by_id = {str(row.get("source_id")): row for row in current}
    for row in refreshed:
        by_id[row["source_id"]] = row
    records = []
    for source_id, stored in by_id.items():
        if source_id not in source_by_id or stored.get("url") != source_by_id[source_id].get("url"):
            continue
        row = dict(stored)
        # Question drill-downs are derived live by the dashboard. Keep only
        # bounded HTTP evidence in this persisted snapshot; repeated legacy
        # report paths and identical final URLs are derivable from the source
        # registry and the persisted snapshot itself.
        row.pop("affected_question_ids", None)
        row.pop("evidence_ref", None)
        if row.get("final_url") == row.get("url"):
            row.pop("final_url", None)
        records.append(row)
    records.sort(key=lambda row: str(row.get("source_id", "")))
    total = sum(1 for source in sources if isinstance(source, dict) and isinstance(source.get("url"), str) and source["url"].strip())
    snapshot = {
        "id": "latest",
        "schema_version": SCHEMA,
        "generated_at_utc": datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
        "registry_fingerprint": registry_fingerprint(sources),
        "coverage": {
            "complete": len(records) == total,
            "checked_source_count": len(records),
            "total_url_source_count": total,
            "scope": "Explicitly selected source IDs plus preserved recent per-source audit evidence; unchecked records have unknown reachability.",
        },
        "records": records,
    }
    snapshot["payload_sha256"] = payload_digest(snapshot)
    temp = SNAPSHOT_PATH.with_name(SNAPSHOT_PATH.name + f".{threading.get_ident()}.tmp")
    temp.write_text(json.dumps([snapshot], ensure_ascii=False, sort_keys=True, separators=(",", ":")) + "\n", encoding="utf-8")
    temp.replace(SNAPSHOT_PATH)
    return snapshot


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source-id", action="append", required=True, help="Registered source ID to check; repeat for a bounded set (maximum 50).")
    parser.add_argument("--write", action="store_true", help="Merge results into storage/source-url-health.json for the Developer dashboard.")
    args = parser.parse_args()
    source_ids = list(dict.fromkeys(args.source_id))
    if len(source_ids) > 50:
        parser.error("At most 50 source IDs can be checked in one run.")
    sources = read_json(SOURCES_PATH)
    source_by_id = {str(source.get("id")): source for source in sources if isinstance(source, dict)}
    missing = [source_id for source_id in source_ids if source_id not in source_by_id]
    if missing:
        parser.error("Unknown source IDs: " + ", ".join(missing))
    questions, links, materials = read_json(QUESTIONS_PATH), read_json(LINKS_PATH), read_json(MATERIALS_PATH)
    ids_by_source = affected_questions(questions, links, materials)
    evidence_ref = "bin/check-question-source-urls.py"
    work = [(source_id, str(source_by_id[source_id].get("url") or "")) for source_id in source_ids]
    if any(not url for _, url in work):
        parser.error("Selected source has no URL; use a source metadata review instead.")
    with ThreadPoolExecutor(max_workers=3) as pool:
        results = list(pool.map(lambda item: classify(item[0], item[1], list(ids_by_source.get(item[0], set())), evidence_ref), work))
    snapshot = write_snapshot(sources, results) if args.write else None
    print(json.dumps({
        "results": [{
            "source_id": row["source_id"], "url": row["url"], "status": row["status"],
            "http_status": row.get("http_status"), "final_url": row.get("final_url"),
            "affected_question_count": len(row.get("affected_question_ids", [])),
        } for row in results],
        "snapshot_status": "WRITTEN_PARTIAL" if snapshot else "NOT_WRITTEN",
        "snapshot_path": str(SNAPSHOT_PATH) if snapshot else None,
    }, ensure_ascii=False, indent=2))
    return 0 if all(row.get("status") == "HTTP_REACHABLE" for row in results) else 2


if __name__ == "__main__":
    raise SystemExit(main())
