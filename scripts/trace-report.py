#!/usr/bin/env python3
# SPDX-License-Identifier: AGPL-3.0-or-later
"""
trace-report.py — OTLP NDJSON Parser + Report-Generator

Parst traces.json (OTel Collector File-Export), filtert nach test.run_id,
gruppiert nach test.case_id und gibt eine Layer-Aufschluesselung aus.
Optional integriert PerfSchema-Daten.
"""

import argparse
import json
import os
import sys
from collections import defaultdict
from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Optional


@dataclass
class Span:
    trace_id: str
    span_id: str
    parent_span_id: Optional[str]
    name: str
    start_ns: int
    end_ns: int
    duration_ms: float
    service_name: str
    scope: str
    attributes: dict = field(default_factory=dict)
    children: list = field(default_factory=list)


def _extract_attrs(attr_list: list) -> dict:
    result = {}
    for a in attr_list:
        val = a.get("value", {})
        result[a["key"]] = (
            val.get("stringValue")
            or val.get("intValue")
            or val.get("doubleValue")
            or val.get("boolValue")
            or str(val)
        )
    return result


def parse_traces(traces_path: str, run_id: str) -> list:
    spans = []
    with open(traces_path) as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            data = json.loads(line)
            for rs in data.get("resourceSpans", []):
                res_attrs = _extract_attrs(
                    rs.get("resource", {}).get("attributes", [])
                )
                svc = res_attrs.get("service.name", "unknown")
                for ss in rs.get("scopeSpans", []):
                    scope = ss.get("scope", {}).get("name", "unknown")
                    for s in ss.get("spans", []):
                        attrs = _extract_attrs(s.get("attributes", []))
                        if attrs.get("test.run_id") != run_id:
                            continue
                        start = int(s["startTimeUnixNano"])
                        end = int(s["endTimeUnixNano"])
                        spans.append(Span(
                            trace_id=s["traceId"],
                            span_id=s["spanId"],
                            parent_span_id=s.get("parentSpanId") or None,
                            name=s["name"],
                            start_ns=start,
                            end_ns=end,
                            duration_ms=round((end - start) / 1_000_000, 2),
                            service_name=svc,
                            scope=scope,
                            attributes=attrs,
                        ))
    return spans


def parse_browser_spans(traces_path: str, time_min: int, time_max: int,
                        trace_ids: set = None) -> list:
    """Parse Boomerang spans via trace_id correlation (Server-Timing bridge)
    with temporal correlation as fallback."""
    spans = []
    with open(traces_path) as f:
        for line in f:
            line = line.strip()
            if not line:
                continue
            data = json.loads(line)
            for rs in data.get("resourceSpans", []):
                res_attrs = _extract_attrs(
                    rs.get("resource", {}).get("attributes", [])
                )
                svc = res_attrs.get("service.name", "unknown")
                if svc != "webtrees-browser":
                    continue
                for ss in rs.get("scopeSpans", []):
                    scope = ss.get("scope", {}).get("name", "unknown")
                    for s in ss.get("spans", []):
                        start = int(s["startTimeUnixNano"])
                        end = int(s["endTimeUnixNano"])
                        tid = s.get("traceId", "")
                        # trace_id-basierte Korrelation (Server-Timing-Bruecke)
                        matched_by_trace = trace_ids and tid in trace_ids
                        # Temporale Korrelation als Fallback
                        matched_by_time = (
                            start >= time_min and end <= time_max
                        )
                        if matched_by_trace or matched_by_time:
                            attrs = _extract_attrs(s.get("attributes", []))
                            spans.append(Span(
                                trace_id=tid,
                                span_id=s["spanId"],
                                parent_span_id=s.get("parentSpanId") or None,
                                name=s["name"],
                                start_ns=start,
                                end_ns=end,
                                duration_ms=round((end - start) / 1_000_000, 2),
                                service_name=svc,
                                scope=scope,
                                attributes=attrs,
                            ))
    return spans


def group_by_test_case(spans: list) -> dict:
    groups = defaultdict(list)
    for span in spans:
        case_id = span.attributes.get("test.case_id", "(unbekannt)")
        groups[case_id].append(span)
    return dict(groups)


def build_hierarchy(spans: list) -> list:
    by_id = {s.span_id: s for s in spans}
    roots = []
    for span in spans:
        if span.parent_span_id and span.parent_span_id in by_id:
            by_id[span.parent_span_id].children.append(span)
        else:
            roots.append(span)
    for span in spans:
        span.children.sort(key=lambda s: s.start_ns)
    roots.sort(key=lambda s: s.start_ns)
    return roots


def classify_span(span: Span) -> str:
    if span.service_name == "playwright-tests":
        return "Playwright (E2E)"
    if span.service_name == "webtrees-browser":
        return "Browser (RUM)"
    if span.scope and "pdo" in span.scope:
        return "DB Query"
    if span.scope and "psr15" in span.scope:
        return "PHP Backend"
    if span.scope and "otel-spans" in span.scope:
        return "webtrees Custom"
    if span.service_name == "webtrees":
        return "PHP"
    return "Unknown"


def print_span_tree(span: Span, indent: int = 2):
    layer = classify_span(span)
    prefix = " " * indent
    name_part = span.name
    if "webtrees.action" in span.attributes:
        name_part = f"webtrees.action: {span.attributes['webtrees.action']}"
    print(f"{prefix}+-- {layer}: {span.duration_ms}ms  [{name_part}]")
    for child in span.children:
        print_span_tree(child, indent + 4)


def load_perfschema(perfschema_dir: str) -> dict:
    data = {}
    for fname in ["statements_by_digest.json", "table_io_waits.json",
                   "stages_global.json", "transactions_global.json"]:
        fpath = os.path.join(perfschema_dir, fname)
        if os.path.exists(fpath):
            with open(fpath) as f:
                content = f.read().strip()
                if content:
                    data[fname.replace(".json", "")] = json.loads(content)
    return data


def print_perfschema(perfschema: dict):
    print("\n--- Performance Schema (Testlauf-Aggregat) ---")

    stmts = perfschema.get("statements_by_digest")
    if stmts:
        print("Top SQL by Latenz:")
        for i, s in enumerate(stmts[:10], 1):
            digest = s.get("digest_text", "?")[:80]
            print(f"  {i}. {digest}  "
                  f"avg={s.get('avg_ms', '?')}ms  "
                  f"calls={s.get('count', '?')}  "
                  f"rows={s.get('rows_examined', '?')}")

    io_waits = perfschema.get("table_io_waits")
    if io_waits:
        print("\nTable I/O:")
        for t in io_waits[:5]:
            print(f"  {t.get('table_name', '?')}:  "
                  f"reads={t.get('count_read', '?')}  "
                  f"writes={t.get('count_write', '?')}  "
                  f"total_wait={t.get('total_wait_ms', '?')}ms")

    # Warnungen
    warnings = []
    if stmts:
        full_scans = sum(1 for s in stmts if s.get("full_scans", 0) > 0)
        no_index = sum(1 for s in stmts if s.get("no_index", 0) > 0)
        tmp_disk = sum(1 for s in stmts if s.get("tmp_disk_tables", 0) > 0)
        if full_scans > 0:
            warnings.append(f"Full Table Scans: {full_scans} Queries")
        if no_index > 0:
            warnings.append(f"No-Index Queries: {no_index} Queries")
        if tmp_disk > 0:
            warnings.append(f"Temp-Tabellen auf Disk: {tmp_disk} Queries")

    print(f"\nWarnungen: {'; '.join(warnings) if warnings else 'keine'}")


def generate_json_report(run_id: str, spans: list, browser_spans: list,
                         perfschema: dict) -> dict:
    cases = group_by_test_case(spans)
    playwright_spans = [s for s in spans if s.service_name == "playwright-tests"]
    trace_ids = {s.trace_id for s in spans}
    report = {
        "run_id": run_id,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "total_spans": len(spans),
        "playwright_root_spans": len(playwright_spans),
        "browser_spans": len(browser_spans),
        "browser_spans_trace_linked": sum(
            1 for bs in browser_spans if bs.trace_id in trace_ids
        ),
        "test_cases": {},
        "perfschema": perfschema,
    }
    for case_id, case_spans in cases.items():
        report["test_cases"][case_id] = {
            "span_count": len(case_spans),
            "spans": [
                {
                    "name": s.name,
                    "duration_ms": s.duration_ms,
                    "layer": classify_span(s),
                    "attributes": s.attributes,
                }
                for s in sorted(case_spans, key=lambda x: x.start_ns)
            ],
        }
    return report


def main():
    parser = argparse.ArgumentParser(description="OTel Trace Report Generator")
    parser.add_argument("--run-id", required=True, help="Test Run ID (UUID)")
    parser.add_argument("--traces-file", default="artifacts/traces.json",
                        help="Path to OTLP NDJSON traces file")
    parser.add_argument("--perfschema-dir",
                        help="Path to PerfSchema JSON directory")
    parser.add_argument("--output-json", help="Output JSON report path")
    parser.add_argument("--layer", choices=["3", "4", "5"],
                        help="Layer number (determines PerfSchema path)")
    args = parser.parse_args()

    # Determine PerfSchema directory
    perfschema_dir = args.perfschema_dir
    if not perfschema_dir and args.layer:
        perfschema_dir = f"artifacts/layer{args.layer}/perfschema"

    if not os.path.exists(args.traces_file):
        print(f"Traces-Datei nicht gefunden: {args.traces_file}", file=sys.stderr)
        print("Ueberspringe Report-Generierung.", file=sys.stderr)
        sys.exit(0)

    # Parse spans
    spans = parse_traces(args.traces_file, args.run_id)
    print(f"=== Testlauf: {args.run_id[:8]} ({datetime.now(timezone.utc).isoformat()}) ===")
    print(f"Gefundene Spans: {len(spans)}")

    if not spans:
        print("Keine Spans fuer diese Run-ID gefunden.")
        if args.output_json:
            with open(args.output_json, "w") as f:
                json.dump({"run_id": args.run_id, "total_spans": 0}, f, indent=2)
        return

    # Playwright root-spans
    playwright_spans = [s for s in spans if s.service_name == "playwright-tests"]
    if playwright_spans:
        print(f"Playwright-Root-Spans: {len(playwright_spans)}")
        for ps in playwright_spans:
            print(f"  test: {ps.attributes.get('test.case_id', '?')}  "
                  f"trace_id={ps.trace_id[:8]}...")

    # trace_ids from all matched spans (for trace_id-based browser correlation)
    trace_ids = {s.trace_id for s in spans}

    # Temporal bounds for browser span correlation (fallback)
    time_min = min(s.start_ns for s in spans)
    time_max = max(s.end_ns for s in spans)
    # Add 5s buffer
    browser_spans = parse_browser_spans(
        args.traces_file,
        time_min - 5_000_000_000,
        time_max + 5_000_000_000,
        trace_ids=trace_ids,
    )
    if browser_spans:
        trace_linked = sum(1 for bs in browser_spans if bs.trace_id in trace_ids)
        print(f"Browser-Spans: {len(browser_spans)} "
              f"(trace-korreliert: {trace_linked}, "
              f"temporal: {len(browser_spans) - trace_linked})")

    # Group by test case and display
    cases = group_by_test_case(spans)
    for case_id, case_spans in sorted(cases.items()):
        print(f"\nTestfall: {case_id}")
        roots = build_hierarchy(case_spans + [
            bs for bs in browser_spans
            if any(bs.start_ns >= s.start_ns - 1_000_000_000
                   and bs.end_ns <= s.end_ns + 1_000_000_000
                   for s in case_spans)
        ])
        for root in roots:
            layer = classify_span(root)
            print(f"  {layer}: {root.duration_ms}ms  [{root.name}]")
            for child in root.children:
                print_span_tree(child, indent=4)

    # PerfSchema
    perfschema = {}
    if perfschema_dir and os.path.exists(perfschema_dir):
        perfschema = load_perfschema(perfschema_dir)
        print_perfschema(perfschema)
    elif perfschema_dir:
        print(f"\nPerfSchema-Verzeichnis nicht gefunden: {perfschema_dir}")

    # JSON output
    if args.output_json:
        report = generate_json_report(args.run_id, spans, browser_spans, perfschema)
        os.makedirs(os.path.dirname(args.output_json) or ".", exist_ok=True)
        with open(args.output_json, "w") as f:
            json.dump(report, f, indent=2)
        print(f"\nJSON-Report: {args.output_json}")


if __name__ == "__main__":
    main()
