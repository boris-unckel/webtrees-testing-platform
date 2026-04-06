<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Testqualität verbessern — Umsetzungsplan (Serie 2)

**Scope:** 29 Referenz-IDs (26 aktiv + 3 EXCLUDED)  
**Refs:** G30, S52–S53, P38–P41, SEC-UTL01, E01–E08, A01–A11, K01–K02  
**Grundlage:** [testquality_improve_common2.md](testquality_improve_common2.md) + je [testquality_improve_\<REFERENZ\>.md](testquality_improve_G30.md)  
**Ziel:** Strukturbasierte CRAP-Analyse-Tests auf spezifikationsbasierte (ISTQB B) oder pragmatisch erweiterte (C) Qualitätsstufe anheben

---

## Arbeitsablauf je Referenz-ID

Identisch zum Ablauf in [testquality_improve_plan.md](testquality_improve_plan.md), Abschnitt "Arbeitsablauf je Referenz-ID" (5 Phasen: Konsistenzcheck, Soll-Design, Test-Coding, Ausführung, Big-Picture). Hier nicht wiederholt.

---

## Randbedingungen

Identisch zu [testquality_improve_plan.md](testquality_improve_plan.md), Abschnitt "Randbedingungen". Insbesondere:
- Exklusive Test-Ausführung (kein paralleler PHPUnit-Lauf)
- Lang laufende Tests mit `run_in_background: true`
- Kein SUT-Code ändern
- GPG-signierte Commits

---

## Gesamtstatus

| Ref | Titel | Aufwand | P1 | P2 | P3 | P4 | P5 |
|---|---|---|---|---|---|---|---|
| **G30** | Mediendatei-Upload (HTTP-Formular) | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S52** | Standortdaten-Verwaltung (CRUD) | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **S53** | Legacy-URL-Weiterleitungen | Niedrig | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 |
| **P38** | Account-Selbstverwaltung | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P39** | Authentifizierung-Aktionen | Hoch | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P40** | Änderungsverwaltung (HTTP-Handler) | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **P41** | Datensatz-Zusammenführung (vollständig) | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **SEC-UTL01** | Web-Assets & Utility-Endpoints | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E01** | Person/Familie anlegen & verknüpfen | Hoch | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E02** | Fakten bearbeiten | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E03** | Rohdaten-Edit (Raw GEDCOM) | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E04** | Nebenrecords anlegen | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E05** | Medienobjekte anlegen & verknüpfen | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E06** | Sortierung (Reorder) | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E07** | Mediendatei-Download & Thumbnail | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **E08** | TomSelect & AutoComplete (Edit-Hilfs-APIs) | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A01** | Stammbaum-Management | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A02** | Stammbaum-Import (HTTP-Formular) | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A03** | Stammbaum-Export (HTTP-Formular) | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A04** | Stammbaum-Präferenzen | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A05** | Modul-Konfiguration | Mittel | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A06** | Site-Präferenzen | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A07** | Benutzerverwaltung Admin | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A08** | Medienverwaltung Admin | Niedrig | ⬜ | ⬜ | ⬜ | ⬜ | ⬜ |
| **A09** | Datenpflege-Werkzeuge | Hoch | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A10** | Protokolle & Monitoring | Niedrig | ✅ | ✅ | ✅ | ✅ | ✅ |
| **A11** | System & Upgrade | Hoch | ✅ | ✅ | ✅ | ✅ | ✅ |
| **K01** | Kontaktformular | Niedrig | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 |
| **K02** | Benutzer-Nachrichten | Niedrig | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 |

**Legende:** ⬜ OPEN · 🔄 IN PROGRESS · ✅ DONE · 🚫 EXCLUDED

---

## Empfohlene Reihenfolge

### Runde 1 — Quick Wins (Niedrig, sofort umsetzbar)

| Schritt | Ref | Testklasse | Begründung |
|---|---|---|---|
| 1 | **SEC-UTL01** | neue `UtilityEndpointsIntegrationTest` | Web-Assets, kein Auth nötig, DataProvider-Batch |
| 2 | **E07** | neue `MediaFileDeliveryIntegrationTest` | MediaDownload/Thumbnail, Guard-Matrix |
| 3 | **E08** | neue `TomSelectIntegrationTest` | TomSelect AJAX, JSON-Ausgabe, kein komplexes Setup |
| 4 | **E06** | neue `ReorderIntegrationTest` | Reorder POST → JSON, Manager-Auth |
| 5 | **A04** | neue `TreePreferencesIntegrationTest` | Admin, Stammbaum-Präferenzen |
| 6 | **A06** | neue `SitePreferencesIntegrationTest` | Admin, SitePreferences EP-Matrix |
| 7 | **A07** | neue `UserAdminIntegrationTest` | UserListPage + UsersCleanup, Admin |
| 8 | **A10** | neue `LogsMonitoringIntegrationTest` | PendingChangesLog + SiteLogsDownload + PhpInfo |

### Runde 2 — Mittlere Komplexität

| Schritt | Ref | Testklasse | Begründung |
|---|---|---|---|
| 9 | **P38** | neue `AccountSelfManagementIntegrationTest` | AccountEdit/Update/Delete EP-Matrix |
| 10 | **P40** | neue `PendingChangesIntegrationTest` | PendingChanges Accept/Reject mit DB-Postconditions |
| 11 | **P41** | neue `MergeRecordsIntegrationTest` | MergeRecordsPage GET + Action POST |
| 12 | **G30** | neue `UploadMediaIntegrationTest` | Filesystem im Container, PSR-7 UploadedFile |
| 13 | **S52** | neue `MapDataCrudIntegrationTest` | MapDataSave Insert/Update, Koordinaten-EP |
| 14 | **E02** | neue `EditFactIntegrationTest` | EditFactPage + AddNewFact + DeleteFact |
| 15 | **E03** | neue `EditRawGedcomIntegrationTest` | EditRawFactAction Validierungslogik |
| 16 | **E04** | neue `CreateSubrecordIntegrationTest` | CreateNoteModal + Action, Smoke für Rest |
| 17 | **A01** | neue `TreeManagementIntegrationTest` | CreateTreeAction + DeleteTreeAction + MergeTreesAction |
| 18 | **A02** | neue `ImportGedcomActionIntegrationTest` | PSR-7 Upload, source-Guards |
| 19 | **A03** | neue `ExportGedcomIntegrationTest` | ExportGedcomClient + Server |
| 20 | **A07** | `UserAdminIntegrationTest` erweitern | UsersCleanupAction POST |

### Runde 3 — Hoch (externe Infrastruktur / viele Handler)

| Schritt | Ref | Bemerkung |
|---|---|---|
| 21 | **E01** | Person/Familie: ~14 Handler, AddChildToIndividual vollständig + DataProvider-Smoke |
| 22 | **E05** | MediaObject verknüpfen: ~9 Handler, Filesystem-Abhängigkeit |
| 23 | **A05** | Modul-Konfiguration: ~46 Handler, DataProvider-Batch, ModulesAllAction EP |
| 24 | **A09** | DataFix + FindDuplicates + AddUnlinked, hoch |
| 25 | **A11** | UpgradeWizard + Masquerade (sicherheitsrelevant) + BroadcastPage + EmailPreferences |
| 26 | **P39** | LoginAction: Cookie-Einschränkung, nur B0-Guard-Test; Rest EXCLUDED |

---

## P5: Big-Picture — Was zu aktualisieren ist

Identisch zu `testquality_improve_plan.md`, Abschnitt "P5: Big-Picture". Nach Abschluss jeder Referenz in `docs/testing-bigpicture.md`:

- Feature-Matrix: Test-Anzahl, Qualitätsstufe
- Testentwurfsverfahren: Neue EP/BVA-Einträge
- Abdeckungsmatrix: Neue Testklassen
- Changelog: Datum + Kurzbeschreibung

---

## Abschluss

Voll-Lauf nach Runde 3 (2026-04-06): **658/658 grün, 2169 Assertions** (19:29 min, Memory 207 MB).

```bash
make test-integration   # Voll-Lauf — run_in_background: true, auf Fertigmeldung warten
make crap-report        # CRAP-Score-Tabelle neu berechnen
```

Anschließend Neubewertung der verbleibenden CRAP > 100 Methoden und Ratchet-Update.
