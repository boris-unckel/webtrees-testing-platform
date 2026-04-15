<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Abdeckungs-Zusammenfassung — Snapshot 2026-04-11

> **Scope:** Frozener Snapshot der Abdeckungs-Zusammenfassungs-Zahlen aus
> [`tds_coverage_ref.md`](../tds_coverage_ref.md) mit Stand 2026-04-11 (Testing-Platform
> `main`, Commit `698479661f`). Dieses Dokument wird **nicht** fortgeschrieben — spätere
> Neuerhebungen entstehen als neuer, datierter Snapshot unter `docs/coverage-runs/`.
>
> **Zweck:** Die Zusammenfassungs-Zahlen (`165 abgedeckt / 5 nicht abgedeckt / 170 Features
> gesamt`) werden im Hauptdokument nur noch als Verweis auf diesen Snapshot ausgewiesen —
> zusammen mit der jeweils jüngsten Gap-Analyse. Das entlastet die Abdeckungsmatrix von
> historischen Zahlen-Ständen und hält den laufenden Stand neben der frozen Referenz lesbar
> parallel.
>
> **Plan-Bezug:** Plan-Phase 2.2 des Coverage-Doc-Improvement-Plans (abgeschlossen, Plandokument archiviert).

---

## Kopfzeile (ursprünglich Zeile 13 in `tds_coverage_ref.md`)

**Aktueller Stand:** 165 abgedeckt (164 spezifikationsbasiert + 1 strukturbasiert), 5 nicht abgedeckt / SKIP (davon 1 deprecated).

## Zusammenfassungstabelle (ursprünglich Zeilen 233–240 in `tds_coverage_ref.md`)

| Status | G (G01–G30) | S (S01–S53) | P (P01–P41) | SEC (inkl. UTL01) | E (E01–E08) | A (A01–A11) | K (K01–K02) | U (U01–U02) | Gesamt |
|---|---|---|---|---|---|---|---|---|---|
| **Abgedeckt** (spezifikationsbasiert) | 28 (G01–G26, G28–G30) | 50 (S01–S50, S52) | 41 (P01–P41) | 26 (SEC-UTL01 inkl.) | 8 (E01–E08) | 10 (A01–A07, A09–A11) | 0 | 1 (U01) | **164** |
| Davon mit Einschränkung (Upstream-Bug) | 1 (G16) | 0 | 0 | 1 (SEC-C03) | — | — | — | — | **2** |
| Deployment-Empfehlung | 0 | 0 | 0 | 1 (SEC-HDR04) | — | — | — | — | **1** |
| **Abgedeckt** (strukturbasiert, CRAP-Analyse, niedrigere Qualitätsstufe) | 1 (G27) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | **1** |
| **Nicht abgedeckt / SKIP** | 0 | 1 (S53) | 0 | 0 | 0 | 1 (A08) | 2 | 1 (U02 deprecated) | **5** |
| **Gesamt** | **29** | **51** | **41** | **26** | **8** | **11** | **2** | **2** | **170** |

---

## Anschlussverweise

- Nachfolge-Erhebung gegen den Fork-Branch `port-layer2-test-doubles`:
  [`2026-04-11_gap-analyse-fork.md`](2026-04-11_gap-analyse-fork.md) — enthält die aktuelle
  L2/L3/L4-Klassifikation nach der Hybrid-Heuristik V2.
- Historischer Gap-Analyse-Befund (2026-03-26, ~95 % Stub-Quote):
  [`historical/2026-03-26_gap-analyse.md`](historical/2026-03-26_gap-analyse.md).
- Historische E2E-Gap-Analyse (2026-03-27):
  [`historical/2026-03-27_e2e-gap.md`](historical/2026-03-27_e2e-gap.md).
