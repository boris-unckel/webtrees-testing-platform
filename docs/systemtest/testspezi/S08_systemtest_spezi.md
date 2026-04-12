<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Systemtest-Spezifikation — S08: Phonetische Suche (Daitch-Mokotoff Soundex)

**Referenz:** S08 | **Teststufe:** 3 — Systemtest (L4 Playwright)
**Seite/Route:** `/tree/{tree}/search-phonetic` (GET Page), POST Action → `SearchPhoneticPage`, `SearchPhoneticAction`
**L3-Referenztest:** SearchIntegrationTest
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l4.md](../uebergreifende_konzepte_l4.md)

---

## Status quo

Für die phonetische Suche mit Daitch-Mokotoff Soundex existieren bisher keine L4-Systemtests. Die L3-Komponentenintegrationstests (SearchIntegrationTest) decken partiell DM-Soundex-Treffer und Nicht-Treffer ab. Die bestehende `search-forms.spec.ts` prüft nur das Rendering des phonetischen Suchformulars.

---

## Upstream-Analyse

### Route und Handler

| Route | Methode | Handler |
|---|---|---|
| `/tree/{tree}/search-phonetic` | GET | `SearchPhoneticPage` |
| `/tree/{tree}/search-phonetic` | POST | `SearchPhoneticAction` |

Gleiche Route wie S07. Der Algorithmus wird über ein Formularfeld (Radio-Button oder Select) zwischen Russell und Daitch-Mokotoff umgeschaltet.

### View-Analyse

Das phonetische Suchformular enthält neben dem Namensfeld die Algorithmus-Auswahl. Bei Daitch-Mokotoff wird ein anderer Kodierungsalgorithmus verwendet, der für osteuropäische Namensvarianten optimiert ist. Selektoren: Gleich wie S07.

### Theme-Abhängigkeit

Formular-Layout variiert zwischen Themes. Funktionale Elemente sind theme-unabhängig. Theme-Loop sinnvoll.

---

## L3-Referenz-Analyse

**SearchIntegrationTest** — partiell: Daitch-Mokotoff Soundex:

- Treffer-Test: DM-Suche nach phonetischer Variante liefert Ergebnisse
- Nicht-Treffer-Test: DM-Suche nach nicht-phonetisch-ähnlichem Namen liefert keine Ergebnisse
- EP: Gültige phonetische DM-Variante vs. ungültige Variante

Die L3-Tests validieren die Ergebnis-Arrays auf Handler-Ebene.

---

## Bestehende L4-Muster-Analyse

Kein bestehendes L4-Pattern für DM-Soundex-Suche. Das Such-Ausführungs-Verification-Pattern (Konzept 3, 3.1) wird angewendet. Diese Spec-Datei wird mit S07 (Russell Soundex) geteilt (Konzept 8 Zusammenlegung).

---

## Testszenarien

| # | Szenario | Rolle | Erwartung | Theme-Loop |
|---|---|---|---|---|
| T1 | DM-Soundex-Suche nach phonetischer Variante liefert Treffer | Admin | Ergebnistabelle enthält mindestens einen Treffer trotz abweichender Schreibweise | Ja |
| T2 | DM-Soundex-Suche nach nicht-phonetisch-ähnlichem Namen liefert keinen Treffer | Admin | Ergebnistabelle ist leer oder zeigt "keine Ergebnisse"-Meldung | Ja |

---

## Playwright-Pattern

**Gewähltes Pattern:** Theme-Loop + Such-Ausführungs-Verification (Konzept 3, 3.1)
**Begründung:** Daitch-Mokotoff ist für osteuropäische Namensvarianten optimiert und erzeugt andere Kodierungen als Russell Soundex. Der Phonetik-Nachweis muss zeigen, dass DM-spezifische Varianten erkannt werden. Der Negativtest schließt beliebige Matches aus.

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
| **Baum** | demo |

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L4-Spalte: `phonetic-search-execution.spec.ts` [Spec-C] ✅ *(2 Tests)* |
| `docs/tds_conditions_ref.md` | Teststufe prüfen |
| `docs/tp_ratchet_spec.md` | Endekriterien aktualisieren |
| `docs/tds_methodik_spec.md` | Testentwurfsverfahren ergänzen falls neu |

---

## DM-Besonderheit

Daitch-Mokotoff Soundex ist für osteuropäische Namensvarianten optimiert (z.B. slawische, jüdische Namen). Der Algorithmus erzeugt bis zu 6-stellige numerische Codes und kann mehrere Codes pro Name generieren (Branching). Im Gegensatz zu Russell Soundex (4-stellig, 1 Code) ist DM-Soundex sensitiver für Konsonantencluster, die in osteuropäischen Sprachen häufig sind.

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ✅ | |
| P2: Soll-Design | ✅ | |
| P3: Test-Coding | ✅ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ✅ | |
