<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Delta-Analyse: Fehlende Systemtests (L4) mit L3-Wissensbasis

**Erstellt:** 2026-04-12
**Basis:** [`tds_coverage_ref.md`](tds_coverage_ref.md) (Stand 2026-04-12, 173/35/1/209)

Dieses Dokument identifiziert Features, die in der Komponentenintegration (L3) getestet
sind, aber keinen Systemtest (L4/Playwright) besitzen und UI-relevant sind.

**Ausschlusskriterien:** CLI-Features (G25, G26, G31, P35, P36, P42, A12-A16) und reine
Backend-Logik ohne UI-Sichtbarkeit (P08-P13 isDead-Inferenz) sind ausgeklammert.
Ebenso ausgeschlossen: Features, die bereits L4-Abdeckung haben.

**Grundregel:** Playwright testet UI-Verhalten im Browser. Kein Auslösen von PHPUnit aus
Playwright heraus — es geht um echte Nutzerinteraktion.

---

## Hohe Prioritat -- Kernfunktionen

| # | Feature | L3-Wissensbasis | L4-Testidee (Playwright) |
|---|---------|----------------|--------------------------|
| E01 | Person/Familie anlegen | `AddRelationIntegrationTest` (6 Tests: AddChild POST->302, AddParent/AddSpouse/AddChild GET->200) | Formular oeffnen, Felder ausfuellen, Submit, neuer Record im Baum sichtbar |
| E02 | Fakten bearbeiten | `EditFactIntegrationTest` (3 Tests: AddNewFact GET->200, unknown->redirect) | Fakt hinzufuegen auf Personenseite, Formular, Speichern, Fakt auf Seite sichtbar |
| E08 | TomSelect und AutoComplete | `TomSelectIntegrationTest` (5 Tests: Individual leer/XREF/Name, Source, Folder) | JS-Widget: Tippen, Dropdown erscheint, Eintrag waehlen, Wert uebernommen. Ideal fuer Playwright -- reine JS-Interaktion |
| S47 | Interaktiver Stammbaum | `InteractiveTreeIntegrationTest` (3 Tests: getDetails->XREF, 'p'/'c'-Request->HTML) | Canvas/SVG-Widget laden, Knoten klicken, Detail-Panel zeigt Daten. JS-lastiges Feature |
| S05+S06 | Erweiterte Suche (Ausfuehrung) | `SearchIntegrationTest` (8 Tests: Name, Nachname, Sterbedatum, Multi-Feld, +/-0/+/-5/+/-20 J.) | S38 testet nur Page-Render. Fehlend: Felder ausfuellen, Submit, Ergebnistabelle mit erwarteten Personen |
| S07+S08 | Phonetische Suche (Ausfuehrung) | `SearchIntegrationTest` (4 Tests: Russell Treffer+keinTreffer, DM Treffer+keinTreffer) | S39 testet nur Page-Render. Fehlend: Suchbegriff eingeben, phonetischer Treffer in Ergebnisliste |
| P30+P41 | Datensaetze zusammenfuehren | `MergeFactsActionIntegrationTest` (5 Tests) + `MergeRecordsIntegrationTest` (3 Tests) | Merge-Seite, zwei XREFs eingeben, Vorschau, Zusammenfuehren, ein Record bleibt |
| P40 | Aenderungsverwaltung (Workflow) | `PendingChangesIntegrationTest` (3 Tests: Accept/Reject/GET->200) | Editor erzeugt Pending Change, Moderator sieht Pending-Seite, Accept, Aenderung uebernommen. Ergaenzt `access-control.spec.ts` |
| P38 | Account-Selbstverwaltung | `AccountSelfManagementIntegrationTest` (4 Tests: Edit->200, Update E-Mail, Delete-Guard) | Account-Seite, E-Mail aendern, Speichern, neue E-Mail bestaetigt |

---

## Mittlere Prioritat -- Charts und Listen

| # | Feature | L3-Wissensbasis | L4-Testidee |
|---|---------|----------------|-------------|
| S18 | Charts: fehlende Typen | `ChartModuleIntegrationTest` (17 Tests: Timeline, Lifespan, FamilyBook, Relationships, Branches) | Bisher nur Pedigree in L4. Fehlend: 5 Chart-Typen je als Smoke (Laden + sichtbarer Chartbereich). L3 liefert die Routen und erwarteten Antworten |
| S16 | Beziehungsfinder-Chart | `RelationshipServiceIntegrationTest` (16 Tests: direkte Pfade, Onkel, Grosseltern, Ehepartner) | Chart laden, zwei Personen, Pfad angezeigt. L3 kennt die erwarteten Beziehungstypen |
| S10 | Paginierung (Suchergebnisse) | `SearchIntegrationTest` (3 Tests: Limit, Offset, Offset+Limit) | Suche mit vielen Treffern, Paginierung-Controls sichtbar, naechste Seite, andere Ergebnisse |
| S41 | Statistikseite | `StatisticsDataIntegrationTest` (13 Tests) | Statistik-Chart-Seite laden, Diagramme/Tabellen sichtbar |
| S46 | Homepage-Bloecke | `BlockModuleIntegrationTest` (14 Tests: infoStyles DataProvider) | Homepage, verschiedene Bloecke sichtbar (News, Statistics, etc.). Ergaenzt `homepage.spec.ts` um Blocktypen-Smoke |

---

## Mittlere Prioritat -- Admin-UI

| # | Feature | L3-Wissensbasis | L4-Testidee |
|---|---------|----------------|-------------|
| A01 | Stammbaum-Management | `TreeManagementIntegrationTest` (4 Tests: Create Dup->302, Create Neu->DB, Delete->204, ManageTrees->200) | Admin, ManageTrees-Seite, neuen Baum anlegen, Baum in Liste sichtbar |
| A04 | Stammbaum-Praeferenzen | `TreePreferencesIntegrationTest` (3 Tests: GET->200, POST->preference saved) | Praeferenzen-Seite, Einstellung aendern, Speichern, Einstellung wirkt |
| A05 | Modul-Konfiguration | `ModuleConfigIntegrationTest` (7 Tests: All/Analytics/Blocks/Charts/Menus/Reports->200) | Module-Admin-Seite, Modul deaktivieren, Speichern, Modul nicht mehr im Frontend |
| P37 | Benutzer-Bearbeitung (Admin) | `UserEditActionIntegrationTest` (7 Tests: Duplikat-Email/Username, Self-Edit, Passwort) | Admin, User-Edit, Passwort/E-Mail aendern, Speichern, User kann sich mit neuem PW einloggen |
| A07 | Benutzerverwaltung | `UserAdminIntegrationTest` (3 Tests: UserList, Filter, Cleanup) | Admin, User-Liste, Filter funktioniert, Cleanup-Seite erreichbar |

---

## Niedrigere Prioritat -- Datenpflege-Spezialfeatures

| # | Feature | L3-Wissensbasis | L4-Testidee |
|---|---------|----------------|-------------|
| E04 | Nebenrecords (Note/Source/Repo) | `CreateSubrecordIntegrationTest` (4 Tests: Modal GET->200, Action POST->JSON-XREF) | Modal-Dialog, Note/Source anlegen, XREF zurueck, Verknuepfung sichtbar |
| E05 | Medienobjekte | `MediaObjectIntegrationTest` (3 Tests: Modal, Link POST->302) | Media-Modal, Datei waehlen, verknuepfen, Media auf Person sichtbar |
| E06 | Sortierung (Reorder) | `ReorderIntegrationTest` (4 Tests: Children/Names/Families GET->200) | Reorder-Seite, Reihenfolge aendern, Speichern, neue Reihenfolge auf Personenseite |
| E03 | Raw-GEDCOM-Edit | `EditRawGedcomIntegrationTest` (3 Tests) | Raw-Edit-Seite, GEDCOM editieren, Speichern, Aenderung in Record sichtbar |
| S50 | Hilfetexte | `HelpTextIntegrationTest` (13 Tests: 12 Topics) | Hilfe-Icon klicken, Tooltip/Modal mit Text erscheint |
| K01+K02 | Kontakt/Nachrichten | Keine L3-Tests, aber UI-relevant | Kontaktformular, Nachricht senden, Bestaetigungsmeldung |

---

## Zusammenfassung

**26 Feature-IDs** haben L3-Abdeckung aber keine L4-Tests und sind UI-relevant.

Die staerksten Kandidaten (hohe Prioritaet):

1. **E01/E02** -- Datenerfassung ist die Kerninteraktion; L3 kennt Routen, Statuscodes,
   Redirects
2. **E08** -- TomSelect/AutoComplete ist reines JS-Verhalten, das nur Playwright testen kann
3. **S47** -- Interaktiver Stammbaum ist ebenfalls JS-lastig
4. **S05-S08** -- Suchausfuehrung fehlt komplett (nur Page-Render in L4)
5. **P40** -- Pending-Changes-Workflow vervollstaendigt den bereits getesteten
   Access-Control-Bereich

**K01+K02** sind ein Sonderfall: keine L3-Abdeckung vorhanden, aber UI-relevant. Hier
muessten L4-Tests unabhaengig von L3-Wissen entworfen werden.
