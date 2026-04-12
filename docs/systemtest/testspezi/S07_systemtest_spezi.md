<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S07: Phonetische Suche (Russell Soundex)

**Referenz:** S07 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/search-phonetic` (GET Page), POST Action → `SearchPhoneticPage`, `SearchPhoneticAction`
**L3-Referenztest:** SearchIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die phonetische Suche mit Russell Soundex existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (SearchIntegrationTest) decken partiell Russell-Soundex-Treffer und Nicht-Treffer ab. Die bestehende `search-forms.spec.ts` prüft nur das Rendering des phonetischen Suchformulars, nicht die phonetische Suchergebnis-Qualität.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/search-phonetic` | GET | `SearchPhoneticPage` |
| `/tree/{tree}/search-phonetic` | POST | `SearchPhoneticAction` |

Die Handler erfordern Viewer-Berechtigung (mindestens). Der GET-Handler rendert das phonetische Suchformular mit Namensfeld und Algorithmus-Auswahl (Russell/Daitch-Mokotoff). Der POST-Handler führt die phonetische Suche aus.

### View-Analyse

Das phonetische Suchformular enthält ein Namensfeld und eine Algorithmus-Auswahl (Radio-Buttons oder Select). Ergebnisse werden in einer Tabelle dargestellt. Selektoren: `form` für das Suchformular, Algorithmus-Auswahl (Radio/Select), Ergebnistabelle.

### Theme-Abhängigkeit

Formular-Layout variiert zwischen Themes. Funktionale Elemente sind theme-unabhängig. Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**SearchIntegrationTest** — partiell: Russell Soundex:

- Treffer-Test: Suche nach phonetisch ähnlichem Namen liefert Ergebnisse
- Nicht-Treffer-Test: Suche nach phonetisch eindeutig abweichendem Namen liefert keine Ergebnisse
- EP: Gültige phonetische Variante vs. ungültige Variante

Die L3-Tests validieren die Ergebnis-Arrays auf Handler-Ebene. Sie prüfen nicht die visuelle Darstellung im Browser.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Pattern für phonetische Suche. Das Such-Ausführungs-Verification-Pattern (Konzept 3) wird angewendet, erweitert um Konzept 3.1 (Phonetik-Nachweis). Diese Spec-Datei wird mit S08 (Daitch-Mokotoff) geteilt (Konzept 8 Zusammenlegung).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | Russell-Soundex-Suche nach "Elisabeth" (phonetisch ähnlich zu "Elizabeth") liefert Treffer | Admin | Ergebnistabelle enthält mindestens einen Treffer trotz abweichender Schreibweise | Ja |
| T2 | Russell-Soundex-Suche nach eindeutig abweichendem Namen liefert keinen Treffer | Admin | Ergebnistabelle ist leer oder zeigt "keine Ergebnisse"-Meldung | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Such-Ausführungs-Verification (Konzept 3, 3.1)
**Begründung:** Die phonetische Suche erfordert den Nachweis, dass nicht-exakte Schreibweisen Treffer liefern (Phonetik-Nachweis). Russell Soundex: E421 für Elizabeth/Elisabeth. Der Negativtest stellt sicher, dass der Algorithmus nicht beliebig matcht.

---

## Code-Vorgaben

| Aspekt | Vorgabe |
|---|---|
| **Dateiname** | `phonetic-search-execution.spec.ts` |
| **Ablage** | `layer4-e2e/tests/` |
| **Fixture** | `perfschema-fixture` |
| **Helper** | `loginAsAdmin`, Theme-Loop-Helper |
| **Theme-Loop** | Ja — alle aktiven Themes |
| **Login-Strategie** | Admin-Login |
| **Baum** | demo (Elizabeth als Testziel) |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `phonetic-search-execution.spec.ts` [Spec-C] ✅ *(2 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## Phonetik-Nachweis

Russell Soundex kodiert "Elizabeth" und "Elisabeth" beide als E421. Die nicht-exakte Schreibweise muss Treffer liefern — das ist der Kern der phonetischen Suche. Der Negativtest verwendet einen Namen mit deutlich abweichendem Soundex-Code, um False-Positive-Matches auszuschließen.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
