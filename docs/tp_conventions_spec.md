<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Testkonventionen und Verfolgbarkeit

> Verbindliche Regeln für alle PHPUnit-Tests in diesem Repo und im Upstream-Branch.
> Basiert auf ISTQB-Grundprinzipien und Mariia Vain "Unit Testing Best Practices in PHP".

Querverweise: [Feature-Matrizen](tds_conditions_ref.md), [Designentscheidungen](tp_decisions_spec.md)

## AAA-Pattern (Arrange-Act-Assert)

Jeder Test folgt der Dreigliederung:

```php
public function test_import_indi_record_creates_correct_db_entries(): void
{
    // Arrange — Testobjekt und Testdaten vorbereiten
    $service = new GedcomImportService();
    $gedcom  = file_get_contents(__DIR__ . '/fixtures/single-indi.ged');

    // Act — zu testende Aktion ausführen
    $service->importRecord($tree, $gedcom);

    // Assert — erwartetes Ergebnis prüfen
    $this->assertSame(1, DB::table('individuals')->count());
}
```

Die Kommentare `// Arrange`, `// Act`, `// Assert` sind optional — die Struktur muss erkennbar sein.

## FIRST-Prinzipien

| Prinzip | Regel | Umsetzung |
|---|---|---|
| **Fast** | Tests sollen schnell laufen | Keine Sleeps; DB-Fixtures minimal; Teststufe 1 mit SQLite in-memory |
| **Independent** | Tests sind voneinander unabhängig | Kein shared State zwischen Testmethoden; jeder Test baut eigene Fixtures auf |
| **Repeatable** | Gleiche Ergebnisse in jeder Umgebung | Container-Stack garantiert identische Umgebung; deterministische Fixtures |
| **Self-validating** | Test entscheidet selbst: bestanden/fehlgeschlagen | PHPUnit-Assertions; kein manuelles Prüfen von Logdateien |
| **Timely** | Tests zeitnah zum Code schreiben | Feature-Matrix als Leitfaden; Tests vor oder parallel zum Feature |

## Namenskonvention

**Format:** `test_<feature>_<szenario>_<erwartetes_ergebnis>`

```
test_import_indi_record_creates_correct_db_entries
test_export_with_privacy_hides_restricted_records
test_search_with_quoted_phrase_returns_exact_match
test_date_parsing_with_range_sets_both_date_fields
test_conc_wrapping_at_253_chars_splits_correctly
```

- Englisch (Upstream-Kompatibilität)
- Snake_case (PHP-Konvention für Testmethoden)
- Kein `testXyz`-CamelCase (schlechter lesbar bei langen Namen)

## Testklassen-Organisation

**Regel:** Jedes Feature erhält eine eigene Testklasse.

**Ausnahme — Homogene Handler-Gruppen:** Wenn viele Handler ein identisches Interface-Pattern
implementieren (gleiche Signatur, gleiches Antwortmuster), werden sie in einer einzelnen
Testklasse mit DataProvider zusammengefasst. Für die wenigen Handler mit komplexerer Logik
innerhalb der Gruppe können zusätzlich separate Detail-Tests erstellt werden.

**Namensschema Testklassen:**

| Typ | Pattern |
|---|---|
| Handler-Test | `{HandlerKlasse}IntegrationTest` |
| Middleware-Test | `{MiddlewareKlasse}IntegrationTest` |
| Command-Test | `{CommandName}CommandIntegrationTest` |
| Batch-Test (DataProvider) | `{Gruppenname}IntegrationTest` |

## Data Provider

**Pflicht bei ≥3 Äquivalenzklassen.** Verhindert Codeduplizierung und macht Testfälle erweiterbar.

```php
/**
 * @see docs/tds_conditions_ref.md G05
 */
#[DataProvider('gedcomDateProvider')]
public function test_date_parsing_creates_correct_fields(
    string $gedcomDate, string $expectedDate1, string $expectedDate2
): void {
    // ...
}

public static function gedcomDateProvider(): array
{
    return [
        'exact date'   => ['1 JAN 1900', '1900-01-01', ''],
        'date range'   => ['BET 1900 AND 1910', '1900-00-00', '1910-00-00'],
        'before date'  => ['BEF 1900', '', '1900-00-00'],
        'after date'   => ['AFT 1900', '1900-00-00', ''],
        'approx date'  => ['ABT 1900', '1900-00-00', ''],
    ];
}
```

## Ein Verhalten pro Test

Jede Testmethode prüft **ein logisches Verhalten**. Mehrere Assertions sind erlaubt,
wenn sie dasselbe Verhalten aus verschiedenen Perspektiven prüfen. Verboten: ein Test,
der Import UND Export UND Suche in einer Methode prüft.

## Private Methoden

Private und protected Methoden werden **ausschließlich indirekt** über die öffentliche
API getestet. Wenn eine private Methode schwer testbar ist, deutet das auf Refactoring-Bedarf hin.

---

## Verfolgbarkeit

> ISTQB: Fähigkeit, explizite Beziehungen zwischen Arbeitsergebnissen darzustellen.

**Mechanismus:** `@see`-Annotation mit Feature-Matrix-IDs in jeder Testdatei.

```php
/**
 * @covers \Fisharebest\Webtrees\Services\GedcomImportService
 * @see docs/tds_conditions_ref.md G01, G02, G04
 */
class GedcomImportServiceTest extends MysqlTestCase
{
    // ...
}
```

**Bidirektionale Abfrage:**
- Vorwärts (Anforderung → Test): `grep -r "G01" layer*/` bzw. `grep -r "SEC-H01" layer4-e2e/ scripts/`
- Rückwärts (Test → Anforderung): `@see`-Zeile in der Testdatei (`// @see SEC-H01` in Playwright, `# @see SEC-H01` in Shell)

Keine separate Traceability-Matrix im Dokument — die Verfolgbarkeit lebt im Code und
kann bei Bedarf per Skript extrahiert werden.
