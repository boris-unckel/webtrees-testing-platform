<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — S53: Legacy-URL-Weiterleitungen

**Referenz:** S53 | **Status:** 🚫 EXCLUDED — Teststufe 2 nicht anwendbar
**Übergreifende Konzepte:** → [testquality_improve_common2.md](testquality_improve_common2.md)

## Ausschlussgrund

Die ~27 Redirect*-Handler liefern ausschließlich HTTP 301/302-Antworten ohne Geschäftslogik. Das Testen von HTTP-Statuscodes und Location-Headers ist eine Stärke von Playwright (Layer 4) und ist in PHPUnit-Integrationstests (Layer 3) unverhältnismäßig aufwändig ohne nennenswerten Mehrwert.

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1–P5 | 🚫 | Teststufe 3 only |
