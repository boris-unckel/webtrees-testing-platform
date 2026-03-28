# Prompt: Testabdeckung steigern — Offene Features schließen

## Ziel

Erstelle einen Plan zur Steigerung der Testabdeckung von aktuell 69% (43/62 Features)
auf das Maximum. Der Plan soll alle offenen und teilweise abgedeckten Features aus der
Feature-Matrix (`docs/testing-bigpicture-prompt.md`) adressieren. Am Ende steht ein
vollständiger Testlauf (`make test-all`) mit Fehleranalyse und -behebung.

**Wichtig: Erstelle zunächst nur den Plan. Beginne NICHT mit der Implementierung.**

---

## Kontext

- Teststrategie-Dokument: `docs/testing-bigpicture-prompt.md`
- CLAUDE.md enthält den kanonischen Testaufruf und die Layer-Architektur
- webtrees-Source: `../webtrees-upstream/webtrees/` (read-only Mount)
- Upstream-Branch: `5349_add_tests` in `../webtrees-upstream/webtrees/`
- Bekannter Upstream-Bug: `FamilyFactory::mapper()` TypeError bei Privat-Familien

---

## Offene Features (Status: Offen — 10 Stück)

| # | Feature | Teststufe | Prio | Anmerkung |
|---|---|---|---|---|
| G10 | Legacy-Formate (TNG-PLAC, _PLAC_DEFN) | 2 | Niedrig | Konvertierungslogik in GedcomImportService |
| G14 | Export ZIP | 2 | Mittel | `GedcomExportService::export()` mit ZIP-Format |
| G15 | Export ZIP+Media | 2 | Mittel | ZIP mit Mediendateien eingebettet |
| G21 | Upload-Validierung | 3 | Mittel | Ungültige Datei → Fehlermeldung, kein Import |
| G23 | GEDCOM 5.5.1 Compliance | 1 | Niedrig | Tag-Abdeckung: Element-Klassen vs. Standard |
| S05 | Erweiterte Suche (Felder) | 2 | Hoch | 75 GEDCOM-Felder, Feld-spezifische Filterung |
| S06 | Erweiterte Suche (Datum-Modifikatoren) | 2 | Hoch | Geburtsdatum ±5 Jahre |
| S11 | Cross-Tree-Suche | 2 | Mittel | Suche über 2+ Bäume (demo + muster) |
| S13 | Search-and-Replace | 3 | Mittel | Bulk-Editor, erfordert Edit-Recht |
| S28 | Navigation: Notizseite | 3 | Mittel | Kein NOTE-Record in `demo.ged` — Fixture-Ergänzung nötig |

## Teilweise abgedeckte Features (Status: Teilweise — 7 Stück)

| # | Feature | Teststufe | Prio | Was fehlt |
|---|---|---|---|---|
| G08 | Encoding (ANSEL, CP1252) | 2 | Mittel | Nur CONT/CONC + empty fields getestet, echte Encoding-Konvertierung fehlt |
| G09 | Inline-Media | 2 | Mittel | Nur media objects getestet, Inline-OBJE-Aufspaltung fehlt |
| G11 | Custom-Tags | 1 | Mittel | Nur media files getestet, Ancestry/FamilySearch/RootsMagic-Tags fehlen |
| G17 | Export Encoding | 1 | Mittel | Nur CONC getestet, UTF-8→ANSEL/CP1252-Konvertierung fehlt |
| S07 | Phonetische Suche (Russell) | 2 | Mittel | Nur Soundex-Generierung, nicht Suchfunktion getestet |
| S08 | Phonetische Suche (DM) | 2 | Mittel | Nur DM-Soundex-Generierung, nicht Suchfunktion |
| S10 | Paginierung | 2 | Mittel | Nur Place-Search mit Limits, nicht allgemein |

## Unvollständige Smoke-Tests (abgedeckt, aber lückenhaft)

| # | Feature | Status | Was fehlt |
|---|---|---|---|
| S18 | Chart: alle 13 Typen | 7/13 | Fehlend: Timeline, Lifespan, FamilyBook, Relationships, Branches, Statistics |
| S20 | Liste: alle 10 Typen | 7/10 | Fehlend: Location, Place, Branches |

---

## Rahmenbedingungen für den Plan

### Reihenfolge-Prinzip: Komponentenintegrationstest first

**Verbindliche Vorgabe:** Komponentenintegrationstests (Teststufe 2, `layer3-integration/`)
werden **immer zuerst** erstellt. Sie sind das primäre Arbeitsergebnis dieses Projekts.

Komponententests (Teststufe 1, Upstream-Branch `5349_add_tests`) entstehen **nur als
Nebenprodukt**, wenn der Erkenntnisgewinn aus einem Komponentenintegrationstest zeigt,
dass ein lückenhafter Stub im Upstream sinnvoll gefüllt werden kann. Niemals umgekehrt.

**Begründung:** Der bestehende Upstream-PR (`5349_add_tests`) ist im Status "Pending".
Solange er nicht akzeptiert ist, darf kein zusätzlicher Scope auf den Upstream-Branch
geladen werden, der das Zurückführen zum Upstream-Standard erschwert. Gleichzeitig soll
das Projekt `webtrees-testing-platform` eigenständig vorankommen — unabhängig davon,
ob und wann der PR gemergt wird.

**Konkret:**
- Jedes offene Feature wird zuerst als Komponentenintegrationstest (MySQL) implementiert
- Falls dabei ein Upstream-Stub identifiziert wird, der mit minimalem Aufwand gefüllt
  werden kann, wird ein Komponententest (SQLite) als separater Schritt ergänzt
- Systemtests (Teststufe 3, Playwright) folgen nach den Komponentenintegrationstests
- Der Plan muss diese Reihenfolge pro Arbeitspaket explizit ausweisen

### Testorte (wo Tests geschrieben werden)

- **Teststufe 2 (Komponentenintegrationstest) — Primär:** `layer3-integration/tests/` in diesem Repo — MySQL, `MysqlTestCase.php` als Basis
- **Teststufe 1 (Komponententest) — Nur als Nebenprodukt:** Upstream-Branch `5349_add_tests` in `../webtrees-upstream/webtrees/tests/` — SQLite in-memory, `TestCase.php` als Basis. Nur wenn Erkenntnisgewinn aus Teststufe 2 einen lückenhaften Stub sinnvoll füllen lässt.
- **Teststufe 3 (Systemtest) — Nach Teststufe 2:** `layer4-e2e/tests/` in diesem Repo — Playwright, Chromium headless

### Konventionen (aus testing-bigpicture-prompt.md)

- AAA-Pattern (Arrange-Act-Assert)
- Namenskonvention: `test_<feature>_<szenario>_<erwartetes_ergebnis>`
- Data Provider ab ≥3 Äquivalenzklassen
- `@see docs/testing-bigpicture-prompt.md G01` Verfolgbarkeit
- Theme-Loop für tree-gebundene E2E-Tests (5 Themes via `helpers/theme-switch.ts`)
- Ein Verhalten pro Testmethode

### Bekannte Einschränkungen

- Upstream-Bug `FamilyFactory::mapper()` → Tests für PRIV_NONE/PRIV_USER überspringen
- Read-only Bind-Mount → kein Schreibzugriff auf webtrees-Source im Container
- SELinux-Falle (Fedora/rootless Podman) → kein `:Z` auf gemeinsame Mounts

---

## Fixture-Analyse und -Generierung

Die bestehenden Fixtures (`fixtures/demo.ged`, `fixtures/gedcom-l-muster.ged`) decken
nicht alle offenen Features ab. Die folgenden Fixtures müssen als Vorarbeit generiert
werden, bevor die zugehörigen Tests geschrieben werden können.

### Bestandsaufnahme der vorhandenen Fixtures

| Fixture | Encoding | INDI | FAM | SOUR | OBJE | REPO | SUBM | NOTE (top-level) | _LOC | Inline OBJE | Custom-Tags |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `demo.ged` | UTF-8 | 72 | 29 | 2 | 62 | 1 | 1 | **0** | **0** | 98 | `_WT_USER`, `_MARNM`, `_SEPR`, `_NMR`, `_INTE` |
| `gedcom-l-muster.ged` | UTF-8 | 37 | 18 | 11 | 1 | 1 | 2 | **1** | **11** | 1 | `_UID`, `_LOC`, `_GOV`, `_DMGD`, `_ASSO`, `_WITN`, `_RUFNAME` |

### Definitiv fehlende Fixtures (generierbar → im Plan umsetzen)

| Fixture-Datei | Zweck | Benötigt für | Generierbar? |
|---|---|---|---|
| `fixtures/note-record.ged` | Mini-GEDCOM mit top-level NOTE-Record (`0 @N1@ NOTE ...`) | S28 (Notizseite) | **Ja** — synthetischer NOTE-Record + HEAD/TRLR. Alternativ: NOTE-Record direkt zu `demo.ged` hinzufügen. Entscheidung im Plan treffen. |
| `fixtures/encoding-ansel.ged` | ANSEL-codiertes Mini-GEDCOM (`1 CHAR ANSEL`) mit Umlauten/Diakritika | G08 (Encoding ANSEL) | **Ja** — minimales GEDCOM (HEAD + 1 INDI mit Sonderzeichen) in ANSEL-Encoding erzeugen. Referenz: `AnselTest.php` upstream (80+ Zeichen-Mappings). |
| `fixtures/encoding-cp1252.ged` | Windows-1252-codiertes Mini-GEDCOM (`1 CHAR ANSI`) | G08 (Encoding CP1252) | **Ja** — wie ANSEL, aber CP1252-codiert. Referenz: `Windows1252Test.php` upstream. |
| `fixtures/legacy-tng.ged` | GEDCOM mit TNG-PLAC-Tags und `_PLAC_DEFN`-Struktur | G10 (Legacy-Formate) | **Ja** — synthetisches GEDCOM mit `_PLAC_DEFN` und TNG-spezifischen Ort-Strukturen. Code-Analyse von `GedcomImportService` nötig, um exakte Tag-Struktur zu ermitteln. |
| `fixtures/custom-tags.ged` | GEDCOM mit Ancestry/FamilySearch/RootsMagic-Custom-Tags | G11 (Custom-Tags) | **Ja** — synthetisches GEDCOM mit allen 13 Custom-Tag-Klassen aus `app/Gedcom.php`. Tags und Struktur aus dem Code ableitbar. |
| `fixtures/invalid-empty.txt` | Leere Datei (0 Bytes) | G21 (Upload-Validierung) | **Ja** — trivial |
| `fixtures/invalid-text.txt` | Textdatei ohne GEDCOM-Struktur | G21 (Upload-Validierung) | **Ja** — trivial |
| `fixtures/invalid-no-head.ged` | GEDCOM ohne HEAD-Record | G21 (Upload-Validierung) | **Ja** — `0 @I1@ INDI\n1 NAME Test /User/\n0 TRLR` |
| `fixtures/invalid-binary.bin` | Binärdatei (kein Text) | G21 (Upload-Validierung) | **Ja** — wenige Bytes Zufallsdaten |

### Potentielle Fixture-Lücken (im Plan klären)

| Problem | Betrifft | Klärungsbedarf |
|---|---|---|
| **Media-Dateien im Container** | G14, G15 (ZIP+Media Export) | `demo.ged` referenziert Medien (`Elizabeth_II.jpg` etc.), aber die Dateien liegen in `../webtrees-upstream/webtrees/tests/data/media/` — nicht im Container-Volume `data/media/`. Ohne Medien wird ZIP+Media-Export leeres Archiv produzieren. **Plan muss klären:** Media-Dateien in Container kopieren (via `setup-webtrees.sh`) oder Mock-Strategie. |
| **_LOC-Records für Location-List** | S20 (Liste: Location) | `demo.ged` hat 0 `_LOC`-Records. `muster` hat 11. E2E-Test muss entweder Tree `muster` nutzen oder `_LOC` zu `demo.ged` hinzugefügt werden. |
| **Cross-Tree-Setup** | S11 (Cross-Tree-Suche) | `setup-webtrees.sh` importiert beide Bäume (`demo` + `muster`). Muss verifiziert werden, dass Cross-Tree-Suche im Stack tatsächlich funktioniert, bevor Tests geschrieben werden. |
| **Erweiterte Suche: Feldvielfalt** | S05 (Erweiterte Suche) | `demo.ged` (brit. Königshaus) hat hauptsächlich Geburts-/Sterbedaten und Orte. Felder wie Occupation, Religion, Cause of Death sind dünn besetzt. **Plan muss klären:** Reicht `demo.ged` oder braucht es ergänzende Fixture-Daten? Code-Analyse der 75 Suchfelder in `SearchService` nötig. |

### Fixture-Generierung: Vorgehen

Generierbare Fixtures sollen **im Rahmen der Implementierung** erzeugt werden (nicht separat).
Reihenfolge:

1. **Zuerst Code-Analyse** der betroffenen webtrees-Klassen (Encoding-Handling, Legacy-Import,
   Custom-Tags, Upload-Validierung), um exakte Fixture-Anforderungen zu ermitteln
2. **Dann Fixtures generieren** — minimal, deterministisch, mit Kommentar zum Zweck
3. **Dann Tests schreiben**, die diese Fixtures verwenden

Fixtures kommen nach `fixtures/` und werden in `.gitignore` NICHT ausgeschlossen (versioniert).
Binäre Fixtures (`invalid-binary.bin`) sollten minimal sein (< 100 Bytes).

---

## Erwartete Plan-Struktur

Der Plan soll folgende Struktur haben:

1. **Priorisierung**: Welche Features zuerst? (Hoch → Mittel → Niedrig)
2. **Arbeitspakete**: Gruppierung nach Teststufe und Testdatei (nicht pro Feature)
3. **Abhängigkeiten**: Welche APs hängen voneinander ab? (z.B. Fixture-Ergänzung vor S28)
4. **Aufwandschätzung pro AP**: Anzahl neue Tests / Assertions (grob)
5. **Risiken und Entscheidungen**: Wo ist Code-Analyse nötig, bevor Tests geschrieben werden können?
6. **Abschluss-Phase**: Vollständiger Testlauf (`make test-all`) mit:
   - `make test-unit` (Layer 2 — 3278+ Upstream-Tests)
   - `make test-integration` (Layer 3 — 129+ eigene Tests)
   - `make test-e2e` (Layer 4 — 130+ Playwright-Tests)
   - `make test-performance` (Layer 5 — 3 Perf-Tests)
   - Fehleranalyse und Fix-Iterationen bis alle Tests grün sind

---

## Abschließender Hinweis

Nach Erstellung des Plans: `testing-bigpicture-prompt.md` mit dem Plan als neue Phase(n)
im Implementierungs-Fahrplan aktualisieren. Abdeckungsmatrix und Zusammenfassung erst
nach tatsächlicher Implementierung aktualisieren — nicht vorher.

---

## Status

**Plan erstellt und persistiert** am 2026-03-28. Ergebnis: `docs/plan-phase-next-coverage.md`.

| Phase | Inhalt | Neue Tests |
|---|---|---|
| **Phase 8** | 8 APs Komponentenintegrationstest (Teststufe 2) — Primär | ~48 |
| **Phase 8a** | Komponententest als Nebenprodukt (Teststufe 1) — bedingt | ~6-10 |
| **Phase 9** | 5 APs Systemtest (Teststufe 3) | ~20 |
| **Phase 10** | Testlauf + Fehlerbereinigung + Matrix-Update | — |

- **Priorisierung:** S05/S06 (Hoch) zuerst, dann 15 Mittel-Features, dann G10/G23/G22 (Niedrig)
- **Reihenfolge-Prinzip eingehalten:** Integration → Unit (nur Nebenprodukt) → E2E
- **8 Arbeitspakete Phase 8**, gruppiert nach Testdatei (SearchIntegrationTest, GedcomImportTest, TreeOperationsTest, ChartModuleIntegrationTest, ListModuleIntegrationTest)
- **7 Fixtures** zu generieren (2× Encoding, 1× Custom-Tags, 1× Legacy, 4× Invalidierung)
- **6 Risiken** identifiziert (Media-Pfade, Feldverfügbarkeit, Cross-Tree, ANSEL-Encoding, Branches-Zuordnung, G22-Status)
- **Ziel:** 60-62/62 Features (97-100%), ~68-78 neue Tests, ~164-228 Assertions
- **Implementierungs-Fahrplan:** `testing-bigpicture-prompt.md` um Phasen 8–10 (Status "Geplant") ergänzt
