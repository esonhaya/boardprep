#!/usr/bin/env python3
"""Verify Batch 705 study-PDF hashes, searchable text, source attribution, and order."""
import hashlib
import json
import re
import sys
from pathlib import Path

try:
    from pypdf import PdfReader
except ImportError as exc:
    raise SystemExit(f"DEPENDENCY_MISSING: install the project test environment's pypdf package: {exc}")

ROOT = Path(__file__).resolve().parents[2]
manifest = json.loads((ROOT / "public/study-resources/manifest.json").read_text())
coverage_before = json.loads((ROOT / "docs/reports/batch-705-nursing-coverage-before.json").read_text())
lessons = json.loads((ROOT / "storage/study-lessons.json").read_text())
sources = json.loads((ROOT / "storage/sources.json").read_text())
old_assets = coverage_before["pdf_manifest_before"]
lesson_by_id = {lesson["id"]: lesson for lesson in lessons if lesson.get("id")}
source_by_id = {source["id"]: source for source in sources if source.get("id")}
expected_regenerated = {
    "nursing-community-risk-and-prevention",
    "nursing-medication-safety-and-calculation",
    "nursing-nutrition-and-sensory-assessment",
    "nursing-oxygenation-fluid-immunity-assessment",
    "nursing-therapeutic-communication-and-crisis",
}

def normalize(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip()

errors = []
entries = manifest.get("files", [])
new_by_id = {entry.get("lesson_id"): entry for entry in entries}
if len(entries) != 59 or len(new_by_id) != 59:
    errors.append(f"expected 59 unique manifest entries; got {len(entries)} entries/{len(new_by_id)} ids")
if set(new_by_id) != set(old_assets):
    errors.append("manifest lesson identities differ from B704")
changed = set()
searchable = 0
ordered_headings = 0
source_titles = 0
for lesson_id, entry in new_by_id.items():
    old = old_assets.get(lesson_id)
    if old is None:
        errors.append(f"new unverified identity: {lesson_id}")
        continue
    if entry.get("path") != old.get("path"):
        errors.append(f"path changed: {lesson_id}")
    pdf_path = ROOT / entry.get("path", "")
    if not pdf_path.is_file():
        errors.append(f"missing asset: {lesson_id}")
        continue
    actual_sha = hashlib.sha256(pdf_path.read_bytes()).hexdigest()
    if actual_sha != entry.get("sha256"):
        errors.append(f"manifest SHA mismatch: {lesson_id}")
    if actual_sha != old.get("sha256"):
        changed.add(lesson_id)
    lesson = lesson_by_id.get(lesson_id)
    if lesson is None:
        errors.append(f"published lesson missing: {lesson_id}")
        continue
    text = normalize("\n".join(page.extract_text() or "" for page in PdfReader(str(pdf_path)).pages))
    if not text:
        errors.append(f"PDF has no searchable text: {lesson_id}")
        continue
    searchable += 1
    cursor = 0
    for section in lesson.get("sections", []):
        heading = normalize(str(section.get("heading", "")))
        position = text.find(heading, cursor)
        if not heading or position < 0:
            errors.append(f"section heading missing/out of order: {lesson_id}/{section.get('id')}")
        else:
            cursor = position + len(heading)
            ordered_headings += 1
        for source_id in section.get("source_ids", []):
            title = normalize(str(source_by_id.get(str(source_id), {}).get("title", "")))
            if title and title in text:
                source_titles += 1
            else:
                errors.append(f"section source title absent from PDF: {lesson_id}/{section.get('id')}/{source_id}")
    lesson_source_ids = list(lesson.get("official_scope_source_ids", [])) + list(lesson.get("instructional_source_ids", []))
    for source_id in lesson_source_ids:
        title = normalize(str(source_by_id.get(str(source_id), {}).get("title", "")))
        if title and title in text:
            source_titles += 1
        else:
            errors.append(f"lesson source title absent from PDF: {lesson_id}/{source_id}")
if changed != expected_regenerated:
    errors.append(f"regenerated PDF set differs: {sorted(changed)}")

print(json.dumps({
    "manifest_entries": len(entries),
    "previous_asset_identities_retained": len(set(new_by_id) & set(old_assets)),
    "regenerated_pdf_ids": sorted(changed),
    "unchanged_pdf_assets": len(entries) - len(changed),
    "searchable_text_pdfs": searchable,
    "section_headings_in_order": ordered_headings,
    "source_attributions_checked": source_titles,
    "errors": errors,
}, indent=2))
if errors:
    sys.exit(1)
