<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# 04 — Explizit ausgeschlossene Tests (Layer-3-only)

Diese Feature-IDs und Testszenarien werden **nicht** nach Layer 2 portiert.
Begründung: Ihre Kernlogik baut auf `DB::table()`-Queries, importierten
GEDCOM-Daten oder Individual/Family-Graph-Traversierung auf. Sie gehören
architekturbedingt in Layer 3 (Komponentenintegrationstests mit MySQL).

**Für diese Features erfolgt keine Umsetzung in dieser Portierungsrunde.**

---

## GEDCOM Import/Export (DB-inhärent)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| G01–G06 | Record-Import (INDI, FAM, Nebenrecords, Places, Dates, Names) | `importRecord()` schreibt in 15+ DB-Tabellen |
| G09 | Inline-Media-Import | DB-Tabelle `media` |
| G10 | Legacy-Formate | Braucht Import-Pipeline |
| G12 | XREF-Eindeutigkeit | DB-Constraint |
| G14–G17 | Export ZIP, ZIP+Media, Privacy, Encoding | Tree-Daten aus DB |
| G20 | Import→Export Roundtrip | Nur mit DB sinnvoll |
| G23 | GEDCOM 5.5.1 Compliance | Import-Pipeline |
| G25–G26 | GedcomLoad/Export CLI | CLI+DB |

## Suche (DB-Query-basiert)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| S01–S04 | Allgemeine Suche (Personen, Familien, SOUR/NOTE/REPO, Query-Parsing) | `SearchService` baut intern SQL-Queries |
| S10 | Paginierung | DB-LIMIT/OFFSET |
| S11 | Cross-Tree-Suche | Multi-DB-Query |
| S41 | Statistikdaten | `DB::table()` Aggregationen |
| S47 | Interaktiver Stammbaum | TreeView+DB |

## Datenschutz & Zugriffskontrolle (Graph-Traversierung)

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| P01–P07 | Stammbaum-Sichtbarkeit, Altersregeln | Privacy-Logik traversiert Individual/Family-Graph |
| P08–P13 | isDead()-Inferenzen | Individual-Traversierung über FAMS/FAMC |
| P14–P15 | Vertrauliche Namen/Beziehungen | Privacy-Graph |
| P16–P21 | RESN-Regeln | RESN+DB |
| P22–P23 | Relationship Privacy | Graph-Traversierung |
| P24 | Privacy in Suche | DB-Query mit Privacy-Filter |

## Services (DB-Kernlogik)

| Service | Begründung |
|---------|-----------|
| `SearchService` | Alle Methoden bauen `DB::table()`-Queries |
| `TreeService` | `create()`, `delete()`, `all()`, `find()` sind direkte DB-Ops |
| `GedcomImportService` | Schreibt in 15+ DB-Tabellen |
| `RelationshipService` | Traversiert FAMS/FAMC-Links in DB |
| `GedcomExportService::export()` | Liest Records aus DB (Hilfsmethoden wie `wrapLongLines` sind L2-fähig) |

## Module-Methoden (DB-abhängig)

| Methode | Begründung |
|---------|-----------|
| `*ListModule::listIsEmpty()` | Fragt DB-Tabelle ab; kein sinnvoller Mock möglich |

## Sonstiges

| Feature-ID | Beschreibung | Begründung |
|-----------|-------------|-----------|
| S16 | Beziehungsfinder | Individual-Graph-Traversierung |
| S44 | Report-Parser | Parser+DB |
| A01 (TreeService) | Stammbaum-CRUD | Direkte DB-Operationen |
| U02 | CountryService | Deprecated |

---

## Zusammenfassung

| Kategorie | Ausgeschlossene Feature-IDs | Anteil an Kategorie |
|-----------|---------------------------|-------------------|
| G | G01–G06, G09–G10, G12, G14–G17, G20, G23, G25–G26 | 18 von 30 |
| S | S01–S04, S10–S11, S41, S47 | 8 von 53 |
| P | P01–P24 | 24 von 41 |
| SEC | — | 0 von 26 |
| E | — | 0 von 8 |
| A | A01 (TreeService nur) | 1 von 11 |
| K | — | 0 von 2 |
| U | U02 | 1 von 2 |
| **Gesamt** | | **~52 Feature-IDs** |
