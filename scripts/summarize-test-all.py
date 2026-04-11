#!/usr/bin/env python3
# SPDX-License-Identifier: AGPL-3.0-or-later
"""
summarize-test-all.py — Aggregiert die Ergebnisse aller 5 Teststufen zu einer
kompakten JSON/TXT-Zusammenfassung unter `artifacts/summary/`.

Wird ausschliesslich am Ende von `make test-all` aufgerufen (Invariante I1).
Einzel-Layer-Targets (make test-unit, make test-static, ...) erzeugen
bewusst keine Summary.

Aufruf:
    python3 scripts/summarize-test-all.py [--artifacts-dir artifacts/]
"""

import argparse
import json
import sys
import xml.etree.ElementTree as ET
from pathlib import Path


def _status(condition: bool, is_fail: bool = False) -> str:
    if not condition:
        return "skipped"
    return "fail" if is_fail else "ok"


def _read_json(path: Path):
    if not path.is_file():
        return None
    try:
        with path.open(encoding="utf-8") as fh:
            return json.load(fh)
    except (json.JSONDecodeError, OSError):
        return None


def _read_xml(path: Path):
    if not path.is_file():
        return None
    try:
        return ET.parse(path).getroot()
    except (ET.ParseError, OSError):
        return None


def parse_layer1(base: Path) -> dict:
    phpstan = _read_json(base / "layer1" / "phpstan.json")
    phpcs = _read_json(base / "layer1" / "phpcs.json")
    trivy = _read_json(base / "layer1" / "trivy-report.json")

    if phpstan is None and phpcs is None and trivy is None:
        return {"status": "skipped", "phpstan_errors": 0, "phpcs_errors": 0, "trivy_findings": 0}

    phpstan_errors = 0
    if isinstance(phpstan, dict):
        phpstan_errors = int(phpstan.get("totals", {}).get("file_errors", 0) or 0)

    phpcs_errors = 0
    if isinstance(phpcs, dict):
        phpcs_errors = int(phpcs.get("totals", {}).get("errors", 0) or 0)
        phpcs_errors += int(phpcs.get("totals", {}).get("warnings", 0) or 0)

    trivy_findings = 0
    if isinstance(trivy, dict):
        for result in trivy.get("Results") or []:
            trivy_findings += len(result.get("Vulnerabilities") or [])
            trivy_findings += len(result.get("Misconfigurations") or [])
            trivy_findings += len(result.get("Secrets") or [])

    return {
        "status": "ok",
        "phpstan_errors": phpstan_errors,
        "phpcs_errors": phpcs_errors,
        "trivy_findings": trivy_findings,
    }


def _junit_totals(root) -> dict:
    """Liest Tests/Assertions/Failures/Errors/Time aus einer JUnit-XML.

    PHPUnit legt die Summen nicht auf `<testsuites>` ab, sondern auf dem
    ersten `<testsuite>`-Kind. Playwright dagegen setzt sie direkt auf
    `<testsuites>`. Dieses Helfer deckt beide Formen ab.
    """
    if root is None:
        return {"tests": 0, "assertions": 0, "failures": 0, "errors": 0, "time": 0.0}

    if root.tag not in ("testsuites", "testsuite"):
        return {"tests": 0, "assertions": 0, "failures": 0, "errors": 0, "time": 0.0}

    # Primaer das Root-Element nutzen. Falls `<testsuites>` keine Tests-Attribute
    # traegt (PHPUnit), auf das erste direkte `<testsuite>`-Kind zurueckfallen.
    target = root
    if root.tag == "testsuites" and "tests" not in root.attrib:
        first_child = root.find("testsuite")
        if first_child is not None:
            target = first_child

    def _int(attr: str) -> int:
        try:
            return int(target.attrib.get(attr, "0") or 0)
        except ValueError:
            return 0

    def _float(attr: str) -> float:
        try:
            return float(target.attrib.get(attr, "0") or 0)
        except ValueError:
            return 0.0

    return {
        "tests": _int("tests"),
        "assertions": _int("assertions"),
        "failures": _int("failures"),
        "errors": _int("errors"),
        "time": _float("time"),
    }


def _clover_coverage_pct(root) -> float:
    """Liest <metrics elements="X" coveredelements="Y"/> aus dem Clover-XML."""
    if root is None:
        return 0.0
    metrics = root.find(".//project/metrics")
    if metrics is None:
        metrics = root.find(".//metrics")
    if metrics is None:
        return 0.0
    try:
        elements = int(metrics.attrib.get("elements", "0") or 0)
        covered = int(metrics.attrib.get("coveredelements", "0") or 0)
    except ValueError:
        return 0.0
    if elements == 0:
        return 0.0
    return round(covered * 100.0 / elements, 2)


def parse_phpunit_layer(base: Path, junit_name: str) -> dict:
    junit = _read_xml(base / junit_name)
    clover = _read_xml(base / "coverage.xml")

    if junit is None:
        return {
            "status": "skipped",
            "tests": 0,
            "assertions": 0,
            "failures": 0,
            "coverage_pct": 0.0,
        }

    totals = _junit_totals(junit)
    is_fail = totals["failures"] > 0 or totals["errors"] > 0
    return {
        "status": _status(True, is_fail=is_fail),
        "tests": totals["tests"],
        "assertions": totals["assertions"],
        "failures": totals["failures"] + totals["errors"],
        "coverage_pct": _clover_coverage_pct(clover),
    }


def _playwright_count(results: dict) -> tuple:
    """Rekursiv alle specs aus dem Playwright-JSON zaehlen (tests, failed)."""
    tests = 0
    failed = 0

    def walk(node):
        nonlocal tests, failed
        if not isinstance(node, dict):
            return
        for spec in node.get("specs") or []:
            tests += 1
            if not spec.get("ok", False):
                failed += 1
        for sub in node.get("suites") or []:
            walk(sub)

    for suite in results.get("suites") or []:
        walk(suite)

    return tests, failed


def parse_layer4(base: Path) -> dict:
    results = _read_json(base / "layer4" / "playwright-results.json")
    if results is None:
        return {"status": "skipped", "tests": 0, "failures": 0}
    tests, failed = _playwright_count(results)
    return {
        "status": _status(True, is_fail=failed > 0),
        "tests": tests,
        "failures": failed,
    }


def parse_layer5(base: Path) -> dict:
    results = _read_json(base / "layer5" / "performance-results.json")
    if results is None:
        return {"status": "skipped", "tests": 0, "failures": 0, "p95_ms": 0.0}
    tests, failed = _playwright_count(results)

    # Zusaetzlich p95_ms aus den Perf-Output-Dateien ziehen (optional, falls vorhanden).
    # Unterstuetzte Schluessel (snake_case und camelCase), sowohl Skalare als auch Arrays:
    #   loadTimeMs / load_time_ms, durationMs / duration_ms, ms, value
    p95 = 0.0
    perf_files = sorted((base / "layer5").glob("perf-*.json"))
    values: list = []
    _keys = (
        "loadTimeMs", "load_time_ms",
        "durationMs", "duration_ms",
        "ms", "value",
    )
    for perf_file in perf_files:
        data = _read_json(perf_file)
        if not isinstance(data, dict):
            continue
        for key in _keys:
            raw = data.get(key)
            if isinstance(raw, list):
                for item in raw:
                    if isinstance(item, (int, float)):
                        values.append(float(item))
                break
            if isinstance(raw, (int, float)):
                values.append(float(raw))
                break
    if values:
        values.sort()
        idx = int(round(0.95 * (len(values) - 1)))
        p95 = round(values[idx], 1)

    return {
        "status": _status(True, is_fail=failed > 0),
        "tests": tests,
        "failures": failed,
        "p95_ms": p95,
    }


def _format_txt(summary: dict) -> str:
    lines = []
    lines.append("=== webtrees Test-Stack — Gesamt-Zusammenfassung ===")
    lines.append(f"Lauf: {summary.get('run_at', '—')}")
    lines.append(f"Gesamtdauer: {summary.get('duration_seconds', 0):.1f} s")
    lines.append("")
    lines.append(f"{'Layer':<10} {'Status':<8} {'Tests':>8} {'Fails':>7} {'Assertions':>12} {'Coverage':>10}")
    lines.append("-" * 60)
    layers = summary.get("layers", {})
    for key in ("layer1", "layer2", "layer3", "layer4", "layer5"):
        layer = layers.get(key, {})
        status = layer.get("status", "skipped")
        tests = layer.get("tests", 0)
        fails = layer.get("failures", 0)
        asserts = layer.get("assertions", "-")
        cov = layer.get("coverage_pct", "-")
        if key == "layer1":
            tests_col = f"{layer.get('phpstan_errors', 0)}/{layer.get('phpcs_errors', 0)}/{layer.get('trivy_findings', 0)}"
            lines.append(f"{key:<10} {status:<8} {tests_col:>8} {'-':>7} {'-':>12} {'-':>10}")
        elif key == "layer5":
            p95 = layer.get("p95_ms", 0)
            lines.append(f"{key:<10} {status:<8} {tests:>8} {fails:>7} {'-':>12} p95={p95}ms")
        else:
            cov_s = f"{cov}%" if isinstance(cov, (int, float)) else str(cov)
            asserts_s = str(asserts)
            lines.append(f"{key:<10} {status:<8} {tests:>8} {fails:>7} {asserts_s:>12} {cov_s:>10}")
    lines.append("")
    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--artifacts-dir",
        default="artifacts",
        help="Basisordner fuer Testartefakte (default: artifacts/)",
    )
    args = parser.parse_args()

    base = Path(args.artifacts_dir).resolve()
    if not base.is_dir():
        print(f"Fehler: {base} ist kein Verzeichnis.", file=sys.stderr)
        return 1

    from datetime import datetime, timezone

    summary = {
        "run_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "layers": {
            "layer1": parse_layer1(base),
            "layer2": parse_phpunit_layer(base / "layer2", "phpunit-unit.xml"),
            "layer3": parse_phpunit_layer(base / "layer3", "phpunit-integration.xml"),
            "layer4": parse_layer4(base),
            "layer5": parse_layer5(base),
        },
        "duration_seconds": 0.0,
    }

    # Gesamtdauer = Summe der L2/L3 Zeit-Felder (best effort; Playwright-Zeit
    # laesst sich ohne JUnit-XML nicht zuverlaessig ableiten).
    duration = 0.0
    for key, junit_name in (("layer2", "phpunit-unit.xml"), ("layer3", "phpunit-integration.xml")):
        junit = _read_xml(base / key / junit_name)
        if junit is not None:
            duration += _junit_totals(junit)["time"]
    summary["duration_seconds"] = round(duration, 2)

    out_dir = base / "summary"
    out_dir.mkdir(parents=True, exist_ok=True)

    json_path = out_dir / "test-all.json"
    with json_path.open("w", encoding="utf-8") as fh:
        json.dump(summary, fh, indent=2, ensure_ascii=False)
        fh.write("\n")

    txt_path = out_dir / "test-all.txt"
    txt_content = _format_txt(summary)
    with txt_path.open("w", encoding="utf-8") as fh:
        fh.write(txt_content)

    # stdout: Kurz-Zusammenfassung (<20 Zeilen)
    print(txt_content, end="")
    print(f"Summary-JSON: {json_path}")
    print(f"Summary-TXT:  {txt_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
