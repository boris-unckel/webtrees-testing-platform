<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — Umsetzungsplan

**Scope:** 24 Referenz-IDs (G25–G29, S41–S50, P30–P37, SEC-BOT01)  
**Grundlage:** [testquality_improve_common.md](testquality_improve_common.md) + je [testquality_improve_\<REFERENZ\>.md](testquality_improve_G25.md)  
**Ziel:** Strukturbasierte CRAP-Analyse-Tests auf spezifikationsbasierte (ISTQB B) oder pragmatisch erweiterte (C) Qualitätsstufe anheben

---

## Arbeitsablauf je Referenz-ID (5 Phasen)

Jede Referenz durchläuft die folgenden Phasen — sequenziell pro Referenz, aber P3 (Coding) kann für **unterschiedliche Referenz-IDs** parallel laufen, solange die Testklassen sich nicht überschneiden.

| Phase | Inhalt | Abnahmekriterium |
|---|---|---|
| **P1: Konsistenzcheck** | (a) Upstream-SUT lesen (`./upstream/webtrees/app/...`); (b) aktuellen Test-Code lesen; (c) Abgleich mit Einzelvorgabe-Dokument | Konzept stimmt mit Code-Ist überein. Falls Diskrepanz: Soll-Dokument wird korrigiert, nicht der SUT. |
| **P2: Soll-Design** | EP/BVA-Matrix aus Einzelvorgabe finalisieren; konkrete Testmethoden-Namen und DataProvider-Struktur festlegen; Fixture-Bedarf identifizieren; Mocking-Strategie festlegen | Testmethoden-Liste vollständig, Implementierungsreihenfolge bekannt |
| **P3: Test-Coding** | Testmethoden schreiben; ggf. neue Testklasse anlegen; DataProvider implementieren; Fixtures vorbereiten | Code syntaktisch korrekt; alle geplanten Testmethoden vorhanden; ggf. neue Klasse in phpunit.xml eingetragen |
| **P4: Ausführung + Fixing** | Einzelnen Test/Klasse isoliert ausführen (`make test-integration T=...`); Fehler analysieren und im **Testcode** beheben (nicht im SUT); iterieren bis grün | Alle neuen Tests grün; keine Regressionen in der gesamten Test-Suite |
| **P5: Big-Picture** | Einträge in `docs/testing-bigpicture.md` aktualisieren (Feature-Matrix, Abdeckungsmatrix, Endekriterien, Testentwurfsverfahren, Changelog) | Big-Picture konsistent mit neuem Test-Zustand |

**Statusaktualisierung:** Unmittelbar nach Abschluss jeder Phase in `testquality_improve_<REFERENZ>.md` eintragen — nicht akkumuliert am Ende.

---

## Randbedingungen (aus CLAUDE.md)

- **Keine leere .env:** Alle Integration-Tests über `make test-integration` oder isoliert via Klassen-Filter (`make test-integration T=<Klasse>` falls vorhanden, sonst `--filter`).
- **Exklusive Ausführung:** Immer nur ein Test-Lauf gleichzeitig. Vor neuem Lauf sicherstellen, dass kein laufender Prozess aktiv ist (`pgrep -f phpunit`).
- **Kein Timeout-Limit:** Testläufe immer mit `run_in_background: true` starten; auf Fertigmeldung warten.
- **GPG-Commits:** Alle Commits GPG-signiert (`commit.gpgsign=true`). Commits nach abgeschlossenen Runden oder bei logisch zusammengehörigen Abschlüssen — nicht nach jeder einzelnen Testmethode.
- **Neue Dev-Dependencies** (z.B. `php-mock/php-mock-phpunit`): Erst evaluieren und Benutzer-Zustimmung einholen, dann installieren.
- **Bestehende Batch-Tests erhalten:** Neue spezifische Klassen ergänzen; bestehende `RequestHandlerBatchA/B`, `CliSettingsBatch` als Smoke-Tests behalten.
- **Kein SUT-Code ändern:** Alle Tests müssen ohne Änderung am webtrees-Upstream funktionieren. Ausnahme: explizite Entscheidung nach Diskussion.

---

## Gesamtstatus

| Ref | Titel | Aufwand | P1 | P2 | P3 | P4 | P5 |
|---|---|---|---|---|---|---|---|
| **G25** | GedcomLoad CLI-Import | Hoch | ✅ | ✅ | ✅ | ✅ | ✅ |
| **G26** | GEDCOM-Export via CLI | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **G27** | Mediendatei-Upload URL | Mittel/Hoch | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 |
| **G28** | OBJE-Metadaten bearbeiten | Mittel | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ |
| **G29** | GEDCOM-Bearbeitungsservice | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S41** | Statistikdaten-Abfragen | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S42** | Such-HTTP-Handler | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S43** | Report-Generierung HTTP | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S44** | Report-Parser Erweitert | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S45** | Report-Primitive PDF/HTML | Hoch | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S46** | Homepage-Block-Module | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S47** | Interaktiver Stammbaum | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S48** | Standortdaten-Import Admin | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S49** | Medienverwaltungsliste Admin | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S50** | Hilfetexte | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P30** | Datensätze zusammenführen | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P31** | Familienmitglieder bearbeiten | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P32** | Record-Ansicht und -Löschung | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P33** | Stammbaum-Privacy-Einstellungen | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P34** | Stammbaum-Umnummerierung | Hoch | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ |
| **P35** | CLI Benutzer-Verwaltung | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P36** | CLI Einstellungs-Verwaltung | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P37** | HTTP Benutzer-Bearbeitung | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **SEC-BOT01** | UA-basierte Bot-Blockierung | Niedrig–Hoch | ✅ | ✅ | ✅ | ✅ | ✅ |

**Legende:** ⬜ OPEN · 🔄 IN PROGRESS · ✅ DONE · 🚫 BLOCKED

---

## Empfohlene Reihenfolge

Reihenfolge nach Aufwand/Erkenntnisgewinn gemäß `testquality_improve_common.md` Abschnitt 9. Innerhalb einer Runde sind P3 (Coding) für verschiedene Referenzen parallelisierbar; P4 (Ausführung) immer sequenziell.

### Runde 1 — Quick Wins (Niedrig, sofort umsetzbar)

| Schritt | Ref | Testklasse | Begründung |
|---|---|---|---|
| 1 | **G26** | `TreeExportCommandIntegrationTest` | DataProvider für Format-/Privacy-EP, `--tree` not-found: bestehende Klasse erweitern |
| 2 | **G29** | `GedcomEditServiceIntegrationTest` | Vollständige EP-Matrix, beste Ausgangsbasis aller G-Tests |
| 3 | **P35** | `UserEditCommandIntegrationTest` | 15 Guard-Branches, DataProvider für Konflikt-Detection |
| 4 | **P36** | neue Klassen je Command (→ common Abschnitt 6) | Zustandsautomat 14 Branches, DataProvider über 4 Commands |
| 5 | **S50** | neue `HelpTextIntegrationTest` (aus Batch A herauslösen) | DataProvider aller topic IDs via glob, 404-Test |
| 6 | **S49** | `ManageMediaDataIntegrationTest` | JSON-Struktur-Assertion, type='unused'-Branch |

### Runde 2 — Mittlere Komplexität

| Schritt | Ref | Testklasse | Begründung |
|---|---|---|---|
| 7 | **S42** | `SearchRequestHandlerIntegrationTest` | Single-Result-Redirect: kritisch, bisher 0 Tests |
| 8 | **SEC-BOT01 (Teile)** | `BadBotBlockerIntegrationTest` | Cookie-Heuristik + WordPress-Pfade + BAD_ROBOTS-Sampling (ohne DNS) |
| 9 | **P37** | neue `UserEditActionIntegrationTest` | Duplikat-Checks B5/B6/B7/B8, Self-Edit B4 |
| 10 | **P30** | neue `MergeFactsActionIntegrationTest` | 6 Guard-Clauses, DB-Postcondition |
| 11 | **P31** | neue `ChangeFamilyMembersActionIntegrationTest` | Datums-Sortierlogik B7/B8 genealogisch kritisch |
| 12 | **P33** | neue `TreePrivacyActionIntegrationTest` | Array-Parallelitäts-Validierung, Rule-Typ-Matrix |
| 13 | **S41** | `StatisticsDataIntegrationTest` | Sex/Sort/Year-Matrizen, whereBetween-Branch |
| 14 | **S43** | `ReportIntegrationTest` | PDF-Format-Pfad, Content-Disposition-Header |
| 15 | **S44** | `ReportParserGenerateExtendedIntegrationTest` | Output-Validierung statt no-exception |
| 16 | **S46** | `BlockModuleIntegrationTest` | AJAX-Flag SlideShow, TopSurnames style EP |
| 17 | **S47** | `InteractiveTreeIntegrationTest` | HTML-Strukturvalidierung, EP mit/ohne Eltern |
| 18 | **S48** | `MapDataImportIntegrationTest` | option-EP-Matrix, DB-Postcondition Koordinaten |
| 19 | **P32** | neue `DeleteRecordIntegrationTest` + `GedcomRecordPageIntegrationTest` | Familie-Kaskade, Cross-Table |

### Runde 3 — Hoch (externe Infrastruktur / neue Dependencies)

| Schritt | Ref | Bemerkung |
|---|---|---|
| 20 | **G25** | keep_media, BOM-Stripping: direkter DB-Insert für gedcom_chunk nötig |
| 21 | **G28** | GEDCOM-String-Postcondition, xref-not-found Guard |
| 22 | **G27** | 🚫 EXCLUDED — HTTP-Mock erfordert Upstream-Patch: `GuzzleHttp\Client` wird in webtrees direkt instanziiert (`new Client()`), kein DI-Interface. Kein Mock ohne SUT-Änderung möglich. |
| 23 | **P34** | Duplikate manuell via DB-Insert, Cross-Table-Konsistenz |
| 24 | **S45** | HTML-Renderer zuerst (Mittel); PDF-TCPDF-Zustand (Hoch) danach |
| 25 | **SEC-BOT01 (DNS)** | 🚫 EXCLUDED — DNS-Branches dauerhaft ausgeklammert (entschieden in P5, dokumentiert in `testquality_improve_SEC-BOT01.md` + `testing-bigpicture.md`). `php-mock` nicht nötig. |

---

## P1: Konsistenzcheck — Checkliste

Pro Referenz vor Beginn von P2:

1. **SUT lesen:** `./upstream/webtrees/app/<Pfad>` — hat sich die Klasse seit der Analyse verändert? Neue Branches? Gelöschte Branches?
2. **Test-Ist lesen:** Aktuelle Testklasse lesen — welche Methoden existieren bereits?
3. **Einzelvorgabe prüfen:** Stimmt der Branch-Katalog im `.md` mit dem aktuellen SUT überein?
4. **Ggf. Einzelvorgabe korrigieren:** Falls Diskrepanz → Soll-Dokument (`testquality_improve_<REF>.md`) aktualisieren, dann P1 als ✅ markieren.
5. **Fixture-Check:** Welche Demo-Daten sind verfügbar? XREF-IDs aus Fixture-Datei ableiten.

---

## P5: Big-Picture — Was zu aktualisieren ist

Nach Abschluss jeder Referenz in `docs/testing-bigpicture.md`:

| Abschnitt | Änderung |
|---|---|
| **Feature-Matrix** (§ Feature-Matrix Teststufe 2) | Test-Anzahl aktualisieren; Qualitätsstufe von `*(strukturbasiert)*` auf `*(spezifikationsbasiert)*` oder `*(spezifikationsbasiert+strukturbasiert)*` anpassen, wenn EP/BVA-Tests hinzugekommen sind |
| **Testentwurfsverfahren** (§ Testentwurfsverfahren pro Domäne) | Zeile für strukturbasiertes CRAP-Testen: Verweis auf neue EP/BVA-Tests ergänzen, oder neue Zeile für betroffene Referenz-IDs wenn vollständig hochgestuft |
| **Abdeckungsmatrix** (§ Abdeckungsmatrix Teststufe 2, Domäne G/S/P/SEC) | Testklassen-Namen, Test-Anzahl, ggf. neue Klassen-Namen nach Aufsplittung |
| **Endekriterien** (§ Endekriterien Teststufe 2) | Neue Testklassen-Namen in die Auflistung aufnehmen wenn Batch-Klassen gesplittet werden |
| **Changelog** (Ende des Dokuments) | Neuen `*Aktualisiert: DATUM — ...*`-Eintrag anhängen |

---

## Abschluss

Nach Abschluss aller erreichbaren Referenz-IDs (Runde 1 + 2, ggf. Runde 3):

```bash
make test-integration   # Voll-Lauf — run_in_background: true, auf Fertigmeldung warten
make crap-report        # CRAP-Score-Tabelle neu berechnen aus artifacts/layer3/coverage.xml
```

Anschließend:

1. **Neubewertung:** Welche CRAP-Score-Einträge (CRAP > 100) sind durch die neuen Tests eliminiert oder reduziert?
2. **Ratchet aktualisieren** (falls vorhanden): Neuen Coverage-Stand eintragen.
3. **Abschluss-Commit nicht machen:** Wird manuell angestossen

---

### Neubewertungs-Ergebnis (2026-04-05, nach Runde 1+2)

**Voll-Lauf:** 536/536 grün, 1762 Assertions, Exit 0.

| Kennzahl | Vorher | Nachher |
|---|---|---|
| Methoden CRAP > 100 bei 0% Coverage | **43** | **16** |
| Eliminiert (Runde 1+2) | — | **27** |

**Verbleibende aktive CRAP-Kandidaten (Runde 3):**
- G25: `TreeImport::execute` (CRAP 110, cx=10)
- S45: `ReportPdfImage::render` (CRAP 132, cx=11); `ReportPdfTextBox/Cell/Footnote/Text::render/getWidth` unter 100 (Coverage durch S43/S44-Report-Tests gestiegen)

**Aus CRAP-Liste verschwunden ohne Runde-3-Test:**
- G28 (`EditMediaFileAction`): durch S44-Report-Tests abgedeckt
- P34 (`RenumberTreeAction`): durch `RequestHandlerBatchBIntegrationTest` abgedeckt
- G27: 🚫 EXCLUDED (GuzzleHttp-DI-Problem — keine Testbarkeit ohne SUT-Änderung)

**Kein Ratchet-File vorhanden** — kein Update nötig.
