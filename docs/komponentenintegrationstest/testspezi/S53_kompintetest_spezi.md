<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testdesign — S53: Legacy-URL-Weiterleitungen

**Referenz:** S53 | **SUT:** ~27 `Redirect*Php`-Handler unter `app/Http/RequestHandlers/`
**Bestehender Test:** keiner
**Übergreifende Konzepte:** → [uebergreifende_konzepte_l3.md](../uebergreifende_konzepte_l3.md) Abschnitt 3, [wf_test-iteration_guide.md](../../wf_test-iteration_guide.md)

---

## Status quo

Keine L3-Tests vorhanden. Die 27 Legacy-URL-Redirect-Handler leiten alte webtrees-1.x-URLs
(`*.php`-Endpunkte) auf die neuen webtrees-2.x-Routen um. Alle folgen einem gemeinsamen
Muster: `ged`-Parameter → Tree-Lookup → Record-Factory → 301 Redirect oder 410 Gone.
Vier Handler haben komplexere Logik: `RedirectModulePhp` (Switch über `mod`/`mod_action`),
`RedirectPedigreePhp` (Style-Mapping-Array), `RedirectReportEnginePhp` (`basename`/`dirname`-Trick),
`RedirectCalendarPhp` (`view`/`month`/`year`-Parameter).

**Vollständige Liste der 27 Handler:**
`RedirectAncestryPhp`, `RedirectBranchesPhp`, `RedirectCalendarPhp`, `RedirectCompactPhp`,
`RedirectDescendencyPhp`, `RedirectFamilyBookPhp`, `RedirectFamilyPhp`, `RedirectFanChartPhp`,
`RedirectGedRecordPhp`, `RedirectHourGlassPhp`, `RedirectIndiListPhp`, `RedirectIndividualPhp`,
`RedirectLifeSpanPhp`, `RedirectMediaListPhp`, `RedirectMediaViewerPhp`, `RedirectModulePhp`,
`RedirectNoteListPhp`, `RedirectNotePhp`, `RedirectPedigreePhp`, `RedirectPlaceListPhp`,
`RedirectRelationshipPhp`, `RedirectRepoListPhp`, `RedirectRepositoryPhp`, `RedirectSourceListPhp`,
`RedirectSourcePhp`, `RedirectStatisticsPhp`, `RedirectTimeLinePhp`, `RedirectReportEnginePhp`

---

## SUT-Kernbefunde

**Repräsentatives Muster (alle Handler):**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B1 | `ged=valid` + `pid=valid` → 301 Redirect + Location-Header + Link | Nein |
| B2 | `ged=valid` + `pid=invalid` (Record nicht gefunden) → 410 HttpGoneException | Nein |
| B3 | `ged=invalid` (Tree nicht gefunden) → 410 HttpGoneException | Nein |
| B4 | `pid` ungültiges Format → Validator Exception | Nein |

**Spezial-Handler:**

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| B5 | `RedirectModulePhp`: Switch über `mod`/`mod_action` → verschiedene Module | Nein |
| B6 | `RedirectPedigreePhp`: Style-Mapping-Array | Nein |
| B7 | `RedirectReportEnginePhp`: `basename`/`dirname`-Trick | Nein |
| B8 | `RedirectCalendarPhp`: `view`/`month`/`year`-Parameter | Nein |
| B9 | `ged` fehlt → `DEFAULT_GEDCOM` Fallback | Nein |

---

## Äquivalenzklassen (EP)

| Klasse | Wert/Szenario | Erwartung |
|---|---|---|
| EP1 | Individual: gültiger Tree + gültige PID | 301 Redirect |
| EP2 | Individual: gültiger Tree, PID nicht gefunden | 410 HttpGoneException |
| EP3 | Tree nicht gefunden | 410 HttpGoneException |
| EP4 | PID ungültiges Format | Validator Exception |
| EP5 | Family: gültiger Tree + gültige XREF | 301 Redirect |
| EP6 | Source: gültiger Tree + gültige XREF | 301 Redirect |
| EP7 | Calendar: alle Parameter (`view`, `month`, `year`) gesetzt | 301 Redirect |
| EP8 | Module-Switch: `mod=googlemap`, `mod_action=pedigree_map` | 301 Redirect (PedigreeMap) |
| EP9 | Report-Redirect: gültiger Report-Pfad | 301 Redirect |
| EP10 | `ged` fehlt, `DEFAULT_GEDCOM` gesetzt (Fallback) | 301 Redirect |

---

## Grenzwerte (BVA)

Keine signifikanten Grenzwerte jenseits der EP-Abdeckung — die Logik ist primär
Lookup-basiert (gefunden/nicht gefunden).

---

## Empfohlene Strategie

- **Testklasse:** `LegacyUrlRedirectIntegrationTest`
- **Strategie:** Batch-Smoke (DataProvider für alle 27 Handler: gültiger Request → 301)
  + EP für 3–4 komplexe Handler (`RedirectModulePhp`, `RedirectPedigreePhp`,
  `RedirectReportEnginePhp`, `RedirectCalendarPhp`)
- **Priorität:** Mittel
- **Fixtures:** Tree mit GEDCOM-Import (Individuals, Families, Sources, Notes, Repositories,
  Media), Module konfiguriert
- **Dependencies:** `TreeService`, `ModuleService`, `RecordFactories` — real durchlaufen
- **Mocking:** Kein Mocking nötig
- **DataProvider:** Ein DataProvider liefert für jeden der 27 Handler einen gültigen
  Request mit erwarteter 301-Antwort. Dies sichert die Batch-Smoke-Abdeckung.
- **Besonderheit:** Die vier komplexen Handler benötigen jeweils eigene Testmethoden
  mit mehreren EP-Varianten. `DEFAULT_GEDCOM`-Fallback (EP10) als Sonderfall testen.

---

## Doku-Vorgaben

| Dokument | Aktion |
|---|---|
| `docs/tds_coverage_ref.md` | L3-Spalte: `LegacyUrlRedirectIntegrationTest [EP] ✅ *(N Tests)*` |
| `docs/tds_conditions_ref.md` | Teststufe-Spalte auf `2, 3` erweitern (aktuell nur `3`) |
| `docs/tp_ratchet_spec.md` | Endekriterien Teststufe 2 prüfen (S53 ggf. ergänzen) |
| `docs/tds_methodik_spec.md` | Ggf. Redirect-Batch-Testing als Verfahren ergänzen |

---

## Phase-Status

| Phase | Status | Notizen |
|---|---|---|
| P1: Konsistenzcheck | ⬜ | |
| P2: Soll-Design | ⬜ | |
| P3: Test-Coding | ⬜ | |
| P4: Ausführung + Fixing | ⬜ | |
| P5: Dokumentation | ⬜ | |
