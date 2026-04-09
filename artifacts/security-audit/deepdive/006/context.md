<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# SEC-AUDIT-006 — D1 Context: RenumberTreeAction raw Expression()

## Sink

`app/Http/RequestHandlers/RenumberTreeAction.php` — 31× `new Expression("REPLACE(...)")` mit PHP-String-Interpolation von `$old_xref`, `$new_xref`, `$tag`, `$type`.

## Entry Point

- Route: `POST /admin/trees/renumber` (Admin-only via `AuthAdministrator` Middleware)
- `handle()` Zeile 54–555

## Variable Sourcing

### `$old_xref` (DB-Quelle)
- `AdminService::duplicateXrefs($tree)` → SQL-Abfrage über `i_id`, `f_id`, `s_id`, `m_id`, `o_id`
- Rückgabe: `array<string, string>` = `[xref => type]`
- Xref-Werte stammen aus DB-Spalten, die beim GEDCOM-Import gegen `Gedcom::REGEX_XREF` validiert werden

### `$new_xref` (generiert)
- `Registry::xrefFactory()->make($type)` → `XrefFactory::generate('X', '')` → `'X' . $num`
- Immer alphanumerisch (Prefix 'X' + Integer), inhärent sicher

### `$tag` (Hardcoded)
- Nur aus `foreach`-Literalen: `['CHIL', 'ASSO', '_ASSO']`, `['ALIA', 'ASSO', '_ASSO']`, `['FAMC', 'FAMS']`
- Keine externe Quelle, sicher

### `$type` (DB-Quelle, switch-Case + default)
- Bekannte Werte: `Individual::RECORD_TYPE` = `'INDI'`, `Family::RECORD_TYPE` = `'FAM'`, etc.
- `default`-Case: `$type` aus `o_type`-Spalte → GEDCOM-Parser-validiert
- Im Expression interpoliert in Zeilen 431, 444, 457, 470, 483, 496

## Defense Chain

| Schicht | Mechanismus | Wo |
|---|---|---|
| L1 | `Gedcom::REGEX_XREF = '[A-Za-z0-9:_.-]{1,20}'` | `app/Gedcom.php:243` |
| L2 | GEDCOM-Import-Parser validiert Xrefs beim Import | `GedcomImportService` |
| L3 | DB-Schema `VARCHAR(20)` auf Xref-Spalten | Migration/Schema |
| L4 | Admin-only Middleware | `AuthAdministrator` |

**Schwachstelle:** Keine **lokale** Validierung in `RenumberTreeAction` selbst. Safety hängt transitiv von L1–L3 ab (Cross-File-Dependency).

## SQL-Pattern (Beispiel Zeile 79)

```php
'i_gedcom' => new Expression("REPLACE(i_gedcom, '0 @$old_xref@ INDI', '0 @$new_xref@ INDI')")
```

Generiertes SQL (vereinfacht):
```sql
UPDATE individuals SET i_gedcom = REPLACE(i_gedcom, '0 @I1@ INDI', '0 @X42@ INDI')
WHERE i_file = ? AND i_id = ?
```

Wenn `$old_xref` ein Single-Quote enthielte (`I1' OR 1=1--`):
```sql
... REPLACE(i_gedcom, '0 @I1' OR 1=1--@ INDI', '0 @X42@ INDI') ...
```
→ SQL-Syntax-Fehler oder SQLi, je nach DB-Driver.

## Ergebnis

**Latent, nicht exploitierbar heute.** Die REGEX_XREF-Zeichenklasse erlaubt keine SQL-brechenden Zeichen. Aber ein Defense-in-Depth-Guard (lokale Xref-Validierung) eliminiert die Cross-File-Dependency und macht die Sicherheit explizit.
