<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S46: Homepage-Blöcke

**Referenz:** S46 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}` (Homepage) → `TreePage` (Homepage mit konfigurierten Blöcken)
**L3-Referenztest:** BlockModuleIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die Homepage-Blöcke existieren bisher keine spezifischen L4-Systemtests. Die bestehende `homepage.spec.ts` prüft das Grundrendering der Homepage (S40), nicht die spezifischen Blocktypen. Die L3-Komponentenintegrationstests (BlockModuleIntegrationTest) decken 10 Tests ab: SlideShow, Yahrzeit, ReviewChanges, ChartsBlock, UpcomingAnniversaries, TopSurnames, ResearchTask, ClippingsCart, info_style-Varianten.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}` | GET | `TreePage` |

Der Handler erfordert Viewer-Berechtigung (mindestens). Die Homepage rendert konfigurierte Blöcke (Module), die in der Admin-Oberfläche verwaltet werden. Jeder Block ist ein eigenständiges Modul mit eigener Rendering-Logik.

### View-Analyse

Die Homepage-Blöcke werden als Bootstrap-Cards oder ähnliche Container dargestellt. Selektoren: `.wt-block` oder `.block` oder `.card` für Block-Container, `.wt-stats-table` für den Statistik-Block, Admin-Menü für Block-Konfiguration. Jeder Block hat einen Header (Titel) und Body (Inhalt).

### Theme-Abhängigkeit

Block-Rendering variiert stark zwischen Themes (Card-Style, Header-Farben, Layout). Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**BlockModuleIntegrationTest** — 10 Tests:

- SlideShow: Block rendert Medienobjekte
- Yahrzeit: Block zeigt Jahrzeiteinträge
- ReviewChanges: Block zeigt ausstehende Änderungen
- ChartsBlock: Block zeigt eingebettete Diagramme
- UpcomingAnniversaries: Block zeigt bevorstehende Jahrestage
- TopSurnames: Block zeigt häufigste Nachnamen
- ResearchTask: Block zeigt Forschungsaufgaben
- ClippingsCart: Block zeigt Sammelkorb-Status
- info_style-Varianten: Verschiedene Info-Block-Stile

Die L3-Tests validieren das Block-HTML auf Handler-Ebene. Sie prüfen nicht die visuelle Darstellung im Browser-Kontext der Homepage.

---

## Bestehende L4-Muster-Analyse

`homepage.spec.ts` prüft das Grundrendering der Homepage (S40). S46 ergänzt dieses um Blocktypen-Smoke: Prüfung, ob spezifische Block-Container sichtbar sind und ob die Block-Konfiguration erreichbar ist. Das Pattern folgt wf_code-to-systemtest_guide.md 4.3 (Theme-Loop).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Homepage zeigt mindestens einen Block (.wt-block, .block, .card sichtbar) | Admin | Mindestens ein Block-Container ist auf der Homepage sichtbar | Ja |
| T2 | Statistik-Block auf Homepage sichtbar (.wt-stats-table) | Admin | Statistik-Tabelle ist innerhalb eines Blocks sichtbar | Ja |
| T3 | Block-Konfiguration erreichbar (Admin-Menü) | Admin | Admin-Menü enthält Link zur Block-Konfiguration, Seite lädt | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop (wf_code-to-systemtest_guide.md 4.3)
**Begründung:** Die Homepage-Blöcke variieren stark im Rendering zwischen Themes. Auf Smoke-Level wird geprüft, ob Blöcke sichtbar sind und die Konfiguration erreichbar ist. Eine tiefere Validierung einzelner Blocktypen erfolgt auf L3-Ebene.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `homepage-blocks.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `homepage-blocks.spec.ts` [Smoke] ✅ *(3 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Abgrenzung

`homepage.spec.ts` prüft S40 (Grundrendering der Homepage). S46 prüft spezifische Blocktypen und deren Sichtbarkeit auf der Homepage. Die Tests ergänzen sich gegenseitig.

---

## Aufwand

Niedrig — Smoke-Level-Tests, die Block-Container-Sichtbarkeit prüfen.

---

## Referenz-Spec

`homepage.spec.ts` (ergänzt um Blocktypen-Smoke).

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
