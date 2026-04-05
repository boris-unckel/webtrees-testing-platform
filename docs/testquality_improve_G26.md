<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — G26: GEDCOM-Export via CLI

**Referenz:** G26 | **SUT:** `app/Cli/Commands/TreeExport.php`  
**Aktueller Test:** `TreeExportCommandIntegrationTest` (2 Tests: Default-Export, explizites gedcom-Format)  
**Übergreifende Konzepte:** → [testquality_improve_common.md](testquality_improve_common.md)

---

## Status quo

Beide Tests prüfen nur den Default-Pfad (`--format=gedcom`, Standardbaum). Alle anderen Formate, der „Tree-not-found"-Pfad und ungültige Eingaben sind ungetestet.

---

## SUT-Kernbefunde

`TreeExport::execute()` hat klar definierte Entscheidungspunkte:

| Branch | Bedingung | Bisher getestet? |
|---|---|---|
| A1 | `format=''` → Default `'gedcom'` | ✅ |
| A2 | `privacy=''` → Default `'none'` | ✅ (implizit) |
| A3b/A3c | `privacy` nicht in ACCESS_LEVELS → FAILURE + Fehlermeldung | ❌ |
| B2 | Tree-Name nicht in DB → FAILURE | ❌ |
| C2 | `--format=gedzip` → .gdz erzeugen | ❌ |
| C3 | `--format=zip` → .zip erzeugen | ❌ |
| C4 | `--format=zipmedia` → .zip mit Media | ❌ |
| C5 | `--format=xyz` → FAILURE | ❌ |

**Invarianten:** FORMAT muss einer von 4 Werten sein; PRIVACY muss einer von 4 Werten sein; Tree-Name muss case-sensitiv in DB vorhanden sein; ZipArchive muss nach Schreiben geschlossen werden.

---

## Äquivalenzklassen (EP)

### Format-Parameter

| Klasse | Wert | Erwartung |
|---|---|---|
| EP1 | `gedcom` | .ged-Datei erzeugt, SUCCESS |
| EP2 | `gedzip` | .gdz-Datei erzeugt, SUCCESS |
| EP3 | `zip` | .zip ohne Media, SUCCESS |
| EP4 | `zipmedia` | .zip mit Media-Ordner, SUCCESS |
| EP5 | `''` (leer) | Fallback zu `gedcom`, SUCCESS |
| EP6 | `XML` / `json` | FAILURE, Fehlermeldung im Output |
| EP7 | `GEDCOM` (Großschreibung) | FAILURE (case-sensitive) |

### Privacy-Parameter

| Klasse | Wert | Erwartung |
|---|---|---|
| EP8 | `none` | Alle Records exportiert |
| EP9 | `visitor` | Living-Privacy angewendet |
| EP10 | `member` | Member-Privacy angewendet |
| EP11 | `manager` | Manager-Privacy angewendet |
| EP12 | `admin` / `xyz` | FAILURE |

### Tree-Name

| Klasse | Zustand | Erwartung |
|---|---|---|
| EP13 | Tree existiert in DB | SUCCESS |
| EP14 | Tree existiert nicht | FAILURE, „not found"-Meldung |

---

## Grenzwerte (BVA)

- Format: `'gedcom'` (erster valider Case), `'zipmedia'` (letzter valider Case)
- Privacy: `'none'` (erster), `'visitor'` (letzter)
- Tree-Name: Leerstring, exakter Name, Name mit Sonderzeichen

---

## Empfohlene Strategie

**ISTQB B** — klare, vollständig spezifizierte Aufzählungen. DataProvider-Tabelle für Format × Privacy-Kombination ist direkt umsetzbar. Dies ist der **niedrig-aufwändige Einstieg** (→ Common, Priorität 1).

---

## Konkrete Testideen

```
// DataProvider-basiert (niedrig)
test_export_all_valid_formats(string $format, string $ext)  ← DataProvider
test_export_all_valid_privacy_levels(string $privacy)       ← DataProvider
test_export_fails_with_invalid_format()
test_export_fails_with_invalid_privacy()
test_export_fails_when_tree_not_found()
```

---

## Aufwand

**Niedrig** — bestehende Test-Klasse erweitern, DataProvider hinzufügen, `--tree`-Fehlerfall mit bekanntem Nicht-Namen testen.

---

## Status

| Phase | Zustand | Notiz |
|---|---|---|
| P1: Konsistenzcheck | ✅ DONE | SUT stimmt mit Spec überein, keine Korrekturen |
| P2: Soll-Design | ✅ DONE | 5 neue Methoden + 2 DataProvider (validFormats, validPrivacyLevels) |
| P3: Test-Coding | ✅ DONE | TreeExportCommandIntegrationTest.php erweitert |
| P4: Ausführung + Fixing | ✅ DONE | 13/13 grün, 38 Assertions, kein Fixing nötig |
| P5: Big-Picture | ✅ DONE | Feature-Matrix, Testentwurfsverfahren, Abdeckungsmatrix, Endekriterien, Changelog aktualisiert |
