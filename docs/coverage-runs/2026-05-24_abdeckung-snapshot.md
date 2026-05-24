<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Abdeckungs-Zusammenfassung — Snapshot 2026-05-24

> **Scope:** Frozener Snapshot der Abdeckungs-Zusammenfassungs-Zahlen aus
> [`tds_coverage_ref.md`](../tds_coverage_ref.md) mit Stand 2026-05-24
> (Testing-Platform `main`-Commit zum Erhebungs-Zeitpunkt, upstream/webtrees
> `d123a1b789e29872d6736ece1d9d47cb0a038e8c` vom 2026-05-17). Dieses Dokument
> wird **nicht** fortgeschrieben — spätere Neuerhebungen entstehen als neuer,
> datierter Snapshot in diesem Verzeichnis.
>
> **Zweck:** Wer in welchem Layer abgedeckt ist, pro Feature-Matrix-ID. Die
> Zusammenfassungs-Zahlen (`216 abgedeckt / 2 nicht abgedeckt / 1 SKIP / 219
> Features gesamt`) werden im Hauptdokument nur noch als Verweis auf diesen
> Snapshot ausgewiesen — zusammen mit der jeweils jüngsten Gap-Analyse. Das
> entlastet die Abdeckungsmatrix von historischen Zahlen-Ständen und hält den
> laufenden Stand neben der frozen Referenz lesbar parallel.
>
> **Geschwister-Reports (gleicher Erhebungstag):**
>
> - [`2026-05-24_gap-analyse.md`](2026-05-24_gap-analyse.md) — Inventar pro
>   Layer mit Hybrid-V2-Klassifikation, Feature-ID-Abdeckung.
> - [`2026-05-24_layer2-vs-layer3.md`](2026-05-24_layer2-vs-layer3.md) —
>   Quellcode-Coverage L2 vs L3, per Verzeichnis und Top-Files.

---

## Kopfzeile (ursprünglich Zeile 13–14 in `tds_coverage_ref.md`)

**Stand 2026-05-24:** **216 abgedeckt** (215 spezifikationsbasiert + 1
strukturbasiert), **2 nicht abgedeckt** (G05, G06), **1 SKIP** (U02 deprecated)
/ **219 Features gesamt**.

## Zusammenfassungstabelle (Stand 2026-05-24)

| Status | G (G01–G31) | S (S01–S53) | P (P01–P44) | SEC (30) | M (M01–M29) | E (E01–E09) | A (A01–A19) | K (K01–K02) | U (U01–U02) | Gesamt |
|---|---|---|---|---|---|---|---|---|---|---|
| **Abgedeckt** (spezifikationsbasiert) | 28 (G01–G04, G07–G31 exkl. G27) | 53 (S01–S53) | 44 (P01–P44) | 30 (SEC-* inkl. UTL01) | 29 (M01–M29) | 9 (E01–E09) | 19 (A01–A19) | 2 (K01–K02) | 1 (U01) | **215** |
| **Abgedeckt** (strukturbasiert, CRAP-Analyse, niedrigere Qualitätsstufe) | 1 (G27) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | **1** |
| **Nicht abgedeckt** | 2 (G05, G06) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | **2** |
| **SKIP** | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 1 (U02 deprecated) | **1** |
| **Gesamt** | **31** | **53** | **44** | **30** | **29** | **9** | **19** | **2** | **2** | **219** |

### Lesehinweis

- **Spezifikationsbasiert** umfasst die Siegel `[EP]`, `[Spec-B]`, `[Spec-C]`,
  `[Smoke]` aus dem Qualitätssiegel-Katalog. Sie sind aus externer Spezifikation
  oder fachlichen Akzeptanzkriterien abgeleitet.
- **Strukturbasiert** bezeichnet `[CRAP]`-Tests aus der Code-Komplexitäts-
  Analyse (CRAP > 100 oder 0 %-Branch-Coverage). Diese Tests prüfen vorhandenen
  Code-Pfad, leiten ihn aber nicht aus einer fachlichen Erwartung ab —
  niedrigere Aussagekraft, daher separat ausgewiesen.
- **Nicht abgedeckt / SKIP:** G05 (Datums-Parsing — L2-Stub ungenügend, kein L3-
  Direkttest), G06 (Datumsformatierung — analog), U02 (Plugin-API deprecated).

### Veränderungen gegenüber dem April-Snapshot (Differenz)

| Domäne | April 2026-04-11 | Mai 2026-05-24 | Δ |
|---|---:|---:|---:|
| G | 29 | 31 | +2 |
| S | 51 | 53 | +2 |
| P | 41 | 44 | +3 |
| SEC | 26 | 30 | +4 |
| M | 0 | 29 | **+29 (neu eingeführt)** |
| E | 8 | 9 | +1 |
| A | 11 | 19 | +8 |
| K | 2 | 2 | 0 |
| U | 2 | 2 | 0 |
| **Gesamt** | **170** | **219** | **+49** |

Die M-Reihe (Middleware) wurde nach dem April-Snapshot neu eingeführt — sie
war im April-Plan §5.1 als „auf der grünen Wiese" identifiziert worden und
trägt heute 29 Features bei.

---

## Anschlussverweise

- Aktuelle Gap-Analyse (Erhebungs-Tag): [`2026-05-24_gap-analyse.md`](2026-05-24_gap-analyse.md).
- L2-vs-L3-Coverage-Vergleich: [`2026-05-24_layer2-vs-layer3.md`](2026-05-24_layer2-vs-layer3.md).
- Historischer Snapshot (für Diff):
  [`historical/2026-04-11_abdeckung-snapshot.md`](historical/2026-04-11_abdeckung-snapshot.md).
- Historische Gap-Analyse: [`historical/2026-04-11_gap-analyse-fork.md`](historical/2026-04-11_gap-analyse-fork.md).
- Historische E2E-Gap-Analyse:
  [`historical/2026-03-27_e2e-gap.md`](historical/2026-03-27_e2e-gap.md).
